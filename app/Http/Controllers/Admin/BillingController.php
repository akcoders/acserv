<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\StoreQuotationRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobPaymentCollection;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\TaxProfile;
use App\Models\WorkOrder;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\PdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with(['customer:id,name,phone', 'payments:id,invoice_id,amount,status,mode,paid_at'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.commerce.billing', [
            'invoices' => $invoices,
            'quotations' => Quotation::query()->with(['customer:id,name', 'workOrder:id,quotation_id,work_order_number', 'workOrder.invoices:id,work_order_id'])->latest('issued_on')->limit(20)->get(),
            'workOrders' => WorkOrder::query()->with('customer:id,name')->latest()->limit(20)->get(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'taxProfiles' => TaxProfile::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'paymentModes' => PaymentMode::cases(),
            'pendingCollections' => JobPaymentCollection::query()->with(['job.customer', 'technician'])->where('status', 'PENDING')->latest('submitted_at')->paginate(20, ['*'], 'collection_page')->withQueryString(),
            'paymentTenant' => $request->user()->tenant,
        ]);
    }

    public function storeQuotation(StoreQuotationRequest $request, BillingService $billing): JsonResponse
    {
        $quotation = $billing->createQuotation($request->validated());

        return response()->json(['message' => 'Quotation created successfully.', 'quotation' => $quotation, 'reload' => true], 201);
    }

    public function acceptQuotation(Request $request, Quotation $quotation, BillingService $billing): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageBilling(), 403);
        $workOrder = $billing->acceptQuotation($quotation, $request->user());

        return response()->json(['message' => 'Quotation accepted and work order created.', 'work_order' => $workOrder, 'reload' => true]);
    }

    public function storeInvoice(StoreInvoiceRequest $request, BillingService $billing): JsonResponse
    {
        $invoice = $billing->createInvoice($request->validated());

        return response()->json(['message' => 'Invoice created successfully.', 'invoice' => $invoice, 'reload' => true], 201);
    }

    public function invoiceWorkOrder(Request $request, WorkOrder $workOrder, BillingService $billing): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageBilling(), 403);
        $invoice = $billing->createInvoiceFromWorkOrder($workOrder);

        return response()->json(['message' => 'Invoice generated from the work order.', 'invoice' => $invoice, 'reload' => true], 201);
    }

    public function storePayment(StorePaymentRequest $request, BillingService $billing): JsonResponse
    {
        $payment = $billing->recordPayment($request->validated());

        return response()->json(['message' => 'Payment recorded successfully.', 'payment' => $payment, 'reload' => true], 201);
    }

    public function gatewayOrder(Request $request, Invoice $invoice, PaymentGateway $gateway): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageBilling(), 403);
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
            'message' => 'Payment order created.',
            'payment_id' => $payment->getKey(),
            'order' => $order,
            'key_id' => config('services.razorpay.key_id'),
        ]);
    }

    public function downloadInvoice(Invoice $invoice, PdfDocument $pdf): Response
    {
        return response($pdf->invoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
