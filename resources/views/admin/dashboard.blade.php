@extends('layouts.admin')

@section('title', 'Dashboard')

@section('page-title')
Admin Dashboard
@endsection

@section('page-description')
Monitor legal aid requests and confirm payments.
@endsection

@section('content')

<!-- Stat Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pending Requests</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $pendingRequests }}</h2>
        </div>
        <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Confirmed Cases</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $confirmedRequests }}</h2>
        </div>
        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registered Users</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $totalUsers }}</h2>
        </div>
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
    </div>

</div>

<!-- Payment Split -->
@php
    $paidTotal = $paymentSplit['googlePayPaid'] + $paymentSplit['bankConfirmed'];
    $paidShare = $paidTotal + $paymentSplit['free'] > 0 ? round($paidTotal / ($paidTotal + $paymentSplit['free']) * 100) : 0;
@endphp
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="text-sm font-bold text-slate-900">Payment Split</h3>
        <p class="text-xs text-slate-500 mt-0.5">How legal aid requests are being paid.</p>
    </div>
    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        @foreach([
            ['label' => 'Google Pay (paid)', 'count' => $paymentSplit['googlePayPaid'], 'bar' => 'bg-emerald-600'],
            ['label' => 'Bank (confirmed)', 'count' => $paymentSplit['bankConfirmed'], 'bar' => 'bg-amber-500'],
            ['label' => 'Free consultation', 'count' => $paymentSplit['free'], 'bar' => 'bg-purple-500'],
        ] as $row)
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-sm font-semibold text-slate-700">{{ $row['label'] }}</span>
                    <span class="text-sm text-slate-500">{{ $row['count'] }} · {{ $paidTotal + $paymentSplit['free'] > 0 ? round($row['count'] / ($paidTotal + $paymentSplit['free']) * 100) : 0 }}%</span>
                </div>
                <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full {{ $row['bar'] }} transition-all" style="width: {{ $paidTotal + $paymentSplit['free'] > 0 ? round($row['count'] / ($paidTotal + $paymentSplit['free']) * 100) : 0 }}%"></div>
                </div>
            </div>
        @endforeach

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm font-semibold text-slate-700">Paid + confirmed share</span>
                <span class="text-sm text-slate-500">{{ $paidShare }}%</span>
            </div>
            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-slate-900 transition-all" style="width: {{ $paidShare }}%"></div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Requests -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Recent Legal Aid Requests</h2>
            <p class="text-xs text-slate-500 mt-0.5">Latest client requests awaiting action.</p>
        </div>
        <a href="{{ route('admin.legal-aid.index') }}"
           class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">
            View all →
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[640px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Ticket</th>
                    <th class="px-6 py-4">Client</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Uploaded Receipt</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($recentRequests as $request)
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.legal-aid.show', $request->id) }}"
                           class="font-bold text-slate-900 font-mono hover:text-blue-600 hover:underline transition">
                            {{ $request->ticketLabel }}
                        </a>
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.legal-aid.show', $request->id) }}"
                           class="font-semibold text-slate-900 hover:text-blue-600 hover:underline transition">
                            {{ $request->full_name }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $request->email }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                            @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
                            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
                            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_REJECTED) bg-red-50 text-red-700 border-red-200
                            @else bg-amber-50 text-amber-700 border-amber-200 @endif">
                            {{ ucwords(str_replace('_', ' ', $request->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($request->receipt_path)
                            <a href="{{ Storage::url($request->receipt_path) }}" target="_blank"
                               class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">
                                View / Download
                            </a>
                        @else
                            <span class="text-xs text-slate-400 italic">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400 italic">
                        No legal aid requests yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
