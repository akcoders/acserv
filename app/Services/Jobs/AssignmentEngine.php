<?php

namespace App\Services\Jobs;

use App\Enums\AssignmentStatus;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobAssignment;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentEngine
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /** @return Collection<int, TechnicianProfile> */
    public function suggestions(Job $job, int $limit = 10): Collection
    {
        $scheduledDate = $job->scheduled_at?->toDateString();
        $service = mb_strtolower($job->service_type);
        $zones = collect($job->service_address ?? [])
            ->only(['city', 'state', 'postal_code'])
            ->filter()
            ->map(fn ($value): string => mb_strtolower((string) $value));

        return TechnicianProfile::query()
            ->with('user:id,first_name,last_name,email,phone')
            ->where('is_available', true)
            ->when($job->branch_id, fn (Builder $query, string $branchId): Builder => $query
                ->where(fn (Builder $branchQuery): Builder => $branchQuery
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id')))
            ->withCount(['assignments as scheduled_jobs_count' => fn (Builder $query): Builder => $query
                ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])
                ->when($scheduledDate, fn (Builder $assignmentQuery): Builder => $assignmentQuery
                    ->whereHas('job', fn (Builder $jobQuery): Builder => $jobQuery->whereDate('scheduled_at', $scheduledDate)))])
            ->orderBy('scheduled_jobs_count')
            ->orderBy('id')
            ->get()
            ->filter(fn (TechnicianProfile $profile): bool => $profile->scheduled_jobs_count < $profile->max_daily_jobs)
            ->each(function (TechnicianProfile $profile) use ($service, $zones): void {
                $skillMatch = collect($profile->skills ?? [])->contains(function ($skill) use ($service): bool {
                    $skill = mb_strtolower((string) $skill);

                    return str_contains($service, $skill) || str_contains($skill, $service);
                });
                $zoneMatch = collect($profile->service_zones ?? [])->contains(function ($zone) use ($zones): bool {
                    $zone = mb_strtolower((string) $zone);

                    return $zones->contains(fn (string $value): bool => str_contains($value, $zone) || str_contains($zone, $value));
                });
                $profile->setAttribute('assignment_match_score', ($skillMatch ? 2 : 0) + ($zoneMatch ? 1 : 0));
            })
            ->sortBy([
                ['assignment_match_score', 'desc'],
                ['scheduled_jobs_count', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    public function assign(Job $job, User $technician, ?string $notes = null): JobAssignment
    {
        $assignment = DB::transaction(function () use ($job, $technician, $notes): JobAssignment {
            $lockedJob = Job::query()->lockForUpdate()->findOrFail($job->getKey());

            if (! in_array($lockedJob->status, [JobStatus::Created, JobStatus::Assigned], true)) {
                throw ValidationException::withMessages(['technician_id' => 'Only a new or awaiting-acceptance job can be assigned.']);
            }

            $assignment = JobAssignment::query()->updateOrCreate(
                ['job_id' => $lockedJob->getKey(), 'technician_id' => $technician->getKey()],
                [
                    'assigned_by' => auth()->id(),
                    'status' => AssignmentStatus::Assigned,
                    'assigned_at' => now(),
                    'notes' => $notes,
                ],
            );

            if ($lockedJob->status === JobStatus::Created) {
                $lockedJob->update(['status' => JobStatus::Assigned]);
            }

            return $assignment;
        });

        $this->notifications->queue('job.assigned', $technician, [
            'job_number' => $job->job_number,
            'scheduled_at' => $job->scheduled_at?->toIso8601String(),
        ]);

        return $assignment;
    }
}
