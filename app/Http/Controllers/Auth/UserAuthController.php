<?php
// app/Http/Controllers/Auth/AdminAuthController.php

namespace App\Http\Controllers\Auth;

use App\Events\LoginSuccessful;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UserLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    /** Login – chỉ user */
    public function login(UserLoginRequest $request)
    {
        $user = User::where('email', $request->email)
                    ->where('role', 'user')
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.']
            ]);
        }

        // Kiểm tra user bị khóa - KHÔNG cho phép đăng nhập nếu bị locked
        if ($user->status === 'locked') {
            throw ValidationException::withMessages([
                'email' => [
                    'Tài khoản của bạn đã bị khóa.',
                    'Lý do: ' . ($user->ly_do_block ?? 'Không xác định'),
                    $user->block_den_ngay ? 'Khóa đến: ' . $user->block_den_ngay : '',
                ]
            ]);
        }

        // Xóa token cũ của thiết bị hiện tại
        $user->tokens()->where('id', $request->user()?->currentAccessToken()?->id)->delete();

        // Tạo token có ability
        $token = $user->createToken('auth_token', ['role:user'])->plainTextToken;

        event(new LoginSuccessful($user, 'user'));

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user'    => new UserResource($user),
            'token'   => $token,
        ]);
    }

    /** Logout – dùng chung */
    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }
}
