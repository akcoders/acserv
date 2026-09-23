<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
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
            'tax_profile_id' => ['nullable', 'ulid', Rule::exists('tax_profiles', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'sku' => ['required', 'string', 'max:64', Rule::unique('inventory_items')->where('tenant_id', $this->user()?->tenant_id)->ignore($this->route('inventory'))],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'track_serials' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
