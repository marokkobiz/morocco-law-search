@extends('layouts.app')

@section('title', __('shop.success_title') . ' | MarocLoi')

@section('content')
<section class="bg-gray-50 py-16">
    <div class="container-page">
        <div class="max-w-xl mx-auto">
            <div class="text-center mb-8" data-animate="fade-up">
                <span class="section-label">{{ __('shop.badge') }}</span>
                <h1 class="section-title mt-4">{{ __('shop.success_title') }}</h1>
            </div>

            <div class="card p-8 text-center" data-animate="fade-up">
                @if ($verified ?? false)
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="mt-4 text-lg font-bold text-gray-900">{{ __('shop.payment_success') }}</h2>
                    <p class="mt-2 text-sm text-gray-500">{{ __('shop.thank_you') }}</p>

                    <div class="mt-6 rounded-xl border border-green-200 bg-green-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-green-700">{{ __('shop.ticket_number') }}</p>
                        <p class="mt-1 text-2xl font-black tracking-wider text-green-900">{{ $order->ticket_number }}</p>
                        <p class="mt-1 text-xs text-green-700">{{ __('shop.cin_is_ticket') }}</p>
                    </div>

                    <div class="mt-6 text-left rounded-xl bg-gray-50 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ __('shop.order_summary') }}</h3>
                        <div class="mt-3 space-y-2">
                            @foreach ($order->items as $item)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700">{{ $item->service->name ?? $item->stripe_price_id }} <span class="text-gray-400">× {{ $item->quantity }}</span></span>
                                    <span class="font-semibold text-gray-900">{{ number_format($item->line_total_cents / 100, 0) }} MAD</span>
                                </div>
                            @endforeach
                            <div class="border-t border-gray-200 pt-2 flex justify-between text-sm font-bold">
                                <span class="text-gray-900">{{ __('shop.total') }}</span>
                                <span class="text-gray-900">{{ number_format($order->total_cents / 100, 0) }} MAD</span>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">{{ __('shop.payment_status') }}: <span class="font-semibold text-green-700">{{ __('shop.paid') }}</span></p>
                    </div>

                    <p class="mt-6 text-sm text-gray-500">
                        {{ __('shop.confirmation_email_sent', ['email' => $order->email]) }}
                    </p>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <a href="{{ route('legal-aid') }}" class="btn-primary inline-flex justify-center">{{ __('shop.continue_shopping') }}</a>
                        <a href="{{ route('landing') }}" class="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('shop.back_home') }}</a>
                    </div>

                    <script>
                        // Clear cart on successful payment
                        try { localStorage.removeItem('marocloi_shop_cart'); } catch(e) {}
                    </script>
                @elseif ($pending ?? false)
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                        <svg class="h-6 w-6 text-amber-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="mt-4 text-lg font-bold text-gray-900">{{ __('shop.payment_pending') }}</h2>
                    <p class="mt-2 text-sm text-gray-500">{{ __('shop.payment_pending_desc') }}</p>
                    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('shop.ticket_number') }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ $order->ticket_number }}</p>
                    </div>
                    <p class="mt-4 text-xs text-gray-400">{{ __('shop.webhook_note') }}</p>
                    <a href="{{ route('legal-aid') }}" class="btn-primary mt-6 inline-flex">{{ __('shop.continue_shopping') }}</a>
                @else
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <h2 class="mt-4 text-lg font-bold text-gray-900">{{ __('shop.payment_issue') }}</h2>
                    <p class="mt-2 text-sm text-gray-500">{{ $error ?? __('shop.payment_generic_error') }}</p>
                    <div class="mt-6 flex justify-center gap-3">
                        <a href="{{ route('legal-aid.cart') }}" class="btn-primary inline-flex">{{ __('shop.try_again') }}</a>
                        <a href="{{ route('legal-aid') }}" class="inline-flex rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700">{{ __('shop.continue_shopping') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
