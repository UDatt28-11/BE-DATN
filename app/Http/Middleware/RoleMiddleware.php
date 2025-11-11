<?php
// app/Http/Middleware/RoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Kiểm tra Sanctum token
        if (!Auth::guard('sanctum')->check()) {
            return response()->json([
                'message' => 'Chưa đăng nhập.'
            ], 401);
        }

        $user = Auth::guard('sanctum')->user();

        // 2. Kiểm tra role trong token (KHÔNG query DB)
        $hasPermission = false;
        foreach ($roles as $role) {
            if ($user->tokenCan("role:{$role}")) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập.'
            ], 403);
        }

        // 3. Kiểm tra user bị khóa (chỉ áp dụng cho user)
        if ($user->role === 'user' && $user->status === 'locked') {
            return response()->json([
                'message'        => 'Tài khoản của bạn đã bị khóa.',
                'ly_do_block'    => $user->ly_do_block,
                'block_den_ngay' => $user->block_den_ngay,
            ], 403);
        }

        return $next($request);
    }
}
