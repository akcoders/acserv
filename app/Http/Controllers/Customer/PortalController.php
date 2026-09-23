<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BookingChannel;
use App\Enums\BookingStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Http\Requests\StoreNotificationPreferenceRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\NotificationPreference;
use App\Models\Payment;
use App\Models\User;
use App\Models\Warranty;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\PdfDocument;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('customer.index', [
            'customer' => $customer,
            'activeJobCount' => $customer->jobs()->whereNotIn('status', [JobStatus::Completed->value, JobStatus::Verified->value, JobStatus::Closed->value, JobStatus::Cancelled->value])->count(),
            'outstandingBalance' => (float) $customer->invoices()->sum('balance_due'),
            'assets' => $customer->assets()->with('warranties')->orderBy('name')->get(),
            'bookings' => $customer->bookings()->latest()->limit(10)->get(),
            'jobs' => $customer->jobs()->with(['asset:id,name', 'assignments.technician:id,first_name,last_name,phone', 'invoice:id,job_id,invoice_number,grand_total'])->latest()->limit(10)->get(),
            'invoices' => $customer->invoices()->latest('issued_on')->limit(10)->get(),
            'reminders' => $customer->serviceReminders()->where('status', 'PENDING')->orderBy('due_on')->get(),
            'preferences' => $request->user()->notificationPreferences()->get(),
        ]);
    }

    public function storeAsset(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120', Rule::unique('assets')->where('tenant_id', $request->user()->tenant_id)],
            'capacity' => ['nullable', 'string', 'max:50'],
            'asset_type' => ['nullable', 'string', 'max:80'],
            'install_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $asset = $customer->assets()->create([
            ...$data,
            'branch_id' => $customer->branch_id,
            'status' => 'ACTIVE',
        ]);

        return response()->json(['message' => 'Asset added successfully.', 'asset' => $asset, 'reload' => true], 201);
    }

    public function storeBooking(Request $request, NotificationDispatcher $notifications): JsonResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'asset_id' => ['nullable', 'ulid', Rule::exists('assets', 'id')->where(fn ($query) => $query->where('tenant_id', $request->user()->tenant_id)->where('customer_id', $customer->getKey()))],
            'service_type' => ['required', 'string', 'max:100'],
            'complaint' => ['nullable', 'string', 'max:5000'],
            'preferred_start_at' => ['required', 'date', 'after:now'],
            'preferred_end_at' => ['nullable', 'date', 'after:preferred_start_at'],
        ]);
        $booking = Booking::query()->create([
            ...$data,
            'customer_id' => $customer->getKey(),
            'customer_user_id' => $request->user()->getKey(),
            'branch_id' => $customer->branch_id,
            'booking_number' => 'BK-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
            'channel' => BookingChannel::CustomerPwa,
            'status' => BookingStatus::Pending,
            'service_address' => $customer->service_address,
        ]);
        $notifications->queue('booking.created', $request->user(), [
            'booking_number' => $booking->booking_number,
            'preferred_start_at' => $booking->preferred_start_at?->format('d M Y, h:i A'),
        ]);

        return response()->json(['message' => 'Service booking submitted.', 'booking' => $booking, 'reload' => true], 201);
    }

    public function tracking(Request $request, Job $job): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($job->customer_id === $customer->getKey(), 404);
        $job->load(['assignments.technician:id,first_name,last_name,phone', 'invoice:id,job_id,invoice_number']);

        return response()->json([
            'job_number' => $job->job_number,
            'status' => $job->status->value,
            'scheduled_at' => $job->scheduled_at?->toIso8601String(),
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'invoice_number' => $job->invoice?->invoice_number,
            'technicians' => $job->assignments->pluck('technician')->filter()->values(),
            'updated_at' => $job->updated_at->toIso8601String(),
        ]);
    }

    public function storeFeedback(StoreFeedbackRequest $request, NotificationDispatcher $notifications): JsonResponse
    {
        $customer = $this->customer($request);
        $job = Job::query()->where('customer_id', $customer->getKey())->findOrFail($request->validated('job_id'));
        $rating = (int) $request->validated('rating');
        $feedback = Feedback::query()->create([
            ...$request->validated(),
            'customer_id' => $customer->getKey(),
            'status' => $rating <= 2 ? 'ESCALATED' : 'OPEN',
            'escalated_at' => $rating <= 2 ? now() : null,
        ]);

        if ($rating <= 2) {
            User::query()
                ->forTenant($request->user()->tenant_id)
                ->whereIn('role', [Role::Owner->value, Role::Admin->value, Role::Manager->value])
                ->each(fn (User $manager) => $notifications->queue('feedback.received', $manager, [
                    'job_number' => $job->job_number,
                    'rating' => $rating,
                ]));
        }

        return response()->json(['message' => 'Thank you for your feedback.', 'feedback' => $feedback, 'reload' => true], 201);
    }

    public function storePreference(StoreNotificationPreferenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $preference = NotificationPreference::query()->updateOrCreate([
            'user_id' => $request->user()->getKey(),
            'event' => $data['event'],
            'channel' => $data['channel'],
        ], $data);

        return response()->json(['message' => 'Notification preference saved.', 'preference' => $preference, 'reload' => true]);
    }

    public function invoice(Request $request, Invoice $invoice, PdfDocument $pdf): Response
    {
        $customer = $this->customer($request);
        abort_unless($invoice->customer_id === $customer->getKey(), 404);

        return response($pdf->invoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    public function gatewayOrder(Request $request, Invoice $invoice, PaymentGateway $gateway): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($invoice->customer_id === $customer->getKey(), 404);
        abort_if((float) $invoice->balance_due <= 0, 422, 'This invoice is already paid.');
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:'.$invoice->balance_due]]);
        $paymentNumber = 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6));
        $order = $gateway->createOrder($paymentNumber, (float) $data['amount']);
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->getKey(),
            'payment_number' => $paymentNumber,
            'mode' => PaymentMode::Upi,
            'status' => PaymentStatus::Pending,
            'amount' => $data['amount'],
            'currency' => 'INR',
            'provider' => 'RAZORPAY',
            'provider_order_id' => $order['id'],
            'provider_metadata' => ['receipt' => $paymentNumber],
        ]);

        return response()->json([
            'message' => 'Secure payment order created.',
            'order' => $order,
            'key_id' => config('services.razorpay.key_id'),
            'payment_id' => $payment->getKey(),
            'confirm_url' => route('customer.payments.confirm', $payment),
            'customer' => ['name' => $customer->name, 'email' => $customer->email, 'contact' => $customer->phone],
        ]);
    }

    public function confirmPayment(Request $request, Payment $payment, PaymentGateway $gateway, BillingService $billing, NotificationDispatcher $notifications): JsonResponse
    {
        $customer = $this->customer($request);
        $payment->load('invoice');
        abort_unless($payment->invoice->customer_id === $customer->getKey(), 404);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:191'],
            'razorpay_order_id' => ['required', 'string', 'max:191'],
            'razorpay_signature' => ['required', 'string', 'max:512'],
        ]);
        abort_unless(hash_equals((string) $payment->provider_order_id, $data['razorpay_order_id']), 422, 'Payment order mismatch.');
        abort_unless($gateway->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature']), 422, 'Payment signature verification failed.');
        $wasPaid = $payment->status === PaymentStatus::Paid;
        $settled = $billing->settleGatewayPayment($payment, $data['razorpay_payment_id'], $data['razorpay_signature']);

        if (! $wasPaid) {
            $notifications->queue('invoice.paid', $request->user(), ['invoice_number' => $payment->invoice->invoice_number]);
        }

        return response()->json(['message' => 'Payment verified successfully.', 'payment' => $settled, 'reload' => true]);
    }

    public function warranty(Request $request, Warranty $warranty, PdfDocument $pdf): Response
    {
        $customer = $this->customer($request);
        abort_unless($warranty->customer_id === $customer->getKey(), 404);
        $warranty->load('asset');

        return response($pdf->make('ACServ Warranty Certificate', [
            'Asset: '.$warranty->asset->brand.' '.$warranty->asset->model,
            'Type: '.$warranty->type->value,
            'Policy: '.($warranty->policy_number ?? 'N/A'),
            'Valid until: '.$warranty->ends_on->format('d M Y'),
        ]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="warranty-'.$warranty->getKey().'.pdf"',
        ]);
    }

    private function customer(Request $request): Customer
    {
        $customer = Customer::query()->where('user_id', $request->user()->getKey())->first();

        if ($customer === null) {
            throw ValidationException::withMessages(['account' => 'No customer profile is linked to this login.']);
        }

        return $customer;
    }
}
