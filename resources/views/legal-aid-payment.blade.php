@extends('layouts.app')

@section('title', __('legal_aid.payment_title') . ' | MarocLoi')

@section('content')
<section class="bg-gray-50 py-16">
    <div class="container-page">
        <div class="max-w-xl mx-auto">
            <div class="text-center mb-8" data-animate="fade-up">
                <span class="section-label">{{ __('legal_aid.payment_badge') }}</span>
                <h1 class="section-title mt-4">{{ __('legal_aid.payment_title') }}</h1>
                <p class="text-sm text-gray-500 mt-3">{{ __('legal_aid.payment_ticket') }} <span class="font-bold text-gray-900">{{ $request->ticketLabel }}</span></p>
            </div>

            @if ($request->status === \App\Models\LegalAidRequest::STATUS_CONFIRMED)
                <div class="card p-8 text-center" data-animate="fade-up">
                    <div class="text-4xl mb-3">✓</div>
                    <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_confirmed') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_confirmed_desc') }}</p>
                    <a href="{{ route('legal-aid') }}" class="btn-primary inline-flex">{{ __('legal_aid.back_home') }}</a>
                </div>
            @elseif ($request->status === \App\Models\LegalAidRequest::STATUS_PAID)
                <div class="card p-8 text-center" data-animate="fade-up">
                    <div class="text-4xl mb-3">⏳</div>
                    <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_paid') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_paid_desc') }}</p>
                    <a href="{{ route('legal-aid') }}" class="btn-primary inline-flex">{{ __('legal_aid.back_home') }}</a>
                </div>
            @else
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card p-8 mb-6" data-animate="fade-up">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_online_title') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_online_desc') }}</p>
                    @if ($paymentUrl)
                        <a href="{{ $paymentUrl }}" target="_blank" rel="noopener noreferrer" class="btn-primary w-full text-center inline-flex justify-center">
                            {{ __('legal_aid.payment_pay_button') }}
                        </a>
                    @else
                        <p class="text-sm rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                            {{ __('legal_aid.payment_unavailable') }}
                        </p>
                    @endif
                </div>

                <div class="card p-8" data-animate="fade-up" style="--delay:.1s">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_bank_title') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_bank_desc') }}</p>
                    @if ($request->receipt_path)
                        <p class="text-sm rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">
                            {{ __('legal_aid.receipt_uploaded_note') }}
                        </p>
                    @else
                        <form action="{{ route('legal-aid.receipt', $request->ticket_number) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('legal_aid.receipt_field') }}</label>
                                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 transition-colors">
                                <p class="text-xs text-gray-400 mt-1.5">{{ __('legal_aid.receipt_hint') }}</p>
                            </div>
                            <button type="submit" class="btn-primary w-full">
                                {{ __('legal_aid.receipt_submit') }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
