@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.payment_received_hello', ['name' => $request->full_name]) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.payment_received_intro', ['ticket' => $request->ticketLabel]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:20px;">
                <div style="font-size:12px;color:#059669;font-weight:600;text-transform:uppercase;letter-spacing:1px;">{{ __('emails.ticket_label') }}</div>
                <div style="font-size:28px;font-weight:800;color:#065f46;letter-spacing:1px;">{{ $request->ticketLabel }}</div>
                <div style="margin-top:8px;font-size:13px;color:#047857;">{{ __('emails.payment_received_amount', ['amount' => number_format((float) $request->payableTotal, 0) . ' MAD']) }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.payment_received_body', ['whatsapp' => $request->whatsapp ?: $request->phone]) }}
    </p>

    {{-- <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.payment_received_services', ['services' => $request->servicesSummary]) }}
    </p> --}}

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">{{ __('emails.payment_received_note') }}</p>
@endsection
