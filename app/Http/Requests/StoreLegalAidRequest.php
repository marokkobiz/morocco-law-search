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
            'phone' => ['required', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'case_description' => ['required', 'string', 'max:5000'],
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
