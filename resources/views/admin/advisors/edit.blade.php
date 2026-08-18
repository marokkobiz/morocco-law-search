@extends('layouts.admin')

@section('title', 'Edit Advisor')

@section('page-title')
Edit Advisor
@endsection

@section('page-description')
{{ $advisor->name }} — {{ $advisor->email }}
@endsection

@section('content')

<div class="max-w-2xl bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <form method="POST" action="{{ route('admin.advisors.update', $advisor) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700 mb-1.5">Full name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $advisor->name) }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
            @error('name')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $advisor->email) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
            @error('email')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-semibold text-slate-700 mb-1.5">Phone (optional)</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone', $advisor->phone) }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
            @error('phone')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Changes
            </button>
            <a href="{{ route('admin.advisors.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection