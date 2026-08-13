@extends('layouts.admin')

@section('title', 'Services')

@section('page-title')
Services
@endsection

@section('page-description')
Manage legal aid services and their prices.
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
                    <th class="px-6 py-4">Service</th>
                    <th class="px-6 py-4">Price</th>
                    <th class="px-6 py-4">Google Pay Total (−{{ config('legal_aid.online_discount_percent') }}%)</th>
                    <th class="px-6 py-4">Bank Transfer Total (+{{ config('legal_aid.bank_admin_fee_percent') }}%)</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($services as $service)
                <tr class="hover:bg-slate-50/70 transition">
                    <!-- Service -->
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">{{ $service->name }}</div>
                        @if($service->description)
                            <div class="text-xs text-slate-500">{{ $service->description }}</div>
                        @endif
                    </td>

                    <!-- Price -->
                    <td class="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">
                        {{ $service->priceLabel }}
                    </td>

                    <!-- Google Total -->
                    <td class="px-6 py-4 text-emerald-700 whitespace-nowrap">
                        {{ number_format((float) $service->price * (1 - (float) config('legal_aid.online_discount_percent', 10) / 100), 0) }} MAD
                    </td>

                    <!-- Bank Total -->
                    <td class="px-6 py-4 text-rose-700 whitespace-nowrap">
                        {{ number_format((float) $service->price * (1 + (float) config('legal_aid.bank_admin_fee_percent', 10) / 100), 0) }} MAD
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
                    <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400 italic">
                        No services yet. Create your first service.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
