@extends('layouts.app')

@section('title', __('legal_aid.badge') . ' | MarocLoi')

@section('content')
<section class="bg-gray-50 py-16">
    <div class="container-page">
        <div class="max-w-3xl mx-auto text-center mb-12" data-animate="fade-up">
            <span class="section-label">{{ __('legal_aid.badge') }}</span>
            <h1 class="section-title mt-4">{{ __('legal_aid.title') }}</h1>
            <p class="section-desc mt-4 mx-auto">{{ __('legal_aid.subtitle') }}</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-start">
            <div class="card p-8" data-animate="fade-up" style="--delay:.1s">
                <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('legal_aid.pricing_title') }}</h2>
                <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.pricing_desc') }}</p>
                <div class="divide-y divide-gray-100">
                    <div class="flex items-center justify-between py-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.plan_initial') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('legal_aid.plan_initial_desc') }}</p>
                        </div>
                        <span class="text-sm font-bold text-blue-600 whitespace-nowrap ml-4">{{ __('legal_aid.plan_initial_price') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.plan_followup') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('legal_aid.plan_followup_desc') }}</p>
                        </div>
                        <span class="text-sm font-bold text-blue-600 whitespace-nowrap ml-4">{{ __('legal_aid.plan_followup_price') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.plan_full') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('legal_aid.plan_full_desc') }}</p>
                        </div>
                        <span class="text-sm font-bold text-blue-600 whitespace-nowrap ml-4">{{ __('legal_aid.plan_full_price') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.plan_writing') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('legal_aid.plan_writing_desc') }}</p>
                        </div>
                        <span class="text-sm font-bold text-blue-600 whitespace-nowrap ml-4">{{ __('legal_aid.plan_writing_price') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('legal_aid.plan_representation') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('legal_aid.plan_representation_desc') }}</p>
                        </div>
                        <span class="text-sm font-bold text-blue-600 whitespace-nowrap ml-4">{{ __('legal_aid.plan_representation_price') }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-8" data-animate="fade-up" style="--delay:.2s">
                <h2 class="text-xl font-bold text-gray-900 text-center mb-1">{{ __('legal_aid.form_title') }}</h2>
                <p class="text-sm text-gray-500 text-center mb-6">{{ __('legal_aid.form_desc') }}</p>
                @if (session('ticket'))
                    <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        {{ __('legal_aid.success_ticket', ['ticket' => session('ticket')]) }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form class="space-y-4" action="{{ route('legal-aid.store') }}" method="POST">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('legal_aid.field_name') }}</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            placeholder="{{ __('legal_aid.field_name_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('legal_aid.field_email') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            placeholder="{{ __('legal_aid.field_email_placeholder') }}">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('legal_aid.field_phone') }}</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                placeholder="{{ __('legal_aid.field_phone_placeholder') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('legal_aid.field_whatsapp') }}</label>
                            <input type="tel" name="whatsapp" value="{{ old('whatsapp') }}"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                placeholder="{{ __('legal_aid.field_whatsapp_placeholder') }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('legal_aid.field_case') }}</label>
                        <textarea name="case_description" rows="4" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-1 focus:ring-blue-500 outline-none transition-colors resize-none"
                            placeholder="{{ __('legal_aid.field_case_placeholder') }}">{{ old('case_description') }}</textarea>
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
