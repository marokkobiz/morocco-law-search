@extends('layouts.app')

@section('title', __('legal_aid.badge') . ' | MarocLoi')

@push('styles')
    <style>
        #call-time-options::-webkit-scrollbar {
            width: 6px;
        }

        #call-time-options::-webkit-scrollbar-track {
            background: transparent;
        }

        #call-time-options::-webkit-scrollbar-thumb {
            background: #e5e7eb;
            border-radius: 9999px;
        }

        #call-time-options::-webkit-scrollbar-thumb:hover {
            background: #d1d5db;
        }

        #call-time-options {
            scrollbar-width: thin;
            scrollbar-color: #e5e7eb transparent;
        }
    </style>
@endpush

@section('content')
    <section class="bg-gray-50 py-16">
        <div class="container-page">
            <div class="mx-auto mb-12 max-w-3xl text-center" data-animate="fade-up">
                <span class="section-label">{{ __('legal_aid.badge') }}</span>
                <h1 class="section-title mt-4">{{ __('legal_aid.title') }}</h1>
                <p class="section-desc mx-auto mt-4">{!! __('legal_aid.subtitle') !!}</p>
            </div>

            <div class="mx-auto max-w-3xl">
                <div class="card p-8" data-animate="fade-up" style="--delay:.2s">
                    <h2 class="mb-1 text-center text-xl font-bold text-gray-900">{{ __('legal_aid.form_title') }}</h2>
                    <p class="mb-6 text-center text-sm text-gray-500">{{ __('legal_aid.form_desc') }}</p>

                    @if (session('confirmation_sent'))
                        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                            <p>{{ __('legal_aid.confirmation_sent', ['email' => session('confirmation_sent')]) }}</p>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form class="space-y-6" action="{{ route('legal-aid.store') }}" method="POST">
                        @csrf

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_name') }}</label>
                                    <input type="text" name="full_name" value="{{ old('full_name') }}" required
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                        placeholder="{{ __('legal_aid.field_name_placeholder') }}">
                                </div>

                                <div>
                                    <label class="mb-1.5 flex gap-2 text-sm font-semibold text-gray-700">
                                        {{ __('legal_aid.field_email') }}

                                        <p class="mt-1 text-xs text-gray-400">{{ __('legal_aid.field_email_help') }}</p>
                                    </label>
                                    <input type="email" name="email" value="{{ old('email') }}" required
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                        placeholder="{{ __('legal_aid.field_email_placeholder') }}">

                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-gray-700">
                                        {{ __('legal_aid.field_phone') }}
                                        <span class="group relative inline-flex">
                                            <svg class="h-4 w-4 cursor-help text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-lg group-hover:block">
                                                {{ __('legal_aid.field_phone_help') }}
                                            </span>
                                        </span>
                                    </label>
                                    <input type="tel" name="phone" value="{{ old('phone') }}" required
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                        placeholder="{{ __('legal_aid.field_phone_placeholder') }}">
                                </div>

                                <div>
                                    <label class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-gray-700">
                                        {{ __('legal_aid.field_whatsapp') }}
                                        <span class="group relative inline-flex">
                                            <svg class="h-4 w-4 cursor-help text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-lg group-hover:block">
                                                {{ __('legal_aid.field_whatsapp_help') }}
                                            </span>
                                        </span>
                                    </label>
                                    <input type="tel" name="whatsapp" value="{{ old('whatsapp') }}"
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                        placeholder="{{ __('legal_aid.field_whatsapp_placeholder') }}">
                                    <button type="button" id="copy-phone-to-whatsapp"
                                        class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 transition-colors hover:text-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                                        title="{{ __('legal_aid.copy_from_phone_hint') }}">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 8H5a2 2 0 00-2 2v9a2 2 0 002 2h10a2 2 0 002-2v-3M16 3h5v5M8 16L21 3" />
                                        </svg>
                                        {{ __('legal_aid.copy_from_phone') }}
                                    </button>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_call_time') }}</label>
                                    <div class="relative" id="call-time-dropdown">
                                        <button type="button" id="call-time-btn" aria-haspopup="listbox"
                                            aria-expanded="false"
                                            class="relative flex w-full items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-8 py-1.5 text-center text-sm text-gray-900 outline-none transition-colors focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500">
                                            <span id="call-time-label"
                                                class="truncate text-center tabular-nums text-gray-400">{{ __('legal_aid.call_time_placeholder') }}</span>
                                            <svg id="call-time-chevron"
                                                class="pointer-events-none absolute right-3 h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <input type="hidden" name="call_time" id="call-time-value" required>
                                        <ul id="call-time-options"
                                            class="absolute left-0 z-20 mt-2 hidden max-h-56 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-xl shadow-gray-200/50">
                                            <li data-value=""
                                                class="cursor-pointer px-3 py-2 text-center text-sm text-gray-400 hover:bg-blue-50 hover:text-blue-600">
                                                {{ __('legal_aid.call_time_placeholder') }}</li>
                                            <li data-value="09:00-09:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                09:00 - 09:30</li>
                                            <li data-value="09:30-10:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                09:30 - 10:00</li>
                                            <li data-value="10:00-10:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                10:00 - 10:30</li>
                                            <li data-value="10:30-11:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                10:30 - 11:00</li>
                                            <li data-value="11:00-11:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                11:00 - 11:30</li>
                                            <li data-value="11:30-12:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                11:30 - 12:00</li>
                                            <li data-value="12:00-12:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                12:00 - 12:30</li>
                                            <li data-value="12:30-13:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                12:30 - 13:00</li>
                                            <li data-value="13:00-13:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                13:00 - 13:30</li>
                                            <li data-value="13:30-14:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                13:30 - 14:00</li>
                                            <li data-value="14:00-14:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                14:00 - 14:30</li>
                                            <li data-value="14:30-15:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                14:30 - 15:00</li>
                                            <li data-value="15:00-15:30"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                15:00 - 15:30</li>
                                            <li data-value="15:30-16:00"
                                                class="cursor-pointer px-3 py-2 text-center font-mono text-sm tabular-nums tracking-wide text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                15:30 - 16:00</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_case') }}</label>
                                <textarea name="case_description" rows="4" minlength="100" required
                                    class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                    placeholder="{{ __('legal_aid.field_case_placeholder') }}">{{ old('case_description') }}</textarea>
                                <p class="mt-1 text-xs text-gray-400">{{ __('legal_aid.case_min_hint') }}</p>
                            </div>
                        </div>


                        <div>
                            <label
                                class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_service') }}</label>
                            <div class="grid items-stretch gap-3 sm:grid-cols-2">
                                @forelse ($services as $service)
                                    <label
                                        class="flex h-full cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}"
                                            class="mt-0.5 h-4 w-4 shrink-0 accent-blue-600"
                                            data-modes="{{ $service->consultationModesList }}"
                                            data-initial="{{ $service->name_en === 'Initial interview (case content) 30 min.' ? '1' : '0' }}"
                                            {{ in_array($service->id, old('service_ids', []), true) ? 'checked' : '' }}>
                                        <span class="flex min-h-full flex-1 flex-col">
                                            <span class="text-sm font-semibold text-gray-900">{{ $service->name }}</span>
                                            @if ($service->description)
                                                <span
                                                    class="mt-0.5 text-xs text-gray-500">{{ $service->description }}</span>
                                            @endif
                                            <span class="space-y-0.5 pt-2">
                                                @if ($service->notes)
                                                    <span class="block text-xs text-gray-500">•
                                                        {{ $service->notes }}</span>
                                                @endif
                                                @if ($service->additional_notes)
                                                    <span class="block text-xs text-gray-500">•
                                                        {{ $service->additional_notes }}</span>
                                                @endif
                                            </span>
                                        </span>
                                        <span
                                            class="whitespace-nowrap text-sm font-bold text-blue-600">{{ $service->priceLabel }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-gray-500 sm:col-span-2">{{ __('legal_aid.no_services') }}</p>
                                @endforelse
                            </div>
                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                <p>{{ __('legal_aid.price_list_note') }}</p>
                                <p>{{ __('legal_aid.price_list_asterisk') }}</p>
                            </div>
                            @error('service_ids')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="consultation-section" class="{{ !empty(old('service_ids')) ? '' : 'hidden' }}">
                            <input type="hidden" name="consultation_mode" value="whatsapp"
                                id="consultation_mode_hidden">

                            <div id="consultation-question">
                                <label
                                    class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_consultation') }}</label>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label id="mode-office-wrap"
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                                        <input type="radio" name="consultation_mode_choice" value="office"
                                            class="sr-only" {{ old('consultation_mode') === 'office' ? 'checked' : '' }}>
                                        <svg class="h-5 w-5 shrink-0 text-blue-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 21h18M4 21V8a2 2 0 012-2h12a2 2 0 012 2v13M9 21v-5h6v5M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2">
                                            </path>
                                        </svg>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ __('legal_aid.consultation_office') }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500">
                                                {{ __('legal_aid.consultation_office_desc') }}</p>
                                        </div>
                                    </label>

                                    <label id="mode-whatsapp-wrap"
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition-colors has-[:checked]:border-green-500 has-[:checked]:bg-green-50/50">
                                        <input type="radio" name="consultation_mode_choice" value="whatsapp"
                                            class="sr-only"
                                            {{ old('consultation_mode') === 'whatsapp' ? 'checked' : '' }}>
                                        <svg class="h-5 w-5 shrink-0 text-green-600" fill="currentColor"
                                            viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z">
                                            </path>
                                        </svg>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ __('legal_aid.consultation_whatsapp') }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500">
                                                {{ __('legal_aid.consultation_whatsapp_desc') }}</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div id="consultation-office-only" class="hidden">
                                <div class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 21h18M4 21V8a2 2 0 012-2h12a2 2 0 012 2v13M9 21v-5h6v5M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2">
                                        </path>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ __('legal_aid.consultation_office') }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ __('legal_aid.consultation_office_only') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div id="consultation-whatsapp-only" class="hidden">
                                <div
                                    class="flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-600" fill="currentColor"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z">
                                        </path>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ __('legal_aid.consultation_whatsapp') }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ __('legal_aid.consultation_whatsapp_only') }}</p>
                                    </div>
                                </div>
                            </div>

                            @error('consultation_mode')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <input type="hidden" name="payment_method" value="stripe">
                        <div id="payment-section" class="rounded-xl border border-green-200 bg-green-50/50 p-4">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-600" fill="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2H4zm16 3H4v2h16V7zm0 4H4v7h16v-7z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-semibold leading-tight text-gray-900">
                                            {{ __('legal_aid.payment_method_stripe') }}</p>
                                        <span
                                            class="inline-flex shrink-0 whitespace-nowrap rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold leading-none text-green-700">
                                            {{ __('legal_aid.payment_method_stripe_discount', ['percent' => (int) config('legal_aid.online_discount_percent')]) }}
                                        </span>
                                    </div>
                                    {{-- <p class="mt-1 text-xs leading-relaxed text-gray-500">
                                        {{ __('legal_aid.payment_method_stripe_desc') }}</p> --}}
                                    <p class="mt-3 text-xs leading-relaxed text-gray-500">
                                        {{ __('legal_aid.payment_method_stripe_desc') }} —
                                        {{ __('legal_aid.payment_secure_note') }}</p>
                                </div>
                            </div>
                            <img src="{{ asset('images/cards.png') }}" alt="Visa, Mastercard, etc."
                                class="ml-6 mt-3 h-16 w-auto max-w-[160px] object-contain">
                        </div>

                        <button type="submit" class="btn-primary w-full">
                            {{ __('legal_aid.submit') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function() {
            const section = document.getElementById('consultation-section');
            if (!section) return;

            const serviceBoxes = Array.from(document.querySelectorAll('input[name="service_ids[]"]'));
            const modeWraps = {
                office: document.getElementById('mode-office-wrap'),
                whatsapp: document.getElementById('mode-whatsapp-wrap'),
            };
            const modeRadios = Array.from(document.querySelectorAll('input[name="consultation_mode_choice"]'));
            const questionDiv = document.getElementById('consultation-question');
            const officeOnlyDiv = document.getElementById('consultation-office-only');
            const whatsappOnlyDiv = document.getElementById('consultation-whatsapp-only');
            const hiddenInput = document.getElementById('consultation_mode_hidden');
            const singleBanners = {
                office: officeOnlyDiv,
                whatsapp: whatsappOnlyDiv
            };

            function setConsultationMode(mode) {
                if (hiddenInput) hiddenInput.value = mode;
            }

            function updateConsultationModes() {
                const checked = serviceBoxes.filter((box) => box.checked);

                if (checked.length === 0) {
                    section.classList.add('hidden');
                    setConsultationMode('whatsapp');
                    return;
                }

                // Rule: only Initial interview alone = WhatsApp, everything else = Office
                const hasInitial = checked.some((box) => box.dataset.initial === '1');
                const onlyInitial = hasInitial && checked.length === 1 && checked[0].dataset.initial === '1';

                if (onlyInitial) {
                    section.classList.remove('hidden');
                    if (questionDiv) questionDiv.classList.add('hidden');
                    if (officeOnlyDiv) officeOnlyDiv.classList.add('hidden');
                    if (whatsappOnlyDiv) whatsappOnlyDiv.classList.remove('hidden');
                    modeRadios.forEach((r) => {
                        r.checked = false;
                    });
                    setConsultationMode('whatsapp');
                    return;
                }

                // Any other selection (including Tracking/Submission/Participation without Initial) = Office only
                section.classList.remove('hidden');
                if (questionDiv) questionDiv.classList.add('hidden');
                if (whatsappOnlyDiv) whatsappOnlyDiv.classList.add('hidden');
                if (officeOnlyDiv) officeOnlyDiv.classList.remove('hidden');
                modeRadios.forEach((r) => {
                    r.checked = false;
                });
                setConsultationMode('office');
            }

            modeRadios.forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (radio.checked) setConsultationMode(radio.value);
                });
            });

            serviceBoxes.forEach((box) => box.addEventListener('change', updateConsultationModes));
            updateConsultationModes();
        })();
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var phoneInput = document.getElementsByName("phone")[0] || null;
            var whatsBtn = document.getElementById("copy-phone-to-whatsapp");
            var whatsInput = document.getElementsByName("whatsapp")[0] || null;

            function stripNonNumeric(el) {
                var raw = el.value;
                var cleaned = raw.replace(/[^0-9+]/g, '').replace(/(?!^)\+/g, '');
                if (cleaned !== raw) el.value = cleaned;
            }

            if (phoneInput) phoneInput.addEventListener("input", function() {
                stripNonNumeric(this);
            });
            if (whatsInput) whatsInput.addEventListener("input", function() {
                stripNonNumeric(this);
            });

            if (!phoneInput || !whatsBtn || !whatsInput) return;
            var sync = function() {
                whatsBtn.disabled = !phoneInput.value;
            };
            phoneInput.addEventListener("input", sync);
            whatsBtn.addEventListener("click", function() {
                whatsInput.value = phoneInput.value;
                sync();
            });
            sync();

            var ctBtn = document.getElementById("call-time-btn");
            var ctOptions = document.getElementById("call-time-options");
            var ctValue = document.getElementById("call-time-value");
            var ctLabel = document.getElementById("call-time-label");
            var ctChevron = document.getElementById("call-time-chevron");
            if (ctBtn && ctOptions) {
                function setCallTimeOpen(open) {
                    ctOptions.classList.toggle("hidden", !open);
                    ctBtn.setAttribute("aria-expanded", open ? "true" : "false");
                    if (ctChevron) ctChevron.style.transform = open ? "rotate(180deg)" : "";
                    ctBtn.classList.toggle("border-blue-500", open);
                    ctBtn.classList.toggle("bg-white", open);
                    ctBtn.classList.toggle("ring-1", open);
                    ctBtn.classList.toggle("ring-blue-500", open);
                }
                ctBtn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    var isHidden = ctOptions.classList.contains("hidden");
                    setCallTimeOpen(isHidden);
                });
                ctOptions.querySelectorAll("li").forEach(function(li) {
                    li.addEventListener("click", function() {
                        ctValue.value = this.dataset.value;
                        ctLabel.textContent = this.textContent;
                        ctLabel.classList.toggle("text-gray-400", !this.dataset.value);
                        ctLabel.classList.toggle("text-gray-900", !!this.dataset.value);
                        ctOptions.querySelectorAll("li").forEach(function(el) {
                            el.classList.remove("bg-blue-50", "text-blue-600",
                                "font-semibold");
                        });
                        if (this.dataset.value) this.classList.add("bg-blue-50", "text-blue-600",
                            "font-semibold");
                        setCallTimeOpen(false);
                        ctBtn.focus();
                    });
                });
                document.addEventListener("click", function() {
                    setCallTimeOpen(false);
                });
                document.addEventListener("keydown", function(e) {
                    if (e.key === "Escape") setCallTimeOpen(false);
                });
            }
        });
    </script>
@endpush
