@extends('layouts.app')

@section('title', __('legal_aid.badge') . ' | MarocLoi')

@section('content')
    <section class="bg-gray-50 py-16">
        <div class="container-page">
            <div class="mx-auto mb-12 max-w-3xl text-center" data-animate="fade-up">
                <span class="section-label">{{ __('legal_aid.badge') }}</span>
                <h1 class="section-title mt-4">{{ __('legal_aid.title') }}</h1>
                <p class="section-desc mx-auto mt-4">{{ __('legal_aid.subtitle') }}</p>
            </div>

            <div class="mx-auto max-w-3xl">
                <div class="card p-8" data-animate="fade-up" style="--delay:.2s">
                    <h2 class="mb-1 text-center text-xl font-bold text-gray-900">{{ __('legal_aid.form_title') }}</h2>
                    <p class="mb-6 text-center text-sm text-gray-500">{{ __('legal_aid.form_desc') }}</p>

                    @if (session('ticket'))
                        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                            <p>{{ __('legal_aid.success_ticket', ['ticket' => session('ticket')]) }}</p>
                            @if (session('ticket_number'))
                                <a href="{{ route('legal-aid.ticket-pdf', session('ticket_number')) }}"
                                    class="mt-2 inline-flex items-center gap-1.5 font-semibold underline">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    {{ __('legal_aid.download_ticket') }}
                                </a>
                            @endif
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
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_name') }}</label>
                                <input type="text" name="full_name" value="{{ old('full_name') }}" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                    placeholder="{{ __('legal_aid.field_name_placeholder') }}">
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_email') }}</label>
                                <input type="email" name="email" value="{{ old('email') }}" required
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                    placeholder="{{ __('legal_aid.field_email_placeholder') }}">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
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
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
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
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                        placeholder="{{ __('legal_aid.field_whatsapp_placeholder') }}">
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_case') }}</label>
                                <textarea name="case_description" rows="4" required
                                    class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500"
                                    placeholder="{{ __('legal_aid.field_case_placeholder') }}">{{ old('case_description') }}</textarea>
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_service') }}</label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($services as $service)
                                    <label
                                        class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                                        <input type="radio" name="service_id" value="{{ $service->id }}"
                                            class="sr-only" required data-modes="{{ $service->consultationModesList }}"
                                            {{ old('service_id') == $service->id ? 'checked' : '' }}>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $service->name }}</p>
                                            @if ($service->description)
                                                <p class="mt-0.5 text-xs text-gray-500">{{ $service->description }}</p>
                                            @endif
                                            @if ($service->notes)
                                                <p class="mt-0.5 text-xs text-gray-500">• {{ $service->notes }}</p>
                                            @endif
                                            @if ($service->additional_notes)
                                                <p class="mt-0.5 text-xs text-gray-500">• {{ $service->additional_notes }}</p>
                                            @endif
                                        </div>
                                        <span
                                            class="whitespace-nowrap text-sm font-bold text-blue-600">{{ $service->priceLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                <p>{{ __('legal_aid.price_list_note') }}</p>
                                <p>{{ __('legal_aid.price_list_asterisk') }}</p>
                            </div>
                            @error('service_id')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="consultation-section" class="{{ old('service_id') ? '' : 'hidden' }}">
                            <label
                                class="mb-1.5 block text-sm font-semibold text-gray-700">{{ __('legal_aid.field_consultation') }}</label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label id="mode-office-wrap"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                                    <input type="radio" name="consultation_mode" value="office" class="sr-only"
                                        {{ old('consultation_mode') === 'office' ? 'checked' : '' }}>
                                    <svg class="h-5 w-5 shrink-0 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 21h18M4 21V8a2 2 0 012-2h12a2 2 0 012 2v13M9 21v-5h6v5M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.consultation_office') }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ __('legal_aid.consultation_office_desc') }}</p>
                                    </div>
                                </label>

                                <label id="mode-whatsapp-wrap"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition-colors has-[:checked]:border-green-500 has-[:checked]:bg-green-50/50">
                                    <input type="radio" name="consultation_mode" value="whatsapp" class="sr-only"
                                        {{ old('consultation_mode') === 'whatsapp' ? 'checked' : '' }}>
                                    <svg class="h-5 w-5 shrink-0 text-green-600" fill="currentColor" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path
                                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.consultation_whatsapp') }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ __('legal_aid.consultation_whatsapp_desc') }}</p>
                                    </div>
                                </label>
                            </div>
                            @error('consultation_mode')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
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
        (function () {
            const section = document.getElementById('consultation-section');
            if (!section) return;

            const serviceRadios = Array.from(document.querySelectorAll('input[name="service_id"]'));
            const modeWraps = {
                office: document.getElementById('mode-office-wrap'),
                whatsapp: document.getElementById('mode-whatsapp-wrap'),
            };
            const modeRadios = Array.from(document.querySelectorAll('input[name="consultation_mode"]'));

            function updateConsultationModes() {
                const selected = serviceRadios.find((radio) => radio.checked);
                if (!selected) {
                    section.classList.add('hidden');
                    return;
                }

                const allowed = (selected.dataset.modes || '').split(',').filter(Boolean);
                section.classList.toggle('hidden', allowed.length === 0);

                modeRadios.forEach((radio) => {
                    const wrap = modeWraps[radio.value];
                    const isAllowed = allowed.includes(radio.value);
                    if (wrap) wrap.classList.toggle('hidden', !isAllowed);
                    if (!isAllowed && radio.checked) radio.checked = false;
                });
            }

            serviceRadios.forEach((radio) => radio.addEventListener('change', updateConsultationModes));
            updateConsultationModes();
        })();
    </script>
@endpush
