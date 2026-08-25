@extends('layouts.admin')

@section('title', 'Add Advisor')

@section('page-title')
Add Advisor
@endsection

@section('page-description')
A temporary password is generated automatically and emailed to the advisor.
@endsection

@section('content')

<div class="max-w-2xl bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <form method="POST" action="{{ route('admin.advisors.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700 mb-1.5">Full name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                   placeholder="e.g. Yassine El Amrani"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
            @error('name')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   placeholder="advisor@example.com"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
            @error('email')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-semibold text-slate-700 mb-1.5">Phone (optional)</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                   placeholder="+212 6 00 00 00 00"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
            @error('phone')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-start gap-2.5 p-4 rounded-lg bg-slate-50 border border-slate-200">
            <svg class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-1.5 3h9a2 2 0 002-2v-9a2 2 0 00-2-2h-9a2 2 0 00-2 2v9a2 2 0 002 2z"/></svg>
            <p class="text-xs text-slate-500 leading-relaxed">
                The advisor will receive an email with the login link and a generated temporary password. They can then access the advisor portal to work on cases.
            </p>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.5 5L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Create Advisor
            </button>
            <a href="{{ route('admin.advisors.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection