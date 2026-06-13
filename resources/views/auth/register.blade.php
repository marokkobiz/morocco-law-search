@extends('layouts.auth')

@section('title', __('auth.register_title'))

@section('content')
  @php
    $customBarValue = $customBarValue ?? '__custom_bar__';
    $courts = $courts ?? [];
    $selectedBar = old('bar');
    $customBar = old('custom_bar');
    $showCustomBar = $selectedBar === $customBarValue;
  @endphp

  <section class="auth-card auth-card-register">
    <p class="text-xs font-semibold tracking-wider uppercase text-blue-600">{{ __('auth.platform_access') }}</p>
    <h1>{{ __('auth.register_title') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('auth.register_desc') }}</p>

    @if ($errors->any())
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
        {{ $errors->first() }}
      </div>
    @endif

    <form action="/register" method="post" data-auth-gate>
      @csrf

      <div class="auth-field-grid">
        <label>
          {{ __('auth.full_name') }}
          <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('auth.full_name_placeholder') }}" autocomplete="name" required>
        </label>
        <label>
          {{ __('auth.company') }}
          <input type="text" name="company" value="{{ old('company') }}" placeholder="{{ __('auth.company_placeholder') }}" autocomplete="organization" required>
        </label>
        <label>
          {{ __('auth.phone') }}
          <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+212 6 00 00 00 00" autocomplete="tel" required>
        </label>
        <label>
          {{ __('auth.email') }}
          <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" required>
        </label>
        <label class="auth-field-full">
          {{ __('auth.bar') }}
          <select name="bar" required data-other-bar-select data-other-bar-value="{{ $customBarValue }}">
            <option value="">{{ __('auth.bar_placeholder') }}</option>
            @foreach ($courts as $court)
              <option value="{{ $court }}" @selected($selectedBar === $court)>{{ $court }}</option>
            @endforeach
            <option value="{{ $customBarValue }}" @selected($showCustomBar)>{{ __('auth.other_bar') }}</option>
          </select>
        </label>
        <label class="auth-field-full {{ $showCustomBar ? '' : 'hidden' }}" data-other-bar-field>
          {{ __('auth.custom_bar') }}
          <input type="text" name="custom_bar" value="{{ $customBar }}" placeholder="{{ __('auth.custom_bar_placeholder') }}" autocomplete="organization-title" data-other-bar-input @required($showCustomBar)>
        </label>
        <label>
          {{ __('auth.password') }}
          <input type="password" name="password" placeholder="{{ __('auth.password_placeholder') }}" minlength="8" maxlength="255" autocomplete="new-password" required>
        </label>
        <label>
          {{ __('auth.password_confirmation') }}
          <input type="password" name="password_confirmation" placeholder="{{ __('auth.password_confirmation_placeholder') }}" minlength="8" maxlength="255" autocomplete="new-password" required>
        </label>
      </div>

      {{-- <p class="auth-note">{{ __('auth.note') }}</p> --}}

      <button type="submit">{{ __('auth.register_button') }}</button>
    </form>

    <a href="/login" class="auth-link text-center">
      {{ __('auth.to_login') }}
    </a>
  </section>

  <script>
    (() => {
      const select = document.querySelector('[data-other-bar-select]');
      const field = document.querySelector('[data-other-bar-field]');
      const input = document.querySelector('[data-other-bar-input]');

      if (!select || !field || !input) {
        return;
      }

      const toggleCustomBar = () => {
        const isCustom = select.value === select.dataset.otherBarValue;
        field.classList.toggle('hidden', !isCustom);
        input.required = isCustom;

        if (!isCustom) {
          input.value = '';
        }
      };

      select.addEventListener('change', toggleCustomBar);
      toggleCustomBar();
    })();
  </script>
@endsection
