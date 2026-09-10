@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">New paid shop order — {{ $order->ticket_label }}</p>

    <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;">
        A customer has completed payment for a legal aid order. Details below.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td style="padding:14px 16px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    @php
                        $rows = [
                            'Ticket / CIN' => $order->ticket_number,
                            'Order ID' => '#'.$order->id,
                            'Full Name' => $order->full_name,
                            'Email' => $order->email,
                            'Phone' => $order->phone,
                            'WhatsApp' => $order->whatsapp ?: '—',
                            'Preferred Call Time' => $order->call_time,
                            'Locale' => $order->locale ?: app()->getLocale(),
                            'Paid At' => $order->paid_at ? $order->paid_at->format('Y-m-d H:i') : '—',
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <tr>
                            <td style="padding:8px 0;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;vertical-align:top;">{{ $label }}</td>
                            <td style="padding:8px 0 8px 16px;font-size:14px;color:#111827;line-height:1.6;vertical-align:top;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;margin:0 0 20px;overflow:hidden;">
        <tr>
            <td style="background-color:#f9fafb;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;">Purchased Services</div>
            </td>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #f3f4f5;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:14px;color:#111827;font-weight:600;">{{ $item->service->name ?? $item->stripe_price_id }}</td>
                            <td align="right" style="font-size:14px;color:#111827;font-weight:600;">{{ number_format($item->line_total_cents / 100, 2) }} MAD</td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;">Qty {{ $item->quantity }} · {{ number_format($item->unit_amount_cents / 100, 2) }} MAD each</td>
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
                        <td style="font-size:14px;font-weight:700;color:#111827;">Total Paid</td>
                        <td align="right" style="font-size:16px;font-weight:800;color:#111827;">{{ number_format($order->total_cents / 100, 2) }} MAD</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="margin:0 0 20px;padding:14px 16px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;">
        <div style="font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Case Description</div>
        <div style="font-size:14px;color:#111827;line-height:1.7;white-space:pre-wrap;">{{ $order->case_description }}</div>
    </div>
@endsection
