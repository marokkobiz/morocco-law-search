<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhone($this->input('phone')),
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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'company' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?0?[1-9][0-9]{7,14}$/'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/'],
            'bar' => ['required', 'string', 'max:255'],
            'custom_bar' => ['nullable', 'required_if:bar,__custom_bar__', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'The :attribute must be a valid email address with a domain like name@company.com.',
            'phone.regex' => 'The :attribute must be a valid phone number like 06XXXXXXXX or +212XXXXXXXXX.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('auth.full_name'),
            'company' => __('auth.company'),
            'phone' => __('auth.phone'),
            'email' => __('auth.email'),
            'bar' => __('auth.bar'),
            'custom_bar' => __('auth.custom_bar'),
            'password' => __('auth.password'),
            'password_confirmation' => __('auth.password_confirmation'),
        ];
    }
}
