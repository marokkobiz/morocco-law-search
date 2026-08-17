<?php

namespace App\Http\Requests;

use App\Models\LegalAidRequest;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9][0-9]{8,14}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[1-9][0-9]{8,14}$/'],
            'case_description' => ['required', 'string', 'max:5000'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'consultation_mode' => ['nullable', 'in:office,whatsapp'],
            'call_time' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['nullable', 'in:'.implode(',', [LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY, LegalAidRequest::PAYMENT_METHOD_BANK])],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $services = Service::whereIn('id', array_values(array_unique((array) $this->input('service_ids'))))
            ->get();

        if ($services->isEmpty()) {
            return;
        }

        $allowed = $services
            ->map->consultationModes
            ->reject(fn (array $modes) => $modes === [])
            ->reduce(
                fn (?array $carry, array $modes) => $carry === null ? $modes : array_values(array_intersect($carry, $modes)),
                null
            ) ?? [];

        if ($allowed !== []) {
            $validator->addRules([
                'consultation_mode' => ['required', 'in:'.implode(',', $allowed)],
            ]);
        }

        if ($services->sum('price') > 0) {
            $validator->addRules([
                'payment_method' => ['required', 'in:'.implode(',', [LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY, LegalAidRequest::PAYMENT_METHOD_BANK])],
            ]);
        }
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
            'service_ids.required' => __('legal_aid.service_required'),
            'service_ids.min' => __('legal_aid.service_required'),
            'service_ids.*.exists' => __('legal_aid.service_invalid'),
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
            'service_ids' => __('legal_aid.field_service'),
            'consultation_mode' => __('legal_aid.field_consultation'),
            'call_time' => __('legal_aid.field_call_time'),
            'payment_method' => __('legal_aid.field_payment'),
        ];
    }
}
