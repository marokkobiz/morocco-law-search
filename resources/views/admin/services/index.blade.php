@extends('layouts.admin')

@section('title', 'Services')

@section('page-title')
Services
@endsection

@section('page-description')
Manage legal aid services and their prices. Order controls the display on the booking form.
@endsection

@section('content')

<div class="mb-6 flex justify-end">
    <a href="{{ route('admin.services.create') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-lg shadow-sm bg-slate-900 hover:bg-slate-800 text-white transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Service
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[640px]">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 w-16 text-center">Order</th>
                    <th class="px-6 py-4">Service</th>
                    <th class="px-6 py-4">Price</th>
                    <th class="px-6 py-4">Stripe Price</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($services as $service)
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center justify-center min-w-8 h-7 px-2.5 rounded-full bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">{{ $service->sort_order }}</span>
                    </td>
                    <!-- Service -->
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">{{ $service->name }}</div>
                        @if($service->description)
                            <div class="text-xs text-slate-500">{{ $service->description }}</div>
                        @endif
                        @if($service->notes)
                            <div class="mt-0.5 text-xs text-slate-500">• {{ $service->notes }}</div>
                        @endif
                        @if($service->additional_notes)
                            <div class="mt-0.5 text-xs text-slate-500">• {{ $service->additional_notes }}</div>
                        @endif
                        @if($service->consultationModes)
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach($service->consultationModes as $mode)
                                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full {{ $mode === 'whatsapp' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                        {{ $mode === 'whatsapp' ? 'WhatsApp' : 'Office' }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <!-- Price -->
                    <td class="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">
                        {{ $service->priceLabel }}
                    </td>

                    <!-- Stripe -->
                    <td class="px-6 py-4 font-mono text-xs whitespace-nowrap">
                        @php $isFree = (float) $service->price < 0.50; @endphp
                        @if ($isFree)
                            @if ($service->stripe_product_id)
                                <span class="rounded bg-slate-50 px-2 py-1 text-slate-600 border border-slate-200">Free — no price</span>
                            @else
                                <span class="text-amber-600" title="Stripe Product not yet created. Will auto-sync via Observer, or run: php artisan services:sync-stripe --force">pending sync</span>
                            @endif
                        @elseif ($service->stripe_price_id)
                            <span class="rounded bg-green-50 px-2 py-1 text-green-700 border border-green-200">{{ $service->stripe_price_id }}</span>
                        @else
                            <span class="text-amber-600" title="Will auto-sync via Observer on save. If stuck, run: php artisan services:sync-stripe --force">pending sync</span>
                        @endif
                    </td>
                    <!-- Status -->
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if ($service->is_active)
                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-bold text-green-700 border border-green-200">Active</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-600 border border-gray-200">Hidden</span>
                        @endif
                    </td>

                    <!-- Actions -->
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('admin.services.edit', $service->id) }}"
                               class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-blue-50 text-blue-600 border-blue-200 hover:border-blue-300">
                                Edit
                            </a>
                            <form action="{{ route('admin.services.destroy', $service->id) }}" method="POST"
                                  class="inline" onsubmit="return confirm('Delete this service permanently?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-rose-50 text-rose-600 border-rose-200 hover:border-rose-300">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400 italic">
                        No services yet. Create your first service.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
