<?php

namespace App\Services\Workforce;

use App\Enums\JobStatus;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Feedback;
use App\Models\JobAssignment;
use App\Models\PayoutCycle;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayoutService
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /** @param array<string, mixed> $rates */
    public function generate(string $type, string $startsOn, string $endsOn, array $rates): PayoutCycle
    {
        $cycle = DB::transaction(function () use ($type, $startsOn, $endsOn, $rates): PayoutCycle {
            $cycle = PayoutCycle::query()->create([
                'cycle_number' => 'PAYOUT-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'type' => $type,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => PayoutStatus::Processing,
            ]);

            $grossTotal = 0.0;
            $deductionTotal = 0.0;
            $netTotal = 0.0;

            User::query()
                ->where('tenant_id', $cycle->tenant_id)
                ->where('role', Role::Technician)
                ->where('status', 'ACTIVE')
                ->orderBy('id')
                ->each(function (User $technician) use ($cycle, $startsOn, $endsOn, $rates, &$grossTotal, &$deductionTotal, &$netTotal): void {
                    $assignments = JobAssignment::query()
                        ->where('technician_id', $technician->getKey())
                        ->whereHas('job', fn ($query) => $query
                            ->where('status', JobStatus::Completed)
                            ->whereBetween('completed_at', [$startsOn.' 00:00:00', $endsOn.' 23:59:59']))
                        ->with('job:id,completed_at')
                        ->get();
                    $workedMinutes = (int) AttendanceRecord::query()
                        ->where('user_id', $technician->getKey())
                        ->whereBetween('attendance_date', [$startsOn, $endsOn])
                        ->sum('worked_minutes');
                    $averageRating = (float) Feedback::query()
                        ->whereHas('job.assignments', fn ($query) => $query->where('technician_id', $technician->getKey()))
                        ->whereBetween('created_at', [$startsOn.' 00:00:00', $endsOn.' 23:59:59'])
                        ->avg('rating');

                    $jobAmount = $assignments->count() * (float) $rates['per_job_rate'];
                    $hourAmount = round(($workedMinutes / 60) * (float) $rates['base_hourly_rate'], 2);
                    $incentive = $averageRating >= 4.5 ? (float) ($rates['rating_incentive'] ?? 0) : 0.0;
                    $penalty = $averageRating > 0 && $averageRating < 3 ? (float) ($rates['low_rating_penalty'] ?? 0) : 0.0;
                    $deductions = (float) ($rates['fixed_deduction'] ?? 0);
                    $gross = round($jobAmount + $hourAmount + $incentive, 2);
                    $net = max(0, round($gross - $penalty - $deductions, 2));

                    $cycle->lines()->create([
                        'user_id' => $technician->getKey(),
                        'job_count' => $assignments->count(),
                        'worked_hours' => round($workedMinutes / 60, 2),
                        'base_amount' => $jobAmount + $hourAmount,
                        'incentive_amount' => $incentive,
                        'penalty_amount' => $penalty,
                        'deduction_amount' => $deductions,
                        'net_amount' => $net,
                        'details' => ['average_rating' => $averageRating],
                        'status' => PayoutStatus::Processed,
                    ]);

                    $grossTotal += $gross;
                    $deductionTotal += $penalty + $deductions;
                    $netTotal += $net;
                });

            $cycle->update([
                'status' => PayoutStatus::Processed,
                'gross_total' => round($grossTotal, 2),
                'deduction_total' => round($deductionTotal, 2),
                'net_total' => round($netTotal, 2),
                'processed_at' => now(),
            ]);

            return $cycle->load('lines.user');
        });

        foreach ($cycle->lines as $line) {
            $this->notifications->queue('payout.processed', $line->user, [
                'cycle_number' => $cycle->cycle_number,
                'net_amount' => $line->net_amount,
            ]);
        }

        return $cycle;
    }
}
