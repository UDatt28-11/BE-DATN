<?php

namespace App\Http\Controllers\Auth;

use App\Events\LoginSuccessful;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    public function login(StaffLoginRequest $request)
    {
        $user = User::where('email', $request->email)
                    ->where('role', 'staff')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.']
            ]);
        }

        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        $token = $user->createToken('auth_token', ['role:staff'])->plainTextToken;

        event(new LoginSuccessful($user, 'staff'));

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user'    => new UserResource($user),
            'token'   => $token,
        ]);
    }

    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }
}
