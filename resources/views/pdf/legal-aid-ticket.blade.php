<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Legal Aid Ticket {{ $request->ticketLabel }}</title>
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
            font-weight: 800;
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
            font-weight: 800;
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
            font-weight: 600;
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
            font-weight: 600;
        }

        table.pricing .total {
            font-size: 13px;
            font-weight: 800;
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
            margin-top: 28px;
            border-top: 1px solid #e5e7eb;
            padding-top: 14px;
            font-size: 10px;
            color: #6b7280;
            line-height: 1.7;
        }

        .note {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.7;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <table class="header" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="60%">
                    <div class="brand">Maroc<span class="accent">Loi</span></div>
                    <div class="tagline">Legal Aid — Session Booking</div>
                </td>
                <td>
                    <div class="ticket-label">Ticket No.</div>
                    <div class="ticket-number">{{ $request->ticketLabel }}</div>
                </td>
            </tr>
        </table>

        <table class="details" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td width="50%" style="padding-right:24px;">
                    <div class="section-title">Client</div>
                    <table class="details" cellpadding="0" cellspacing="0" width="100%">
                        <tr><td class="label">Full name</td><td class="value">{{ $request->full_name }}</td></tr>
                        <tr><td class="label">Email</td><td class="value">{{ $request->email }}</td></tr>
                        <tr><td class="label">Phone</td><td class="value">{{ $request->phone }}</td></tr>
                        @if ($request->whatsapp)
                            <tr><td class="label">WhatsApp</td><td class="value">{{ $request->whatsapp }}</td></tr>
                        @endif
                    </table>
                </td>
                <td width="50%" style="padding-left:24px;">
                    <div class="section-title">Booking</div>
                    <table class="details" cellpadding="0" cellspacing="0" width="100%">
                        <tr><td class="label">Service</td><td class="value">{{ $request->service?->name_en ?: '—' }}</td></tr>
                        <tr><td class="label">Consultation</td><td class="value">{{ $request->consultation_mode ? ($request->consultation_mode === 'whatsapp' ? 'By WhatsApp' : 'At the office') : '—' }}</td></tr>
                        <tr><td class="label">Booked on</td><td class="value">{{ $request->created_at?->format('d M Y, H:i') ?: '—' }}</td></tr>
                        <tr><td class="label">Status</td><td class="value">{{ ucwords(str_replace('_', ' ', $request->status)) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="section-title" style="margin-top:26px;">Payment</div>
        <div class="price-card">
            <table class="pricing" cellpadding="0" cellspacing="0" width="100%">
                @if ($request->isFree())
                    <tr>
                        <td class="muted">Price</td>
                        <td class="amount">Free</td>
                    </tr>
                @else
                    <tr>
                        <td class="muted">Base price</td>
                        <td class="amount muted">{{ number_format((float) $request->base_price, 0) }} MAD</td>
                    </tr>
                    @if ($request->onlineTotal !== null)
                        <tr>
                            <td class="line muted">Google Pay total (online discount {{ config('legal_aid.online_discount_percent') }}%)</td>
                            <td class="line amount">{{ number_format($request->onlineTotal, 0) }} MAD</td>
                        </tr>
                        <tr>
                            <td class="total positive">Pay online with Google Pay</td>
                            <td class="amount total positive">{{ number_format($request->onlineTotal, 0) }} MAD</td>
                        </tr>
                    @endif
                    @if ($request->bankTotal !== null)
                        <tr>
                            <td class="line muted">Bank transfer total (admin fee {{ config('legal_aid.bank_admin_fee_percent') }}%)</td>
                            <td class="line amount">{{ number_format($request->bankTotal, 0) }} MAD</td>
                        </tr>
                        <tr>
                            <td class="total">Pay by bank transfer</td>
                            <td class="amount total">{{ number_format($request->bankTotal, 0) }} MAD</td>
                        </tr>
                    @endif
                @endif
            </table>
        </div>

        <p class="note">
            @if ($request->isFree())
                This service is free of charge — no payment is required. You will be contacted soon on your WhatsApp number to continue with your case.
            @else
                This ticket confirms your booking request. Payment instructions and your secure payment link were
                sent to your email. Please keep this ticket for your records.
            @endif
        </p>

        <div class="footer">
            MarocLoi — Legal Aid Service &nbsp;·&nbsp; {{ config('legal_aid.contact_email') }}<br>
            Issued {{ now()->format('d M Y, H:i') }}
        </div>
    </div>
</body>
</html>
