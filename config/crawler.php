<?php

return [
    // AI provider: ollama | openrouter | groq | openai | github | togetherai
    'ai_provider' => env('AI_PROVIDER', 'ollama'),
    'ai_base_url' => env('AI_BASE_URL', 'http://localhost:11434'),
    'ai_model' => env('AI_MODEL', 'qwen3:8b'),
    'ai_api_key' => env('AI_API_KEY', ''),
    'ai_timeout' => (int) env('AI_TIMEOUT', 300),

    'ollama_url' => env('OLLAMA_URL', 'http://localhost:11434'),
    'ollama_model' => env('CRAWLER_OLLAMA_MODEL', 'qwen3:8b'),
    'ollama_timeout' => (int) env('CRAWLER_OLLAMA_TIMEOUT', 300),

    'max_depth' => (int) env('CRAWLER_MAX_DEPTH', 3),
    'domain_restrict' => (bool) env('CRAWLER_DOMAIN_RESTRICT', true),
    'http_timeout' => (int) env('CRAWLER_HTTP_TIMEOUT', 30),
    'pdftotext_bin' => env('PDFTOTEXT_BIN', 'pdftotext'),
    'tesseract_bin' => env('CRAWLER_TESSERACT_BIN', 'C:/Program Files/Tesseract-OCR/tesseract.exe'),
    'tesseract_data_dir' => env('CRAWLER_TESSERACT_DATA_DIR', ''),
    'pdftoppm_bin' => env('CRAWLER_PDFTOPPM_BIN', ''),
];
