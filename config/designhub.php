<?php

return [
    'otp_length' => 4,
    'otp_ttl_minutes' => 10,
    'otp_max_attempts' => 5,
    'otp_resend_seconds' => 30,
    'otp_log_code' => env('DESIGNHUB_OTP_LOG', true),
    'otp_static_code' => env('DESIGNHUB_OTP_STATIC_CODE'),

    'upload_max_photo_bytes' => 5 * 1024 * 1024,
    'upload_max_stitch_bytes' => 5 * 1024 * 1024,
    'upload_photo_mimes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
    'upload_stitch_mimes' => ['application/octet-stream', 'application/x-binary'],
    'thumbnail_max_width' => 480,
    'thumbnail_max_height' => 480,

    'download_url_ttl_minutes' => 15,

    'razorpay_key_id' => env('RAZORPAY_KEY_ID'),
    'razorpay_key_secret' => env('RAZORPAY_KEY_SECRET'),
    'razorpay_webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
];
