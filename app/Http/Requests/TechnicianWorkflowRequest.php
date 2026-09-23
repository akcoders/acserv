<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechnicianWorkflowRequest extends FormRequest
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
        $action = $this->input('action');

        return [
            'action' => ['required', Rule::in(['accept', 'reached', 'inspection', 'authorize', 'start', 'complete'])],
            'before_photo' => [$action === 'inspection' ? 'required' : 'prohibited', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'fault_remark' => [$action === 'inspection' ? 'required' : 'prohibited', 'string', 'max:5000'],
            'after_photo' => [$action === 'complete' ? 'required' : 'prohibited', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'completion_remark' => [$action === 'complete' ? 'required' : 'prohibited', 'string', 'max:5000'],
            'customer_signature' => [in_array($action, ['authorize', 'complete'], true) ? 'required' : 'prohibited', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_metres' => ['nullable', 'numeric', 'between:0,1000'],
            'captured_at' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ];
    }
}
