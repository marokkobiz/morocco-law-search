<?php

return [
    'contact_email' => env('LEGAL_AID_CONTACT_EMAIL', 'info@marocloi.com'),

    'payment_url' => env('LEGAL_AID_PAYMENT_URL', ''),

    'bank_admin_fee_percent' => env('LEGAL_AID_BANK_FEE_PERCENT', 10),

    'online_discount_percent' => env('LEGAL_AID_ONLINE_DISCOUNT_PERCENT', 10),

    'booking_confirmation_hours' => env('LEGAL_AID_BOOKING_CONFIRMATION_HOURS', 24),
];
