<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    /**
     * Hiển thị form reset password - Redirect về frontend
     * 
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function showResetForm(Request $request, $token)
    {
        $email = $request->query('email');
        
        // Redirect về frontend với token và email
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        
        if ($email) {
            return redirect($frontendUrl . '/reset-password/' . $token . '?email=' . urlencode($email));
        } else {
            // Nếu không có email, vẫn redirect nhưng frontend sẽ yêu cầu nhập email
            return redirect($frontendUrl . '/reset-password/' . $token);
        }
    }
}
