@php
    $shape = fn ($text) => \App\Support\PdfArabic::shape((string) $text, $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $shape(__('legal_aid_ticket.ticket_label')) }} {{ $request->ticketLabel }}</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 18px;
            margin-bottom: 26px;
        }

        .brand {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #111827;
        }

        .brand .accent {
            color: #2563eb;
        }

        .tagline {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            margin-top: 3px;
        }

        .ticket-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-align: right;
            margin-bottom: 4px;
        }

        .ticket-number {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #2563eb;
            text-align: right;
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        table.details {
            width: 100%;
            border-collapse: collapse;
        }

        table.details td {
            padding: 8px 0;
            font-size: 12px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        table.details tr:last-child td {
            border-bottom: none;
        }

        table.details td.label {
            color: #6b7280;
            width: 150px;
        }

        table.details td.value {
            font-weight: 700;
            color: #111827;
        }

        .price-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 18px;
        }

        table.pricing {
            width: 100%;
            border-collapse: collapse;
        }

        table.pricing td {
            padding: 7px 0;
            font-size: 12px;
        }

        table.pricing td.line {
            border-bottom: 1px solid #f3f4f6;
        }

        table.pricing .muted {
            color: #6b7280;
        }

        table.pricing .amount {
            text-align: right;
            font-weight: 700;
        }

        table.pricing .total {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            padding-top: 10px;
        }

        table.pricing .total.positive {
            color: #047857;
        }

        table.pricing .total.negative {
            color: #b91c1c;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #e5e7eb;
            padding-top: 14px;
            font-size: 10px;
            color: #6b7280;
            line-height: 1.7;
            text-align: center;
        }

        .note {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.7;
            margin-top: 16px;
        }

        @if ($locale === 'ar')
        html, body {
            text-align: right;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
        }

        .ticket-label, .ticket-number, table.pricing .amount {
            text-align: left;
        }

        .section-title, .tagline, .note, .footer, table.details td.label, .ticket-label {
            letter-spacing: 0;
        }
        @endif
    </style>
</head>
<body>
    <div class="wrap">
        <table class="header" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="60%">
                    <div class="brand">Maroc<span class="accent">Loi</span></div>
                    <div class="tagline">{{ $shape(__('legal_aid_ticket.brand_tagline')) }}</div>
                </td>
                <td>
                    <div class="ticket-label">{{ $shape(__('legal_aid_ticket.ticket_label')) }}</div>
                    <div class="ticket-number">{{ $request->ticketLabel }}</div>
                </td>
            </tr>
        </table>

        <table class="details" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                @foreach (($locale === 'ar' ? ['booking', 'client'] : ['client', 'booking']) as $column)
                    @if ($column === 'client')
                        <td width="50%" style="padding-{{ $locale === 'ar' ? 'left' : 'right' }}:24px;">
                            <div class="section-title">{{ $shape(__('legal_aid_ticket.client')) }}</div>
                            <table class="details" cellpadding="0" cellspacing="0" width="100%">
                                <tr><td class="label">{{ $shape(__('legal_aid_ticket.full_name')) }}</td><td class="value">{{ $request->full_name }}</td></tr>
                                <tr><td class="label">{{ $shape(__('legal_aid_ticket.email')) }}</td><td class="value">{{ $request->email }}</td></tr>
                                <tr><td class="label">{{ $shape(__('legal_aid_ticket.phone')) }}</td><td class="value">{{ $request->phone }}</td></tr>
                                @if ($request->whatsapp)
                                    <tr><td class="label">{{ $shape(__('legal_aid_ticket.whatsapp')) }}</td><td class="value">{{ $request->whatsapp }}</td></tr>
                                @endif
                            </table>
                        </td>
                    @else
                        <td width="50%" style="padding-{{ $locale === 'ar' ? 'right' : 'left' }}:24px;">
                            <div class="section-title">{{ $shape(__('legal_aid_ticket.booking')) }}</div>
                            <table class="details" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td class="label">{{ $shape(__('legal_aid_ticket.consultation')) }}</td>
                                    <td class="value">
                                        {{ $request->consultation_mode ? $shape(__('legal_aid_ticket.mode_'.$request->consultation_mode)) : '—' }}
                                    </td>
                                </tr>
                                @if ($request->call_time)
                                    <tr><td class="label">{{ $shape(__('legal_aid_ticket.call_time')) }}</td><td class="value">{{ $request->call_time }}</td></tr>
                                @endif
                                <tr><td class="label">{{ $shape(__('legal_aid_ticket.booked_on')) }}</td><td class="value">{{ $request->created_at?->format('d M Y, H:i') ?: '—' }}</td></tr>
                                <tr><td class="label">{{ $shape(__('legal_aid_ticket.status')) }}</td><td class="value">{{ $shape(__('legal_aid_ticket.status_'.$request->status)) }}</td></tr>
                            </table>
                        </td>
                    @endif
                @endforeach
            </tr>
        </table>

        <div class="section-title" style="margin-top:26px;">{{ $shape(__('legal_aid_ticket.payment')) }}</div>
        <div class="price-card">
            <table class="pricing" cellpadding="0" cellspacing="0" width="100%">
                @foreach ($request->selectedServices as $service)
                    <tr>
                        <td class="muted">{{ $shape($service->name) }}</td>
                        <td class="amount muted">{{ number_format((float) $service->price, 0) }} MAD</td>
                    </tr>
                @endforeach
                @if ($request->isFree())
                    <tr>
                        <td class="total">{{ $shape(__('legal_aid_ticket.free')) }}</td>
                        <td class="amount total positive">{{ number_format((float) $request->base_price, 0) }} MAD</td>
                    </tr>
                @else
                    <tr>
                        <td class="line">{{ $shape(__('legal_aid_ticket.base_price')) }}</td>
                        <td class="line amount">{{ number_format((float) $request->base_price, 0) }} MAD</td>
                    </tr>
                    @if ($request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_BANK)
                        <tr>
                            <td class="line muted">{{ $shape(__('legal_aid_ticket.bank_total', ['percent' => (int) config('legal_aid.bank_admin_fee_percent')])) }}</td>
                            <td class="line amount">+{{ number_format((float) $request->bankTotal - (float) $request->base_price, 0) }} MAD</td>
                        </tr>
                    @else
                        <tr>
                            <td class="line muted">{{ $shape(__('legal_aid_ticket.online_total', ['percent' => (int) config('legal_aid.online_discount_percent')])) }}</td>
                            <td class="line amount">- {{ number_format((float) $request->base_price - (float) $request->onlineTotal, 0) }} MAD</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="total">{{ $shape(__('legal_aid_ticket.payment_method')) }} · {{ $shape(__('legal_aid_ticket.method_'.$request->payment_method)) }}</td>
                        <td class="amount total {{ $request->payment_method === \App\Models\LegalAidRequest::PAYMENT_METHOD_BANK ? '' : 'positive' }}">{{ number_format($request->payableTotal, 0) }} MAD</td>
                    </tr>
                @endif
            </table>
        </div>

        <p class="note">
            {{ $request->isFree() ? $shape(__('legal_aid_ticket.note_free')) : $shape(__('legal_aid_ticket.note_paid')) }}
        </p>
    </div>

    <div class="footer">
        {{ $shape(__('legal_aid_ticket.footer_line_1')) }}<br>
        {{ $shape(__('legal_aid_ticket.footer_line_2')) }}
    </div>
</body>
</html>
