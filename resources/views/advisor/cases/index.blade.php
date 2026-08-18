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
        $closedCount = $requests
            ->getCollection()
            ->where('case_status', \App\Models\LegalAidRequest::CASE_CLOSED)
            ->count();
        $unclaimedCount = $requests->getCollection()->whereNull('advisor_id')->count();
        $myCount = $requests
            ->getCollection()
            ->where('advisor_id', auth()->id())
            ->count();
    @endphp

    <!-- Summary Strip -->
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('advisor.cases.index', ['case_status' => 'open']) }}"
            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow">
            <div class="shrink-0 rounded-lg bg-blue-50 p-2.5 text-blue-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open</p>
                <p class="text-lg font-extrabold leading-tight text-slate-900">{{ $openCount }}</p>
            </div>
        </a>

        <a href="{{ route('advisor.cases.index', ['case_status' => 'closed']) }}"
            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow">
            <div class="shrink-0 rounded-lg bg-emerald-50 p-2.5 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Closed</p>
                <p class="text-lg font-extrabold leading-tight text-slate-900">{{ $closedCount }}</p>
            </div>
        </a>

        <a href="{{ route('advisor.cases.index', ['advisor' => 'unclaimed']) }}"
            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-amber-300 hover:shadow">
            <div class="shrink-0 rounded-lg bg-amber-50 p-2.5 text-amber-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Unclaimed</p>
                <p class="text-lg font-extrabold leading-tight text-slate-900">{{ $unclaimedCount }}</p>
            </div>
        </a>

        <a href="{{ route('advisor.cases.index', ['advisor' => auth()->id()]) }}"
            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-purple-300 hover:shadow">
            <div class="shrink-0 rounded-lg bg-purple-50 p-2.5 text-purple-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">My Cases</p>
                <p class="text-lg font-extrabold leading-tight text-slate-900">{{ $myCount }}</p>
            </div>
        </a>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('advisor.cases.index') }}"
            class="grid grid-cols-2 items-end gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <label for="search"
                    class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Search</label>
                <input type="search" name="search" id="search" value="{{ $filters['search'] }}"
                    placeholder="Ticket, name, email, phone…"
                    class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-900 transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20">
            </div>

            <div>
                <label for="case_status"
                    class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Case Status</label>
                <select name="case_status" id="case_status"
                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">All cases</option>
                    <option value="open" @selected($filters['case_status'] === 'open')>Open</option>
                    <option value="closed" @selected($filters['case_status'] === 'closed')>Closed</option>
                </select>
            </div>

            <div>
                <label for="payment_status"
                    class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Payment Status</label>
                <select name="payment_status" id="payment_status"
                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Any payment status</option>
                    <option value="paid" @selected($filters['payment_status'] === 'paid')>Paid (Google Pay)</option>
                    <option value="confirmed" @selected($filters['payment_status'] === 'confirmed')>Confirmed (Bank)</option>
                    <option value="free" @selected($filters['payment_status'] === 'free')>Free consultation</option>
                </select>
            </div>

            <div>
                <label for="advisor" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">First
                    Contact Advisor</label>
                <select name="advisor" id="advisor"
                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Any advisor</option>
                    @foreach ($advisors as $advisor)
                        <option value="{{ $advisor->id }}" @selected((string) $filters['advisor'] === (string) $advisor->id)>{{ $advisor->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="service"
                    class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Service</label>
                <select name="service" id="service"
                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Any service</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected((string) $filters['service'] === (string) $service->id)>{{ $service->name_en }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 lg:justify-end">
                <button type="submit"
                    class="rounded-lg border border-transparent bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    Apply Filters
                </button>
                <a href="{{ route('advisor.cases.index') }}"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-center text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Cases Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] border-collapse text-left">
                <thead
                    class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
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
                        <tr class="transition hover:bg-slate-50/70">
                            <!-- Ticket -->
                            <td class="px-6 py-4">
                                <a href="{{ route('advisor.cases.show', $request->id) }}"
                                    class="font-mono font-bold text-slate-900 transition hover:text-blue-600 hover:underline">
                                    {{ $request->ticketLabel }}
                                </a>
                                {{-- <div class="text-xs text-slate-500">{{ $request->created_at->format('d M Y, H:i') }}</div> --}}
                            </td>

                            <!-- Client -->
                            <td class="max-w-xs px-6 py-4">
                                <a href="{{ route('advisor.cases.show', $request->id) }}"
                                    class="font-normal text-slate-900 transition hover:text-blue-600 hover:underline">
                                    {{ $request->full_name }}
                                </a>
                                {{-- <div class="text-xs text-slate-500">{{ $request->email }}</div> --}}
                            </td>

                            <!-- Services -->
                            <td class="px-6 py-4">
                                @if ($request->selectedServices->isNotEmpty())
                                    <div class="w-44 truncate font-semibold text-slate-900">
                                        @foreach ($request->selectedServices as $service)
                                            {{ $service->name }}@if (!$loop->last)
                                                ,
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs italic text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Payment Status -->
                            <td class="flex px-6 py-4">
                                <span
                                    class="@if ($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
                            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
                            @else bg-purple-50 text-purple-700 border-purple-200 @endif inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold">
                                    @if ($request->isFree())
                                        Free
                                    @else
                                        {{ ucwords(str_replace('_', ' ', $request->status)) }}
                                    @endif
                                </span>
                                {{-- <div class="text-xs text-slate-500 mt-1">
                            {{ $request->isFree()
                                ? 'No charge'
                                : ($request->payableTotal !== null ? number_format($request->payableTotal, 0).' MAD' : '—') }}
                        </div> --}}
                            </td>

                            <!-- Case Status -->
                            <td class="px-6 py-4">
                                <span
                                    class="@if ($request->isCaseOpen()) bg-amber-50 text-amber-700 border-amber-200
                            @else bg-slate-100 text-slate-600 border-slate-200 @endif inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $request->isCaseOpen() ? 'Open' : 'Closed' }}
                                </span>
                                @if ($request->isCaseClosed() && $request->closed_at)
                                    <div class="mt-1 text-xs text-slate-500">{{ $request->closed_at->format('d M Y') }}
                                    </div>
                                @endif
                            </td>

                            <!-- First Contact -->
                            <td class="px-6 py-4 flex justify-center flex-col items-center">
                                @if ($request->advisor)
                                    <div class="flex items-center gap-1.5">
                                        @if ((int) $request->advisor_id != (int) auth()->id())
                                            <span
                                                class="font-semibold text-slate-900">{{ $request->advisor->name }}</span>
                                        @else
                                            <span
                                                class="inline-flex items-center rounded border border-purple-200 bg-purple-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-purple-700">You</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $request->first_contact_at?->format('d M Y') ?: '—' }}</div>
                                @else
                                    <span class="text-xs italic text-slate-400">Unclaimed</span>
                                @endif
                            </td>

                            <!-- Last Touched -->
                            <td class="px-6 py-4">
                                @if ($request->last_touched_at)
                                    <div
                                        class="{{ $request->last_touched_at->lt(now()->subDays(7)) ? 'text-rose-600' : '' }} font-semibold text-slate-700">
                                        {{ $request->last_touched_at->diffForHumans() }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $request->last_touched_at->format('d M Y, H:i') }}</div>
                                    @if ($request->last_touched_at->lt(now()->subDays(7)))
                                        <div class="mt-0.5 text-[11px] font-semibold text-rose-600">No activity for 7+ days
                                        </div>
                                    @endif
                                @else
                                    <span class="text-xs italic text-slate-400">Never</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('advisor.cases.show', $request->id) }}"
                                    class="inline-flex rounded-lg border border-transparent bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                    View
                                </a>
                            </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm italic text-slate-400">
                                    No cases to show. Paid, confirmed and free consultation cases will appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $requests->links() }}
                </div>
            @endif

        </div>

    @endsection
