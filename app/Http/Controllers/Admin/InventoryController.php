<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StockLocationType;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockLocation;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $items = InventoryItem::query()
            ->with('taxProfile:id,name,tax_rate')
            ->withSum('incomingMovements as incoming_quantity', 'quantity')
            ->withSum('outgoingMovements as outgoing_quantity', 'quantity')
            ->withSum(['outgoingMovements as job_consumed_quantity' => fn ($query) => $query->where('type', StockMovementType::Consumption)], 'quantity')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(fn ($filter) => $filter->where('sku', 'like', $search)->orWhere('name', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.commerce.inventory', [
            'items' => $items,
            'locations' => StockLocation::query()->with('branch:id,name')->where('is_active', true)->orderBy('name')->get(),
            'taxProfiles' => TaxProfile::query()->orderBy('name')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'technicians' => User::query()->forTenant($request->user()->tenant_id)->where('role', Role::Technician)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'locationTypes' => StockLocationType::cases(),
            'movementTypes' => StockMovementType::cases(),
        ]);
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $item = InventoryItem::query()->create($request->validated());

        return response()->json(['message' => 'Inventory item created successfully.', 'item' => $item, 'reload' => true], 201);
    }

    public function update(StoreInventoryItemRequest $request, InventoryItem $inventory): JsonResponse
    {
        $inventory->update($request->validated());

        return response()->json(['message' => 'Inventory item updated successfully.', 'reload' => true]);
    }

    public function destroy(InventoryItem $inventory): JsonResponse
    {
        abort_if($inventory->stockMovements()->exists(), 422, 'Items with stock history cannot be deleted.');
        $inventory->delete();

        return response()->json(['message' => 'Inventory item deleted successfully.', 'reload' => true]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageInventory(), 403);
        $data = $request->validate([
            'branch_id' => ['nullable', 'ulid', Rule::exists('branches', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'custodian_id' => ['nullable', 'ulid', Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'code' => ['required', 'string', 'max:40', Rule::unique('stock_locations')->where('tenant_id', $request->user()->tenant_id)],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(StockLocationType::class)],
            'address' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $location = StockLocation::query()->create([...$data, 'is_active' => true]);

        return response()->json(['message' => 'Stock location created successfully.', 'location' => $location, 'reload' => true], 201);
    }

    public function storeTaxProfile(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageInventory(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'sac_code' => ['nullable', 'string', 'max:20'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'is_default' => ['required', 'boolean'],
        ]);
        $profile = DB::transaction(function () use ($data): TaxProfile {
            if ($data['is_default']) {
                TaxProfile::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return TaxProfile::query()->create($data);
        });

        return response()->json(['message' => 'GST tax profile created.', 'tax_profile' => $profile, 'reload' => true], 201);
    }

    public function storeMovement(StoreStockMovementRequest $request, StockService $stockService): JsonResponse
    {
        $movement = $stockService->move($request->validated());

        return response()->json(['message' => 'Stock movement recorded successfully.', 'movement' => $movement, 'reload' => true], 201);
    }
}
