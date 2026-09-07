@extends('layouts.admin')

@section('title', 'Orders')

@section('page-title')
Orders
@endsection

@section('page-description')
Manage shop orders. CIN is Ticket Number.
@endsection

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[900px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Ticket / CIN</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Phone</th>
                    <th class="px-6 py-4">Total</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Date</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($orders as $order)
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 font-mono font-bold text-slate-900">{{ $order->ticket_number }}</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">{{ $order->full_name ?: '—' }}</div>
                        <div class="text-xs text-slate-500">{{ $order->email }}</div>
                    </td>
                    <td class="px-6 py-4 text-xs">
                        <div>{{ $order->phone ?: '—' }}</div>
                        @if($order->whatsapp)<div class="text-green-700">WA: {{ $order->whatsapp }}</div>@endif
                    </td>
                    <td class="px-6 py-4 font-semibold whitespace-nowrap">{{ number_format($order->total_cents/100,0) }} {{ strtoupper($order->currency) }}</td>
                    <td class="px-6 py-4">
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold border {{ $order->status==='paid' ? 'bg-green-50 text-green-700 border-green-200' : ($order->status==='pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-gray-50 text-gray-600 border-gray-200') }}">{{ ucfirst($order->status) }}</span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">{{ $order->created_at?->format('d M Y H:i') }}</td>
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('admin.orders.show', $order) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border bg-white hover:bg-blue-50 text-blue-600 border-blue-200">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400 italic">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="border-t border-slate-200 px-6 py-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
