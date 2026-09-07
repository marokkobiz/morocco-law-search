@extends('layouts.admin')

@section('title', 'Order ' . $order->ticketLabel)

@section('page-title')
    Order {{ $order->ticketLabel }}
@endsection

@section('page-description')
    CIN is Ticket Number • {{ $order->email }} • {{ $order->phone }}
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 text-sm sm:grid-cols-2">
                <div><span class="text-slate-500">Ticket / CIN:</span> <span
                        class="font-mono font-bold">{{ $order->ticket_number }}</span></div>
                <div><span class="text-slate-500">Status:</span> <span
                        class="font-semibold capitalize">{{ $order->status }}</span>
                    @if ($order->paid_at)
                        <span class="text-xs text-slate-400">paid {{ $order->paid_at->format('d M Y H:i') }}</span>
                    @endif
                </div>
                <div><span class="text-slate-500">Customer:</span> {{ $order->full_name }} ({{ $order->email }})</div>
                <div><span class="text-slate-500">Phone:</span> {{ $order->phone }} @if ($order->whatsapp)
                        / WA {{ $order->whatsapp }}
                    @endif
                </div>
                @if ($order->case_description)
                    <div class="min-w-0 sm:col-span-2"><span class="text-slate-500">Case:</span><p class="mt-1 max-w-full whitespace-pre-wrap break-words rounded-lg border bg-slate-50 p-3 text-slate-700 [overflow-wrap:anywhere] [word-break:break-word]">{{ $order->case_description }}</p></div>
                @endif
                <div><span class="text-slate-500">Call time:</span> {{ $order->call_time ?: '—' }}</div>
                <div><span class="text-slate-500">Total:</span> <span
                        class="font-bold">{{ number_format($order->total_cents / 100, 0) }}
                        {{ strtoupper($order->currency) }}</span></div>
                {{-- <div><span class="text-slate-500">Stripe Session:</span> <span class="font-mono text-xs">{{ $order->stripe_checkout_session_id ?: '—' }}</span></div>
            <div><span class="text-slate-500">Stripe Intent:</span> <span class="font-mono text-xs">{{ $order->stripe_payment_intent_id ?: '—' }}</span></div> --}}
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h3 class="text-sm font-bold text-slate-900">Items</h3>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Service</th>
                        <th class="px-6 py-3">Price</th>
                        <th class="px-6 py-3 text-center">Qty</th>
                        <th class="px-6 py-3 text-right">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-6 py-3">
                                <div class="font-semibold">{{ $item->service->name ?? $item->stripe_price_id }}</div>
                                <div class="text-xs text-slate-500">{{ $item->service->description ?? '' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-3">{{ number_format($item->unit_amount_cents / 100, 0) }} MAD
                            </td>
                            <td class="px-6 py-3 text-center">{{ $item->quantity }}</td>
                            <td class="px-6 py-3 text-right font-semibold">
                                {{ number_format($item->line_total_cents / 100, 0) }} MAD</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <a href="{{ route('admin.orders.index') }}"
            class="inline-flex text-sm font-semibold text-blue-600 hover:text-blue-700">← Back to orders</a>
    </div>
@endsection
