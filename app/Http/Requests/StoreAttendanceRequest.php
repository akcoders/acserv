<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Technician;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:check-in,check-out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_metres' => ['required', 'numeric', 'max:100'],
            'device_id' => ['required', 'string', 'max:191'],
            'is_mock_location' => ['required', 'boolean', 'declined'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }
}
