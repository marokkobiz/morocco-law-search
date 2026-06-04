@extends('layouts.auth')

@section('title', $mode === 'register' ? 'Create account' : 'Login')

@section('content')
  <section class="auth-card">
    <p class="text-xs font-semibold tracking-wider uppercase text-blue-600">
      {{ $mode === 'register' ? 'Platform access' : 'Welcome back' }}
    </p>
    <h1>{{ $mode === 'register' ? 'Create account' : 'Login' }}</h1>
    <p class="mt-1 text-sm text-gray-500">Account access is prepared for the legal research platform.</p>

    <form action="/app">
      @if ($mode === 'register')
        <label>
          Full name
          <input type="text" placeholder="Your name">
        </label>
      @endif
      <label>
        Work email
        <input type="email" placeholder="name@company.com">
      </label>
      <label>
        Password
        <input type="password" placeholder="Enter your password">
      </label>
      <button type="submit">{{ $mode === 'register' ? 'Create account' : 'Login' }}</button>
    </form>

    <a href="{{ $mode === 'register' ? '/login' : '/register' }}" class="auth-link">
      {{ $mode === 'register' ? 'Already have an account? Login' : 'Need access? Create account' }}
    </a>
  </section>
@endsection
