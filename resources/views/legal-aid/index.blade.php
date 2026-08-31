@extends('layouts.app')

@section('title', __('shop.title') . ' | MarocLoi')

@push('styles')
<style>
    #shop-cart-badge {
        transition: transform .15s ease;
    }
    #shop-cart-badge.bump {
        transform: scale(1.15);
    }
</style>
@endpush

@section('content')
<section class="bg-gray-50 py-10 sm:py-16">
    <div class="container-page">
        <div class="mx-auto mb-10 max-w-3xl text-center" data-animate="fade-up">
            <span class="section-label">{{ __('shop.badge') }}</span>
            <h1 class="section-title mt-4">{{ __('shop.title') }}</h1>
            <p class="section-desc mx-auto mt-4">{{ __('shop.subtitle') }}</p>
        </div>

        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex items-center justify-between gap-4">
                <p class="text-sm text-gray-500">{{ __('shop.products_desc') }}</p>
                <a href="{{ route('legal-aid.cart') }}" id="shop-cart-link" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>{{ __('shop.view_cart') }}</span>
                    <span id="shop-cart-badge" class="ml-1 hidden rounded-full bg-blue-500 px-2 py-0.5 text-xs font-bold text-white">0</span>
                </a>
            </div>

            @if ($services->isEmpty())
                <div class="card p-12 text-center">
                    <p class="text-sm text-gray-500">{{ __('shop.no_products') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $service)
                        <div class="card flex flex-col overflow-hidden p-0" data-animate="fade-up">
                            <div class="flex flex-1 flex-col p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-sm font-bold text-gray-900">{{ $service->name }}</h3>
                                </div>
                                @if ($service->description)
                                    <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ $service->description }}</p>
                                @endif
                                @if ($service->notes)
                                    <p class="mt-2 text-xs text-gray-500">• {{ $service->notes }}</p>
                                @endif
                                @if ($service->additional_notes)
                                    <p class="text-xs text-gray-500">• {{ $service->additional_notes }}</p>
                                @endif
                                <div class="mt-4 flex items-end justify-between gap-3">
                                    <span class="text-base font-bold text-blue-600">{{ $service->priceLabel }}</span>
                                </div>
                            </div>
                            <div class="border-t border-gray-100 bg-gray-50 px-6 py-3">
                                @if ((float) $service->price === 0.0)
                                    <button disabled class="w-full rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-500 cursor-not-allowed">{{ __('shop.free_product') }}</button>
                                @elseif (!$service->stripe_price_id)
                                    <button disabled class="w-full rounded-xl bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-800 cursor-not-allowed" title="{{ __('shop.not_available') }}">{{ __('shop.not_available') }}</button>
                                @else
                                    <button type="button"
                                        class="shop-add-btn w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                                        data-service-id="{{ $service->id }}"
                                        data-price-id="{{ $service->stripe_price_id }}"
                                        data-name="{{ $service->name }}"
                                        data-price="{{ (float) $service->price }}"
                                        data-price-label="{{ $service->priceLabel }}"
                                    >{{ __('shop.add_to_cart') }}</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Mini cart summary (fixed bottom on mobile, sidebar on desktop) -->
            {{-- <div id="shop-mini-cart" class="mt-8 hidden card p-6">
                <h2 class="text-sm font-bold text-gray-900">{{ __('shop.cart') }}</h2>
                <div id="shop-mini-cart-items" class="mt-4 space-y-3"></div>
                <div id="shop-mini-cart-total" class="mt-4 border-t border-gray-200 pt-4 flex items-center justify-between">
                    <span class="text-sm font-bold text-gray-900">{{ __('shop.total') }}</span>
                    <span id="shop-mini-cart-total-value" class="text-base font-bold text-gray-900">0 MAD</span>
                </div>
                <div class="mt-4 flex gap-3">
                    <a href="{{ route('legal-aid.cart') }}" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('shop.view_cart') }}</a>
                    <a href="{{ route('legal-aid.cart') }}" class="flex-1 rounded-xl bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-500">{{ __('shop.checkout') }}</a>
                </div>
            </div> --}}
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    window.ShopProducts = @json($shopProducts);
</script>
@vite(['resources/js/shop.js'])
@endpush
