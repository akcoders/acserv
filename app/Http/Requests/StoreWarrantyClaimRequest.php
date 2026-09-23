<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarrantyClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageCoreRecords() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warranty_id' => ['required', 'ulid', Rule::exists('warranties', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'job_id' => ['nullable', 'ulid', Rule::exists('jobs', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'issue' => ['required', 'string', 'max:5000'],
        ];
    }
}
