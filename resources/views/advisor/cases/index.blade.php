@extends('layouts.admin')

@section('title', 'Advisor Cases')

@section('page-title')
Advisor Cases
@endsection

@section('page-description')
Work queue of paid, confirmed and free consultation cases.
@endsection

@section('content')

@php
    $openCount = $requests->getCollection()->where('case_status', \App\Models\LegalAidRequest::CASE_OPEN)->count();
    $closedCount = $requests->getCollection()->where('case_status', \App\Models\LegalAidRequest::CASE_CLOSED)->count();
    $unclaimedCount = $requests->getCollection()->whereNull('advisor_id')->count();
@endphp

<!-- Stat Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open Cases (this page)</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $openCount }}</h2>
        </div>
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Closed Cases (this page)</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $closedCount }}</h2>
        </div>
        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Not Yet Claimed</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $unclaimedCount }}</h2>
        </div>
        <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
    <form method="GET" action="{{ route('advisor.cases.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <div>
            <label for="search" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Search</label>
            <input type="search" name="search" id="search" value="{{ $filters['search'] }}" placeholder="Ticket, name, email, phone…"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
        </div>

        <div>
            <label for="case_status" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Case Status</label>
            <select name="case_status" id="case_status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                <option value="">All cases</option>
                <option value="open" @selected($filters['case_status'] === 'open')>Open</option>
                <option value="closed" @selected($filters['case_status'] === 'closed')>Closed</option>
            </select>
        </div>

        <div>
            <label for="payment_status" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Payment Status</label>
            <select name="payment_status" id="payment_status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                <option value="">Any payment status</option>
                <option value="paid" @selected($filters['payment_status'] === 'paid')>Paid (Google Pay)</option>
                <option value="confirmed" @selected($filters['payment_status'] === 'confirmed')>Confirmed (Bank)</option>
                <option value="free" @selected($filters['payment_status'] === 'free')>Free consultation</option>
            </select>
        </div>

        <div>
            <label for="advisor" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">First Contact Advisor</label>
            <select name="advisor" id="advisor" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                <option value="">Any advisor</option>
                @foreach($advisors as $advisor)
                    <option value="{{ $advisor->id }}" @selected((string) $filters['advisor'] === (string) $advisor->id)>{{ $advisor->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="service" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Service</label>
            <select name="service" id="service" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                <option value="">Any service</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((string) $filters['service'] === (string) $service->id)>{{ $service->name_en }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-5 flex justify-end gap-2">
            <a href="{{ route('advisor.cases.index') }}"
               class="text-xs font-semibold px-4 py-2 rounded-lg border transition shadow-sm bg-white hover:bg-slate-50 text-slate-600 border-slate-200">
                Clear filters
            </a>
            <button type="submit"
                    class="text-xs font-semibold px-4 py-2 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                Apply filters
            </button>
        </div>
    </form>
</div>

<!-- Cases Table -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[900px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Ticket</th>
                    <th class="px-6 py-4">Client</th>
                    <th class="px-6 py-4">Services</th>
                    <th class="px-6 py-4">Payment</th>
                    <th class="px-6 py-4">Case</th>
                    <th class="px-6 py-4">First Contact</th>
                    <th class="px-6 py-4">Last Touched</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($requests as $request)
                <tr class="hover:bg-slate-50/70 transition">
                    <!-- Ticket -->
                    <td class="px-6 py-4">
                        <a href="{{ route('advisor.cases.show', $request->id) }}"
                           class="font-bold text-slate-900 font-mono hover:text-blue-600 hover:underline transition">
                            {{ $request->ticketLabel }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $request->created_at->format('d M Y, H:i') }}</div>
                    </td>

                    <!-- Client -->
                    <td class="px-6 py-4 max-w-xs">
                        <a href="{{ route('advisor.cases.show', $request->id) }}"
                           class="font-semibold text-slate-900 hover:text-blue-600 hover:underline transition">
                            {{ $request->full_name }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $request->email }}</div>
                    </td>

                    <!-- Services -->
                    <td class="px-6 py-4">
                        @if($request->selectedServices->isNotEmpty())
                            <div class="font-semibold text-slate-900 w-44 truncate">
                                @foreach($request->selectedServices as $service){{ $service->name }}@if(! $loop->last), @endif @endforeach
                            </div>
                            @php $done = $request->completedServices()->count(); @endphp
                            <span class="text-xs {{ $done === $request->selectedServices->count() ? 'text-emerald-600 font-semibold' : 'text-amber-600 font-semibold' }}">
                                {{ $done }}/{{ $request->selectedServices->count() }} tasks done
                            </span>
                        @else
                            <span class="text-xs text-slate-400 italic">—</span>
                        @endif
                    </td>

                    <!-- Payment Status -->
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                            @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
                            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
                            @else bg-purple-50 text-purple-700 border-purple-200 @endif">
                            @if($request->isFree()) Free
                            @else {{ ucwords(str_replace('_', ' ', $request->status)) }} @endif
                        </span>
                        {{-- <div class="text-xs text-slate-500 mt-1">
                            {{ $request->base_price ? number_format((float) $request->base_price, 0).' MAD' : 'Free' }}
                        </div> --}}
                    </td>

                    <!-- Case Status -->
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                            @if($request->isCaseOpen()) bg-amber-50 text-amber-700 border-amber-200
                            @else bg-slate-100 text-slate-600 border-slate-200 @endif">
                            {{ $request->isCaseOpen() ? 'Open' : 'Closed' }}
                        </span>
                        @if($request->isCaseClosed() && $request->closed_at)
                            <div class="text-xs text-slate-500 mt-1">{{ $request->closed_at->format('d M Y') }}</div>
                        @endif
                    </td>

                    <!-- First Contact -->
                    <td class="px-6 py-4">
                        @if($request->advisor)
                            <div class="font-semibold text-slate-900">{{ $request->advisor->name }}</div>
                            <div class="text-xs text-slate-500">{{ $request->first_contact_at?->format('d M Y') ?: '—' }}</div>
                        @else
                            <span class="text-xs text-slate-400 italic">Unclaimed</span>
                        @endif
                    </td>

                    <!-- Last Touched -->
                    <td class="px-6 py-4 text-slate-500">
                        @if($request->last_touched_at)
                            <div class="font-semibold text-slate-700">{{ $request->last_touched_at->diffForHumans() }}</div>
                            <div class="text-xs text-slate-500">{{ $request->last_touched_at->format('d M Y, H:i') }}</div>
                        @else
                            <span class="text-xs text-slate-400 italic">Never</span>
                        @endif
                    </td>

                    <!-- Actions -->
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('advisor.cases.show', $request->id) }}"
                           class="inline-flex text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                            View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-400 italic">
                        No cases to show. Paid, confirmed and free consultation cases will appear here.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($requests->hasPages())
        <div class="px-6 py-4 border-t border-slate-200">
            {{ $requests->links() }}
        </div>
    @endif

</div>

@endsection
