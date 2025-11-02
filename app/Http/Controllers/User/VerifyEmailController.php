<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Models\User;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['message' => 'Liên kết xác thực không hợp lệ hoặc đã hết hạn.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url') . '/verified?status=already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url') . '/verified?status=success');
    }
}
