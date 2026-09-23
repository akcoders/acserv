<?php

namespace App\Services\Billing;

use App\Enums\CollectionStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMode;
use App\Enums\Role;
use App\Models\Job;
use App\Models\JobPaymentCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobCollectionService
{
    public function __construct(
        private readonly JobInvoiceService $invoices,
        private readonly BillingService $billing,
    ) {}

    public function submit(Job $job, User $technician, PaymentMode $mode, ?string $proofPath, ?string $reference): JobPaymentCollection
    {
        return DB::transaction(function () use ($job, $technician, $mode, $proofPath, $reference): JobPaymentCollection {
            $lockedJob = Job::query()->lockForUpdate()->findOrFail($job->getKey());
            abort_unless($technician->role === Role::Technician && $technician->tenant_id === $lockedJob->tenant_id, 403);
            abort_unless($lockedJob->assignments()->where('technician_id', $technician->getKey())->where('status', 'COMPLETED')->exists(), 403);

            if ($lockedJob->status !== JobStatus::AwaitingPayment) {
                throw ValidationException::withMessages(['job' => 'This job is not ready for payment collection.']);
            }

            if ($mode === PaymentMode::Upi && ($proofPath === null || ! $technician->tenant()->value('upi_qr_path'))) {
                throw ValidationException::withMessages(['transaction_image' => 'UPI QR and transaction screenshot are required.']);
            }

            $collection = $lockedJob->paymentCollections()->create([
                'technician_id' => $technician->getKey(),
                'mode' => $mode,
                'status' => CollectionStatus::Pending,
                'amount' => $this->invoices->estimatedTotal($lockedJob),
                'reference' => $reference,
                'proof_path' => $proofPath,
                'submitted_at' => now(),
            ]);
            $lockedJob->update(['status' => JobStatus::PaymentPending]);

            return $collection;
        });
    }

    public function review(JobPaymentCollection $collection, User $reviewer, bool $verify, ?string $rejectionReason): JobPaymentCollection
    {
        return DB::transaction(function () use ($collection, $reviewer, $verify, $rejectionReason): JobPaymentCollection {
            abort_unless($reviewer->role?->canManageBilling(), 403);
            $locked = JobPaymentCollection::query()->lockForUpdate()->findOrFail($collection->getKey());
            $job = Job::query()->lockForUpdate()->findOrFail($locked->job_id);
            abort_unless($job->tenant_id === $reviewer->tenant_id, 404);

            if ($locked->status !== CollectionStatus::Pending || $job->status !== JobStatus::PaymentPending) {
                throw ValidationException::withMessages(['collection' => 'This collection has already been reviewed.']);
            }

            if (! $verify) {
                $locked->update([
                    'status' => CollectionStatus::Rejected,
                    'reviewed_by' => $reviewer->getKey(),
                    'reviewed_at' => now(),
                    'rejection_reason' => $rejectionReason,
                ]);
                $job->update(['status' => JobStatus::AwaitingPayment]);

                return $locked->refresh();
            }

            if (round((float) $locked->amount, 2) !== $this->invoices->estimatedTotal($job)) {
                throw ValidationException::withMessages(['collection' => 'Bill amount changed after collection. Review the job before verifying payment.']);
            }

            $job->update(['status' => JobStatus::Completed]);
            $invoice = $this->invoices->generate($job);

            if (round((float) $invoice->grand_total, 2) !== round((float) $locked->amount, 2)) {
                throw ValidationException::withMessages(['collection' => 'Collected amount does not match the final invoice.']);
            }

            $payment = $this->billing->recordPayment([
                'invoice_id' => $invoice->getKey(),
                'mode' => $locked->mode,
                'amount' => $locked->amount,
                'reference' => $locked->reference,
                'provider' => 'FIELD_COLLECTION',
                'provider_metadata' => ['collection_id' => $locked->getKey(), 'proof_path' => $locked->proof_path],
            ]);
            $locked->update([
                'status' => CollectionStatus::Verified,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'invoice_id' => $invoice->getKey(),
                'payment_id' => $payment->getKey(),
            ]);

            return $locked->refresh();
        });
    }
}
