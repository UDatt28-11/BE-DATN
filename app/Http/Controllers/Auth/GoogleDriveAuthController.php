<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GoogleDriveAuthController extends Controller
{
    /**
     * Chuyển hướng đến Google OAuth
     */
    public function redirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
        $provider = Socialite::driver('google');
        return $provider
            ->scopes(['https://www.googleapis.com/auth/drive.file'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->stateless()
            ->redirect();
    }

    /**
     * Xử lý callback từ Google
     */
    public function callback()
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->email],
                [
                    'full_name' => $googleUser->name,
                    'password' => bcrypt(str()->random(40)),
                    'email_verified_at' => now(),
                ]
            );

            $user->googleToken()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $googleUser->token,
                    'refresh_token' => $googleUser->refreshToken,
                    'expires_at' => isset($googleUser->expiresIn) ? now()->addSeconds($googleUser->expiresIn) : now()->addHour(),
                ]
            );

            Auth::login($user, true);
            $apiToken = $user->createToken('auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                ],
                'token' => $apiToken,
                'message' => 'Đăng nhập thành công!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage(),
            ], 400);
        }
    }
}
