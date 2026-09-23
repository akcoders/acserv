<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutCycleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageWorkforce() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['DAILY', 'WEEKLY', 'MONTHLY'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'base_hourly_rate' => ['required', 'numeric', 'min:0'],
            'per_job_rate' => ['required', 'numeric', 'min:0'],
            'rating_incentive' => ['nullable', 'numeric', 'min:0'],
            'low_rating_penalty' => ['nullable', 'numeric', 'min:0'],
            'fixed_deduction' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
