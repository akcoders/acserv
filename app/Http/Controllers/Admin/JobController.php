<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignJobRequest;
use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\TransitionJobRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobEvidence;
use App\Models\User;
use App\Services\Billing\PdfDocument;
use App\Services\Jobs\AssignmentEngine;
use App\Services\Jobs\JobLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = Job::query()
            ->with(['customer:id,name,phone', 'asset:id,name,brand,model', 'branch:id,name', 'assignments.technician:id,first_name,last_name'])
            ->withCount('evidence')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(fn ($filter) => $filter
                    ->where('job_number', 'like', $search)
                    ->orWhere('service_type', 'like', $search)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $search)));
            })
            ->orderByRaw('scheduled_at is null')
            ->orderBy('scheduled_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $stageCounts = Job::query()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $pipelineJobs = Job::query()
            ->with(['customer:id,name', 'assignments.technician:id,first_name,last_name'])
            ->whereNotIn('status', [JobStatus::Closed, JobStatus::Cancelled])
            ->latest('updated_at')
            ->limit(80)
            ->get()
            ->groupBy(fn (Job $job): string => $job->status->value);

        return view('admin.operations.jobs', [
            'jobs' => $jobs,
            'stageCounts' => $stageCounts,
            'pipelineJobs' => $pipelineJobs,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'technicians' => User::query()->forTenant($request->user()->tenant_id)->where('role', Role::Technician)->where('status', 'ACTIVE')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => JobStatus::cases(),
        ]);
    }

    public function show(Job $job): View
    {
        $job->load([
            'customer', 'asset', 'branch', 'assignments.technician', 'checklistItems',
            'evidence.uploader', 'partConsumptions.inventoryItem', 'partConsumptions.stockLocation',
            'invoice.lines', 'paymentCollections.technician',
        ]);

        $timeline = collect([
            ['title' => 'Job created', 'at' => $job->created_at, 'detail' => $job->description ?: 'Service request logged.'],
            ['title' => 'Technician assigned', 'at' => $job->assignments->min('assigned_at'), 'detail' => $job->assignments->pluck('technician.name')->filter()->join(', ')],
            ['title' => 'Assignment accepted', 'at' => $job->assignments->min('accepted_at'), 'detail' => 'Technician accepted the job.'],
            ['title' => 'Reached location', 'at' => $job->reached_at, 'detail' => 'Technician checked in on site.'],
            ['title' => 'Inspection recorded', 'at' => $job->inspected_at, 'detail' => $job->inspection_remark],
            ['title' => 'Customer authorized', 'at' => $job->authorized_at, 'detail' => 'Pre-work signature captured.'],
            ['title' => 'Work started', 'at' => $job->started_at, 'detail' => 'Repair or service began.'],
            ['title' => 'Work submitted', 'at' => $job->completed_at, 'detail' => $job->completion_remark],
            ['title' => 'Payment submitted', 'at' => $job->paymentCollections->min('submitted_at'), 'detail' => 'On-site collection awaiting review.'],
            ['title' => 'Payment verified', 'at' => $job->paymentCollections->first(fn ($collection): bool => $collection->status?->value === 'VERIFIED')?->reviewed_at, 'detail' => 'Payment checked by the office.'],
            ['title' => 'Job verified', 'at' => $job->verified_at, 'detail' => 'Manager approved the service record.'],
            ['title' => 'Job closed', 'at' => $job->closed_at, 'detail' => 'Service record closed.'],
        ])->filter(fn (array $event): bool => $event['at'] !== null)->values();

        return view('admin.operations.job-show', compact('job', 'timeline'));
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $job = DB::transaction(function () use ($request): Job {
            $data = $request->validated();
            $checklist = $data['checklist'] ?? [];
            unset($data['checklist']);

            $job = Job::query()->create([
                ...$data,
                'job_number' => 'JOB-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'status' => JobStatus::Created,
            ]);

            foreach ($checklist as $sortOrder => $label) {
                $job->checklistItems()->create([
                    'label' => $label,
                    'is_required' => true,
                    'sort_order' => $sortOrder,
                ]);
            }

            return $job;
        });

        return response()->json([
            'message' => 'Job created successfully.',
            'job' => $job,
            'reload' => true,
        ], 201);
    }

    public function assign(AssignJobRequest $request, Job $job, AssignmentEngine $assignmentEngine): JsonResponse
    {
        $technician = User::query()
            ->forTenant($request->user()->tenant_id)
            ->findOrFail($request->validated('technician_id'));
        $assignment = $assignmentEngine->assign($job, $technician, $request->validated('notes'));

        return response()->json([
            'message' => 'Technician assigned successfully.',
            'assignment' => $assignment,
            'reload' => true,
        ]);
    }

    public function suggestions(Job $job, AssignmentEngine $assignmentEngine): JsonResponse
    {
        return response()->json(['data' => $assignmentEngine->suggestions($job)]);
    }

    public function transition(TransitionJobRequest $request, Job $job, JobLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validated();
        $updatedJob = $lifecycle->transition($job, JobStatus::from($data['status']), $request->user(), $data);

        return response()->json([
            'message' => 'Job status updated successfully.',
            'job' => $updatedJob,
            'reload' => true,
        ]);
    }

    public function destroy(Job $job): JsonResponse
    {
        abort_unless($job->status === JobStatus::Created, 422, 'Only unstarted jobs can be deleted.');
        $job->delete();

        return response()->json(['message' => 'Job deleted successfully.', 'reload' => true]);
    }

    public function downloadJobCard(Job $job, PdfDocument $pdf): Response
    {
        return response($pdf->jobCard($job), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$job->job_number.'.pdf"',
        ]);
    }

    public function evidence(Job $job, JobEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->job_id === $job->getKey() && str_starts_with($evidence->mime_type, 'image/'), 404);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->response($evidence->path, null, [
            'Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
