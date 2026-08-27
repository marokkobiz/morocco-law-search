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

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Pricing</h3>
                    <p class="text-xs text-slate-400">Enter the base price, then optionally a custom display label per language (e.g. "Free", "from 3.000,00 MAD"). If empty, the price is formatted automatically.</p>
                </div>

                <div class="sm:max-w-xs">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Price (MAD)</label>
                    <input type="number" id="price-input" name="price" value="{{ old('price', $service->price) }}" step="0.01" min="0"
                           required
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach(['en', 'fr', 'ar'] as $locale)
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Price display ({{ strtoupper($locale) }})</label>
                            <input type="text" id="price-display-{{ $locale }}" name="price_display_{{ $locale }}" value="{{ old('price_display_' . $locale, $service->{'price_display_' . $locale}) }}"
                                   maxlength="255" placeholder="{{ $locale === 'en' ? 'e.g. Free' : ($locale === 'fr' ? 'ex. Gratuit' : 'مثال: مجاني') }}"
                                   data-auto="1"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
                        </div>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-slate-400">Leave empty to auto-format, or type a custom label like “Free” / “from 3.000,00 MAD”. Editing price auto-fills empty displays.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Notes</h3>
                    <p class="text-xs text-slate-400">Short conditions shown to clients when they select this service (e.g. "Only by WhatsApp").</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach(['en', 'fr', 'ar'] as $locale)
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes ({{ strtoupper($locale) }})</label>
                            <textarea name="notes_{{ $locale }}" rows="2" maxlength="500"
                                      class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">{{ old('notes_' . $locale, $service->{'notes_' . $locale}) }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Additional Notes</h3>
                    <p class="text-xs text-slate-400">Optional second line of conditions or clarifications.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach(['en', 'fr', 'ar'] as $locale)
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Additional notes ({{ strtoupper($locale) }})</label>
                            <textarea name="additional_notes_{{ $locale }}" rows="2" maxlength="500"
                                      class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors">{{ old('additional_notes_' . $locale, $service->{'additional_notes_' . $locale}) }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Consultation Options</h3>
                    <p class="text-xs text-slate-400">Choose how clients can receive this service. Only the enabled options will be shown on the booking form when this service is selected.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 cursor-pointer transition hover:border-blue-300">
                        <input type="checkbox" name="allows_office" value="1"
                               {{ old('allows_office', $service->allows_office) ? 'checked' : '' }}
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">At the office</p>
                            <p class="text-xs text-slate-500 mt-0.5">Client visits the office in person.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 cursor-pointer transition hover:border-green-300">
                        <input type="checkbox" name="allows_whatsapp" value="1"
                               {{ old('allows_whatsapp', $service->allows_whatsapp) ? 'checked' : '' }}
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-green-600 focus:ring-green-500">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">By WhatsApp</p>
                            <p class="text-xs text-slate-500 mt-0.5">Video or voice call over WhatsApp.</p>
                        </div>
                    </label>
                </div>
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

<script>
(function() {
    const priceInput = document.getElementById('price-input');
    const fields = {
        en: document.getElementById('price-display-en'),
        fr: document.getElementById('price-display-fr'),
        ar: document.getElementById('price-display-ar'),
    };
    if (!priceInput || !fields.en) return;

    function formatPrice(price, locale) {
        const num = parseFloat(price);
        if (isNaN(num) || price === '') return '';
        if (num === 0) {
            if (locale === 'en') return 'Free';
            if (locale === 'fr') return 'Gratuit';
            return 'مجاني';
        }
        const formatted = num.toLocaleString(locale === 'ar' ? 'ar-MA' : 'fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (locale === 'ar') return formatted + ' درهم';
        return formatted + ' MAD';
    }

    function autoFill() {
        const price = priceInput.value;
        Object.keys(fields).forEach(locale => {
            const input = fields[locale];
            if (!input) return;
            if (input.dataset.auto === '1' || input.value.trim() === '') {
                input.value = formatPrice(price, locale);
                input.dataset.auto = '1';
            }
        });
    }

    Object.values(fields).forEach(input => {
        input.addEventListener('input', () => {
            if (input.value.trim() === '') {
                input.dataset.auto = '1';
            } else {
                const locale = input.id.replace('price-display-', '');
                const autoVal = formatPrice(priceInput.value, locale);
                input.dataset.auto = (input.value === autoVal) ? '1' : '0';
            }
        });
        if (input.value.trim() !== '') {
            const locale = input.id.replace('price-display-', '');
            input.dataset.auto = (input.value === formatPrice(priceInput.value, locale)) ? '1' : '0';
        }
    });

    priceInput.addEventListener('input', autoFill);
    priceInput.addEventListener('change', autoFill);
    autoFill();
})();
</script>

@endsection
