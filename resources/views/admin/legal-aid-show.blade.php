@extends('layouts.admin')

@section('title', 'Legal Aid Request ' . $request->ticketLabel)

@section('page-title')
Request {{ $request->ticketLabel }}
@endsection

@section('page-description')
Full details for legal aid request {{ $request->ticketLabel }}.
@endsection

@section('content')

@php
    $isBank = $request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_BANK;
    $isGooglePay = $request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY;
    $discountPercent = (float) config('legal_aid.online_discount_percent', 10);
    $bankFeePercent = (float) config('legal_aid.bank_admin_fee_percent', 10);
@endphp

<div class="mb-6">
    <a href="{{ route('admin.legal-aid.index') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Legal Aid Requests
    </a>
</div>

<!-- Header -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold font-mono">
            {{ $request->ticket_number }}
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900">{{ $request->full_name }}</h2>
            <p class="text-xs text-slate-500">{{ $request->email }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border
            @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_REJECTED) bg-red-50 text-red-700 border-red-200
            @else bg-amber-50 text-amber-700 border-amber-200 @endif">
            {{ ucwords(str_replace('_', ' ', $request->status)) }}
        </span>

        @if($request->isFree())
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Free consultation
            </span>
        @endif

        @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                ✓ Confirmed
            </span>
        @else
            @if($request->ticket_pdf_path)
                <a href="{{ route('legal-aid.ticket-pdf', $request->ticket_number) }}"
                   class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-blue-50 text-blue-600 border-blue-200 hover:border-blue-300">
                    Ticket PDF
                </a>
            @endif

            @if(($request->receipt_path || $request->isFree()) && in_array($request->status, [\App\Models\LegalAidRequest::STATUS_PENDING_PAYMENT, \App\Models\LegalAidRequest::STATUS_PENDING, \App\Models\LegalAidRequest::STATUS_PAID], true))
                <form action="{{ route('admin.legal-aid.confirm', $request->id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                        {{ $request->isFree() ? 'Confirm' : 'Confirm Payment' }}
                    </button>
                </form>
            @endif

            @if(!$request->isPaid())
                <form action="{{ route('admin.legal-aid.resend', $request->id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-blue-50 text-blue-600 border-blue-200 hover:border-blue-300">
                        {{ $request->isFree() ? 'Resend Email' : 'Resend Link' }}
                    </button>
                </form>
            @endif

            @if($request->receipt_path && $request->status !== \App\Models\LegalAidRequest::STATUS_CONFIRMED && $request->status !== \App\Models\LegalAidRequest::STATUS_REJECTED)
                <form action="{{ route('admin.legal-aid.reject', $request->id) }}" method="POST"
                      onsubmit="return confirm('Reject this request? The client will be notified.')">
                    @csrf
                    <button type="submit"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-rose-50 text-rose-600 border-rose-200 hover:border-rose-300">
                        Reject
                    </button>
                </form>
            @endif
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 mb-6">

    <!-- Client Details -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Client Details</h3>
        </div>
        <dl class="p-6 space-y-4 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Full Name</dt>
                <dd class="font-semibold text-slate-900">{{ $request->full_name }}</dd>
            </div>
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
            <div>
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
            </div>
            @if($request->call_time)
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Preferred Call Time</dt>
                <dd class="text-slate-700">{{ $request->call_time }}</dd>
            </div>
            @endif
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Locale</dt>
                <dd class="text-slate-700 uppercase">{{ $request->locale ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <!-- Services & Payment -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Services & Payment</h3>
        </div>
        <dl class="p-6 space-y-4 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Service(s)</dt>
                <dd>
                    @if($request->selectedServices->isNotEmpty())
                        <ul class="space-y-1.5">
                            @foreach($request->selectedServices as $service)
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="font-semibold text-slate-900">{{ $service->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </dd>
            </div>

            @if($request->isFree())
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Payment</dt>
                    <dd>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">Free consultation</span>
                    </dd>
                </div>
            @else
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Payment Method</dt>
                    <dd>
                        @if($isBank)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-amber-50 text-amber-700 border-amber-200">Bank Transfer</span>
                        @elseif($isGooglePay)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">Google Pay</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Amount to Pay</dt>
                    <dd>
                        <span class="text-lg font-extrabold text-slate-900">
                            {{ $request->payableTotal !== null ? number_format($request->payableTotal, 0).' MAD' : '—' }}
                        </span>
                        @if($request->base_price !== null && $request->payableTotal !== null && (float) $request->base_price !== $request->payableTotal)
                            <p class="mt-1 text-xs text-slate-400">
                                Base price {{ number_format((float) $request->base_price, 0) }} MAD
                                @if($isBank) · +{{ $bankFeePercent }}% bank fee
                                @elseif($isGooglePay) · −{{ $discountPercent }}% online discount
                                @endif
                            </p>
                        @endif
                    </dd>
                </div>

                @if($isGooglePay)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Payment Link</dt>
                        <dd>
                            @if($paymentUrl)
                                <a href="{{ $paymentUrl }}" target="_blank" rel="noopener noreferrer"
                                   class="text-blue-600 hover:text-blue-800 hover:underline break-all">{{ $paymentUrl }}</a>
                            @else
                                <span class="text-slate-400">Not configured</span>
                            @endif
                        </dd>
                    </div>
                @endif
            @endif

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Receipt</dt>
                <dd>
                    @if($request->receipt_path)
                        <a href="{{ Storage::url($request->receipt_path) }}" target="_blank"
                           class="text-blue-600 hover:text-blue-800 hover:underline">View / Download</a>
                    @else
                        <span class="text-slate-400">No receipt uploaded</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <!-- Timeline -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Timeline</h3>
        </div>
        <div class="p-6 space-y-5 text-sm">
            @foreach([
                ['label' => 'Submitted', 'at' => $request->created_at, 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'text-blue-600 bg-blue-50'],
                ['label' => 'Paid', 'at' => $request->paid_at, 'icon' => 'M17 9V7a2 2 0 00-2-2H9a2 2 0 00-2 2v2m-2 4h14m-2 0a2 2 0 012 2v4a2 2 0 01-2 2H7a2 2 0 01-2-2v-4a2 2 0 012-2z', 'color' => 'text-emerald-600 bg-emerald-50'],
                ['label' => 'Confirmed', 'at' => $request->confirmed_at, 'icon' => 'M5 13l4 4L19 7', 'color' => 'text-emerald-600 bg-emerald-50'],
            ] as $step)
                @if($step['at'])
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-lg {{ $step['color'] }} shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $step['label'] }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $step['at']->format('d M Y, H:i') }} · {{ $step['at']->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @endif
            @endforeach

            @if(! $request->created_at && ! $request->paid_at && ! $request->confirmed_at)
                <p class="text-slate-400">No timeline entries yet.</p>
            @endif
        </div>
    </div>

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

@endsection