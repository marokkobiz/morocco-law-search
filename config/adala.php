<?php

return [
    'base_url' => env('ADALA_BASE_URL', 'https://adala.justice.gov.ma'),

    'seed_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADALA_SEED_URLS', 'https://adala.justice.gov.ma/fr,https://adala.justice.gov.ma/en'))
    ))),

    'allowed_languages' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADALA_ALLOWED_LANGUAGES', 'fr,en'))
    ))),

    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADALA_ALLOWED_HOSTS', 'adala.justice.gov.ma,www.adala.justice.gov.ma'))
    ))),

    'pdf_path_patterns' => [
        '/\/api\/uploads\/.+\.pdf/i',
        '/\/uploads\/.+\.pdf/i',
        '/\.pdf(?:$|\?)/i',
    ],

    'page_path_patterns' => [
        '/\/fr(?:\/|$)/i',
        '/\/en(?:\/|$)/i',
        '/\/categories?\//i',
        '/\/documents?\//i',
        '/\/textes?\//i',
        '/\/lois?\//i',
        '/\/codes?\//i',
        '/\/search/i',
        '/\/api\//i',
        '/page=\d+/i',
        '/\/page\/\d+/i',
    ],

    'queue' => env('ADALA_QUEUE', 'adala'),

    'crawl' => [
        'max_depth' => (int) env('ADALA_MAX_DEPTH', 8),
        'max_pages' => (int) env('ADALA_MAX_PAGES', 0),
        'request_delay_ms' => (int) env('ADALA_REQUEST_DELAY_MS', 500),
        'user_agent' => env('ADALA_USER_AGENT', 'MarokkoBizLawSearch-AdalaCrawler/1.0'),
    ],

    'download' => [
        'connect_timeout_seconds' => (int) env('ADALA_CONNECT_TIMEOUT_SECONDS', 30),
        'read_timeout_seconds' => (int) env('ADALA_READ_TIMEOUT_SECONDS', 600),
        'min_file_size_bytes' => (int) env('ADALA_MIN_FILE_SIZE_BYTES', 512),
        'storage_disk' => env('ADALA_STORAGE_DISK', 'local'),
        'storage_directory' => env('ADALA_STORAGE_DIRECTORY', 'adala/pdfs'),
        'max_retries' => (int) env('ADALA_DOWNLOAD_MAX_RETRIES', 5),
        'retry_backoff_seconds' => [10, 30, 60, 120],
    ],

    'import' => [
        'source_name' => env('ADALA_SOURCE_NAME', 'Adala - Ministere de la Justice'),
        'category' => env('ADALA_CATEGORY', 'adala'),
        'default_language' => env('ADALA_DEFAULT_LANGUAGE', 'fr'),
        'timeout_ms' => (int) env('ADALA_IMPORT_TIMEOUT_MS', 600000),
    ],

    'processing' => [
        'max_retries' => (int) env('ADALA_PROCESSING_MAX_RETRIES', 5),
        'job_timeout_seconds' => (int) env('ADALA_JOB_TIMEOUT_SECONDS', 3600),
        'embedding_batch_size' => (int) env('ADALA_EMBEDDING_BATCH_SIZE', 25),
    ],
];
