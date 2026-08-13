@extends('layouts.admin')

@section('title', $service->exists ? 'Edit Service' : 'New Service')

@section('page-title')
{{ $service->exists ? 'Edit Service' : 'New Service' }}
@endsection

@section('page-description')
{{ $service->exists ? 'Update the service details and price.' : 'Add a new service and its price.' }}
@endsection

@section('content')

<div class="mx-auto max-w-4xl">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <form action="{{ $service->exists ? route('admin.services.update', $service->id) : route('admin.services.store') }}"
              method="POST" class="p-6 space-y-6">
            @csrf
            @if($service->exists)
                @method('PUT')
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach(['en', 'fr', 'ar'] as $locale)
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-slate-900 text-white">{{ $locale }}</span>
                        <span class="text-xs text-slate-400 font-medium">{{ $locale === 'en' ? 'English' : ($locale === 'fr' ? 'French' : 'Arabic') }}</span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Name ({{ strtoupper($locale) }})</label>
                            <input type="text" name="name_{{ $locale }}" value="{{ old('name_' . $locale, $service->{'name_' . $locale}) }}"
                                   required maxlength="255"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description ({{ strtoupper($locale) }})</label>
                            <textarea name="description_{{ $locale }}" rows="1"
                                      class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">{{ old('description_' . $locale, $service->{'description_' . $locale}) }}</textarea>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="sm:max-w-xs">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Price (MAD)</label>
                <input type="number" name="price" value="{{ old('price', $service->price) }}" step="0.01" min="0"
                       required
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('admin.services.index') }}"
                   class="text-sm font-semibold px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit"
                        class="text-sm font-semibold px-4 py-2 rounded-lg shadow-sm bg-slate-900 hover:bg-slate-800 text-white transition">
                    {{ $service->exists ? 'Update Service' : 'Create Service' }}
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
