<?php

return [
    'official_sources' => [
        'official-bulletins' => [
            'name' => 'Secretariat General du Gouvernement - Bulletin officiel',
            'source_type' => 'BO',
            'base_url' => 'https://www.sgg.gov.ma/BO/FR/2873',
            'enabled' => env('LEGAL_SOURCE_OFFICIAL_BULLETINS_ENABLED', true),
            'curated_bulletins' => [
                [
                    'bulletin_id' => 5210,
                    'year' => 2004,
                    'document_title' => 'Bulletin officiel n 5210 - Code du travail',
                    'law_reference' => 'BO n 5210',
                    'tags' => ['official-bulletin', 'labor', 'code-du-travail', '2004'],
                    'urls' => [
                        'https://www.sgg.gov.ma/BO/fr/2004/bo_5210_fr.pdf',
                        'https://www.sgg.gov.ma/Portals/0/Bo/bulletin/Fr/2004/BO_5210_Fr.pdf',
                    ],
                ],
                [
                    'bulletin_id' => 5358,
                    'year' => 2005,
                    'document_title' => 'Bulletin officiel n 5358 - Code de la famille',
                    'law_reference' => 'BO n 5358',
                    'tags' => ['official-bulletin', 'family', 'code-de-la-famille', '2005'],
                    'urls' => [
                        'https://www.sgg.gov.ma/BO/fr/2005/bo_5358_fr.pdf',
                        'https://www.sgg.gov.ma/BO/Fr/2005/BO_5358_Fr.pdf',
                    ],
                ],
            ],
        ],
        'sgg-pages' => [
            'name' => 'Secretariat General du Gouvernement - Official legal text pages',
            'source_type' => 'SGG',
            'enabled' => env('LEGAL_SOURCE_SGG_PAGES_ENABLED', true),
            'pages' => [
                [
                    'url' => 'https://www.sgg.gov.ma/textesconsolides.aspx',
                    'language' => 'fr',
                    'category' => 'official-sgg-consolidated',
                    'tags' => ['official-sgg', 'textes-consolides', 'fr'],
                ],
                [
                    'url' => 'https://www.sgg.gov.ma/textesimportants.aspx',
                    'language' => 'fr',
                    'category' => 'official-sgg-important',
                    'tags' => ['official-sgg', 'textes-importants', 'fr'],
                ],
                [
                    'url' => 'https://www.sgg.gov.ma/arabe/textesconsolides.aspx',
                    'language' => 'ar',
                    'category' => 'official-sgg-consolidated',
                    'tags' => ['official-sgg', 'textes-consolides', 'ar'],
                ],
                [
                    'url' => 'https://www.sgg.gov.ma/arabe/textesimportants.aspx',
                    'language' => 'ar',
                    'category' => 'official-sgg-important',
                    'tags' => ['official-sgg', 'textes-importants', 'ar'],
                ],
            ],
        ],
    ],

    'schedule' => [
        'command' => env('LEGAL_SOURCE_UPDATE_COMMAND', 'corpus:update-official-sources --source=all'),
        'daily_at' => env('LEGAL_SOURCE_UPDATE_DAILY_AT', '03:00'),
        'timezone' => env('LEGAL_SOURCE_UPDATE_TIMEZONE', 'Africa/Casablanca'),
    ],
];
