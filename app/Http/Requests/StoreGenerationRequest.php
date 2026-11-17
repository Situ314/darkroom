<?php

namespace App\Http\Requests;

use App\Services\Billing\Pricing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Empty URL rows in the form are just ignored.
        $this->merge([
            'reference_images' => array_values(array_filter(
                array_map(fn ($url) => is_string($url) ? trim($url) : $url, (array) $this->input('reference_images', [])),
            )),
        ]);
    }

    /**
     * Mirrors Pixelforge's own limits so we fail fast with a nice message.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(Pricing::SIZES)],
            'quality' => ['required', Rule::in(Pricing::QUALITIES)],
            'reference_images' => ['array', 'max:'.Pricing::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add up to :max reference images.',
            'reference_images.*.url' => 'Each reference image must be an http(s) URL.',
        ];
    }
}
