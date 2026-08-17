@extends('layouts.app')

@section('title', __('legal_aid.confirmed_title') . ' | MarocLoi')

@section('content')
<section class="bg-gray-50 py-16">
    <div class="container-page">
        <div class="max-w-xl mx-auto">
            <div class="text-center mb-8" data-animate="fade-up">
                <span class="section-label">{{ __('legal_aid.confirmed_badge') }}</span>
                <h1 class="section-title mt-4">{{ __('legal_aid.confirmed_title') }}</h1>
                <p class="text-sm text-gray-500 mt-3">{{ __('legal_aid.payment_ticket') }} <span class="font-bold text-gray-900">{{ $request->ticketLabel }}</span></p>
            </div>

            <div class="card p-8 text-center" data-animate="fade-up">
                <div class="text-4xl mb-3">✓</div>
                <h2 class="text-lg font-bold text-gray-900 mb-2">{{ __('legal_aid.confirmed_heading') }}</h2>
                <p class="text-sm text-gray-500 mb-6">{{ __('legal_aid.confirmed_desc', ['ticket' => $request->ticketLabel]) }}</p>
                <a href="{{ route('legal-aid') }}" class="btn-primary inline-flex">{{ __('legal_aid.confirmed_back_booking') }}</a>
                <p class="mt-4">
                    <a href="{{ route('legal-aid.ticket-pdf', $request->ticket_number) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">{{ __('legal_aid.download_ticket') }}</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
