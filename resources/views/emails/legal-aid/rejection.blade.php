@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.reject_hello', ['name' => $request->full_name]) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.reject_intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:20px;">
                <div style="font-size:12px;color:#b91c1c;font-weight:600;text-transform:uppercase;letter-spacing:1px;">{{ __('emails.ticket_label') }}</div>
                <div style="font-size:28px;font-weight:800;color:#991b1b;letter-spacing:1px;">{{ $request->ticketLabel }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.reject_body') }}</p>

    @unless ($request->isFree())
        <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;">
            <a href="{{ $paymentLink }}" target="_blank" style="color:#2563eb;text-decoration:underline;">
                {{ __('emails.reject_pay_button') }}
            </a>
        </p>
    @endunless

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">{{ __('emails.reject_note') }}</p>
@endsection
