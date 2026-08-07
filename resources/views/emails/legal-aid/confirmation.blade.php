@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.confirm_hello', ['name' => $request->full_name]) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.confirm_intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:20px;">
                <div style="font-size:12px;color:#059669;font-weight:600;text-transform:uppercase;letter-spacing:1px;">{{ __('emails.ticket_label') }}</div>
                <div style="font-size:28px;font-weight:800;color:#065f46;letter-spacing:1px;">{{ $request->ticketLabel }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.confirm_body') }}</p>

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">{{ __('emails.confirm_note') }}</p>
@endsection
