<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemUpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('acserv:update:run')]
#[Description('Apply one queued ACServ ZIP update from private storage')]
class ApplySystemUpdate extends Command
{
    public function handle(SystemUpdateService $updates): int
    {
        try {
            $updates->runNext();
            $this->components->info('Update runner finished. Check the update status in the admin panel.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
