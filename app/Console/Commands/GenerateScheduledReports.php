<?php

namespace App\Console\Commands;

use App\Enums\ReportFrequency;
use App\Enums\ReportStatus;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\Tenant;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\PdfDocument;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('app:generate-scheduled-reports')]
#[Description('Generate and email all due scheduled PDF reports')]
class GenerateScheduledReports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TenantContext $tenantContext, AnalyticsService $analytics, PdfDocument $pdf): int
    {
        Tenant::query()->where('status', 'ACTIVE')->orderBy('id')->each(function (Tenant $tenant) use ($tenantContext, $analytics, $pdf): void {
            $tenantContext->set($tenant->getKey());

            try {
                ReportSchedule::query()
                    ->where('is_active', true)
                    ->where('next_run_at', '<=', now())
                    ->limit(50)
                    ->get()
                    ->each(function (ReportSchedule $schedule) use ($analytics, $pdf, $tenant): void {
                        $report = GeneratedReport::query()->create([
                            'report_schedule_id' => $schedule->getKey(),
                            'report_type' => $schedule->report_type,
                            'status' => ReportStatus::Processing,
                            'parameters' => $schedule->filters,
                        ]);
                        $metrics = $analytics->dashboard()[$schedule->report_type] ?? [];
                        $lines = collect(Arr::dot($metrics))->map(fn ($value, string $key): string => Str::headline($key).': '.$value)->values()->all();
                        $path = "reports/{$tenant->getKey()}/{$report->getKey()}.pdf";
                        $pdf->store($path, 'ACServ '.Str::headline($schedule->report_type).' Report', $lines);
                        $report->update([
                            'status' => ReportStatus::Completed,
                            'disk' => config('filesystems.default'),
                            'path' => $path,
                            'mime_type' => 'application/pdf',
                            'completed_at' => now(),
                        ]);

                        foreach ($schedule->recipients as $recipient) {
                            Mail::raw('Your scheduled ACServ report is attached.', function ($message) use ($recipient, $schedule, $report): void {
                                $message
                                    ->to($recipient)
                                    ->subject($schedule->name)
                                    ->attach(Storage::disk($report->disk)->path($report->path));
                            });
                        }

                        $schedule->update([
                            'last_run_at' => now(),
                            'next_run_at' => $this->nextRun($schedule->frequency),
                        ]);
                    });
            } finally {
                $tenantContext->clear();
            }
        });

        return self::SUCCESS;
    }

    private function nextRun(ReportFrequency $frequency): Carbon
    {
        return match ($frequency) {
            ReportFrequency::Daily => now()->addDay(),
            ReportFrequency::Weekly => now()->addWeek(),
            ReportFrequency::Monthly => now()->addMonthNoOverflow(),
            ReportFrequency::Quarterly => now()->addQuarter(),
        };
    }
}
