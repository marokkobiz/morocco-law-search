@extends('layouts.auth')

@section('title', __('auth.login_title'))

@section('content')
  <section class="auth-card auth-card-register">
    <p class="text-xs font-semibold tracking-wider uppercase text-blue-600">{{ __('auth.welcome_back') }}</p>
    <h1>{{ __('auth.login_title') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('auth.login_desc') }}</p>

    @if ($errors->any())
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
        {{ $errors->first() }}
      </div>
    @endif

    <form action="/login" method="post" data-auth-gate>
      @csrf

      <label>
        {{ __('auth.email') }}
        <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" maxlength="255" autocomplete="email" required>
      </label>

      <label>
        {{ __('auth.password') }}
        <input type="password" name="password" placeholder="{{ __('auth.password_placeholder') }}" minlength="8" maxlength="255" autocomplete="current-password" required>
      </label>

      <div class="flex items-center justify-end mt-2">
        <a href="/forgot-password" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">
          {{ __('auth.forgot_password') }}
        </a>
      </div>

      <button type="submit">{{ __('auth.login_button') }}</button>
    </form>

    <a href="/register" class="auth-link text-center">
      {{ __('auth.to_register') }}
    </a>
  </section>
@endsection
