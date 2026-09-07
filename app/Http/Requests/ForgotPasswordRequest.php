<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'The :attribute must be a valid email address with a domain like name@company.com.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('auth.email'),
        ];
    }
}
