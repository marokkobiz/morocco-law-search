<?php

return [
    'official_sources' => [
        'official-bulletins' => [
            'name' => 'Secretariat General du Gouvernement - Bulletin officiel',
            'source_type' => 'BO',
            'base_url' => 'https://www.sgg.gov.ma/BO/FR/2873',
            'enabled' => env('LEGAL_SOURCE_OFFICIAL_BULLETINS_ENABLED', true),
        ],
    ],

    'schedule' => [
        'command' => env('LEGAL_SOURCE_UPDATE_COMMAND', 'corpus:update-official-sources --source=all'),
        'daily_at' => env('LEGAL_SOURCE_UPDATE_DAILY_AT', '03:00'),
        'timezone' => env('LEGAL_SOURCE_UPDATE_TIMEZONE', 'Africa/Casablanca'),
    ],
];
