<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @OA\Tag(
     *     name="Admin Auth",
     *     description="Authentication endpoints for admin"
     * )
     */
    /**
     * Đăng nhập Admin
     * 
     * @OA\Post(
     *     path="/api/admin/login",
     *     tags={"Admin Auth"},
     *     summary="Login admin",
     *     description="Authenticate admin and return a bearer token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email", type="string", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful login", @OA\JsonContent(
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="user", type="object"),
     *         @OA\Property(property="token", type="string")
     *     )),
     *     @OA\Response(response=401, description="Unauthorized - invalid credentials"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6'
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'admin')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác hoặc bạn không có quyền admin.']
            ]);
        }

        // Xóa token cũ
        $user->tokens()->delete();

        // Tạo token mới
        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Đăng xuất Admin
     * 
     * @OA\Post(
     *     path="/api/admin/logout",
     *     tags={"Admin Auth"},
     *     summary="Logout admin",
     *     description="Invalidate current admin access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out", @OA\JsonContent(
     *         @OA\Property(property="message", type="string")
     *     ))
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }
}
