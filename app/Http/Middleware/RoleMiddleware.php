<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Kiểm tra vai trò của người dùng.
     * Sử dụng: middleware(['auth:sanctum', 'role:admin'])
     * Hoặc: middleware(['auth:sanctum', 'role:admin,staff'])
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Lấy user từ sanctum token
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(['message' => 'Chưa đăng nhập.'], 401);
        }

        // Kiểm tra role
        if (!in_array($user->role ?? '', $roles)) {
            return response()->json(['message' => 'Bạn không có quyền truy cập.'], 403);
        }

        // Nếu là user và bị khóa
        if (($user->role ?? '') === 'user' && ($user->status ?? '') === 'locked') {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị quản trị viên khóa.',
                'ly_do_block' => $user->ly_do_block ?? null,
                'block_den_ngay' => $user->block_den_ngay ?? null,
            ], 403);
        }

        return $next($request);
    }
}
