<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Authentication"},
     *     summary="Đăng nhập (Admin, Staff, User)",
     *     description="Xác thực người dùng và trả về token + role",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Đăng nhập thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Thông tin không chính xác"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.']
            ]);
        }

        // Kiểm tra user bị khóa
        if ($user->status === 'locked') {
            throw ValidationException::withMessages([
                'email' => [
                    'Tài khoản của bạn đã bị khóa.',
                    'Lý do: ' . ($user->ly_do_block ?? 'Không xác định'),
                    $user->block_den_ngay ? 'Khóa đến: ' . $user->block_den_ngay : '',
                ]
            ]);
        }

        // Xóa token cũ
        $user->tokens()->delete();

        // Tạo token mới với abilities dựa trên role
        $abilities = ['role:' . $user->role];
        $token = $user->createToken('app_token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user->only(['id', 'full_name', 'email', 'role', 'phone_number', 'avatar']),
            'token' => $token,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Authentication"},
     *     summary="Đăng xuất",
     *     description="Xóa token hiện tại",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Đăng xuất thành công")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }
}
