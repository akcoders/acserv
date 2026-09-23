<?php

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Models\ServiceReminder;
use App\Models\Tenant;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-service-reminders')]
#[Description('Queue due customer service and warranty reminders')]
class SendServiceReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TenantContext $tenantContext, NotificationDispatcher $notifications): int
    {
        Tenant::query()->where('status', 'ACTIVE')->orderBy('id')->each(function (Tenant $tenant) use ($tenantContext, $notifications): void {
            $tenantContext->set($tenant->getKey());

            try {
                ServiceReminder::query()
                    ->where('status', ReminderStatus::Pending)
                    ->whereDate('notify_on', '<=', today())
                    ->with('customer.user')
                    ->limit(200)
                    ->get()
                    ->each(function (ServiceReminder $reminder) use ($notifications): void {
                        $user = $reminder->customer->user;

                        if ($user === null) {
                            $reminder->update(['status' => ReminderStatus::Skipped, 'last_attempted_at' => now()]);

                            return;
                        }

                        $notifications->queue('service.reminder', $user, [
                            'type' => $reminder->type,
                            'due_on' => $reminder->due_on->format('d M Y'),
                        ]);
                        $reminder->update([
                            'status' => ReminderStatus::Queued,
                            'last_attempted_at' => now(),
                        ]);
                    });
            } finally {
                $tenantContext->clear();
            }
        });

        return self::SUCCESS;
    }
}
