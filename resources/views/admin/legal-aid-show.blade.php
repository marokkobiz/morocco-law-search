@extends('layouts.admin')

@section('title', 'Legal Aid Request ' . $request->ticketLabel)

@section('page-title')
Request {{ $request->ticketLabel }}
@endsection

@section('page-description')
Full details for legal aid request {{ $request->ticketLabel }}.
@endsection

@section('content')

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
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border
            @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_REJECTED) bg-red-50 text-red-700 border-red-200
            @else bg-amber-50 text-amber-700 border-amber-200 @endif">
            {{ ucwords(str_replace('_', ' ', $request->status)) }}
        </span>

        @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                ✓ Confirmed
            </span>
        @else
            <div class="flex flex-wrap items-center justify-center gap-2">
                @if($request->receipt_path && in_array($request->status, [\App\Models\LegalAidRequest::STATUS_PENDING_PAYMENT, \App\Models\LegalAidRequest::STATUS_PAID], true))
                    <form action="{{ route('admin.legal-aid.confirm', $request->id) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-slate-900 hover:bg-slate-800 text-white border-transparent">
                            Confirm Payment
                        </button>
                    </form>
                @endif

                @if(!$request->isPaid())
                    <form action="{{ route('admin.legal-aid.resend', $request->id) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-blue-50 text-blue-600 border-blue-200 hover:border-blue-300">
                            Resend Link
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
            </div>
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Case Description -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
            <h3 class="text-sm font-bold text-slate-900">Case Description</h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $request->case_description }}</p>
        </div>
    </div>

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
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Locale</dt>
                <dd class="text-slate-700 uppercase">{{ $request->locale ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <!-- Payment & Timeline -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-900">Payment</h3>
        </div>
        <dl class="p-6 space-y-4 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Google Pay Link</dt>
                <dd>
                    @if($paymentUrl)
                        <a href="{{ $paymentUrl }}" target="_blank" rel="noopener noreferrer"
                           class="text-blue-600 hover:text-blue-800 hover:underline break-all">{{ $paymentUrl }}</a>
                    @else
                        <span class="text-slate-400">Not configured</span>
                    @endif
                </dd>
            </div>
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
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Paid At</dt>
                <dd class="text-slate-700">{{ $request->paid_at?->format('d M Y, H:i') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Confirmed At</dt>
                <dd class="text-slate-700">{{ $request->confirmed_at?->format('d M Y, H:i') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Submitted At</dt>
                <dd class="text-slate-700">{{ $request->created_at?->format('d M Y, H:i') ?: '—' }}</dd>
            </div>
        </dl>
    </div>

</div>

@endsection
