<?php

return [
    'marketplace_timezone' => env('DESIGNHUB_MARKETPLACE_TIMEZONE', 'Asia/Kolkata'),

    'otp_length' => 4,
    'otp_ttl_minutes' => 10,
    'otp_max_attempts' => 5,
    'otp_resend_seconds' => 30,
    'otp_log_code' => env('DESIGNHUB_OTP_LOG', true),
    'otp_static_code' => env('DESIGNHUB_OTP_STATIC_CODE'),
    'otp_static_enabled' => env('DESIGNHUB_OTP_STATIC_ENABLED', false),

    'thumbnail_max_width' => 480,
    'thumbnail_max_height' => 480,

    'download_url_ttl_minutes' => 15,

    // Optional bsdtar fallback when the PHP rar extension is unavailable.
    'folder_archive_extractor' => env('DESIGNHUB_FOLDER_ARCHIVE_EXTRACTOR'),

    'image_matching' => [
        'dimensions' => (int) env('DESIGNHUB_IMAGE_EMBEDDING_DIMENSIONS', 512),
        'timeout_seconds' => (int) env('DESIGNHUB_IMAGE_MATCHING_TIMEOUT', 30),
        'preprocessing' => [
            'max_dimension' => (int) env('DESIGNHUB_IMAGE_EMBEDDING_MAX_DIMENSION', 1600),
            'jpeg_quality' => (int) env('DESIGNHUB_IMAGE_EMBEDDING_JPEG_QUALITY', 85),
        ],
        'retry' => [
            'attempts' => (int) env('DESIGNHUB_IMAGE_EMBEDDING_RETRY_ATTEMPTS', 3),
            'base_delay_ms' => (int) env('DESIGNHUB_IMAGE_EMBEDDING_RETRY_BASE_DELAY_MS', 1000),
        ],
        'index_delay_ms' => (int) env('DESIGNHUB_IMAGE_INDEX_DELAY_MS', 1000),
        'gemini' => [
            'enabled' => env('DESIGNHUB_GEMINI_IMAGE_SEARCH_ENABLED', false),
            'project_id' => env('DESIGNHUB_GOOGLE_PROJECT_ID'),
            'location' => env('DESIGNHUB_GOOGLE_LOCATION', 'global'),
            'model' => env('DESIGNHUB_GEMINI_EMBEDDING_MODEL', 'gemini-embedding-2'),
            'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        ],
    ],

    'razorpay_key_id' => env('RAZORPAY_KEY_ID'),
    'razorpay_key_secret' => env('RAZORPAY_KEY_SECRET'),
    'razorpay_webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    'payment_environment' => env('PAYMENT_MODE', 'test'),
];
