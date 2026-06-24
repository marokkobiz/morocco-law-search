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

    /*
    |--------------------------------------------------------------------------
    | PDF discovery (App\Services\PdfDiscoveryService)
    |--------------------------------------------------------------------------
    |
    | Hybrid crawling strategy. Static pages are fetched with the Laravel HTTP
    | client + Symfony DomCrawler (fast, low memory). JavaScript-rendered pages
    | use Symfony Panther + headless Chrome, but only when explicitly enabled or
    | when the static pass yields nothing and the Panther fallback is allowed.
    */
    'discovery' => [
        // Force every fetch through headless Chrome (Panther).
        'dynamic_mode' => (bool) env('CRAWLER_DYNAMIC_MODE', false),

        // When a static fetch returns no links, retry once with Panther.
        'use_panther_fallback' => (bool) env('CRAWLER_USE_PANTHER_FALLBACK', false),

        // Separate connect/read timeouts for very slow origins (Adala).
        'connect_timeout' => (int) env('CRAWLER_CONNECT_TIMEOUT', 30),
        'read_timeout' => (int) env('CRAWLER_READ_TIMEOUT', 300),

        // Retry with exponential backoff: attempt 1..N wait these seconds.
        'max_retries' => (int) env('CRAWLER_MAX_RETRIES', 5),
        'retry_backoff_seconds' => [10, 30, 60, 120],

        'user_agent' => env('CRAWLER_USER_AGENT', 'MarokkoBizLawSearch-Crawler/1.0'),

        // Probe ambiguous "download" endpoints with a GET and inspect the
        // Content-Type header to confirm a PDF. Off by default (slow origin).
        'probe_content_type' => (bool) env('CRAWLER_PROBE_CONTENT_TYPE', false),

        // URL shapes considered to be PDFs even without a .pdf extension.
        'pdf_patterns' => [
            '/\.pdf($|\?|#)/i',
            '/\/uploads\/.+\.pdf/i',
            '/[?&](file|document|path)=[^&]+\.pdf/i',
            '/format=pdf/i',
            '/(download|telecharger|telechargement).*pdf/i',
        ],
    ],

    'panther' => [
        // Path to the Chrome/Chromium binary; null lets Panther auto-detect.
        'chrome_binary' => env('CRAWLER_CHROME_BINARY'),

        // Path to chromedriver; null lets Panther manage it.
        'chrome_driver_binary' => env('CRAWLER_CHROME_DRIVER_BINARY'),

        'arguments' => [
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
        ],

        // Seconds to wait for client-side rendering before reading the DOM.
        'wait_seconds' => (int) env('CRAWLER_PANTHER_WAIT', 5),
    ],
];
