@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.shop_hello', ['name' => $order->full_name ?: $order->email]) }}</p>

    <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.shop_intro', ['ticket' => $order->ticket_number]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:20px;">
                <div style="font-size:12px;color:#059669;font-weight:600;text-transform:uppercase;letter-spacing:1px;">{{ __('emails.ticket_label') }}</div>
                <div style="font-size:28px;font-weight:800;color:#065f46;letter-spacing:1px;">{{ $order->ticket_number }}</div>
                <div style="margin-top:8px;font-size:13px;color:#047857;">{{ __('emails.shop_amount', ['amount' => number_format($order->total_cents / 100, 0) . ' MAD']) }}</div>
                <div style="margin-top:4px;font-size:12px;color:#047857;">{{ __('emails.shop_cin_note') }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;margin:0 0 20px;overflow:hidden;">
        <tr>
            <td style="background-color:#f9fafb;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;">{{ __('emails.shop_items') }}</div>
            </td>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:14px;color:#111827;font-weight:600;">{{ $item->service->name ?? $item->stripe_price_id }}</td>
                            <td align="right" style="font-size:14px;color:#111827;font-weight:600;">{{ number_format($item->line_total_cents / 100, 0) }} MAD</td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;">{{ __('emails.shop_qty', ['qty' => $item->quantity]) }} · {{ number_format($item->unit_amount_cents / 100, 0) }} MAD {{ __('emails.shop_each') }}</td>
                            <td></td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach
        <tr>
            <td style="padding:12px 16px;background-color:#f9fafb;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:14px;font-weight:700;color:#111827;">{{ __('emails.shop_total') }}</td>
                        <td align="right" style="font-size:16px;font-weight:800;color:#111827;">{{ number_format($order->total_cents / 100, 0) }} MAD</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:13px;color:#6b7280;line-height:1.7;">
        {{ __('emails.shop_body', ['cin' => $order->cin]) }}
    </p>
    {{-- <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">{{ __('emails.shop_note') }}</p> --}}
@endsection
