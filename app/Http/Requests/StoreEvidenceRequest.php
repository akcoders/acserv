<?php

namespace App\Http\Requests;

use App\Enums\EvidenceType;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [Role::Owner, Role::Admin, Role::Manager, Role::Technician], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EvidenceType::class)],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:20480'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_metres' => ['required', 'numeric', 'max:100'],
            'captured_at' => ['required', 'date'],
            'device_id' => ['required', 'string', 'max:191'],
            'is_mock_location' => ['required', 'boolean', 'declined'],
        ];
    }
}
