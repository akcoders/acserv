<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RestoreTenantBackup extends Command
{
    protected $signature = 'acserv:restore-tenant-backup
        {path : Path on the configured filesystem disk}
        {--tenant= : Expected tenant ULID}
        {--force : Apply the restore instead of validating only}';

    protected $description = 'Validate or merge an encrypted ACServ tenant backup into MySQL';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $contents = Storage::disk(config('filesystems.default'))->get($path);
        $encrypted = gzdecode($contents);

        if ($encrypted === false) {
            throw new RuntimeException('The backup is not a valid compressed archive.');
        }

        $payload = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

        if (($payload['format'] ?? null) !== 'acserv-tenant-backup-v2-encrypted') {
            throw new RuntimeException('Unsupported backup format.');
        }

        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $expectedTenant = (string) ($this->option('tenant') ?? '');

        if ($tenantId === '' || ($expectedTenant !== '' && $expectedTenant !== $tenantId)) {
            throw new RuntimeException('The backup tenant does not match the expected tenant.');
        }

        $tableCount = count($payload['tables'] ?? []);
        $rowCount = collect($payload['tables'] ?? [])->sum(fn (array $rows): int => count($rows));

        if (! $this->option('force')) {
            $this->info("Backup is valid for tenant {$tenantId}: {$tableCount} tables, {$rowCount} rows.");
            $this->warn('Validation only. Re-run with --tenant='.$tenantId.' --force to merge the records.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($payload, $tenantId): void {
            foreach ($payload['tables'] as $table => $rows) {
                if (! is_array($rows) || $rows === []) {
                    continue;
                }

                foreach ($rows as $row) {
                    if ($table === 'tenants') {
                        abort_unless(($row['id'] ?? null) === $tenantId, 422, 'Invalid tenant record in backup.');
                    } else {
                        abort_unless(($row['tenant_id'] ?? null) === $tenantId, 422, 'Cross-tenant record found in backup.');
                    }

                    DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
                }
            }
        }, 3);

        $this->info("Restored {$rowCount} records for tenant {$tenantId}.");

        return self::SUCCESS;
    }
}
