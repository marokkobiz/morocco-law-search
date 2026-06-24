<?php

return [
    'semantic_search' => [
        'enabled' => (bool) env('LEGAL_SEMANTIC_SEARCH_ENABLED', true),
        'min_score' => (float) env('LEGAL_SEMANTIC_SEARCH_MIN_SCORE', 0.55),
        'candidate_limit' => (int) env('LEGAL_SEMANTIC_SEARCH_CANDIDATE_LIMIT', 2000),
        'result_limit' => (int) env('LEGAL_SEMANTIC_SEARCH_RESULT_LIMIT', 12),
    ],

    'embeddings' => [
        'provider' => env('LEGAL_EMBEDDING_PROVIDER', 'ollama'),
        'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        // Use 127.0.0.1 (not "localhost") so Windows does not try IPv6 ::1 first,
        // where Ollama is not listening, and stall the cURL connect attempt.
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'timeout_seconds' => (int) env('OLLAMA_EMBEDDING_TIMEOUT_SECONDS', 120),
        'connect_timeout_seconds' => (int) env('OLLAMA_EMBEDDING_CONNECT_TIMEOUT_SECONDS', 15),
        'max_retries' => (int) env('OLLAMA_EMBEDDING_MAX_RETRIES', 3),
    ],
];
