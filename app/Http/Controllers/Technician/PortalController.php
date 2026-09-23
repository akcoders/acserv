<?php

namespace App\Http\Controllers\Technician;

use App\Enums\EvidenceType;
use App\Enums\JobStatus;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\TechnicianWorkflowRequest;
use App\Http\Requests\TransitionJobRequest;
use App\Models\AttendanceRecord;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobChecklistItem;
use App\Models\JobPartConsumption;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\PayoutDispute;
use App\Models\PayoutLine;
use App\Models\StockLocation;
use App\Models\TaxProfile;
use App\Services\Billing\JobInvoiceService;
use App\Services\Billing\PdfDocument;
use App\Services\Inventory\StockService;
use App\Services\Jobs\JobLifecycle;
use App\Services\Workforce\GeoFenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = Job::query()
            ->whereHas('assignments', fn ($query) => $query->where('technician_id', $request->user()->getKey()))
            ->with(['customer:id,name,phone,service_address', 'asset:id,name,brand,model,serial_number', 'assignments' => fn ($query) => $query->where('technician_id', $request->user()->getKey())])
            ->whereNotIn('status', [JobStatus::Closed, JobStatus::Cancelled])
            ->orderByRaw('scheduled_at is null')
            ->orderBy('scheduled_at')
            ->get();

        return view('technician.index', [
            'jobs' => $jobs,
            'attendance' => AttendanceRecord::query()->where('user_id', $request->user()->getKey())->whereDate('attendance_date', today())->first(),
            'profile' => $request->user()->technicianProfile()->with('branch')->first(),
            'payoutLines' => PayoutLine::query()->where('user_id', $request->user()->getKey())->with(['cycle', 'disputes'])->latest()->limit(12)->get(),
            'leaveBalances' => LeaveBalance::query()->where('user_id', $request->user()->getKey())->where('year', today()->year)->get(),
            'leaveRequests' => LeaveRequest::query()->where('user_id', $request->user()->getKey())->latest()->limit(8)->get(),
        ]);
    }

    public function show(Request $request, Job $job, JobInvoiceService $invoices): View
    {
        $this->assertAssigned($request, $job);

        return view('technician.job', [
            'job' => $job->load(['customer', 'asset', 'checklistItems', 'evidence', 'partConsumptions.inventoryItem', 'invoice.lines', 'paymentCollections.reviewer']),
            'billTotal' => $invoices->estimatedTotal($job),
            'paymentTenant' => $request->user()->tenant()->firstOrFail(),
            'inventoryItems' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'sale_price']),
            'stockLocations' => $this->availableStockLocations($request, $job)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function workflow(TechnicianWorkflowRequest $request, Job $job, JobLifecycle $lifecycle): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $updatedJob = $lifecycle->technicianAction($job, $request->user(), $request->validated());
        $updatedJob->load('invoice');

        return response()->json([
            'message' => 'Job moved to '.str($updatedJob->status->value)->headline().'.',
            'job' => $updatedJob,
            'invoice_url' => $updatedJob->invoice ? route('technician.jobs.invoice.pdf', $updatedJob) : null,
            'reload' => true,
        ]);
    }

    public function serviceCost(Request $request, Job $job): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $data = $request->validate([
            'service_cost' => ['required', 'numeric', 'between:0,999999999999.99'],
        ]);

        $updatedJob = DB::transaction(function () use ($request, $job, $data): Job {
            $lockedJob = Job::query()->lockForUpdate()->findOrFail($job->getKey());
            abort_unless($lockedJob->status === JobStatus::InProgress, 422, 'Service charges can be edited only while work is in progress.');
            abort_unless($lockedJob->assignments()->where('technician_id', $request->user()->getKey())->where('status', 'ACCEPTED')->exists(), 403);
            $lockedJob->update([
                'service_cost' => $data['service_cost'],
                'service_tax_rate' => TaxProfile::query()->where('is_default', true)->value('tax_rate') ?? 18,
            ]);

            return $lockedJob->refresh();
        });

        return response()->json(['message' => 'Service charges saved.', 'job' => $updatedJob, 'reload' => true]);
    }

    public function invoice(Request $request, Job $job, PdfDocument $pdf): Response
    {
        $this->assertAssigned($request, $job);
        $invoice = $job->invoice()->firstOrFail();

        return response($pdf->invoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    public function transition(TransitionJobRequest $request, Job $job, JobLifecycle $lifecycle): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $data = $request->validated();
        $updatedJob = $lifecycle->transition($job, JobStatus::from($data['status']), $request->user(), $data);

        return response()->json(['message' => 'Job status updated.', 'job' => $updatedJob, 'reload' => true]);
    }

    public function storeEvidence(StoreEvidenceRequest $request, Job $job): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $data = $request->validated();
        $capturedAt = Carbon::parse($data['captured_at']);

        if ($capturedAt->diffInSeconds(now(), absolute: true) > 120) {
            throw ValidationException::withMessages(['captured_at' => 'Evidence timestamp must be within two minutes of server time.']);
        }

        $file = $request->file('evidence');
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = $file->store("tenants/{$request->user()->tenant_id}/jobs/{$job->getKey()}/evidence", 'local');
        $assignment = $job->assignments()->where('technician_id', $request->user()->getKey())->firstOrFail();
        $evidence = $job->evidence()->create([
            'job_assignment_id' => $assignment->getKey(),
            'uploaded_by' => $request->user()->getKey(),
            'type' => EvidenceType::from($data['type']),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => $sha256,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy_metres' => $data['accuracy_metres'],
            'captured_at' => $capturedAt,
            'device_id' => $data['device_id'],
            'is_mock_location' => false,
        ]);

        return response()->json(['message' => 'Evidence uploaded securely.', 'evidence' => $evidence, 'reload' => true], 201);
    }

    public function attendance(StoreAttendanceRequest $request, GeoFenceService $geoFence): JsonResponse
    {
        $profile = $request->user()->technicianProfile()->with('branch')->first();

        if ($profile?->branch === null) {
            throw ValidationException::withMessages(['latitude' => 'Your attendance branch is not configured.']);
        }

        $data = $request->validated();
        $geoFence->assertAllowed(
            $profile->branch,
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $data['accuracy_metres'],
            (bool) $data['is_mock_location'],
        );
        $file = $request->file('selfie');
        $path = $file->store("tenants/{$request->user()->tenant_id}/attendance/{$request->user()->getKey()}", 'local');

        $record = DB::transaction(function () use ($request, $profile, $data, $path): AttendanceRecord {
            $record = AttendanceRecord::query()->lockForUpdate()->firstOrNew([
                'user_id' => $request->user()->getKey(),
                'attendance_date' => today()->toDateString(),
            ]);

            if ($data['action'] === 'check-in') {
                abort_if($record->checked_in_at !== null, 422, 'You have already checked in today.');
                $record->fill([
                    'branch_id' => $profile->branch_id,
                    'checked_in_at' => now(),
                    'check_in_latitude' => $data['latitude'],
                    'check_in_longitude' => $data['longitude'],
                    'check_in_accuracy_metres' => $data['accuracy_metres'],
                    'check_in_selfie_path' => $path,
                    'check_in_device_id' => $data['device_id'],
                    'status' => 'PRESENT',
                ]);
            } else {
                abort_if($record->checked_in_at === null || $record->checked_out_at !== null, 422, 'A valid open attendance record is required.');
                $record->fill([
                    'checked_out_at' => now(),
                    'check_out_latitude' => $data['latitude'],
                    'check_out_longitude' => $data['longitude'],
                    'check_out_accuracy_metres' => $data['accuracy_metres'],
                    'check_out_selfie_path' => $path,
                    'check_out_device_id' => $data['device_id'],
                    'worked_minutes' => $record->checked_in_at->diffInMinutes(now()),
                ]);
            }

            $record->save();

            return $record;
        });

        return response()->json(['message' => 'Attendance recorded.', 'attendance' => $record, 'reload' => true]);
    }

    public function storeLeave(StoreLeaveRequest $request): JsonResponse
    {
        $data = $request->validated();
        $days = Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) + 1;
        $leave = LeaveRequest::query()->create([
            ...$data,
            'user_id' => $request->user()->getKey(),
            'days' => $days,
            'status' => 'PENDING',
        ]);

        return response()->json(['message' => 'Leave request submitted.', 'leave' => $leave, 'reload' => true], 201);
    }

    public function disputePayout(Request $request, PayoutLine $payoutLine): JsonResponse
    {
        abort_unless($payoutLine->user_id === $request->user()->getKey(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        $dispute = DB::transaction(function () use ($payoutLine, $request, $data): PayoutDispute {
            abort_if($payoutLine->disputes()->where('status', 'OPEN')->exists(), 422, 'An open dispute already exists for this statement.');
            $payoutLine->update(['status' => PayoutStatus::Disputed]);

            return $payoutLine->disputes()->create([
                'user_id' => $request->user()->getKey(),
                'status' => 'OPEN',
                'reason' => $data['reason'],
            ]);
        });

        return response()->json(['message' => 'Payout dispute submitted.', 'dispute' => $dispute, 'reload' => true], 201);
    }

    public function payslip(Request $request, PayoutLine $payoutLine, PdfDocument $pdf): Response
    {
        abort_unless($payoutLine->user_id === $request->user()->getKey(), 404);
        $payoutLine->load(['cycle', 'user']);

        return response($pdf->make('ACServ Payslip', [
            'Cycle: '.$payoutLine->cycle->cycle_number,
            'Employee: '.$payoutLine->user->name,
            'Period: '.$payoutLine->cycle->starts_on->format('d M Y').' - '.$payoutLine->cycle->ends_on->format('d M Y'),
            'Completed jobs: '.$payoutLine->job_count,
            'Worked hours: '.$payoutLine->worked_hours,
            'Base amount: INR '.$payoutLine->base_amount,
            'Incentive: INR '.$payoutLine->incentive_amount,
            'Deductions: INR '.$payoutLine->deduction_amount,
            'Net payout: INR '.$payoutLine->net_amount,
        ]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payslip-'.$payoutLine->getKey().'.pdf"',
        ]);
    }

    public function checklist(Request $request, Job $job, JobChecklistItem $item): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $this->assertWorkInProgress($request, $job);
        abort_unless($item->job_id === $job->getKey(), 404);
        $item->update([
            'completed_at' => $item->completed_at === null ? now() : null,
            'completed_by' => $item->completed_at === null ? $request->user()->getKey() : null,
        ]);

        return response()->json(['message' => 'Checklist updated.', 'reload' => true]);
    }

    public function consumePart(Request $request, Job $job, StockService $stock): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $this->assertWorkInProgress($request, $job);
        $data = $request->validate([
            'inventory_item_id' => ['required', 'ulid'],
            'stock_location_id' => ['required', 'ulid'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $item = InventoryItem::query()->with('taxProfile')->where('is_active', true)->findOrFail($data['inventory_item_id']);
        $location = $this->availableStockLocations($request, $job)->findOrFail($data['stock_location_id']);
        $taxRate = $item->taxProfile?->tax_rate ?? TaxProfile::query()->where('is_default', true)->value('tax_rate') ?? 18;
        $consumption = $stock->consume($job, $item, $location, (float) $data['quantity'], (float) $item->sale_price, (float) $taxRate);

        return response()->json(['message' => 'Part consumption recorded.', 'consumption' => $consumption, 'reload' => true], 201);
    }

    public function returnPart(Request $request, Job $job, JobPartConsumption $consumption, StockService $stock): JsonResponse
    {
        $this->assertAssigned($request, $job);
        $this->assertWorkInProgress($request, $job);
        abort_unless($consumption->job_id === $job->getKey(), 404);
        $location = $this->availableStockLocations($request, $job)->findOrFail($consumption->stock_location_id);
        abort_unless($location->getKey() === $consumption->stock_location_id, 404);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0']]);
        $updated = $stock->returnConsumption($consumption, (float) $data['quantity']);

        return response()->json(['message' => 'Part return recorded.', 'consumption' => $updated, 'reload' => true]);
    }

    private function assertAssigned(Request $request, Job $job): void
    {
        abort_unless($job->assignments()->where('technician_id', $request->user()->getKey())->exists(), 404);
    }

    private function assertWorkInProgress(Request $request, Job $job): void
    {
        abort_unless($job->status === JobStatus::InProgress, 422, 'Start work before recording parts or checklist progress.');
        abort_unless($job->assignments()->where('technician_id', $request->user()->getKey())->where('status', 'ACCEPTED')->exists(), 403);
    }

    private function availableStockLocations(Request $request, Job $job): Builder
    {
        return StockLocation::query()
            ->where('is_active', true)
            ->where(function ($query) use ($request, $job): void {
                $query->where('custodian_id', $request->user()->getKey())
                    ->orWhere(function ($shared) use ($job): void {
                        $shared->whereNull('custodian_id')
                            ->where(function ($branch) use ($job): void {
                                $branch->whereNull('branch_id');
                                if ($job->branch_id !== null) {
                                    $branch->orWhere('branch_id', $job->branch_id);
                                }
                            });
                    });
            });
    }
}
