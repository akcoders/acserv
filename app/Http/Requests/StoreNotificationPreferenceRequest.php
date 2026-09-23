<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationPreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:100'],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'is_enabled' => ['required', 'boolean'],
            'quiet_starts_at' => ['nullable', 'date_format:H:i'],
            'quiet_ends_at' => ['nullable', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
        ];
    }
}
