<?php

return [
    'contact_email' => env('LEGAL_AID_CONTACT_EMAIL', 'info@marocloi.com'),

    // Internal recipients notified on every paid shop order
    'admin_payment_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('LEGAL_AID_ADMIN_PAYMENT_EMAILS', 'info@marocloi.com,hra@marokkobiz.com'))))),

    'payment_url' => env('LEGAL_AID_PAYMENT_URL', ''),

    'bank_admin_fee_percent' => env('LEGAL_AID_BANK_FEE_PERCENT', 10),

    'online_discount_percent' => env('LEGAL_AID_ONLINE_DISCOUNT_PERCENT', 10),

    'booking_confirmation_hours' => env('LEGAL_AID_BOOKING_CONFIRMATION_HOURS', 24),
];
