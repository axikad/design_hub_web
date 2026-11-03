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
        'qdrant_url' => env('DESIGNHUB_QDRANT_URL'),
        'qdrant_api_key' => env('DESIGNHUB_QDRANT_API_KEY'),
        'collection' => env('DESIGNHUB_QDRANT_COLLECTION', 'design_images'),
        'model' => env('DESIGNHUB_IMAGE_EMBEDDING_MODEL', 'qdrant/clip-vit-b-32-vision'),
        'dimensions' => (int) env('DESIGNHUB_IMAGE_EMBEDDING_DIMENSIONS', 512),
        'timeout_seconds' => (int) env('DESIGNHUB_IMAGE_MATCHING_TIMEOUT', 30),
    ],

    'razorpay_key_id' => env('RAZORPAY_KEY_ID'),
    'razorpay_key_secret' => env('RAZORPAY_KEY_SECRET'),
    'razorpay_webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    'payment_environment' => env('PAYMENT_MODE', 'test'),
];
