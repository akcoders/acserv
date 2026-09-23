<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountEntry;
use App\Models\PurchaseOrder;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request, AccountingService $accounting): View
    {
        $request->validate([
            'from' => ['sometimes', 'date', 'before_or_equal:to'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
        $from = (string) $request->query('from', today()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', today()->toDateString());

        return view('admin.commerce.accounts', [
            'from' => $from,
            'to' => $to,
            'statement' => $accounting->statement($from, $to),
            'entries' => AccountEntry::query()->with('purchaseOrder.vendor:id,name')->latest('entry_date')->latest('id')->paginate(25)->withQueryString(),
            'unpaidOrders' => PurchaseOrder::query()->with('vendor:id,name')->withSum('payments as paid_amount', 'amount')->where('status', 'RECEIVED')->latest('ordered_on')->limit(100)->get()->filter(fn (PurchaseOrder $order): bool => (float) $order->grand_total > (float) ($order->paid_amount ?? 0)),
        ]);
    }

    public function store(Request $request, AccountingService $accounting): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['EXPENSE', 'OTHER_INCOME', 'PURCHASE_PAYMENT'])],
            'category' => ['required_unless:type,PURCHASE_PAYMENT', 'nullable', Rule::in(['RENT', 'UTILITIES', 'TRAVEL', 'MARKETING', 'MAINTENANCE', 'BANK_FEES', 'OTHER', 'OTHER_INCOME'])],
            'description' => ['required_unless:type,PURCHASE_PAYMENT', 'nullable', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'payment_method' => ['required', Rule::in(['CASH', 'BANK', 'UPI'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'entry_date' => ['required', 'date'],
            'purchase_order_id' => ['required_if:type,PURCHASE_PAYMENT', 'nullable', 'ulid', Rule::exists('purchase_orders', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ]);

        if ($data['type'] === 'OTHER_INCOME') {
            $data['category'] = 'OTHER_INCOME';
        }

        $entry = $accounting->record($data);

        return response()->json(['message' => 'Account entry recorded.', 'entry' => $entry, 'reload' => true], 201);
    }
}
