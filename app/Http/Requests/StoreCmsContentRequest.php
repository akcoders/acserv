<?php

namespace App\Http\Requests;

use App\Enums\ContentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageContent() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['page', 'post', 'service', 'offer', 'testimonial'])],
            'title' => ['required_unless:type,testimonial', 'nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', 'alpha_dash:ascii'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required_unless:type,testimonial', 'nullable', 'string'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'og_media_id' => ['nullable', 'ulid', Rule::exists('media_assets', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'featured_media_id' => ['nullable', 'ulid', Rule::exists('media_assets', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'customer_name' => ['required_if:type,testimonial', 'nullable', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'quote' => ['required_if:type,testimonial', 'nullable', 'string', 'max:3000'],
            'starting_price' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['nullable', 'in:FIXED,PERCENT'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
