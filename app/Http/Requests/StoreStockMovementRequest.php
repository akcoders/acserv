<?php

namespace App\Http\Requests;

use App\Enums\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageInventory() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'ulid', Rule::exists('inventory_items', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'from_location_id' => ['nullable', 'ulid', Rule::exists('stock_locations', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'to_location_id' => ['nullable', 'ulid', Rule::exists('stock_locations', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'job_id' => ['nullable', 'ulid', Rule::exists('jobs', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
