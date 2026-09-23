<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClaimStatus;
use App\Enums\ReminderStatus;
use App\Enums\WarrantyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarrantyClaimRequest;
use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\ServiceReminder;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Services\Billing\PdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WarrantyController extends Controller
{
    public function index(): View
    {
        return view('admin.commerce.warranties', [
            'warranties' => Warranty::query()
                ->with(['customer:id,name,phone', 'asset:id,name,brand,model,serial_number', 'claims:id,warranty_id,claim_number,status,decision_notes,approved_amount,submitted_at'])
                ->orderBy('ends_on')
                ->paginate(20),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'assets' => Asset::query()->with('customer:id,name')->orderBy('name')->get(['id', 'customer_id', 'name', 'brand', 'model']),
            'warrantyTypes' => WarrantyType::cases(),
            'amcContracts' => AmcContract::query()->with(['customer:id,name', 'asset:id,name,brand,model'])->orderBy('ends_on')->limit(30)->get(),
            'claimStatuses' => [ClaimStatus::UnderReview, ClaimStatus::Approved, ClaimStatus::PartiallyApproved, ClaimStatus::Rejected, ClaimStatus::Closed],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCoreRecords(), 403);
        $data = $request->validate([
            'asset_id' => ['required', 'ulid', Rule::exists('assets', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'customer_id' => ['required', 'ulid', Rule::exists('customers', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'job_id' => ['nullable', 'ulid', Rule::exists('jobs', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'type' => ['required', Rule::enum(WarrantyType::class)],
            'provider' => ['nullable', 'string', 'max:160'],
            'policy_number' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'coverage_terms' => ['nullable', 'array'],
        ]);

        $warranty = Warranty::query()->create([...$data, 'status' => 'ACTIVE']);
        ServiceReminder::query()->create([
            'customer_id' => $warranty->customer_id,
            'asset_id' => $warranty->asset_id,
            'warranty_id' => $warranty->getKey(),
            'type' => 'WARRANTY_EXPIRY',
            'due_on' => $warranty->ends_on,
            'notify_on' => $warranty->ends_on->copy()->subDays((int) config('acserv.reminders.warranty_advance_days', 30)),
            'status' => ReminderStatus::Pending,
        ]);

        return response()->json(['message' => 'Warranty registered successfully.', 'warranty' => $warranty, 'reload' => true], 201);
    }

    public function storeClaim(StoreWarrantyClaimRequest $request): JsonResponse
    {
        $claim = WarrantyClaim::query()->create([
            ...$request->validated(),
            'claim_number' => 'CLM-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        return response()->json(['message' => 'Warranty claim submitted.', 'claim' => $claim, 'reload' => true], 201);
    }

    public function reviewClaim(Request $request, WarrantyClaim $warrantyClaim): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCoreRecords(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::enum(ClaimStatus::class), Rule::notIn([ClaimStatus::Submitted->value])],
            'decision_notes' => ['nullable', 'string', 'max:5000'],
            'approved_amount' => ['nullable', 'required_if:status,APPROVED,PARTIALLY_APPROVED', 'numeric', 'min:0'],
        ]);
        $status = ClaimStatus::from($data['status']);
        $warrantyClaim->update([
            ...$data,
            'decided_at' => in_array($status, [ClaimStatus::Approved, ClaimStatus::PartiallyApproved, ClaimStatus::Rejected, ClaimStatus::Closed], true) ? now() : null,
        ]);

        return response()->json(['message' => 'Warranty claim updated.', 'claim' => $warrantyClaim, 'reload' => true]);
    }

    public function storeAmc(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCoreRecords(), 403);
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'customer_id' => ['required', 'ulid', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'asset_id' => ['nullable', 'ulid', Rule::exists('assets', 'id')->where('tenant_id', $tenantId)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'included_visits' => ['required', 'integer', 'min:1', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($data['asset_id'])) {
            Asset::query()->where('customer_id', $data['customer_id'])->findOrFail($data['asset_id']);
        }

        $terms = $data['terms'] ?? null;
        $contract = AmcContract::query()->create([
            ...$data,
            'terms' => $terms === null ? null : ['description' => $terms],
            'contract_number' => 'AMC-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
            'used_visits' => 0,
            'status' => 'ACTIVE',
        ]);
        ServiceReminder::query()->create([
            'customer_id' => $contract->customer_id,
            'asset_id' => $contract->asset_id,
            'amc_contract_id' => $contract->getKey(),
            'type' => 'AMC_EXPIRY',
            'due_on' => $contract->ends_on,
            'notify_on' => $contract->ends_on->copy()->subDays((int) config('acserv.reminders.warranty_advance_days', 30)),
            'status' => ReminderStatus::Pending,
        ]);

        return response()->json(['message' => 'AMC contract created.', 'contract' => $contract, 'reload' => true], 201);
    }

    public function certificate(Warranty $warranty, PdfDocument $pdf): Response
    {
        $warranty->load(['customer', 'asset']);

        return response($pdf->make('ACServ Warranty Certificate', [
            'Customer: '.$warranty->customer->name,
            'Asset: '.$warranty->asset->brand.' '.$warranty->asset->model,
            'Serial: '.($warranty->asset->serial_number ?? 'N/A'),
            'Warranty type: '.$warranty->type->value,
            'Provider: '.($warranty->provider ?? 'ACServ'),
            'Policy: '.($warranty->policy_number ?? 'N/A'),
            'Valid from: '.$warranty->starts_on->format('d M Y'),
            'Valid until: '.$warranty->ends_on->format('d M Y'),
        ]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="warranty-'.$warranty->getKey().'.pdf"',
        ]);
    }
}
