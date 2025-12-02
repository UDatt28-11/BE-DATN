<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // Các origin FE được phép gọi API
    'allowed_origins' => [
        // URL frontend chính (có thể là trycloudflare/ngrok tùy env)
        env('FRONTEND_URL', 'http://localhost:5173'),
        // Local development
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    // Cho phép các domain động (VD: Cloudflare tunnel)
    'allowed_origins_patterns' => [
        '^https://.*\.trycloudflare\.com$',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Đảm bảo đây LÀ 'true'
    'supports_credentials' => true,
];
