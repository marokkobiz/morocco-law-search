<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deprecated: use config/ai.php instead
    |--------------------------------------------------------------------------
    |
    | This file is kept for backward compatibility and delegates all values
    | to the new config/ai.php. Existing code reading config('legal_ai.*')
    | will continue to work without changes.
    |
    */

    'semantic_search' => [
        'enabled' => config('ai.semantic_search.enabled', true),
        'min_score' => config('ai.semantic_search.min_score', 0.55),
        'candidate_limit' => config('ai.semantic_search.candidate_limit', 2000),
        'result_limit' => config('ai.semantic_search.result_limit', 12),
    ],

    'embeddings' => [
        'provider' => config('ai.default_provider', 'ollama'),
        'model' => config('ai.providers.ollama.embedding_model', 'nomic-embed-text'),
        'base_url' => config('ai.providers.ollama.base_url', 'http://localhost:11434'),
        'timeout_seconds' => config('ai.providers.ollama.embedding_timeout', 30),
    ],
];
