@extends('layouts.admin')

@section('title', 'Advisors')

@section('page-title')
Advisor Management
@endsection

@section('page-description')
Create, edit, suspend and reset credentials for advisor accounts.
@endsection

@section('content')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">
        {{ $advisors->total() }} advisor{{ $advisors->total() !== 1 ? 's' : '' }} on the team.
    </p>
    <a href="{{ route('admin.advisors.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition cursor-pointer">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Advisor
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                <th class="px-5 py-3.5">Advisor</th>
                <th class="px-5 py-3.5">Phone</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="px-5 py-3.5">Joined</th>
                <th class="px-5 py-3.5 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($advisors as $advisor)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-sm shrink-0">
                                {{ strtoupper(substr($advisor->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900 truncate">{{ $advisor->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $advisor->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-slate-600">{{ $advisor->phone ?: '—' }}</td>
                    <td class="px-5 py-4">
                        @if($advisor->access_status === 'suspended')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full bg-rose-100 text-rose-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Suspended
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-slate-600">{{ $advisor->created_at->format('M j, Y') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            <a href="{{ route('admin.advisors.edit', $advisor) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>

                            <form method="POST" action="{{ $advisor->access_status === 'suspended'
                                ? route('admin.advisors.unsuspend', $advisor)
                                : route('admin.advisors.suspend', $advisor) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer
                                        {{ $advisor->access_status === 'suspended'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                            : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                                    {{ $advisor->access_status === 'suspended' ? 'Reactivate' : 'Suspend' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.advisors.reset-password', $advisor) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-100 transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    Reset Password
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.advisors.destroy', $advisor) }}"
                                  onsubmit="return confirm('Delete advisor {{ $advisor->name }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 text-xs font-semibold hover:bg-rose-100 transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-14 text-center">
                        <p class="text-sm font-semibold text-slate-500">No advisors yet</p>
                        <p class="text-xs text-slate-400 mt-1">Create your first advisor to share the case workload.</p>
                        <a href="{{ route('admin.advisors.create') }}" class="inline-flex mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition cursor-pointer">
                            Add Advisor
                        </a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($advisors->hasPages())
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        {{ $advisors->links() }}
    </div>
@endif

@endsection