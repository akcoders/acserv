<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageBilling() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_id' => ['nullable', 'ulid', Rule::exists('jobs', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'customer_id' => ['required', 'ulid', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'work_order_id' => ['nullable', 'ulid', Rule::exists('work_orders', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'tax_profile_id' => ['nullable', 'ulid', Rule::exists('tax_profiles', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'billing_address' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['nullable', 'ulid', Rule::exists('inventory_items', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line): bool => is_array($line) && filled($line['description'] ?? null))
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }
}
