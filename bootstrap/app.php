<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // XÓA HOẶC COMMENT DÒNG "web:" NÀY ĐI
        web: __DIR__.'/../routes/web.php',

        // CHỈ GIỮ LẠI CÁC DÒNG NÀY
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Đây là cấu hình middleware cho API (đã sửa)
        $middleware->group('api', [
            // Bật Sanctum cho SPA
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

            // Dòng này đã gây lỗi 500, chúng ta đã xóa
            // 'throttle:api',

            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
