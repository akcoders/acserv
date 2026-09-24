<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemUpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('acserv:update:package {source-dir : Prepared release directory} {version : Release version} {output-zip : New ZIP path outside source-dir} {--remove=* : Obsolete allowed relative path to delete}')]
#[Description('Build a checksummed ACServ update ZIP from a prepared release directory')]
class PackageSystemUpdate extends Command
{
    public function handle(SystemUpdateService $updates): int
    {
        try {
            $package = $updates->package(
                (string) $this->argument('source-dir'),
                (string) $this->argument('version'),
                (string) $this->argument('output-zip'),
                (array) $this->option('remove'),
            );
            $this->components->info("Built {$package['version']} update with {$package['file_count']} files ({$package['total_bytes']} bytes).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
