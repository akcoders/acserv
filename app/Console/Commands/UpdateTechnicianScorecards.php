<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Feedback;
use App\Models\JobAssignment;
use App\Models\TechnicianScorecard;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:update-technician-scorecards')]
#[Description('Recalculate monthly technician performance scorecards')]
class UpdateTechnicianScorecards extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TenantContext $tenantContext): int
    {
        $periodStartsOn = now()->startOfMonth();
        $periodEndsOn = now()->endOfMonth();

        Tenant::query()->where('status', 'ACTIVE')->orderBy('id')->each(function (Tenant $tenant) use ($tenantContext, $periodStartsOn, $periodEndsOn): void {
            $tenantContext->set($tenant->getKey());

            try {
                User::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('role', Role::Technician)
                    ->orderBy('id')
                    ->each(function (User $technician) use ($periodStartsOn, $periodEndsOn): void {
                        $assignments = JobAssignment::query()
                            ->where('technician_id', $technician->getKey())
                            ->whereHas('job', fn ($query) => $query
                                ->where('status', JobStatus::Completed)
                                ->whereBetween('completed_at', [$periodStartsOn, $periodEndsOn]));
                        $completedJobs = $assignments->count();
                        $averageRating = (float) Feedback::query()
                            ->whereHas('job.assignments', fn ($query) => $query->where('technician_id', $technician->getKey()))
                            ->whereBetween('created_at', [$periodStartsOn, $periodEndsOn])
                            ->avg('rating');
                        $onTimeJobs = (clone $assignments)->whereHas('job', fn ($query) => $query->whereColumn('completed_at', '<=', 'scheduled_at'))->count();
                        $sla = $completedJobs === 0 ? 0 : round($onTimeJobs / $completedJobs * 100, 2);
                        $presentDays = AttendanceRecord::query()->where('user_id', $technician->getKey())->whereBetween('attendance_date', [$periodStartsOn, $periodEndsOn])->count();
                        $workingDays = max(1, $periodStartsOn->diffInWeekdays(min(now(), $periodEndsOn)) + 1);
                        $attendance = min(100, round($presentDays / $workingDays * 100, 2));
                        $score = round(($averageRating / 5 * 45) + ($sla * 0.35) + ($attendance * 0.20), 2);

                        TechnicianScorecard::query()->updateOrCreate([
                            'user_id' => $technician->getKey(),
                            'period_starts_on' => $periodStartsOn->toDateString(),
                            'period_ends_on' => $periodEndsOn->toDateString(),
                        ], [
                            'completed_jobs' => $completedJobs,
                            'average_rating' => $averageRating,
                            'rework_percentage' => 0,
                            'sla_percentage' => $sla,
                            'attendance_percentage' => $attendance,
                            'score' => $score,
                            'metrics' => ['present_days' => $presentDays, 'working_days' => $workingDays],
                            'computed_at' => now(),
                        ]);
                    });
            } finally {
                $tenantContext->clear();
            }
        });

        return self::SUCCESS;
    }
}
