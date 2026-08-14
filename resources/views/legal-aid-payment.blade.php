@extends('layouts.app')

@push('styles')
<style>
    #google-pay-wrap {
        position: relative;
        transition: opacity .2s ease;
    }
    #google-pay-wrap.is-loading {
        opacity: .55;
        pointer-events: none;
    }
    #google-pay-button iframe {
        width: 100% !important;
        min-height: 48px;
    }
    .payment-alert {
        margin-top: .75rem;
        border-radius: .75rem;
        padding: .75rem 1rem;
        font-size: .875rem;
    }
    .payment-alert-error {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
    }
    .payment-alert-info {
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }
</style>
@endpush

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
                @if ($request->isFree())
                    <div class="card p-8 text-center" data-animate="fade-up">
                        <div class="text-4xl mb-3">✓</div>
                        <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_free_title') }}</h2>
                        <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_free_desc', ['whatsapp' => $request->whatsapp ?: $request->phone]) }}</p>
                        <a href="{{ route('legal-aid') }}" class="btn-primary inline-flex">{{ __('legal_aid.back_home') }}</a>
                    </div>
                @else
                    @if ($request->status === \App\Models\LegalAidRequest::STATUS_REJECTED)
                        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            <strong class="block font-semibold mb-1">{{ __('legal_aid.payment_rejected') }}</strong>
                            {{ __('legal_aid.payment_rejected_retry') }}
                        </div>
                    @endif

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

                @if ($request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY)
                <div class="card p-8 mb-6" data-animate="fade-up">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_online_title') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_online_desc') }}</p>
                    @if ($request->onlineTotal !== null)
                        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-green-800">{{ __('legal_aid.payment_base_price', ['price' => number_format((float) $request->base_price, 0)]) }}</span>
                                <span class="text-sm font-semibold text-green-800">{{ number_format((float) $request->base_price, 0) }} MAD</span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-sm text-green-700">{{ __('legal_aid.payment_online_discount', ['percent' => (int) config('legal_aid.online_discount_percent')]) }}</span>
                                <span class="text-sm font-semibold text-green-700">−{{ number_format((float) $request->base_price - $request->onlineTotal, 0) }} MAD</span>
                            </div>
                            <div class="border-t border-green-200 mt-3 pt-3 flex items-center justify-between">
                                <span class="text-sm font-bold text-green-900">{{ __('legal_aid.payment_total') }}</span>
                                <span class="text-base font-bold text-green-900">{{ number_format($request->onlineTotal, 0) }} MAD</span>
                            </div>
                        </div>
                    @endif
                    @if (config('cashier.key'))
                        <div id="google-pay-wrap" class="relative">
                            <div id="google-pay-button" class="hidden"></div>

                            <div id="payment-status"
                                class="hidden rounded-xl border border-blue-200 bg-blue-50 p-4 text-center text-sm text-blue-800">
                                {{ __('legal_aid.payment_processing') }}
                            </div>

                            <div id="google-pay-unsupported"
                                class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                {{ __('legal_aid.payment_unsupported') }}
                            </div>
                        </div>

                        <div id="payment-message" class="hidden"></div>

                        <p class="text-xs text-gray-400 mt-4 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            {{ __('legal_aid.payment_secure_note') }}
                        </p>

                        <script>
                            window.MarocLoiStripe = {
                                stripeKey: @json(config('cashier.key')),
                                intentUrl: @json(route('legal-aid.payment.intent', $request->ticket_number)),
                                verifyUrl: @json(route('legal-aid.payment.verify', $request->ticket_number)),
                                csrfToken: @json(csrf_token()),
                                totalLabel: @json(__('legal_aid.payment_total')),
                                currency: @json(strtolower((string) config('cashier.currency', 'mad'))),
                                country: @json((string) config('cashier.country', 'MA')),
                                messages: {
                                    success: @json(__('legal_aid.payment_success')),
                                    processing: @json(__('legal_aid.payment_processing')),
                                    unsupported: @json(__('legal_aid.payment_unsupported')),
                                    loadStripeError: @json(__('legal_aid.payment_load_stripe_error')),
                                    networkError: @json(__('legal_aid.payment_network_error')),
                                    genericError: @json(__('legal_aid.payment_generic_error')),
                                    couldNotComplete: @json(__('legal_aid.payment_incomplete')),
                                },
                            };
                        </script>

                        @vite(['resources/js/stripe-payment-request.js'])
                    @else
                        <p class="text-sm rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                            {{ __('legal_aid.payment_unavailable') }}
                        </p>
                    @endif
                </div>
                @else
                <div class="card p-8" data-animate="fade-up" style="--delay:.1s">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.payment_bank_title') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.payment_bank_desc') }}</p>
                    @if ($request->bankTotal !== null)
                        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-amber-800">{{ __('legal_aid.payment_base_price', ['price' => number_format((float) $request->base_price, 0)]) }}</span>
                                <span class="text-sm font-semibold text-amber-800">{{ number_format((float) $request->base_price, 0) }} MAD</span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-sm text-amber-700">{{ __('legal_aid.payment_bank_fee', ['percent' => (int) config('legal_aid.bank_admin_fee_percent')]) }}</span>
                                <span class="text-sm font-semibold text-amber-700">+{{ number_format((float) $request->bankTotal - (float) $request->base_price, 0) }} MAD</span>
                            </div>
                            <div class="border-t border-amber-200 mt-3 pt-3 flex items-center justify-between">
                                <span class="text-sm font-bold text-amber-900">{{ __('legal_aid.payment_total') }}</span>
                                <span class="text-base font-bold text-amber-900">{{ number_format($request->bankTotal, 0) }} MAD</span>
                            </div>
                        </div>
                    @endif
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
                @endif
            @endif
        </div>
    </div>
</section>
@endsection
