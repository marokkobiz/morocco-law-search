@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.admin_hello') }}</p>

    <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.admin_intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td style="padding:14px 16px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    @php
                        $rows = [
                            __('emails.field_ticket') => $request->ticketLabel,
                            __('emails.field_name') => $request->full_name,
                            __('emails.field_email') => $request->email,
                            __('emails.field_phone') => $request->phone,
                            __('emails.field_whatsapp') => $request->whatsapp ?: '—',
                            __('emails.field_service') => $request->servicesSummary,
                            __('emails.field_case') => $request->case_description,
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <tr>
                            <td style="padding:8px 0;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;vertical-align:top;">{{ $label }}</td>
                            <td style="padding:8px 0 8px 16px;font-size:14px;color:#111827;line-height:1.6;vertical-align:top;white-space:pre-wrap;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    {{-- <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.admin_payment_url') }}
    </p>
    @if ($paymentUrl)
        <p style="margin:0 0 16px;">
            <a href="{{ $paymentUrl }}" style="color:#2563eb;font-weight:600;text-decoration:underline;">{{ $paymentUrl }}</a>
        </p>
    @endif

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.admin_upload_url') }}
        <a href="{{ $paymentLink }}" style="color:#2563eb;font-weight:600;text-decoration:underline;">{{ $paymentLink }}</a>
    </p> --}}

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">
        {{ $request->isFree() ? __('emails.admin_note_free') : __('emails.admin_note') }}
    </p>
@endsection
