<?php

return [
    'cases' => [
        [
            'id' => 'smoke_ar_unregistered_property_double_sale_priority',
            'language' => 'ar',
            'legalArea' => 'real_estate_civil_ownership',
            'message' => 'باع شخص عقارًا غير محفظ لشخصين مختلفين، من له الأولوية؟',
            'expectedIntent' => 'legal_case_analysis',
            'expectedSourceType' => 'code',
            'expectedDocuments' => ['Code des Obligations et des Contrats'],
            'expectedArticlesAny' => ['Article 488', 'Article 491'],
            'expectedDomains' => ['civil_obligations_contracts'],
        ],
    ],
];
