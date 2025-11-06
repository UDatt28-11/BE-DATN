<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // SỬA MỤC NÀY:
    // Đảm bảo cả hai biến thể của React đều có ở đây
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'), // Dòng này bạn đã có
        'http://127.0.0.1:5173', // <-- Thêm dòng này để cho chắc chắn
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Đảm bảo đây LÀ 'true'
    'supports_credentials' => true,
];
