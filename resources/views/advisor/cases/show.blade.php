@extends('layouts.admin')

@section('title', 'Case ' . $request->ticketLabel)

@section('page-title')
Case {{ $request->ticketLabel }}
@endsection

@section('page-description')
Manage the advisor work on case {{ $request->ticketLabel }}.
@endsection

@section('content')

@php
    $totalServices = $request->selectedServices->count();
    $doneServices = $request->completedServices()->count();
    $progressPercent = $totalServices > 0 ? (int) round($doneServices / $totalServices * 100) : 0;
    $claimedByMe = $request->advisor && $request->advisor->id === auth()->id();
    $claimedByOther = $request->advisor && ! $claimedByMe;
@endphp

<div class="mb-6">
    <a href="{{ route('advisor.cases.index') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Advisor Cases
    </a>
</div>

<!-- Header -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-fit px-4 h-12 shrink-0 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold font-mono">
                {{ $request->ticket_number }}
            </div>
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-slate-900 truncate">{{ $request->full_name }}</h2>
                <p class="text-xs text-slate-500 truncate">{{ $request->email }} · {{ $request->phone }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border
                @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
                @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
                @else bg-purple-50 text-purple-700 border-purple-200 @endif">
                @if($request->isFree()) Free consultation
                @else {{ ucwords(str_replace('_', ' ', $request->status)) }} @endif
            </span>

            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border
                @if($request->isCaseOpen()) bg-amber-50 text-amber-700 border-amber-200
                @else bg-slate-100 text-slate-600 border-slate-200 @endif">
                {{ $request->isCaseOpen() ? 'Open' : 'Closed' }}
            </span>
        </div>
    </div>

    <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/50 flex flex-wrap items-center gap-2">
        @if($request->isCaseOpen())
            <form action="{{ route('advisor.cases.close', $request->id) }}" method="POST"
                  onsubmit="return confirm('Close case {{ $request->ticketLabel }}? You can reopen it later if the customer pays for more services.')">
                @csrf
                <button type="submit"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                    Close case
                </button>
            </form>
        @else
            <form action="{{ route('advisor.cases.reopen', $request->id) }}" method="POST">
                @csrf
                <button type="submit"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-blue-50 text-blue-600 border-blue-200 hover:border-blue-300">
                    Reopen case
                </button>
            </form>
        @endif

        @if(! $request->advisor)
            <form action="{{ route('advisor.cases.first-contact', $request->id) }}" method="POST">
                @csrf
                <button type="submit"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-blue-50 text-blue-600 border-blue-200 hover:border-blue-300">
                    Claim as first contact
                </button>
            </form>
        @endif

        @if($request->isCaseOpen())
            <span class="text-xs text-slate-400 italic ml-auto">Close the case when all services are finished</span>
        @else
            <span class="text-xs text-slate-400 italic ml-auto">Closed — reopen if the customer pays for more services</span>
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Services & Tasks -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-visible">
        <div class="px-6 py-4 border-b border-slate-100 rounded-t-xl overflow-visible">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                    Services & Tasks
                    <span class="relative inline-flex group">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 cursor-help rounded-full" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 top-full mt-2 hidden group-hover:block z-30 w-64 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium leading-relaxed text-white shadow-lg">
                            In order to mark a task as done, the case must be claimed first.
                            <span class="absolute left-1/2 -translate-x-1/2 -top-1 h-2 w-2 rotate-45 bg-slate-900"></span>
                        </span>
                    </span>
                </h3>
                @if($totalServices > 0)
                    <span class="text-xs font-bold {{ $request->isFullyCompleted() ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ $doneServices }}/{{ $totalServices }} done
                    </span>
                @endif
            </div>
            @if($totalServices > 0)
                <div class="mt-3 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $progressPercent }}%"></div>
                </div>
            @endif
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse($request->selectedServices as $service)
                @php
                    $completed = $request->serviceIsCompleted($service);
                    $isUnclaimed = ! $request->advisor_id;
                    $completedById = $service->pivot->completed_by ?? null;
                    $completedByUser = $completedById ? ($completedByUsers[$completedById] ?? null) : null;
                    $completedByLabel = null;
                    if ($completed && $completedById) {
                        $completedByLabel = $completedById === auth()->id() ? 'You' : ($completedByUser->name ?? 'Advisor');
                    }
                @endphp
                <li class="px-6 py-4 {{ $completed ? 'bg-emerald-50/40' : '' }} flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-900 flex items-center gap-2">
                            {{ $service->name }}
                            @if($completed)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Completed
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            @if($service->price_display)
                                {{ $service->price_display }}
                            @elseif($service->price !== null && (float) $service->price > 0)
                                {{ number_format((float) $service->price, 0).' MAD' }}
                            @else
                                Free
                            @endif
                            @if($completed)
                                · completed {{ $service->pivot->completed_at?->format('d M Y') ?: '' }}
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($completed && $completedByLabel)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                Done by {{ $completedByLabel }}
                            </span>
                        @endif
                        <form action="{{ route('advisor.cases.toggle-service', [$request->id, $service->id]) }}" method="POST" class="shrink-0">
                            @csrf
                            <button type="submit"
                                    @if($isUnclaimed) disabled title="Claim this case as first contact to manage tasks" @endif
                                    class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-full border transition shadow-sm
                                    {{ $isUnclaimed ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed opacity-60' : ($completed ? 'bg-white hover:bg-amber-50 text-amber-600 border-amber-200 hover:border-amber-300' : 'bg-slate-900 hover:bg-slate-800 text-white border-transparent') }}">
                                @if($completed)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Mark as missing
                                @else
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Mark as done
                                @endif
                            </button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="p-6 text-sm text-slate-400 italic">No services recorded for this case.</li>
            @endforelse
        </ul>

        {{-- <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Base Price</dt>
                <dd class="font-semibold text-slate-900">{{ $request->base_price !== null ? number_format((float) $request->base_price, 0).' MAD' : '—' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Paid Total</dt>
                <dd class="font-bold text-slate-900">{{ $request->payableTotal !== null ? number_format($request->payableTotal, 0).' MAD' : '—' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Payment Method</dt>
                <dd>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border
                        @if($request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_BANK) bg-amber-50 text-amber-700 border-amber-200
                        @else bg-emerald-50 text-emerald-700 border-emerald-200 @endif">
                        {{ $request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_BANK ? 'Bank Transfer' : 'Card' }}
                    </span>
                </dd>
            </div>
        </div> --}}
    </div>

    <!-- Client Details -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Client Details</h3>
        </div>
        <dl class="p-6 space-y-4 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Email</dt>
                <dd class="text-slate-700 break-all">{{ $request->email }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Phone</dt>
                <dd class="text-slate-700">{{ $request->phone }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">WhatsApp</dt>
                <dd class="text-slate-700">{{ $request->whatsapp ?: '—' }}</dd>
            </div>
            {{-- <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Consultation Mode</dt>
                <dd>
                    @if($request->consultation_mode === 'whatsapp')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-green-50 text-green-700 border-green-200">WhatsApp</span>
                    @elseif($request->consultation_mode === 'office')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-slate-100 text-slate-600 border-slate-200">At the office</span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </dd>
            </div> --}}
            @if($request->call_time)
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Preferred Call Time</dt>
                <dd class="text-slate-700">{{ $request->call_time }}</dd>
            </div>
            @endif
            {{-- <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Locale</dt>
                <dd class="text-slate-700 uppercase">{{ $request->locale ?? '—' }}</dd>
            </div> --}}
        </dl>
    </div>

    <!-- First Contact -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">First Contact</h3>
        </div>
        <div class="p-6">
            @if($request->advisor)
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 shrink-0 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-sm">
                        {{ strtoupper(substr($request->advisor->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-slate-900 truncate">{{ $request->advisor->name }}</div>
                        <div class="text-xs text-slate-500 truncate">{{ $request->advisor->email }}</div>
                    </div>
                </div>
                <div class="mt-4 text-xs text-slate-500">
                    Spoke to the customer first on {{ $request->first_contact_at?->format('d M Y, H:i') ?: '—' }}
                </div>
                @if($claimedByOther)
                    <div class="mt-4 inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        Claimed by {{ $request->advisor->name }}
                    </div>
                @endif
            @else
                <div class="text-sm text-slate-500 leading-relaxed">
                    Nobody has spoken to this customer yet.
                </div>
                <form action="{{ route('advisor.cases.first-contact', $request->id) }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit"
                            class="w-full text-xs font-semibold px-3 py-2 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                        Claim — I spoke to this customer first
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Status & Timeline -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Status & Timeline</h3>
        </div>
        <dl class="p-6 space-y-4 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Last Touched</dt>
                <dd class="text-slate-700">
                    @if($request->last_touched_at)
                        {{ $request->last_touched_at->format('d M Y, H:i') }}
                        <span class="text-slate-500">({{ $request->last_touched_at->diffForHumans() }})</span>
                    @else
                        Never touched
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Submitted At</dt>
                <dd class="text-slate-700">{{ $request->created_at?->format('d M Y, H:i') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Paid At</dt>
                <dd class="text-slate-700">{{ $request->paid_at?->format('d M Y, H:i') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Confirmed At</dt>
                <dd class="text-slate-700">{{ $request->confirmed_at?->format('d M Y, H:i') ?: '—' }}</dd>
            </div>
            @if($request->closed_at)
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Closed At</dt>
                <dd class="text-slate-700">{{ $request->closed_at->format('d M Y, H:i') }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <!-- Case Description -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Case Description</h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $request->case_description }}</p>
        </div>
    </div>

    <!-- Case Notes -->
    <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">Case Notes</h3>
            <span class="text-xs text-slate-500">{{ $request->caseNotes->count() }} note(s)</span>
        </div>

        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <form action="{{ route('advisor.cases.notes', $request->id) }}" method="POST">
                @csrf
                <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Add a note</label>
                <textarea name="note" id="note" rows="2" required
                          placeholder="Write what happened with this case (calls, emails, progress…) — this updates the last touched time."
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition"></textarea>
                <div class="mt-3 flex justify-end">
                    <button type="submit"
                            class="text-xs font-semibold px-4 py-2 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                        Save note
                    </button>
                </div>
            </form>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse($request->caseNotes as $note)
                <li class="px-6 py-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs">
                                {{ strtoupper(substr($note->user?->name ?? 'U', 0, 1)) }}
                            </div>
                            <span class="text-xs font-bold text-slate-900">{{ $note->user?->name ?? 'Unknown' }}</span>
                        </div>
                        <span class="text-xs text-slate-500">{{ $note->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $note->note }}</p>
                </li>
            @empty
                <li class="p-6 text-sm text-slate-400 italic">No notes yet. Add the first note to keep track of the case.</li>
            @endforelse
        </ul>
    </div>

</div>

@endsection
