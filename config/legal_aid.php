<?php

return [
    'contact_email' => env('LEGAL_AID_CONTACT_EMAIL', 'contact@marocloi.com'),

    'payment_url' => env('LEGAL_AID_PAYMENT_URL', ''),

    'bank_admin_fee_percent' => env('LEGAL_AID_BANK_FEE_PERCENT', 10),

    'online_discount_percent' => env('LEGAL_AID_ONLINE_DISCOUNT_PERCENT', 10),
];
