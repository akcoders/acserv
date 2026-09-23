<?php

namespace App\Services\Analytics;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\BookingEnquiry;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\TenantContext;

class AnalyticsService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $monthStart = now()->startOfMonth();
        $activeStatuses = [
            JobStatus::Created,
            JobStatus::Assigned,
            JobStatus::Accepted,
            JobStatus::EnRoute,
            JobStatus::Reached,
            JobStatus::Inspected,
            JobStatus::Authorized,
            JobStatus::InProgress,
            JobStatus::AwaitingPayment,
            JobStatus::PaymentPending,
        ];

        return [
            'jobs' => [
                'active' => Job::query()->whereIn('status', $activeStatuses)->count(),
                'completed_this_month' => Job::query()->whereIn('status', [JobStatus::Completed, JobStatus::Verified, JobStatus::Closed])->where('completed_at', '>=', $monthStart)->count(),
                'overdue' => Job::query()->whereIn('status', $activeStatuses)->where('scheduled_at', '<', now())->count(),
            ],
            'revenue' => [
                'invoiced_this_month' => (float) Invoice::query()->where('issued_on', '>=', $monthStart->toDateString())->sum('grand_total'),
                'collected_this_month' => (float) Invoice::query()->where('issued_on', '>=', $monthStart->toDateString())->sum('paid_total'),
                'outstanding' => (float) Invoice::query()->whereNotIn('status', [DocumentStatus::Paid, DocumentStatus::Cancelled])->sum('balance_due'),
            ],
            'customers' => [
                'total' => Customer::query()->count(),
                'new_this_month' => Customer::query()->where('created_at', '>=', $monthStart)->count(),
                'average_rating' => round((float) Feedback::query()->avg('rating'), 2),
            ],
            'workforce' => [
                'technicians' => User::query()
                    ->where('tenant_id', $this->tenantContext->id())
                    ->where('role', Role::Technician)
                    ->count(),
                'present_today' => AttendanceRecord::query()->whereDate('attendance_date', today())->count(),
            ],
            'inventory' => [
                'items' => InventoryItem::query()->count(),
                'movements_this_month' => StockMovement::query()->where('moved_at', '>=', $monthStart)->count(),
            ],
            'marketing' => [
                'leads_this_month' => BookingEnquiry::query()->where('created_at', '>=', $monthStart)->count(),
                'converted_this_month' => BookingEnquiry::query()->where('status', 'CONVERTED')->where('updated_at', '>=', $monthStart)->count(),
            ],
        ];
    }
}
