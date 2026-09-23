<?php

namespace App\Services\Operations;

use App\Enums\ReportStatus;
use App\Models\SystemBackup;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    /** @var array<int, string> */
    private const TABLES = [
        'tenants', 'users', 'branches', 'customers', 'assets', 'technician_profiles',
        'bookings', 'jobs', 'job_assignments', 'job_checklist_items', 'job_evidence',
        'push_subscriptions', 'tax_profiles', 'inventory_items', 'stock_locations',
        'stock_movements', 'job_part_consumptions', 'quotations',
        'quotation_lines', 'work_orders', 'invoices', 'invoice_lines', 'payments',
        'warranties', 'warranty_claims', 'amc_contracts', 'feedback', 'service_reminders',
        'notification_templates', 'notification_preferences', 'notification_logs',
        'attendance_records', 'leave_balances', 'leave_requests', 'payout_cycles',
        'payout_lines', 'payout_disputes', 'technician_scorecards', 'cms_pages',
        'cms_posts', 'cms_services', 'cms_offers', 'testimonials', 'media_assets',
        'content_revisions', 'booking_enquiries',
        'report_schedules', 'generated_reports', 'analytics_snapshots', 'audit_logs',
        'security_events',
    ];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function create(?string $requestedBy = null): SystemBackup
    {
        $backup = SystemBackup::query()->create([
            'requested_by' => $requestedBy,
            'status' => ReportStatus::Processing,
            'disk' => config('filesystems.default'),
            'started_at' => now(),
        ]);

        try {
            $tenantId = $this->tenantContext->id();
            $payload = [
                'format' => 'acserv-tenant-backup-v2-encrypted',
                'created_at' => now()->toIso8601String(),
                'tenant_id' => $tenantId,
                'tables' => [],
            ];

            foreach (self::TABLES as $table) {
                $query = DB::table($table);

                if ($table === 'tenants') {
                    $query->where('id', $tenantId);
                } else {
                    $query->where('tenant_id', $tenantId);
                }

                $payload['tables'][$table] = $query->orderBy('id')->get()->map(function (object $row): array {
                    return Arr::except((array) $row, [
                        'password', 'remember_token', 'token_hash', 'code_hash', 'auth_token',
                        'public_key', 'provider_signature_hash',
                    ]);
                })->all();
            }

            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $encrypted = Crypt::encryptString($json);
            $contents = gzencode($encrypted, 9);

            if ($contents === false) {
                throw new RuntimeException('The backup could not be compressed.');
            }

            $path = "backups/{$tenantId}/acserv-".now()->format('Ymd-His').'.json.gz.enc';
            Storage::disk($backup->disk)->put($path, $contents);
            $backup->update([
                'status' => ReportStatus::Completed,
                'path' => $path,
                'size_bytes' => mb_strlen($contents, '8bit'),
                'sha256' => hash('sha256', $contents),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $backup->update([
                'status' => ReportStatus::Failed,
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        return $backup->refresh();
    }
}
