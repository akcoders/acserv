<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportFrequency;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportScheduleRequest;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\SecurityEvent;
use App\Models\SystemBackup;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\PdfDocument;
use App\Services\Operations\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(AnalyticsService $analytics): View
    {
        return view('admin.analytics.index', [
            'metrics' => $analytics->dashboard(),
            'schedules' => ReportSchedule::query()->orderBy('next_run_at')->get(),
            'reports' => GeneratedReport::query()->orderByDesc('created_at')->limit(20)->get(),
            'backups' => SystemBackup::query()->orderByDesc('created_at')->limit(10)->get(),
            'securityEvents' => SecurityEvent::query()->orderByDesc('occurred_at')->limit(20)->get(),
            'frequencies' => ReportFrequency::cases(),
        ]);
    }

    public function storeSchedule(StoreReportScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schedule = ReportSchedule::query()->create([
            ...$data,
            'next_run_at' => $this->nextRun(ReportFrequency::from($data['frequency'])),
        ]);

        return response()->json(['message' => 'Report schedule saved.', 'schedule' => $schedule, 'reload' => true], 201);
    }

    public function generate(Request $request, AnalyticsService $analytics, PdfDocument $pdf): JsonResponse
    {
        abort_unless($request->user()?->role?->canViewAnalytics(), 403);
        $data = $request->validate([
            'report_type' => ['required', Rule::in(['jobs', 'revenue', 'inventory', 'workforce', 'customers', 'marketing'])],
            'period_starts_on' => ['nullable', 'date'],
            'period_ends_on' => ['nullable', 'date', 'after_or_equal:period_starts_on'],
        ]);
        $report = GeneratedReport::query()->create([
            'requested_by' => $request->user()->getKey(),
            'report_type' => $data['report_type'],
            'status' => ReportStatus::Processing,
            'period_starts_on' => $data['period_starts_on'] ?? null,
            'period_ends_on' => $data['period_ends_on'] ?? null,
            'parameters' => $data,
        ]);
        $metrics = $analytics->dashboard()[$data['report_type']] ?? [];
        $lines = collect(Arr::dot($metrics))->map(fn ($value, string $key): string => Str::headline($key).': '.$value)->values()->all();
        $path = 'reports/'.$request->user()->tenant_id.'/'.$report->getKey().'.pdf';
        $pdf->store($path, 'ACServ '.Str::headline($data['report_type']).' Report', $lines);
        $report->update([
            'status' => ReportStatus::Completed,
            'disk' => config('filesystems.default'),
            'path' => $path,
            'mime_type' => 'application/pdf',
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Report generated successfully.', 'report' => $report, 'reload' => true]);
    }

    public function backup(Request $request, DatabaseBackupService $backups): JsonResponse
    {
        abort_unless($request->user()?->role?->canViewAnalytics(), 403);
        $backup = $backups->create($request->user()->getKey());

        return response()->json(['message' => 'Tenant backup completed.', 'backup' => $backup, 'reload' => true]);
    }

    public function downloadReport(GeneratedReport $generatedReport): StreamedResponse
    {
        abort_unless($generatedReport->status === ReportStatus::Completed && $generatedReport->path !== null, 404);

        return Storage::disk($generatedReport->disk)->download($generatedReport->path);
    }

    public function downloadBackup(SystemBackup $systemBackup): StreamedResponse
    {
        abort_unless($systemBackup->status === ReportStatus::Completed && $systemBackup->path !== null, 404);

        return Storage::disk($systemBackup->disk)->download($systemBackup->path);
    }

    public function readiness(): JsonResponse
    {
        DB::select('select 1');

        return response()->json([
            'status' => 'ready',
            'database' => 'connected',
            'queue_pending' => DB::table('jobs')->count(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function nextRun(ReportFrequency $frequency): Carbon
    {
        return match ($frequency) {
            ReportFrequency::Daily => now()->addDay()->startOfDay()->addHours(7),
            ReportFrequency::Weekly => now()->next('Monday')->startOfDay()->addHours(7),
            ReportFrequency::Monthly => now()->addMonthNoOverflow()->startOfMonth()->addHours(7),
            ReportFrequency::Quarterly => now()->addQuarter()->startOfQuarter()->addHours(7),
        };
    }
}
