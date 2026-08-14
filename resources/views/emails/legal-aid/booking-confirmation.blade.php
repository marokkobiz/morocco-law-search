@extends('emails.layout')

@php
    $serviceIds = array_values(array_unique((array) ($confirmation->payload['service_ids'] ?? [])));
    $serviceNames = \App\Models\Service::whereIn('id', $serviceIds)
        ->orderBy('price')
        ->get()
        ->map(fn ($service) => $service->name)
        ->implode(', ');
@endphp

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.booking_confirm_hello', ['name' => $confirmation->payload['full_name'] ?? '']) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.booking_confirm_intro') }}
    </p>

    @if ($serviceNames)
        <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
            {{ __('emails.booking_confirm_services', ['services' => $serviceNames]) }}
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td align="center">
                <a href="{{ route('legal-aid.confirm-booking', $confirmation->token) }}" target="_blank"
                   style="display:inline-block;background-color:#2563eb;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:10px;">
                    {{ __('emails.booking_confirm_button') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.booking_confirm_note', ['email' => $confirmation->email]) }}
    </p>

    <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.7;">
        {{ __('emails.booking_confirm_expiry', ['hours' => (int) config('legal_aid.booking_confirmation_hours', 24)]) }}
    </p>
@endsection
