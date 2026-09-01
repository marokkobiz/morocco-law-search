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

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
        $this->merge([
            'phone' => $this->normalizePhone($this->input('phone')),
            'whatsapp' => $this->normalizePhone($this->input('whatsapp')),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/'],
            'phone' => ['required', 'string', 'regex:/^\+?0?[1-9][0-9]{7,14}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?0?[1-9][0-9]{7,14}$/'],
            'case_description' => ['required', 'string', 'max:5000'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'consultation_mode' => ['nullable', 'in:office,whatsapp'],
            'call_time' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['nullable', 'in:'.implode(',', [LegalAidRequest::PAYMENT_METHOD_STRIPE, LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY])],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $services = Service::whereIn('id', array_values(array_unique((array) $this->input('service_ids'))))
            ->get();

        if ($services->isEmpty()) {
            return;
        }

        // Rule: only Initial interview alone = WhatsApp, everything else = Office
        $hasInitial = $services->contains(fn (Service $s) => $s->name_en === 'Initial interview (case content) 30 min.');
        if ($hasInitial && $services->count() === 1) {
            $allowed = ['whatsapp'];
        } else {
            $allowed = ['office'];
        }

        if ($allowed !== []) {
            $validator->addRules([
                'consultation_mode' => ['required', 'in:'.implode(',', $allowed)],
            ]);
        }

        if ($services->sum('price') > 0) {
            $validator->addRules([
                'payment_method' => ['required', 'in:'.implode(',', [LegalAidRequest::PAYMENT_METHOD_STRIPE, LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY])],
            ]);
        }
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
            'email.regex' => 'The :attribute must be a valid email address with a domain like name@company.com.',
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
