<?php

namespace Tests\Feature;

use App\Services\Operations\SystemUpdateService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class SystemUpdateServiceTest extends TestCase
{
    private string $fixture;

    private SystemUpdateService $updates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = sys_get_temp_dir().'/acserv-updates-'.bin2hex(random_bytes(8));
        mkdir($this->fixture.'/app', 0700, true);
        mkdir($this->fixture.'/web', 0700, true);
        mkdir($this->fixture.'/public', 0700, true);
        mkdir($this->fixture.'/release', 0700, true);
        file_put_contents($this->fixture.'/web/index.php', '<?php');
        file_put_contents($this->fixture.'/artisan', '<?php file_put_contents(__DIR__."/commands.log", ($argv[1] ?? "")."\n", FILE_APPEND); exit(0);');
        $this->updates = new SystemUpdateService($this->fixture, $this->fixture.'/private/updates');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixture);

        parent::tearDown();
    }

    public function test_private_access_key_is_stable_and_rejects_unknown_tokens(): void
    {
        $this->updates->ensureAccessToken();
        $token = file_get_contents($this->updates->accessTokenPath());
        $this->updates->ensureAccessToken();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, file_get_contents($this->updates->accessTokenPath()));
        $this->assertTrue($this->updates->hasValidToken($token));
        $this->assertFalse($this->updates->hasValidToken(str_repeat('0', 64)));
    }

    public function test_valid_package_is_staged_queued_and_applied_without_touching_protected_files(): void
    {
        file_put_contents($this->fixture.'/app/Example.php', 'old code');
        mkdir($this->fixture.'/web/build', 0700, true);
        mkdir($this->fixture.'/public/build', 0700, true);
        file_put_contents($this->fixture.'/web/build/old.js', 'obsolete');
        file_put_contents($this->fixture.'/public/build/old.js', 'obsolete');
        mkdir($this->fixture.'/release/app', 0700, true);
        mkdir($this->fixture.'/release/public/build', 0700, true);
        file_put_contents($this->fixture.'/release/app/Example.php', 'new code');
        file_put_contents($this->fixture.'/release/public/build/app.js', 'new asset');
        $zip = $this->fixture.'/release.zip';
        $built = $this->updates->package($this->fixture.'/release', '1.2.3', $zip, ['public/build/old.js']);
        $staged = $this->updates->stage(new UploadedFile($zip, 'release.zip', 'application/zip', null, true));

        $queued = $this->updates->queue($staged['id'], $this->fixture.'/web', 'owner-1', 'Full MySQL snapshot 2026-09-24');
        Artisan::shouldReceive('call')->once()->with('down', ['--retry' => 60])->andReturn(0);
        $this->updates->runNext();

        $this->assertSame(['version' => '1.2.3', 'file_count' => 2, 'total_bytes' => 17], $built);
        $this->assertSame('queued', $queued['state']);
        $this->assertSame('completed', $this->updates->status($staged['id'])['state']);
        $this->assertSame('new code', file_get_contents($this->fixture.'/app/Example.php'));
        $this->assertSame('new asset', file_get_contents($this->fixture.'/web/build/app.js'));
        $this->assertSame('new asset', file_get_contents($this->fixture.'/public/build/app.js'));
        $this->assertFileDoesNotExist($this->fixture.'/web/build/old.js');
        $this->assertFileDoesNotExist($this->fixture.'/public/build/old.js');
        $this->assertSame("migrate\noptimize:clear\nconfig:cache\nevent:cache\nroute:cache\nview:cache\nup\n", file_get_contents($this->fixture.'/commands.log'));
    }

    public function test_rejects_traversal_protected_public_uploads_and_unlisted_zip_entries(): void
    {
        foreach (['app/../.env', 'public/storage/evidence.jpg', 'public/index.php', 'bootstrap/cache/routes.php'] as $path) {
            $archive = $this->makeZip([$path => 'payload']);

            try {
                $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));
                $this->fail("Unsafe path {$path} was accepted.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('path', $exception->getMessage());
            }
        }

        $archive = $this->makeZip(['app/Good.php' => 'good'], ['app/Extra.php' => 'unlisted']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unlisted ZIP entry');
        $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));
    }

    public function test_rejects_manifest_hash_mismatch_and_symbolic_link_entry(): void
    {
        $archive = $this->makeZip(['app/Good.php' => 'good'], [], ['app/Good.php' => str_repeat('0', 64)]);

        try {
            $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));
            $this->fail('A mismatched hash was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('manifest hash', $exception->getMessage());
        }

        $archive = $this->makeZip(['app/Good.php' => 'target']);
        $zip = new ZipArchive;
        $zip->open($archive);
        $zip->setExternalAttributesName('app/Good.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Symbolic links');
        $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));
    }

    public function test_queue_requires_backup_confirmation_and_valid_document_root(): void
    {
        $archive = $this->makeZip(['app/Good.php' => 'good']);
        $staged = $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));

        try {
            $this->updates->queue($staged['id'], $this->fixture.'/web', 'owner-1', '');
            $this->fail('A missing full backup confirmation was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('A valid backup reference is required.', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('actual writable document root');
        $this->updates->queue($staged['id'], $this->fixture.'/release', 'owner-1', 'Snapshot-1');
    }

    public function test_pre_migration_error_restores_existing_files_and_brings_site_up(): void
    {
        file_put_contents($this->fixture.'/app/Good.php', 'old code');
        file_put_contents($this->fixture.'/app/bad', 'blocks directory');
        $archive = $this->makeZip(['app/Good.php' => 'new code', 'app/bad/Second.php' => 'new file']);
        $staged = $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));
        $this->updates->queue($staged['id'], $this->fixture.'/web', 'owner-1', 'Snapshot-1');
        Artisan::shouldReceive('call')->once()->with('down', ['--retry' => 60])->andReturn(0);

        try {
            $this->updates->runNext();
            $this->fail('The blocked target directory should fail the update.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Code was restored', $exception->getMessage());
        }

        $this->assertSame('failed', $this->updates->status($staged['id'])['state']);
        $this->assertSame('old code', file_get_contents($this->fixture.'/app/Good.php'));
        $this->assertSame("up\n", file_get_contents($this->fixture.'/commands.log'));
    }

    public function test_stale_running_update_is_marked_failed_without_applying_another_package(): void
    {
        $archive = $this->makeZip(['app/Good.php' => 'good']);
        $staged = $this->updates->stage(new UploadedFile($archive, 'update.zip', 'application/zip', null, true));
        $queued = $this->updates->queue($staged['id'], $this->fixture.'/web', 'owner-1', 'Snapshot-1');
        $queued['state'] = 'running';
        $queued['started_at'] = now()->subMinutes(31)->toIso8601String();
        file_put_contents($this->fixture.'/private/updates/'.$staged['id'].'/status.json', json_encode($queued));

        try {
            $this->updates->runNext();
            $this->fail('A stale running update should fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Maintenance mode may remain on', $exception->getMessage());
        }

        $this->assertSame('failed', $this->updates->status($staged['id'])['state']);
        $this->assertFileDoesNotExist($this->fixture.'/commands.log');
    }

    /** @param array<string, string> $entries @param array<string, string> $extra @param array<string, string> $hashOverrides */
    private function makeZip(array $entries, array $extra = [], array $hashOverrides = []): string
    {
        $archive = $this->fixture.'/package-'.bin2hex(random_bytes(8)).'.zip';
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        $files = [];

        foreach ($entries as $path => $contents) {
            $zip->addFromString($path, $contents);
            $files[$path] = $hashOverrides[$path] ?? hash('sha256', $contents);
        }

        foreach ($extra as $path => $contents) {
            $zip->addFromString($path, $contents);
        }

        $zip->addFromString('update.json', json_encode([
            'format' => 'acserv-update-v1',
            'version' => '1.0.0',
            'files' => $files,
            'remove' => [],
        ]));
        $zip->close();

        return $archive;
    }
}
