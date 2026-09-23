<?php

namespace App\Http\Requests\Auth;

use App\Enums\OtpChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant' => ['required', 'string', 'max:100'],
            'login' => ['required', 'string', 'max:320'],
            'channel' => ['required', Rule::enum(OtpChannel::class)],
            'code' => ['required', 'digits:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $login = mb_strtolower(trim((string) $this->input('login')));

        if (! str_contains($login, '@')) {
            $login = preg_replace('/[^0-9+]/', '', $login) ?? $login;

            if (preg_match('/^\d{10}$/', $login) === 1) {
                $login = '+91'.$login;
            }
        }

        $this->merge([
            'tenant' => mb_strtolower(trim((string) $this->input('tenant'))),
            'login' => $login,
        ]);
    }
}
