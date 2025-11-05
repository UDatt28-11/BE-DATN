<?php

namespace App\Http\Controllers\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="DATN API",
 *         version="1.0.0",
 *         description="API for DATN project"
 *     ),
 *     @OA\Server(
 *         url="http://127.0.0.1:8000",
 *         description="Local server"
 *     )
 * )
 */
class OpenApiSpec {}
