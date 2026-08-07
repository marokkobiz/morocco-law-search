<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'company' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'min:8', 'max:40'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'bar' => ['required', 'string', 'max:255'],
            'custom_bar' => ['nullable', 'required_if:bar,__custom_bar__', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8', 'max:255'],
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
