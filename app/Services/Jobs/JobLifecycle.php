<?php

namespace App\Services\Jobs;

use App\Enums\AssignmentStatus;
use App\Enums\EvidenceType;
use App\Enums\JobStatus;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\Job;
use App\Models\JobAssignment;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class JobLifecycle
{
    /** @var array<string, array<int, JobStatus>> */
    private const TRANSITIONS = [
        'CREATED' => [JobStatus::Assigned, JobStatus::Cancelled],
        'ASSIGNED' => [JobStatus::Accepted, JobStatus::Cancelled],
        'ACCEPTED' => [JobStatus::EnRoute, JobStatus::Reached, JobStatus::Cancelled],
        'EN_ROUTE' => [JobStatus::Reached, JobStatus::Cancelled],
        'REACHED' => [JobStatus::Inspected, JobStatus::Cancelled],
        'INSPECTED' => [JobStatus::Authorized, JobStatus::Cancelled],
        'AUTHORIZED' => [JobStatus::InProgress, JobStatus::Cancelled],
        'IN_PROGRESS' => [JobStatus::AwaitingPayment, JobStatus::Cancelled],
        'AWAITING_PAYMENT' => [],
        'PAYMENT_PENDING' => [],
        'COMPLETED' => [JobStatus::Verified],
        'VERIFIED' => [JobStatus::Closed],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /** @param array<string, mixed> $data */
    public function technicianAction(Job $job, User $actor, array $data): Job
    {
        $storedPaths = [];

        try {
            $updatedJob = DB::transaction(function () use ($job, $actor, $data, &$storedPaths): Job {
                $lockedJob = Job::query()->lockForUpdate()->findOrFail($job->getKey());
                abort_unless($lockedJob->tenant_id === $actor->tenant_id && $actor->role === Role::Technician, 403);

                $assignment = $lockedJob->assignments()->where('technician_id', $actor->getKey())->lockForUpdate()->firstOrFail();
                $action = $data['action'];

                if ($action === 'accept') {
                    $this->assertStage($lockedJob, [JobStatus::Assigned, JobStatus::Accepted], $action);
                    abort_unless($assignment->status === AssignmentStatus::Assigned, 422, 'This assignment has already been accepted or closed.');
                    $assignment->update(['status' => AssignmentStatus::Accepted, 'accepted_at' => now()]);
                    if ($lockedJob->status === JobStatus::Assigned) {
                        $lockedJob->update(['status' => JobStatus::Accepted]);
                    }
                } else {
                    abort_unless($assignment->status === AssignmentStatus::Accepted, 422, 'Accept the assignment before working on this job.');

                    match ($action) {
                        'reached' => $this->markReached($lockedJob),
                        'inspection' => $this->recordInspection($lockedJob, $assignment, $actor, $data, $storedPaths),
                        'authorize' => $this->recordAuthorization($lockedJob, $assignment, $actor, $data, $storedPaths),
                        'start' => $this->startWork($lockedJob, $data),
                        'complete' => $this->completeWork($lockedJob, $assignment, $actor, $data, $storedPaths),
                        default => throw ValidationException::withMessages(['action' => 'Choose a valid technician action.']),
                    };
                }

                return $lockedJob->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        $this->afterStatusChange($updatedJob);

        return $updatedJob;
    }

    /** @param array<string, mixed> $data */
    public function transition(Job $job, JobStatus $nextStatus, User $actor, array $data): Job
    {
        $job = DB::transaction(function () use ($job, $nextStatus, $actor): Job {
            $lockedJob = Job::query()->lockForUpdate()->findOrFail($job->getKey());
            $allowed = self::TRANSITIONS[$lockedJob->status->value] ?? [];

            if (! in_array($nextStatus, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "The job cannot move from {$lockedJob->status->value} to {$nextStatus->value}.",
                ]);
            }

            $isTechnician = $actor->role === Role::Technician;

            if ($isTechnician) {
                throw ValidationException::withMessages(['status' => 'Use the technician workflow actions to update this job.']);
            }

            if (! in_array($nextStatus, [JobStatus::Cancelled, JobStatus::Verified, JobStatus::Closed], true)) {
                throw ValidationException::withMessages(['status' => 'Use dispatch to assign jobs; guided work stages can only be advanced by the assigned technician.']);
            }

            $updates = ['status' => $nextStatus];

            if ($nextStatus === JobStatus::Verified) {
                $updates['verified_at'] = now();
            }

            if ($nextStatus === JobStatus::Closed) {
                $updates['closed_at'] = now();
            }

            if ($nextStatus === JobStatus::Cancelled) {
                $updates['cancelled_at'] = now();
            }

            $lockedJob->update($updates);

            return $lockedJob->refresh();
        });

        $this->afterStatusChange($job);

        return $job;
    }

    private function markReached(Job $job): void
    {
        $this->assertStage($job, [JobStatus::Accepted, JobStatus::EnRoute], 'reached');
        $job->update(['status' => JobStatus::Reached, 'reached_at' => now()]);
    }

    /** @param array<string, mixed> $data @param array<int, string> $storedPaths */
    private function recordInspection(Job $job, JobAssignment $assignment, User $actor, array $data, array &$storedPaths): void
    {
        $this->assertStage($job, [JobStatus::Reached], 'inspection');
        $this->storeEvidence($job, $assignment, $actor, $data['before_photo'], EvidenceType::Before, $data, ['stage' => 'inspection', 'remark' => $data['fault_remark']], $storedPaths);
        $job->update([
            'status' => JobStatus::Inspected,
            'inspected_at' => now(),
            'inspection_remark' => $data['fault_remark'],
        ]);
    }

    /** @param array<string, mixed> $data @param array<int, string> $storedPaths */
    private function recordAuthorization(Job $job, JobAssignment $assignment, User $actor, array $data, array &$storedPaths): void
    {
        $this->assertStage($job, [JobStatus::Inspected], 'authorize');
        $path = $this->storeEvidence($job, $assignment, $actor, $data['customer_signature'], EvidenceType::Signature, $data, ['stage' => 'prework'], $storedPaths);
        $job->update([
            'status' => JobStatus::Authorized,
            'authorized_at' => now(),
            'prework_signature_path' => $path,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function startWork(Job $job, array $data): void
    {
        $this->assertStage($job, [JobStatus::Authorized], 'start');

        if (! $job->prework_signature_path || ! $job->evidence()->where('type', EvidenceType::Before)->exists()) {
            throw ValidationException::withMessages(['action' => 'Inspection photo and customer authorization are required before work begins.']);
        }

        $job->update([
            'status' => JobStatus::InProgress,
            'started_at' => now(),
            'start_latitude' => $data['latitude'] ?? null,
            'start_longitude' => $data['longitude'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $data @param array<int, string> $storedPaths */
    private function completeWork(Job $job, JobAssignment $assignment, User $actor, array $data, array &$storedPaths): void
    {
        $this->assertStage($job, [JobStatus::InProgress], 'complete');

        if ($job->checklistItems()->where('is_required', true)->whereNull('completed_at')->exists()) {
            throw ValidationException::withMessages(['action' => 'Complete all required checklist items before submitting the job.']);
        }

        $this->storeEvidence($job, $assignment, $actor, $data['after_photo'], EvidenceType::After, $data, ['stage' => 'completion', 'remark' => $data['completion_remark']], $storedPaths);
        $signaturePath = $this->storeEvidence($job, $assignment, $actor, $data['customer_signature'], EvidenceType::Signature, $data, ['stage' => 'completion'], $storedPaths);

        $job->update([
            'status' => JobStatus::AwaitingPayment,
            'completed_at' => now(),
            'completion_remark' => $data['completion_remark'],
            'resolution' => $data['completion_remark'],
            'end_latitude' => $data['latitude'] ?? null,
            'end_longitude' => $data['longitude'] ?? null,
            'customer_signature_path' => $signaturePath,
        ]);
        $assignment->update(['status' => AssignmentStatus::Completed, 'completed_at' => now()]);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $metadata @param array<int, string> $storedPaths */
    private function storeEvidence(Job $job, JobAssignment $assignment, User $actor, UploadedFile $file, EvidenceType $type, array $data, array $metadata, array &$storedPaths): string
    {
        $path = $file->store("tenants/{$job->tenant_id}/jobs/{$job->getKey()}/evidence", 'local');
        $storedPaths[] = $path;
        $job->evidence()->create([
            'job_assignment_id' => $assignment->getKey(),
            'uploaded_by' => $actor->getKey(),
            'type' => $type,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_metres' => $data['accuracy_metres'] ?? null,
            'captured_at' => isset($data['captured_at']) ? Carbon::parse($data['captured_at']) : now(),
            'device_id' => $data['device_id'] ?? 'technician-web',
            'is_mock_location' => false,
            'metadata' => $metadata,
        ]);

        return $path;
    }

    /** @param array<int, JobStatus> $allowedStages */
    private function assertStage(Job $job, array $allowedStages, string $action): void
    {
        if (! in_array($job->status, $allowedStages, true)) {
            throw ValidationException::withMessages(['action' => "The {$action} action is unavailable while this job is {$job->status->value}."]);
        }
    }

    private function afterStatusChange(Job $job): void
    {
        foreach ($job->assignments()->with('technician')->get() as $assignment) {
            $this->notifications->queue('job.status_changed', $assignment->technician, [
                'job_number' => $job->job_number,
                'status' => $job->status->value,
            ]);
        }

        $job->load('customer.user');

        if ($job->customer->user !== null) {
            $this->notifications->queue('job.status_changed', $job->customer->user, [
                'job_number' => $job->job_number,
                'status' => $job->status->value,
            ]);
        }

        if ($job->status === JobStatus::Completed) {
            $dueOn = today()->addMonths((int) config('acserv.reminders.service_interval_months', 3));
            ServiceReminder::query()->create([
                'customer_id' => $job->customer_id,
                'asset_id' => $job->asset_id,
                'type' => 'NEXT_SERVICE',
                'due_on' => $dueOn,
                'notify_on' => $dueOn->copy()->subDays((int) config('acserv.reminders.advance_days', 7)),
                'status' => ReminderStatus::Pending,
                'metadata' => ['source_job_id' => $job->getKey()],
            ]);
        }

    }
}
