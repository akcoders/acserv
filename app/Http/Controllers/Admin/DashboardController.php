<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AnalyticsService $analytics): View
    {
        $user = auth()->user();
        $analyticsMetrics = $analytics->dashboard();
        $statusCounts = Job::query()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('admin.dashboard', [
            'tenantName' => $user->tenant()->value('name'),
            'metrics' => [
                'users' => User::query()->where('tenant_id', $user->tenant_id)->count(),
                'branches' => Branch::query()->count(),
                'customers' => Customer::query()->count(),
                'assets' => Asset::query()->count(),
            ],
            'analytics' => $analyticsMetrics,
            'statusCounts' => $statusCounts,
            'pipelineStatuses' => array_filter(JobStatus::cases(), fn (JobStatus $status): bool => $status !== JobStatus::Cancelled),
            'todayJobs' => Job::query()->whereDate('scheduled_at', today())->count(),
            'recentJobs' => Job::query()
                ->with(['customer:id,name', 'assignments.technician:id,first_name,last_name'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
