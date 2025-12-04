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
        // Fallback: Nếu token không có abilities, kiểm tra role từ user model
        $hasPermission = false;
        $token = $user->currentAccessToken();
        
        // Debug: Log token abilities
        $tokenAbilities = $token ? $token->abilities : [];
        \Log::info('RoleMiddleware: Checking permissions', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'required_roles' => $roles,
            'token_abilities' => $tokenAbilities,
            'request_path' => $request->path(),
        ]);
        
        // Kiểm tra token abilities trước
        if ($token && !empty($tokenAbilities)) {
            foreach ($roles as $role) {
                $requiredAbility = "role:{$role}";
                // Kiểm tra bằng tokenCan() và in_array() để đảm bảo
                $canViaTokenCan = $user->tokenCan($requiredAbility);
                $canViaInArray = in_array($requiredAbility, $tokenAbilities, true);
                
                if ($canViaTokenCan || $canViaInArray) {
                    $hasPermission = true;
                    \Log::info('RoleMiddleware: Permission granted via token ability', [
                        'role' => $role,
                        'ability' => $requiredAbility,
                        'tokenCan' => $canViaTokenCan,
                        'inArray' => $canViaInArray,
                    ]);
                    break;
                }
            }
        }
        
        // Fallback: Nếu token không có abilities hoặc không match, kiểm tra role từ user model
        // QUAN TRỌNG: Luôn kiểm tra user role như một fallback an toàn
        if (!$hasPermission && $user->role) {
            foreach ($roles as $role) {
                if ($user->role === $role) {
                    $hasPermission = true;
                    \Log::info('RoleMiddleware: Permission granted via user role (fallback)', [
                        'user_role' => $user->role,
                        'required_role' => $role,
                        'token_abilities' => $tokenAbilities,
                    ]);
                    break;
                }
            }
        }

        if (!$hasPermission) {
            // Log để debug
            \Log::warning('RoleMiddleware: User không có quyền', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'required_roles' => $roles,
                'token_abilities' => $token ? $token->abilities : null,
                'request_path' => $request->path(),
                'request_method' => $request->method(),
            ]);
            
            return response()->json([
                'message' => 'Bạn không có quyền truy cập.',
                'debug' => config('app.debug') ? [
                    'user_role' => $user->role,
                    'required_roles' => $roles,
                    'token_abilities' => $token ? $token->abilities : null,
                ] : null,
            ], 403);
        }

        // 3. Kiểm tra user bị khóa (chỉ áp dụng cho user)
        // Cho phép user bị locked xem bookings của mình (GET requests)
        // Nhưng chặn các thao tác tạo mới hoặc cập nhật (POST, PUT, PATCH, DELETE)
        if ($user->role === 'user' && $user->status === 'locked') {
            $allowedMethods = ['GET', 'HEAD', 'OPTIONS'];
            if (!in_array($request->method(), $allowedMethods)) {
                return response()->json([
                    'message'        => 'Tài khoản của bạn đã bị khóa. Bạn không thể thực hiện thao tác này.',
                    'ly_do_block'    => $user->ly_do_block,
                    'block_den_ngay' => $user->block_den_ngay,
                ], 403);
            }
        }

        return $next($request);
    }
}
