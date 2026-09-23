<?php

namespace App\Http\Requests;

use App\Enums\BookingChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'customer_id' => ['required', 'ulid', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'asset_id' => ['nullable', 'ulid', Rule::exists('assets', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'branch_id' => ['nullable', 'ulid', Rule::exists('branches', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'channel' => ['required', Rule::enum(BookingChannel::class)],
            'service_type' => ['required', 'string', 'max:100'],
            'complaint' => ['nullable', 'string', 'max:5000'],
            'preferred_start_at' => ['nullable', 'date'],
            'preferred_end_at' => ['nullable', 'date', 'after:preferred_start_at'],
            'service_address' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
