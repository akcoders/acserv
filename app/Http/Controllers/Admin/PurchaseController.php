<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\Vendor;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        return view('admin.commerce.purchases', [
            'orders' => PurchaseOrder::query()
                ->with(['vendor:id,name,code', 'stockLocation:id,name'])
                ->withCount('lines')
                ->withSum('payments as paid_amount', 'amount')
                ->latest('ordered_on')->latest('id')->paginate(25),
            'vendors' => Vendor::query()->orderBy('name')->get(),
            'locations' => StockLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost']),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('admin.commerce.purchase-show', [
            'order' => $purchaseOrder->load(['vendor', 'stockLocation', 'lines.inventoryItem', 'payments']),
        ]);
    }

    public function storeVendor(Request $request): JsonResponse
    {
        $data = $request->validate($this->vendorRules($request));
        $vendor = Vendor::query()->create($data);

        return response()->json(['message' => 'Vendor added.', 'vendor' => $vendor, 'reload' => true], 201);
    }

    public function updateVendor(Request $request, Vendor $vendor): JsonResponse
    {
        $vendor->update($request->validate($this->vendorRules($request, $vendor)));

        return response()->json(['message' => 'Vendor updated.', 'reload' => true]);
    }

    public function store(Request $request, PurchaseService $purchases): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'vendor_id' => ['required', 'ulid', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'stock_location_id' => ['required', 'ulid', Rule::exists('stock_locations', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'ordered_on' => ['required', 'date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.inventory_item_id' => ['required', 'ulid', Rule::exists('inventory_items', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $order = $purchases->create($data);

        return response()->json(['message' => 'Purchase order created. Receive it when goods arrive.', 'order' => $order, 'redirect' => route('admin.purchases.show', $order)], 201);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseService $purchases): JsonResponse
    {
        $data = $request->validate(['supplier_invoice_number' => ['nullable', 'string', 'max:100']]);
        $order = $purchases->receive($purchaseOrder, $data['supplier_invoice_number'] ?? null);

        return response()->json(['message' => 'Goods received and stock increased.', 'order' => $order, 'reload' => true]);
    }

    /** @return array<string, array<int, mixed>> */
    private function vendorRules(Request $request, ?Vendor $vendor = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('vendors', 'code')->where('tenant_id', $request->user()->tenant_id)->ignore($vendor?->getKey())],
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:5000'],
            'payment_terms_days' => ['required', 'integer', 'between:0,365'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
