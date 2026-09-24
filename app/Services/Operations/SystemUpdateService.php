<?php

namespace App\Services\Operations;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class SystemUpdateService
{
    private const FORMAT = 'acserv-update-v1';

    private const MAX_ARCHIVE_BYTES = 104857600;

    private const MAX_FILE_BYTES = 41943040;

    private const MAX_TOTAL_BYTES = 524288000;

    private const MAX_FILES = 2000;

    private readonly string $applicationPath;

    private readonly string $updatePath;

    public function __construct(?string $applicationPath = null, ?string $updatePath = null)
    {
        $this->applicationPath = realpath($applicationPath ?? base_path()) ?: throw new RuntimeException('Application directory was not found.');
        $this->updatePath = $updatePath ?? storage_path('app/private/system-updates');
    }

    /** @return array{id: string, version: string, file_count: int, total_bytes: int} */
    public function stage(UploadedFile $file): array
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('The PHP zip extension is required for updates.');
        }

        if (! $file->isValid() || ($file->getSize() ?: 0) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Upload a valid ZIP package no larger than 100 MB.');
        }

        $id = bin2hex(random_bytes(16));
        $directory = $this->packagePath($id);
        $this->ensurePrivateDirectory($directory);

        try {
            $file->move($directory, 'package.zip');
            $archive = $directory.'/package.zip';
            @chmod($archive, 0600);
            $package = $this->inspectArchive($archive);
            $metadata = [
                'id' => $id,
                'version' => $package['version'],
                'file_count' => $package['file_count'],
                'total_bytes' => $package['total_bytes'],
                'archive_sha256' => hash_file('sha256', $archive),
                'state' => 'staged',
                'created_at' => now()->toIso8601String(),
            ];
            $this->writeJson($directory.'/status.json', $metadata);

            return array_intersect_key($metadata, array_flip(['id', 'version', 'file_count', 'total_bytes']));
        } catch (Throwable $exception) {
            $this->deletePackageDirectory($directory);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function status(string $id): array
    {
        $path = $this->packagePath($id).'/status.json';

        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Update package was not found.');
        }

        $status = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($status) || ($status['id'] ?? null) !== $id) {
            throw new RuntimeException('Update status is damaged.');
        }

        return $status;
    }

    /** @return array<string, mixed> */
    public function queue(string $id, string $webRoot, string $actorId, string $backupReference): array
    {
        return $this->withLock(function () use ($id, $webRoot, $actorId, $backupReference): array {
            $status = $this->status($id);

            if ($status['state'] !== 'staged') {
                throw new RuntimeException('Only a staged update can be queued.');
            }

            foreach (['actor' => $actorId, 'backup reference' => $backupReference] as $label => $value) {
                if (trim($value) === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                    throw new RuntimeException("A valid {$label} is required.");
                }
            }

            foreach ($this->allStatuses() as $other) {
                if ($other['id'] !== $id && in_array($other['state'], ['queued', 'running'], true)) {
                    throw new RuntimeException('Another application update is already pending.');
                }
            }

            $realWebRoot = realpath($webRoot);

            if ($realWebRoot === false || ! str_starts_with($realWebRoot, DIRECTORY_SEPARATOR) || ! is_file($realWebRoot.'/index.php') || ! is_writable($realWebRoot)) {
                throw new RuntimeException('The actual writable document root with index.php is required.');
            }

            if ($realWebRoot === $this->applicationPath || str_starts_with($this->applicationPath.'/', $realWebRoot.'/') || str_starts_with((realpath($this->updatePath) ?: $this->updatePath).'/', $realWebRoot.'/')) {
                throw new RuntimeException('Application source and update storage must stay outside the public document root.');
            }

            $status['web_root'] = $realWebRoot;
            $status['queued_by'] = $actorId;
            $status['backup_reference'] = $backupReference;
            $status['queued_at'] = now()->toIso8601String();
            $status['state'] = 'queued';
            $this->writeJson($this->packagePath($id).'/status.json', $status);

            return $status;
        });
    }

    public function runNext(): void
    {
        $this->withLock(function (): void {
            $statuses = $this->allStatuses();

            foreach ($statuses as $status) {
                if (($status['state'] ?? null) !== 'running') {
                    continue;
                }

                if (isset($status['started_at']) && abs(now()->diffInMinutes(Carbon::parse($status['started_at']))) < 30) {
                    return;
                }

                $status['state'] = 'failed';
                $status['error'] = 'Update runner stopped before recording completion. Maintenance mode may remain on; inspect the private backup and restore the full database backup before retrying.';
                $status['completed_at'] = now()->toIso8601String();
                $this->writeJson($this->packagePath($status['id']).'/status.json', $status);
                throw new RuntimeException($status['error']);
            }

            $queued = array_values(array_filter(
                $statuses,
                fn (array $status): bool => ($status['state'] ?? null) === 'queued',
            ));
            usort($queued, fn (array $first, array $second): int => strcmp($first['queued_at'], $second['queued_at']));

            if ($queued === []) {
                return;
            }

            $status = $queued[0];

            try {
                $this->apply($status);
            } catch (Throwable $exception) {
                if ($this->status($status['id'])['state'] === 'queued') {
                    $status['state'] = 'failed';
                    $status['error'] = mb_substr($exception->getMessage(), 0, 2000);
                    $status['completed_at'] = now()->toIso8601String();
                    $this->writeJson($this->packagePath($status['id']).'/status.json', $status);
                }

                throw $exception;
            }
        });
    }

    public function accessTokenPath(): string
    {
        return $this->updatePath.'/access-token';
    }

    public function ensureAccessToken(): void
    {
        $this->ensurePrivateDirectory($this->updatePath);

        if (is_file($this->accessTokenPath())) {
            return;
        }

        $handle = @fopen($this->accessTokenPath(), 'x');

        if ($handle === false) {
            if (is_file($this->accessTokenPath())) {
                return;
            }

            throw new RuntimeException('Could not create the private update access key.');
        }

        try {
            @chmod($this->accessTokenPath(), 0600);

            if (fwrite($handle, bin2hex(random_bytes(32))) !== 64) {
                throw new RuntimeException('Could not write the private update access key.');
            }
        } finally {
            fclose($handle);
        }
    }

    public function hasValidToken(string $token): bool
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token) || ! is_file($this->accessTokenPath()) || is_link($this->accessTokenPath())) {
            return false;
        }

        return hash_equals(trim((string) file_get_contents($this->accessTokenPath())), $token);
    }

    /** @return array{version: string, file_count: int, total_bytes: int} */
    public function package(string $sourceDirectory, string $version, string $outputZip, array $remove = []): array
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('The PHP zip extension is required for updates.');
        }

        $source = realpath($sourceDirectory);

        if ($source === false || ! is_dir($source) || ! $this->validVersion($version)) {
            throw new RuntimeException('Provide a prepared directory and a valid release version.');
        }

        $outputDirectory = realpath(dirname($outputZip));

        if ($outputDirectory === false || ! is_writable($outputDirectory) || file_exists($outputZip) || str_starts_with($outputDirectory.'/', $source.'/')) {
            throw new RuntimeException('Choose a new ZIP path outside the prepared directory.');
        }

        $files = [];
        $totalBytes = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Prepared update contains a symbolic link.');
            }

            if (! $entry->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($source) + 1));
            $this->assertAllowedPath($relative);
            $size = $entry->getSize();
            $totalBytes += $size;

            if ($size > self::MAX_FILE_BYTES || $totalBytes > self::MAX_TOTAL_BYTES || count($files) >= self::MAX_FILES) {
                throw new RuntimeException('Prepared update exceeds the package size or file-count limit.');
            }

            $files[$relative] = hash_file('sha256', $entry->getPathname());
        }

        if ($files === []) {
            throw new RuntimeException('Prepared update contains no files.');
        }

        $casefold = array_fill_keys(array_map('strtolower', array_keys($files)), true);

        foreach ($remove as $relative) {
            $this->assertAllowedPath($relative);

            if (isset($casefold[strtolower($relative)])) {
                throw new RuntimeException('Removal path duplicates or conflicts with a packaged file.');
            }

            $casefold[strtolower($relative)] = true;
        }

        ksort($files);
        $zip = new ZipArchive;

        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Could not create update ZIP.');
        }

        $packagingFailed = false;

        try {
            foreach ($files as $relative => $hash) {
                if (! $zip->addFile($source.'/'.$relative, $relative)) {
                    throw new RuntimeException("Could not package {$relative}.");
                }
            }

            if (! $zip->addFromString('update.json', json_encode([
                'format' => self::FORMAT,
                'version' => $version,
                'files' => $files,
                'remove' => array_values($remove),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Could not write update manifest.');
            }
        } catch (Throwable $exception) {
            $packagingFailed = true;

            throw $exception;
        } finally {
            $zip->close();

            if ($packagingFailed) {
                @unlink($outputZip);
            }
        }

        try {
            $package = $this->inspectArchive($outputZip);

            return array_intersect_key($package, array_flip(['version', 'file_count', 'total_bytes']));
        } catch (Throwable $exception) {
            @unlink($outputZip);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $status */
    private function apply(array $status): void
    {
        $id = $status['id'];
        $archive = $this->packagePath($id).'/package.zip';

        if (! is_file($archive) || is_link($archive) || ! hash_equals($status['archive_sha256'], hash_file('sha256', $archive))) {
            throw new RuntimeException('The staged update archive changed or is missing.');
        }

        $package = $this->inspectArchive($archive);

        if ($package['version'] !== $status['version'] || $package['file_count'] !== $status['file_count']) {
            throw new RuntimeException('The staged update manifest changed.');
        }

        $webRoot = realpath($status['web_root']);

        if ($webRoot === false || $webRoot !== $status['web_root'] || ! is_file($webRoot.'/index.php')) {
            throw new RuntimeException('The queued document root is no longer valid.');
        }

        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('Application is already in maintenance mode; resolve that before updating.');
        }

        if (! function_exists('proc_open') || ! is_file($this->applicationPath.'/artisan')) {
            throw new RuntimeException('A usable PHP CLI process and private artisan entrypoint are required for updates.');
        }

        $status['state'] = 'running';
        $status['started_at'] = now()->toIso8601String();
        $this->writeJson($this->packagePath($id).'/status.json', $status);
        $migrationStarted = false;
        $backupReady = false;

        try {
            $this->artisanOrFail('down', ['--retry' => 60]);
            $originals = $this->backupTargets($id, $package, $webRoot);
            $backupReady = true;
            $this->replaceFiles($archive, $package, $webRoot);
            $migrationStarted = true;
            $this->freshArtisanOrFail($id, 'migrate', ['--force', '--no-interaction']);
            $this->freshArtisanOrFail($id, 'optimize:clear');
            $this->freshArtisanOrFail($id, 'config:cache');
            $this->freshArtisanOrFail($id, 'event:cache');
            $this->freshArtisanOrFail($id, 'route:cache');
            $this->freshArtisanOrFail($id, 'view:cache');
            $this->freshArtisanOrFail($id, 'up');
            $status['state'] = 'completed';
            $status['completed_at'] = now()->toIso8601String();
            $this->writeJson($this->packagePath($id).'/status.json', $status);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            if (! $migrationStarted) {
                try {
                    if ($backupReady) {
                        $this->restoreTargets($id, $originals, $webRoot);
                    }

                    $this->freshArtisanOrFail($id, 'up');
                    $error .= ' Code was restored; database migrations were not started.';
                } catch (Throwable $rollbackException) {
                    $error .= ' Automatic rollback failed: '.$rollbackException->getMessage().'. Maintenance mode remains on.';
                }
            } else {
                $error .= ' Database migration may be partial. Maintenance mode remains on; restore the full backup before retrying.';
            }

            $status['state'] = 'failed';
            $status['error'] = mb_substr($error, 0, 2000);
            $status['completed_at'] = now()->toIso8601String();
            $this->writeJson($this->packagePath($id).'/status.json', $status);

            throw new RuntimeException($error, previous: $exception);
        }
    }

    /** @return array<string, array<string, array{exists: bool, sha256?: string}>> */
    private function backupTargets(string $id, array $package, string $webRoot): array
    {
        $originals = [];
        $paths = array_merge(array_keys($package['files']), $package['remove']);

        foreach ($paths as $relative) {
            foreach ($this->targetRoots($relative, $webRoot) as $location => $publicRoot) {
                $target = $this->destination($relative, $publicRoot);
                $exists = is_file($target);
                $originals[$relative][$location] = ['exists' => $exists];

                if ($exists) {
                    $backup = $this->backupPath($id, $location, $relative);
                    $this->ensurePrivateDirectory(dirname($backup));

                    if (! copy($target, $backup)) {
                        throw new RuntimeException("Could not back up {$relative} before update.");
                    }

                    @chmod($backup, 0600);
                    $originals[$relative][$location]['sha256'] = hash_file('sha256', $backup);
                }
            }
        }

        $this->writeJson($this->packagePath($id).'/originals.json', $originals);

        return $originals;
    }

    private function replaceFiles(string $archive, array $package, string $webRoot): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Staged update could not be opened.');
        }

        try {
            foreach ($package['files'] as $relative => $expectedHash) {
                foreach ($this->targetRoots($relative, $webRoot) as $publicRoot) {
                    $target = $this->destination($relative, $publicRoot);
                    $this->ensureTargetDirectory(dirname($target), $relative, $publicRoot);
                    $temporary = dirname($target).'/.acserv-update-'.bin2hex(random_bytes(8));
                    $input = $zip->getStream($relative);

                    if ($input === false) {
                        throw new RuntimeException("Could not read {$relative} from update ZIP.");
                    }

                    $output = @fopen($temporary, 'x');

                    if ($output === false) {
                        fclose($input);
                        throw new RuntimeException("Could not write {$relative} during update.");
                    }

                    try {
                        $hash = hash_init('sha256');
                        $bytes = 0;

                        while (! feof($input)) {
                            $chunk = fread($input, 65536);

                            if ($chunk === false || ($chunk === '' && ! feof($input))) {
                                throw new RuntimeException("Could not finish reading {$relative} from update ZIP.");
                            }

                            $bytes += strlen($chunk);

                            if ($bytes > self::MAX_FILE_BYTES || fwrite($output, $chunk) !== strlen($chunk)) {
                                throw new RuntimeException("Could not safely write {$relative} during update.");
                            }

                            hash_update($hash, $chunk);
                        }

                        if (! hash_equals($expectedHash, hash_final($hash))) {
                            throw new RuntimeException("Hash mismatch while applying {$relative}.");
                        }
                    } finally {
                        fclose($input);
                        fclose($output);
                    }

                    @chmod($temporary, 0644);

                    if (! rename($temporary, $target)) {
                        @unlink($temporary);
                        throw new RuntimeException("Could not replace {$relative} during update.");
                    }
                }
            }
        } finally {
            $zip->close();
        }

        foreach ($package['remove'] as $relative) {
            foreach ($this->targetRoots($relative, $webRoot) as $publicRoot) {
                $target = $this->destination($relative, $publicRoot);

                if (is_file($target) && ! unlink($target)) {
                    throw new RuntimeException("Could not remove {$relative} during update.");
                }
            }
        }
    }

    /** @param array<string, array<string, array{exists: bool, sha256?: string}>> $originals */
    private function restoreTargets(string $id, array $originals, string $webRoot): void
    {
        foreach ($originals as $relative => $locations) {
            foreach ($this->targetRoots($relative, $webRoot) as $location => $publicRoot) {
                $original = $locations[$location];
                $target = $this->destination($relative, $publicRoot);

                if ($original['exists']) {
                    $backup = $this->backupPath($id, $location, $relative);

                    if (! is_file($backup) || ! hash_equals($original['sha256'], hash_file('sha256', $backup))) {
                        throw new RuntimeException("Backup for {$relative} is missing or changed.");
                    }

                    $this->ensureTargetDirectory(dirname($target), $relative, $publicRoot);
                    $temporary = dirname($target).'/.acserv-restore-'.bin2hex(random_bytes(8));

                    if (! copy($backup, $temporary) || ! rename($temporary, $target)) {
                        throw new RuntimeException("Could not restore {$relative}.");
                    }
                } elseif (is_file($target) && ! unlink($target)) {
                    throw new RuntimeException("Could not remove new file {$relative} during rollback.");
                }
            }
        }
    }

    /** @return array{version: string, file_count: int, total_bytes: int, files: array<string, string>, remove: array<int, string>} */
    private function inspectArchive(string $archive): array
    {
        if (! is_file($archive) || filesize($archive) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Update ZIP is missing or larger than 100 MB.');
        }

        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Update ZIP is damaged or not a ZIP file.');
        }

        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_FILES + 1) {
                throw new RuntimeException('Update ZIP must contain update.json and 1–2000 files.');
            }

            $manifestIndex = $zip->locateName('update.json', 0);
            $manifestStat = $manifestIndex === false ? false : $zip->statIndex($manifestIndex);

            if ($manifestStat === false || $manifestStat['size'] > 1048576) {
                throw new RuntimeException('A valid root update.json manifest is required.');
            }

            $manifest = json_decode((string) $zip->getFromIndex($manifestIndex, 1048577), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ! $this->validVersion($manifest['version'] ?? null) || ! is_array($manifest['files'] ?? null) || ! is_array($manifest['remove'] ?? null) || ! array_is_list($manifest['remove']) || $manifest['files'] === []) {
                throw new RuntimeException('Update manifest format, version, files, or removal list is invalid.');
            }

            $files = $manifest['files'];
            $remove = $manifest['remove'];
            $names = [];
            $casefold = [];
            $totalBytes = 0;

            foreach ($files as $relative => $hash) {
                $this->assertAllowedPath($relative);

                if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                    throw new RuntimeException("Invalid SHA-256 hash for {$relative}.");
                }

                $lower = strtolower($relative);

                if (isset($casefold[$lower])) {
                    throw new RuntimeException('Update contains case-colliding paths.');
                }

                $casefold[$lower] = true;
            }

            foreach ($remove as $relative) {
                $this->assertAllowedPath($relative);
                $lower = strtolower($relative);

                if (isset($casefold[$lower])) {
                    throw new RuntimeException('Update contains a duplicate or conflicting removal path.');
                }

                $casefold[$lower] = true;
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'] ?? null;

                if (! is_string($name) || isset($names[strtolower($name)])) {
                    throw new RuntimeException('ZIP contains a duplicate or invalid entry.');
                }

                $names[strtolower($name)] = true;

                if ($name !== 'update.json') {
                    $this->assertAllowedPath($name);

                    if (! array_key_exists($name, $files)) {
                        throw new RuntimeException("Unlisted ZIP entry {$name} is not allowed.");
                    }
                }

                $this->assertRegularZipEntry($zip, $index);
                $size = $entry['size'];

                if ($size > ($name === 'update.json' ? 1048576 : self::MAX_FILE_BYTES)) {
                    throw new RuntimeException("ZIP entry {$name} exceeds the size limit.");
                }

                if ($name === 'update.json') {
                    continue;
                }

                $totalBytes += $size;

                if ($totalBytes > self::MAX_TOTAL_BYTES) {
                    throw new RuntimeException('Uncompressed update exceeds 500 MB.');
                }

                $stream = $zip->getStream($name);

                if ($stream === false) {
                    throw new RuntimeException("Cannot read {$name} from ZIP.");
                }

                $hash = hash_init('sha256');
                $actualBytes = 0;

                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 65536);

                        if ($chunk === false) {
                            throw new RuntimeException("Cannot read {$name} from ZIP.");
                        }

                        if ($chunk === '' && ! feof($stream)) {
                            throw new RuntimeException("Cannot finish reading {$name} from ZIP.");
                        }

                        $actualBytes += strlen($chunk);

                        if ($actualBytes > self::MAX_FILE_BYTES) {
                            throw new RuntimeException("Uncompressed {$name} exceeds 40 MB.");
                        }

                        hash_update($hash, $chunk);
                    }
                } finally {
                    fclose($stream);
                }

                if ($actualBytes !== $size || ! hash_equals($files[$name], hash_final($hash))) {
                    throw new RuntimeException("ZIP entry {$name} did not match its manifest hash or size.");
                }
            }

            if (count($files) !== $zip->numFiles - 1) {
                throw new RuntimeException('Every ZIP file must appear exactly once in update.json.');
            }

            return [
                'version' => $manifest['version'],
                'file_count' => count($files),
                'total_bytes' => $totalBytes,
                'files' => $files,
                'remove' => $remove,
            ];
        } finally {
            $zip->close();
        }
    }

    private function assertRegularZipEntry(ZipArchive $zip, int $index): void
    {
        $entry = $zip->statIndex($index);

        if (str_ends_with($entry['name'], '/') || ($entry['encryption_method'] ?? 0) !== 0) {
            throw new RuntimeException('Directories and encrypted ZIP entries are not allowed.');
        }

        if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes) && $operatingSystem === ZipArchive::OPSYS_UNIX) {
            $fileType = ($attributes >> 16) & 0170000;

            if ($fileType !== 0 && $fileType !== 0100000) {
                throw new RuntimeException('Symbolic links and special files are not allowed in updates.');
            }
        }
    }

    private function assertAllowedPath(mixed $path): void
    {
        if (! is_string($path) || $path === '' || strlen($path) > 240 || str_contains($path, "\0") || str_contains($path, '\\') || ! preg_match('~^[A-Za-z0-9._@+/-]+$~', $path)) {
            throw new RuntimeException('Update contains an unsafe path.');
        }

        $segments = explode('/', $path);

        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw new RuntimeException("Unsafe update path {$path}.");
        }

        if (count(array_intersect($segments, ['.env', '.git', 'storage', 'node_modules', 'tests'])) > 0 || count(array_filter($segments, fn (string $segment): bool => str_starts_with($segment, '.'))) > 0) {
            throw new RuntimeException("Protected update path {$path}.");
        }

        $root = $segments[0];
        $allowedDirectory = in_array($root, ['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'public', 'vendor'], true) && count($segments) > 1;
        $allowedRootFile = in_array($path, ['composer.json', 'composer.lock'], true);

        if (! $allowedDirectory && ! $allowedRootFile) {
            throw new RuntimeException("Update path {$path} is not allowed.");
        }

        if (($root === 'bootstrap' && ($segments[1] ?? '') === 'cache') || ($root === 'public' && str_ends_with(strtolower($path), '.php')) || in_array($path, ['public/index.php', 'public/install.php', 'public/.htaccess', 'public/storage'], true) || str_ends_with($path, '/.htaccess')) {
            throw new RuntimeException("Protected update path {$path}.");
        }
    }

    private function validVersion(mixed $version): bool
    {
        return is_string($version) && (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}$/', $version);
    }

    private function destination(string $relative, string $webRoot): string
    {
        $this->assertAllowedPath($relative);
        $public = str_starts_with($relative, 'public/');
        $root = $public ? $webRoot : $this->applicationPath;
        $suffix = $public ? substr($relative, 7) : $relative;
        $target = $root.'/'.$suffix;
        $cursor = $root;

        foreach (explode('/', $suffix) as $segment) {
            $cursor .= '/'.$segment;

            if (is_link($cursor)) {
                throw new RuntimeException("Update target {$relative} passes through a symbolic link.");
            }
        }

        if (is_dir($target)) {
            throw new RuntimeException("Update target {$relative} is a directory.");
        }

        return $target;
    }

    private function ensureTargetDirectory(string $directory, string $relative, string $webRoot): void
    {
        $root = str_starts_with($relative, 'public/') ? $webRoot : $this->applicationPath;
        $suffix = substr($directory, strlen($root) + 1);
        $cursor = $root;

        foreach (explode('/', $suffix) as $segment) {
            if ($segment === '') {
                continue;
            }

            $cursor .= '/'.$segment;

            if (is_link($cursor) || (file_exists($cursor) && ! is_dir($cursor))) {
                throw new RuntimeException("Unsafe target directory for {$relative}.");
            }

            if (! is_dir($cursor) && ! mkdir($cursor, 0755)) {
                throw new RuntimeException("Could not create target directory for {$relative}.");
            }
        }
    }

    private function artisanOrFail(string $command, array $arguments = []): void
    {
        if (Artisan::call($command, $arguments) !== 0) {
            throw new RuntimeException("Artisan {$command} failed.");
        }
    }

    /** @param array<int, string> $arguments */
    private function freshArtisanOrFail(string $id, string $command, array $arguments = []): void
    {
        $process = new Process([PHP_BINARY, $this->applicationPath.'/artisan', $command, ...$arguments], $this->applicationPath);
        $process->setTimeout(600);
        $process->run();
        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
        $log = $this->packagePath($id).'/runner.log';
        file_put_contents($log, "[{$command}] exit {$process->getExitCode()}\n".mb_substr($output, -12000)."\n", FILE_APPEND | LOCK_EX);
        @chmod($log, 0600);

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Artisan {$command} failed in the fresh PHP process. Check the private runner log.");
        }
    }

    /** @return array<string, string> */
    private function targetRoots(string $relative, string $webRoot): array
    {
        if (! str_starts_with($relative, 'public/')) {
            return ['application' => $webRoot];
        }

        $privatePublic = realpath($this->applicationPath.'/public');

        if ($privatePublic === false) {
            throw new RuntimeException('Private application public directory is missing.');
        }

        return $privatePublic === $webRoot
            ? ['public' => $webRoot]
            : ['web' => $webRoot, 'private_public' => $privatePublic];
    }

    private function backupPath(string $id, string $location, string $relative): string
    {
        return $this->packagePath($id).'/backup/'.$location.'/'.$relative;
    }

    private function packagePath(string $id): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new RuntimeException('Invalid update package ID.');
        }

        return $this->updatePath.'/'.$id;
    }

    /** @return array<int, array<string, mixed>> */
    private function allStatuses(): array
    {
        $this->ensurePrivateDirectory($this->updatePath);
        $statuses = [];

        foreach (scandir($this->updatePath) ?: [] as $id) {
            if (preg_match('/^[a-f0-9]{32}$/', $id) && is_file($this->packagePath($id).'/status.json')) {
                $statuses[] = $this->status($id);
            }
        }

        return $statuses;
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory) || (! is_dir($directory) && ! mkdir($directory, 0700, true))) {
            throw new RuntimeException('Could not create private update storage.');
        }

        @chmod($directory, 0700);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Could not write private update status.');
        }

        @chmod($temporary, 0600);

        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not save private update status.');
        }
    }

    /** @template T @param callable(): T $callback @return T */
    private function withLock(callable $callback): mixed
    {
        $this->ensurePrivateDirectory($this->updatePath);
        $handle = fopen($this->updatePath.'/process.lock', 'c');

        if ($handle === false) {
            throw new RuntimeException('Could not lock application updates.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another update operation is already running.');
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function deletePackageDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($directory);
    }
}
