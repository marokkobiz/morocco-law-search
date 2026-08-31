@extends('layouts.admin')

@section('title', 'Dashboard')

@section('page-title')
Admin Dashboard
@endsection

@section('page-description')
Monitor Stripe orders and revenue.
@endsection

@section('content')

<!-- Stat Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pending Orders</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $pendingOrders }}</h2>
            <p class="text-xs text-slate-500 mt-1">Awaiting Stripe payment</p>
        </div>
        <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Paid Orders</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $paidOrders }}</h2>
            <p class="text-xs text-slate-500 mt-1">{{ number_format($totalRevenueCents / 100, 0) }} MAD total revenue</p>
        </div>
        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registered Users</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $totalUsers }}</h2>
        </div>
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
    </div>

</div>

<!-- Recent Orders -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Recent Orders</h2>
            <p class="text-xs text-slate-500 mt-0.5">Latest Stripe orders.</p>
        </div>
        <a href="{{ route('admin.orders.index') }}"
           class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">
            View all →
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Ticket / CIN</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Total</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($recentOrders as $order)
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.orders.show', $order) }}"
                           class="font-bold text-slate-900 font-mono hover:text-blue-600 hover:underline transition">
                            {{ $order->ticketLabel }}
                        </a>
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.orders.show', $order) }}"
                           class="font-semibold text-slate-900 hover:text-blue-600 hover:underline transition">
                            {{ $order->full_name ?: '—' }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $order->email }}</div>
                    </td>
                    <td class="px-6 py-4 font-semibold whitespace-nowrap">{{ number_format($order->total_cents / 100, 0) }} {{ strtoupper($order->currency) }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $order->status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($order->status === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-600 border-slate-200') }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">{{ $order->created_at?->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400 italic">
                        No orders yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
