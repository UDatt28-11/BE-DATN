<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, $token)
    {
        $email = $request->query('email');
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        
        // Redirect đến trang reset password trên frontend
        return redirect("{$frontendUrl}/reset-password?token={$token}&email={$email}");
    }
}
