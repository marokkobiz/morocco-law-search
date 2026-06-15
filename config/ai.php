<?php

return [
    'default_provider' => env('AI_PROVIDER', 'ollama'),

    'semantic_search' => [
        'enabled' => (bool) env('LEGAL_SEMANTIC_SEARCH_ENABLED', true),
        'min_score' => (float) env('LEGAL_SEMANTIC_SEARCH_MIN_SCORE', 0.55),
        'candidate_limit' => (int) env('LEGAL_SEMANTIC_SEARCH_CANDIDATE_LIMIT', 2000),
        'result_limit' => (int) env('LEGAL_SEMANTIC_SEARCH_RESULT_LIMIT', 12),
    ],

    'chat' => [
        'enabled' => (bool) env('AI_CHAT_ENABLED', false),
        'timeout' => (int) env('AI_CHAT_TIMEOUT_SECONDS', 120),
    ],

    'providers' => [
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen3:8b'),
            'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'embedding_timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT_SECONDS', 30),
            'chat_timeout' => (int) env('OLLAMA_CHAT_TIMEOUT_SECONDS', 120),
        ],
    ],
];
