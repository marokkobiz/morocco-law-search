@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.ticket_hello', ['name' => $request->full_name]) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.ticket_intro') }}
    </p>

    @if ($request->selectedServices->isNotEmpty())
        <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
            {{ __('emails.ticket_services', ['services' => $request->servicesSummary]) }}
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:20px;">
                <div style="font-size:12px;color:#2563eb;font-weight:600;text-transform:uppercase;letter-spacing:1px;">{{ __('emails.ticket_label') }}</div>
                <div style="font-size:28px;font-weight:800;color:#1e3a8a;letter-spacing:1px;">{{ $request->ticketLabel }}</div>
            </td>
        </tr>
    </table>

    @if ($request->isFree())
        <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
            {{ __('emails.ticket_free_intro') }}
        </p>
        <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
            {{ __('emails.ticket_free_note', ['whatsapp' => $request->whatsapp ?: $request->phone]) }}
        </p>
    @else
        @if ($request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_BANK)
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
                {{ __('emails.ticket_bank_intro') }}
                <a href="{{ $paymentLink }}" style="color:#2563eb;font-weight:600;text-decoration:underline;">{{ $paymentLink }}</a>
            </p>
        @else
            @if ($paymentUrl)
                <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.ticket_payment_intro') }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                    <tr>
                        <td align="center">
                            <a href="{{ $paymentUrl }}" target="_blank"
                               style="display:inline-block;background-color:#2563eb;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:10px;">
                                {{ __('emails.ticket_pay_button') }}
                            </a>
                        </td>
                    </tr>
                </table>
            @endif
        @endif

        <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">
            {{ __('emails.ticket_note') }}
        </p>
    @endif
@endsection
