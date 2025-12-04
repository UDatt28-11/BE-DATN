<?php
// app/Http/Controllers/User/VerifyEmailController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Models\User;

class VerifyEmailController extends Controller
{
    /**
     * Xác thực email qua API
     *
     * @param Request $request
     * @param int $id
     * @param string $hash
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request, $id, $hash)
    {
        // 1. Tìm user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Người dùng không tồn tại.'
            ], 404);
        }

        // 2. Kiểm tra hash (chuẩn Laravel)
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Liên kết xác thực không hợp lệ hoặc đã hết hạn.'
            ], 400);
        }

        // 3. Đã xác thực rồi?
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email đã được xác thực trước đó.',
                'redirect' => config('app.frontend_url') . '/verified?status=already'
            ], 200);
        }

        // 4. Xác thực thành công
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Xác thực email thành công!',
            'redirect' => config('app.frontend_url') . '/verified?status=success'
        ], 200);
    }
}
