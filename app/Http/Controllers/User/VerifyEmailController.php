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
            // Redirect về frontend với thông báo lỗi
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            return redirect($frontendUrl . '/verified?status=error&message=' . urlencode('Người dùng không tồn tại.'));
        }

        // 2. Kiểm tra hash (chuẩn Laravel)
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            // Redirect về frontend với thông báo lỗi
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            return redirect($frontendUrl . '/verified?status=error&message=' . urlencode('Liên kết xác thực không hợp lệ hoặc đã hết hạn.'));
        }

        // 3. Đã xác thực rồi?
        if ($user->hasVerifiedEmail()) {
            // Redirect về frontend với status already
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            return redirect($frontendUrl . '/verified?status=already');
        }

        // 4. Xác thực thành công
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // Redirect về frontend với status success
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        return redirect($frontendUrl . '/verified?status=success');
    }
}
