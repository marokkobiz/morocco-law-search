@extends('layouts.auth')

@section('title', __('auth.password_title'))

@section('content')
  <section class="auth-card auth-card-register">
    <p class="text-xs font-semibold tracking-wider uppercase text-blue-600">{{ __('auth.password_title') }}</p>
    <h1>{{ __('auth.password_title') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('auth.password_desc') }}</p>

    @if ($errors->any())
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
        {{ $errors->first() }}
      </div>
    @endif

    @if (session('status'))
      <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
        {{ session('status') }}
      </div>
    @endif

    <form action="/forgot-password" method="post">
      @csrf
      <input type="hidden" name="lang" value="{{ $lang }}">

      <label>
        {{ __('auth.email') }}
        <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" maxlength="255" autocomplete="email" required>
      </label>

      <button type="submit">{{ __('auth.send_reset_link') }}</button>
    </form>

    <a href="/login?lang={{ $lang }}" class="auth-link text-center">
      {{ __('auth.back_to_login') }}
    </a>
  </section>
@endsection
