<?php

return [
    'require_payment' => (bool) env('BILLING_REQUIRE_PAYMENT', false),
    'default_trial_days' => (int) env('BILLING_DEFAULT_TRIAL_DAYS', 0),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'price_id' => env('STRIPE_PRICE_ID'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
