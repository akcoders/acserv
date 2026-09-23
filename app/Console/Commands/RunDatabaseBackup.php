<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Operations\DatabaseBackupService;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:run-database-backup')]
#[Description('Create compressed tenant backups on the configured storage disk')]
class RunDatabaseBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TenantContext $tenantContext, DatabaseBackupService $backups): int
    {
        Tenant::query()->where('status', 'ACTIVE')->orderBy('id')->each(function (Tenant $tenant) use ($tenantContext, $backups): void {
            $tenantContext->set($tenant->getKey());

            try {
                $backups->create();
            } finally {
                $tenantContext->clear();
            }
        });

        return self::SUCCESS;
    }
}
