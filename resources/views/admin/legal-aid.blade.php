@extends('layouts.admin')

@section('title', 'Legal Aid Requests')

@section('page-title')
Legal Aid Requests
@endsection

@section('page-description')
Track client requests, payments, and confirm cases.
@endsection

@section('content')

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Ticket</th>
                    <th class="px-6 py-4">Client</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Receipt</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($requests as $request)
                <tr class="hover:bg-slate-50/70 transition">
                    <!-- Ticket -->
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.legal-aid.show', $request->id) }}"
                           class="font-bold text-slate-900 font-mono hover:text-blue-600 hover:underline transition">
                            {{ $request->ticketLabel }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $request->created_at->format('d M Y, H:i') }}</div>
                    </td>

                    <!-- Client Details -->
                    <td class="px-6 py-4 max-w-xs">
                        <a href="{{ route('admin.legal-aid.show', $request->id) }}"
                           class="font-semibold text-slate-900 hover:text-blue-600 hover:underline transition">
                            {{ $request->full_name }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $request->email }}</div>
                        <div class="text-xs text-slate-500">{{ $request->phone }}@if($request->whatsapp) · WA: {{ $request->whatsapp }}@endif</div>
                    </td>

                    <!-- Status Badge -->
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                            @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED) bg-emerald-50 text-emerald-700 border-emerald-200
                            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_PAID) bg-blue-50 text-blue-700 border-blue-200
                            @elseif($request->status === \App\Models\LegalAidRequest::STATUS_REJECTED) bg-red-50 text-red-700 border-red-200
                            @else bg-amber-50 text-amber-700 border-amber-200 @endif">
                            {{ ucwords(str_replace('_', ' ', $request->status)) }}
                        </span>
                        @if($request->receipt_path)
                            <div class="text-xs text-slate-500 mt-1">Receipt uploaded</div>
                        @endif
                    </td>

                    <!-- Receipt -->
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

                    <!-- Action Buttons -->
                    <td class="px-6 py-4 text-center">
                        @if($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED)
                            <span class="text-xs text-emerald-600 font-semibold">✓ Confirmed</span>
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
