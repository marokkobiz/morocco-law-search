@extends('layouts.admin')

@section('title', 'Advisor Dashboard')

@section('page-title')
Advisor Dashboard
@endsection

@section('page-description')
Overview of the case workload across paid, confirmed and free consultation cases.
@endsection

@section('content')

@php
    $s = $stats;
    $openPct = $s['total'] > 0 ? round($s['open'] / $s['total'] * 100) : 0;
    $closedPct = $s['total'] > 0 ? round($s['closed'] / $s['total'] * 100) : 0;
    $unclaimedPct = $s['total'] > 0 ? round($s['unclaimed'] / $s['total'] * 100) : 0;
    $tasksPct = $s['tasksTotal'] > 0 ? round($s['tasksDone'] / $s['tasksTotal'] * 100) : 0;
@endphp

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open Cases</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $s['open'] }}</h2>
            <p class="mt-1 text-xs text-slate-400">{{ $s['total'] }} total visible cases</p>
        </div>
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Closed Cases</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $s['closed'] }}</h2>
            <p class="mt-1 text-xs text-slate-400">{{ $closedPct }}% of all cases</p>
        </div>
        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Not Yet Claimed</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $s['unclaimed'] }}</h2>
            <p class="mt-1 text-xs text-slate-400">Waiting for a first contact</p>
        </div>
        <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">My Cases</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $s['myCases'] }}</h2>
            <p class="mt-1 text-xs text-slate-400">{{ $s['myOpen'] }} open, {{ $s['myCases'] - $s['myOpen'] }} closed</p>
        </div>
        <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">

    <!-- Case Overview -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Case Overview</h3>
        </div>
        <div class="p-6 space-y-5">
            @foreach([
                ['label' => 'Open', 'count' => $s['open'], 'pct' => $openPct, 'bar' => 'bg-blue-600'],
                ['label' => 'Closed', 'count' => $s['closed'], 'pct' => $closedPct, 'bar' => 'bg-emerald-600'],
                ['label' => 'Unclaimed', 'count' => $s['unclaimed'], 'pct' => $unclaimedPct, 'bar' => 'bg-amber-500'],
            ] as $row)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm font-semibold text-slate-700">{{ $row['label'] }}</span>
                        <span class="text-sm text-slate-500">{{ $row['count'] }} · {{ $row['pct'] }}%</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full {{ $row['bar'] }} transition-all" style="width: {{ $row['pct'] }}%"></div>
                    </div>
                </div>
            @endforeach

            <div class="pt-3 border-t border-slate-100">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-sm font-semibold text-slate-700">All visible cases</span>
                    <span class="text-sm text-slate-500">{{ $s['total'] }}</span>
                </div>
                <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-slate-900" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tasks & Notes -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Tasks & Notes</h3>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-sm font-semibold text-slate-700">Service tasks completed</span>
                    <span class="text-sm text-slate-500">{{ $s['tasksDone'] }}/{{ $s['tasksTotal'] }} · {{ $tasksPct }}%</span>
                </div>
                <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full {{ $tasksPct === 100 ? 'bg-emerald-600' : 'bg-blue-600' }} transition-all" style="width: {{ $tasksPct }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100">
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-center">
                    <p class="text-2xl font-extrabold text-slate-900">{{ $s['tasksTotal'] }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">Total tasks</p>
                </div>
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-center">
                    <p class="text-2xl font-extrabold text-slate-900">{{ $s['notesCount'] }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">Case notes</p>
                </div>
            </div>

            <a href="{{ route('advisor.cases.index') }}"
               class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">
                Open the case queue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    </div>

</div>

<!-- Recent Cases -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-900">Recently Submitted</h3>
        <a href="{{ route('advisor.cases.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">
            View all cases →
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[720px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-3.5">Ticket</th>
                    <th class="px-6 py-3.5">Client</th>
                    <th class="px-6 py-3.5">Payment</th>
                    <th class="px-6 py-3.5">Case</th>
                    <th class="px-6 py-3.5">First Contact</th>
                    <th class="px-6 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($recent as $request)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-6 py-3.5">
                            <a href="{{ route('advisor.cases.show', $request->id) }}"
                               class="font-bold text-slate-900 font-mono hover:text-blue-600 hover:underline transition">{{ $request->ticketLabel }}</a>
                            <div class="text-xs text-slate-500">{{ $request->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-3.5">
                            <a href="{{ route('advisor.cases.show', $request->id) }}"
                               class="font-semibold text-slate-900 hover:text-blue-600 hover:underline transition">{{ $request->full_name }}</a>
                            <div class="text-xs text-slate-500">{{ $request->email }}</div>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                                @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
                                @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
                                @else bg-purple-50 text-purple-700 border-purple-200 @endif">
                                @if($request->isFree()) Free
                                @else {{ ucwords(str_replace('_', ' ', $request->status)) }} @endif
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                                @if($request->isCaseOpen()) bg-amber-50 text-amber-700 border-amber-200
                                @else bg-slate-100 text-slate-600 border-slate-200 @endif">
                                {{ $request->isCaseOpen() ? 'Open' : 'Closed' }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            @if($request->advisor)
                                <span class="font-semibold text-slate-900">{{ $request->advisor->name }}</span>
                            @else
                                <span class="text-xs text-slate-400 italic">Unclaimed</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('advisor.cases.show', $request->id) }}"
                               class="inline-flex text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400 italic">
                            No cases yet. Paid, confirmed and free consultation cases will appear here.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection