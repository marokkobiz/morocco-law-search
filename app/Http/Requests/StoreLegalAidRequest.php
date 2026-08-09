<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegalAidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9][0-9]{8,14}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[1-9][0-9]{8,14}$/'],
            'case_description' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizePhone($this->input('phone')),
            'whatsapp' => $this->normalizePhone($this->input('whatsapp')),
        ]);
    }

    private function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/[^0-9+]/', '', trim($value));
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('legal_aid.phone_invalid'),
            'whatsapp.regex' => __('legal_aid.whatsapp_invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => __('legal_aid.field_name'),
            'email' => __('legal_aid.field_email'),
            'phone' => __('legal_aid.field_phone'),
            'whatsapp' => __('legal_aid.field_whatsapp'),
            'case_description' => __('legal_aid.field_case'),
        ];
    }
}
