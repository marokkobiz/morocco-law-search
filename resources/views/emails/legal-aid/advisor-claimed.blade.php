@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.advisor_claimed_hello', ['name' => $request->full_name]) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.advisor_claimed_intro', ['ticket' => $request->ticketLabel, 'advisor' => $request->advisor?->name ?? __('emails.advisor_claimed_default_advisor')]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:20px;">
                <div style="font-size:12px;color:#2563eb;font-weight:600;text-transform:uppercase;letter-spacing:1px;">{{ __('emails.ticket_label') }}</div>
                <div style="font-size:28px;font-weight:800;color:#1e3a8a;letter-spacing:1px;">{{ $request->ticketLabel }}</div>
                <div style="margin-top:8px;font-size:13px;color:#4b5563;">{{ $request->advisor?->name }} — {{ __('emails.advisor_claimed_role') }}</div>
                @if($request->advisor?->phone)
                    <div style="font-size:13px;color:#6b7280;">{{ $request->advisor->phone }}</div>
                @endif
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.advisor_claimed_body', ['whatsapp' => $request->whatsapp ?: $request->phone]) }}
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">{{ __('emails.advisor_claimed_note') }}</p>
@endsection
