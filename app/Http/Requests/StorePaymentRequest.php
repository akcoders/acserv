<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'invoice_id' => ['required', 'ulid', Rule::exists('invoices', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'mode' => ['required', Rule::enum(PaymentMode::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:191'],
            'provider_payment_id' => ['nullable', 'string', 'max:191'],
            'provider_signature' => ['nullable', 'string', 'max:512'],
        ];
    }
}
