@extends('layouts.app')

@section('title', __('shop.cart_title') . ' | MarocLoi')

@push('styles')
<style>
    .field-error { color: #dc2626; font-size: 12px; margin-top: 4px; }
</style>
@endpush

@section('content')
<section class="bg-gray-50 py-10 sm:py-16">
    <div class="container-page">
        <div class="mx-auto max-w-3xl">
            <div class="mb-8 text-center" data-animate="fade-up">
                <span class="section-label">{{ __('shop.badge') }}</span>
                <h1 class="section-title mt-4">{{ __('shop.cart_title') }}</h1>
                <p class="mt-3 text-sm text-gray-500">{{ __('shop.cart_desc') }}</p>
            </div>

            <div id="shop-cart-empty" class="hidden card p-12 text-center" data-animate="fade-up">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900">{{ __('shop.cart_empty') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('shop.cart_empty_desc') }}</p>
                <a href="{{ route('legal-aid') }}" class="btn-primary mt-6 inline-flex">{{ __('shop.continue_shopping') }}</a>
            </div>

            <div id="shop-cart-full" class="hidden">
                <div class="card overflow-hidden p-0" data-animate="fade-up">
                    <div id="shop-cart-items" class="divide-y divide-gray-100"></div>
                    <div class="bg-gray-50 p-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-500">{{ __('shop.subtotal') }}</span>
                            <span id="shop-cart-subtotal" class="text-sm font-semibold text-gray-900">0 MAD</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3">
                            <span class="text-base font-bold text-gray-900">{{ __('shop.total') }}</span>
                            <span id="shop-cart-total" class="text-lg font-bold text-gray-900">0 MAD</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">{{ __('shop.vat_included') }}</p>
                    </div>
                </div>

                <div class="mt-8 card p-6 sm:p-8" data-animate="fade-up" style="--delay:.1s">
                    <h2 class="text-base font-bold text-gray-900">{{ __('shop.customer_info') }}</h2>
                    <p class="mt-1 text-xs text-gray-500">{{ __('shop.customer_info_desc') }}</p>

                    <div id="shop-cart-checkout-message" class="hidden mt-4 rounded-xl border p-3 text-sm"></div>

                    <div class="mt-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" id="shop-full-name" name="full_name" required maxlength="255"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none"
                                    placeholder="{{ __('legal_aid.field_name_placeholder') }}">
                                <p id="shop-full-name-error" class="field-error hidden"></p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_email') }} <span class="text-red-500">*</span> <span class="ml-1 text-xs font-light text-gray-400">{{ __('legal_aid.field_email_help') }}</span> </label>
                                <input type="email" id="shop-email" name="email" required maxlength="255"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none"
                                    placeholder="{{ __('legal_aid.field_email_placeholder') }}">
                                <p id="shop-email-error" class="field-error hidden"></p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-gray-700">
                                    {{ __('legal_aid.field_phone') }} <span class="text-red-500">*</span>
                                    <span class="group relative inline-flex">
                                        <svg class="h-4 w-4 cursor-help text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-lg group-hover:block">{{ __('legal_aid.field_phone_help') }}</span>
                                    </span>
                                </label>
                                <input type="tel" id="shop-phone" name="phone" required
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none"
                                    placeholder="{{ __('legal_aid.field_phone_placeholder') }}">
                                <p id="shop-phone-error" class="field-error hidden"></p>
                            </div>
                            <div>
                                <label class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-gray-700">
                                    {{ __('legal_aid.field_whatsapp') }}
                                    <span class="group relative inline-flex">
                                        <svg class="h-4 w-4 cursor-help text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-lg group-hover:block">{{ __('legal_aid.field_whatsapp_help') }}</span>
                                    </span>
                                </label>
                                <input type="tel" id="shop-whatsapp" name="whatsapp"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none"
                                    placeholder="{{ __('legal_aid.field_whatsapp_placeholder') }}">
                                <button type="button" id="copy-phone-to-whatsapp"
                                    class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 transition-colors hover:text-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                                    title="{{ __('legal_aid.copy_from_phone_hint') }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 8H5a2 2 0 00-2 2v9a2 2 0 002 2h10a2 2 0 002-2v-3M16 3h5v5M8 16L21 3" /></svg>
                                    {{ __('legal_aid.copy_from_phone') }}
                                </button>
                                <p id="shop-whatsapp-error" class="field-error hidden"></p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_call_time') }} <span class="text-red-500">*</span></label>
                                <div class="relative" id="call-time-dropdown">
                                    <button type="button" id="call-time-btn" aria-haspopup="listbox" aria-expanded="false"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-[9px] text-left text-sm text-gray-900 outline-none transition-all hover:border-gray-300 hover:bg-white focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500">
                                        <span id="call-time-label" class="truncate text-gray-400">{{ __('legal_aid.call_time_placeholder') }}</span>
                                        <svg id="call-time-chevron" class="pointer-events-none h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                    <input type="hidden" id="shop-call-time" name="call_time" required>
                                    <ul id="call-time-options" class="absolute left-0 z-20 mt-2 hidden max-h-56 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-xl shadow-gray-200/50">
                                        <li data-value="" class="cursor-pointer px-3 py-2 text-center text-sm text-gray-400 hover:bg-blue-50 hover:text-blue-600">{{ __('legal_aid.call_time_placeholder') }}</li>
                                        <li data-value="09:00-09:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">09:00 - 09:30</li>
                                        <li data-value="09:30-10:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">09:30 - 10:00</li>
                                        <li data-value="10:00-10:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">10:00 - 10:30</li>
                                        <li data-value="10:30-11:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">10:30 - 11:00</li>
                                        <li data-value="11:00-11:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">11:00 - 11:30</li>
                                        <li data-value="11:30-12:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">11:30 - 12:00</li>
                                        <li data-value="12:00-12:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">12:00 - 12:30</li>
                                        <li data-value="12:30-13:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">12:30 - 13:00</li>
                                        <li data-value="13:00-13:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">13:00 - 13:30</li>
                                        <li data-value="13:30-14:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">13:30 - 14:00</li>
                                        <li data-value="14:00-14:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">14:00 - 14:30</li>
                                        <li data-value="14:30-15:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">14:30 - 15:00</li>
                                        <li data-value="15:00-15:30" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">15:00 - 15:30</li>
                                        <li data-value="15:30-16:00" class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">15:30 - 16:00</li>
                                    </ul>
                                </div>
                                <p id="shop-call-time-error" class="field-error hidden"></p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_case') }} <span class="text-red-500">*</span></label>
                            <textarea id="shop-case-description" name="case_description" rows="4" minlength="100" required
                                class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 outline-none placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                placeholder="{{ __('legal_aid.field_case_placeholder') }}"></textarea>
                            <p class="mt-1 text-xs text-gray-400">{{ __('legal_aid.case_min_hint') }}</p>
                            <p id="shop-case-description-error" class="field-error hidden"></p>
                        </div>

                        {{-- <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <div class="flex gap-3">
                                <svg class="h-5 w-5 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-blue-900">{{ __('shop.cin_is_ticket') }}</p>
                                    <p class="mt-1 text-xs leading-relaxed text-blue-700">{{ __('shop.cin_is_ticket_desc') }} {{ __('legal_aid.field_phone_help') }}</p>
                                </div>
                            </div>
                        </div> --}}
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-between" data-animate="fade-up">
                    <a href="{{ route('legal-aid') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        {{ __('shop.continue_shopping') }}
                    </a>
                    <button type="button" id="shop-cart-checkout-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg id="shop-cart-checkout-spinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span id="shop-cart-checkout-label">{{ __('shop.proceed_checkout') }}</span>
                    </button>
                </div>
                <p class="mt-3 text-center text-xs text-gray-400 flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    {{ __('shop.secure_note') }}
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    window.ShopCartConfig = {
        checkoutUrl: @json(route('legal-aid.checkout.create')),
        csrfToken: @json(csrf_token()),
        messages: {
            cartEmpty: @json(__('shop.cart_empty')),
            networkError: @json(__('shop.network_error')),
            genericError: @json(__('shop.payment_generic_error')),
            phoneInvalid: @json(__('legal_aid.phone_invalid')),
            whatsappInvalid: @json(__('legal_aid.whatsapp_invalid')),
            cinInvalid: @json(__('shop.cin_invalid')),
        }
    };
</script>
@vite(['resources/js/shop.js'])
@endpush
