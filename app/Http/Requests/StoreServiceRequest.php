<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_fr' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_fr' => ['nullable', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_display_en' => ['nullable', 'string', 'max:255'],
            'price_display_fr' => ['nullable', 'string', 'max:255'],
            'price_display_ar' => ['nullable', 'string', 'max:255'],
            'notes_en' => ['nullable', 'string', 'max:500'],
            'notes_fr' => ['nullable', 'string', 'max:500'],
            'notes_ar' => ['nullable', 'string', 'max:500'],
            'additional_notes_en' => ['nullable', 'string', 'max:500'],
            'additional_notes_fr' => ['nullable', 'string', 'max:500'],
            'additional_notes_ar' => ['nullable', 'string', 'max:500'],
            'allows_office' => ['sometimes', 'boolean'],
            'allows_whatsapp' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allows_office' => $this->boolean('allows_office'),
            'allows_whatsapp' => $this->boolean('allows_whatsapp'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
