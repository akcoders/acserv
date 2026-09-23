<?php

namespace App\Http\Requests;

use App\Enums\ReportFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canViewAnalytics() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'report_type' => ['required', Rule::in(['jobs', 'revenue', 'inventory', 'workforce', 'customers', 'marketing'])],
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['required', 'email', 'max:255'],
            'filters' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
