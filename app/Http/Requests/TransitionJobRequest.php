<?php

namespace App\Http\Requests;

use App\Enums\JobStatus;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [
            Role::Owner,
            Role::Admin,
            Role::Manager,
            Role::Dispatcher,
            Role::Technician,
        ], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(JobStatus::class)],
            'latitude' => ['nullable', 'required_if:status,IN_PROGRESS,COMPLETED', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:status,IN_PROGRESS,COMPLETED', 'numeric', 'between:-180,180'],
            'accuracy_metres' => ['nullable', 'required_if:status,IN_PROGRESS,COMPLETED', 'numeric', 'max:100'],
            'captured_at' => ['nullable', 'required_if:status,IN_PROGRESS,COMPLETED', 'date'],
            'device_id' => ['nullable', 'required_if:status,IN_PROGRESS,COMPLETED', 'string', 'max:191'],
            'is_mock_location' => ['nullable', 'boolean', 'declined'],
            'resolution' => ['nullable', 'required_if:status,COMPLETED', 'string', 'max:5000'],
            'customer_signature' => ['nullable', 'required_if:status,COMPLETED', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}
