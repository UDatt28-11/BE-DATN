<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    public function showResetForm($token)
    {
        return response()->json([
            'message' => 'Hiển thị form reset password',
            'token' => $token,
        ]);
    }
}
