<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageJobs() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'ulid', Rule::exists('bookings', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'customer_id' => ['required', 'ulid', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'asset_id' => ['nullable', 'ulid', Rule::exists('assets', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'branch_id' => ['nullable', 'ulid', Rule::exists('branches', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])],
            'service_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'service_address' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'between:15,1440'],
            'checklist' => ['nullable', 'array', 'max:30'],
            'checklist.*' => ['required', 'string', 'max:255'],
        ];
    }
}
