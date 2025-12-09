<?php

namespace App\Http\Controllers\Auth;

use App\Events\LoginSuccessful;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    /**
     * Unified Login - Tự động phát hiện role của user
     * Chỉ cần email + password, backend sẽ trả về user với role tương ứng
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Tìm user theo email, không quan tâm role
        $user = User::where('email', $request->email)->first();

        // Kiểm tra user tồn tại và mật khẩu đúng
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không chính xác.']
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

        // Tạo token với role ability
        $token = $user->createToken('auth_token', ['role:' . $user->role])->plainTextToken;

        event(new LoginSuccessful($user, $user->role));

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Đăng ký tài khoản mới (chỉ cho user thường)
     */
    public function register(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone_number' => 'nullable|string|regex:/^[0-9]{9,11}$/',
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'role' => 'user', // Đăng ký mới luôn là user
            'status' => 'active',
        ]);

        // Tạo token luôn
        $token = $user->createToken('auth_token', ['role:user'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công!',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Quên mật khẩu - gửi email reset
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'Email này không tồn tại trong hệ thống.',
        ]);

        $user = User::where('email', $request->email)->first();

        // TODO: Gửi email reset password
        // Tạm thời chỉ trả về success
        // Trong production, cần implement gửi email với token reset

        return response()->json([
            'success' => true,
            'message' => 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu.',
        ]);
    }

    /**
     * Đặt lại mật khẩu
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // TODO: Validate reset token
        // Tạm thời update trực tiếp

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Email không tồn tại.']
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mật khẩu đã được đặt lại thành công.',
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công',
        ]);
    }

    /**
     * Lấy thông tin user hiện tại
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => new UserResource($request->user()),
        ]);
    }
}

