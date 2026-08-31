@extends('layouts.app')

@section('title', __('shop.cancel_title') . ' | MarocLoi')

@section('content')
<section class="bg-gray-50 py-16">
    <div class="container-page">
        <div class="max-w-xl mx-auto">
            <div class="text-center mb-8" data-animate="fade-up">
                <span class="section-label">{{ __('shop.badge') }}</span>
                <h1 class="section-title mt-4">{{ __('shop.cancel_title') }}</h1>
            </div>

            <div class="card p-8 text-center" data-animate="fade-up">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="mt-4 text-lg font-bold text-gray-900">{{ __('shop.payment_cancelled') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ __('shop.payment_cancelled_desc') }}</p>

                @if ($order->items->isNotEmpty())
                    <div class="mt-6 text-left rounded-xl bg-gray-50 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ __('shop.order_summary') }}</h3>
                        <div class="mt-3 space-y-2">
                            @foreach ($order->items as $item)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700">{{ $item->service->name ?? $item->stripe_price_id }} <span class="text-gray-400">× {{ $item->quantity }}</span></span>
                                    <span class="font-semibold text-gray-900">{{ number_format($item->line_total_cents / 100, 0) }} MAD</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="mt-6 text-xs text-gray-400">{{ __('shop.cart_kept') }}</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a href="{{ route('legal-aid.cart') }}" class="btn-primary inline-flex justify-center">{{ __('shop.return_checkout') }}</a>
                    <a href="{{ route('legal-aid') }}" class="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('shop.continue_shopping') }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
