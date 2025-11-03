<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GoogleController extends Controller
{
    // Bước 1: Redirect sang Google
    public function redirectToGoogle($role)
    {
        if (!in_array($role, ['user', 'staff', 'admin'])) {
            return response()->json(['message' => 'Role không hợp lệ'], 400);
        }

        $redirectUrl = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
            'role' => $role
        ]);
    }

    // Bước 2: Xử lý callback
    public function handleGoogleCallback(Request $request, $role)
    {
        try {
            if (!in_array($role, ['user', 'staff', 'admin'])) {
                return response()->json(['message' => 'Role không hợp lệ'], 400);
            }

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => bcrypt(Str::random(16)),
                    'role' => $role,
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Đăng nhập Google thành công!',
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi Google login: ' . $e->getMessage(),
            ], 500);
        }
    }
}
