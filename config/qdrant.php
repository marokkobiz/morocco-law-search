<?php

return [
    'enabled' => (bool) env('QDRANT_ENABLED', true),

    'url' => rtrim((string) env('QDRANT_URL', 'http://127.0.0.1:6333'), '/'),

    'api_key' => env('QDRANT_API_KEY'),

    'collection' => (string) env('QDRANT_COLLECTION', 'legal_chunks'),

    'vector_size' => (int) env('QDRANT_VECTOR_SIZE', 768),

    'timeout_seconds' => (int) env('QDRANT_TIMEOUT_SECONDS', 10),
];
