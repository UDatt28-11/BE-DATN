<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // DÙNG GUARD 'sanctum' THAY VÌ MẶC ĐỊNH
        if (!Auth::guard('sanctum')->check()) {
            return response()->json(['message' => 'Chưa đăng nhập.'], 401);
        }

        $user = Auth::guard('sanctum')->user();

        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Bạn không có quyền truy cập.'], 403);
        }

        if ($user->role === 'user' && $user->status === 'locked') {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa.',
                'ly_do_block' => $user->ly_do_block,
                'block_den_ngay' => $user->block_den_ngay,
            ], 403);
        }

        return $next($request);
    }
}
