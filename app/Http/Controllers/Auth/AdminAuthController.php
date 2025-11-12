<?php

namespace App\Http\Controllers\Auth;

use App\Events\LoginSuccessful;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)
                        ->where('role', 'admin')
                        ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Thông tin đăng nhập không chính xác.']
                ]);
            }

            // Xóa token hiện tại (nếu có)
            try {
                if ($request->user()?->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                }
            } catch (\Exception $e) {
                // Ignore error khi xóa token cũ
            }

            // Tạo token mới
            $token = $user->createToken('auth_token', ['role:admin'])->plainTextToken;

            // Bọc event trong try-catch để không làm crash login nếu có lỗi
            try {
                event(new LoginSuccessful($user, 'admin'));
            } catch (\Exception $e) {
                // Log lỗi nhưng không làm crash login
                \Log::warning('Failed to log login activity: ' . $e->getMessage());
            }

            // Trả về response đơn giản, không dùng UserResource để tránh lỗi
            $userData = [
                'id' => $user->id,
                'full_name' => $user->full_name ?? null,
                'email' => $user->email,
                'role' => $user->role,
                'phone_number' => $user->phone_number ?? null,
            ];

            return response()->json([
                'message' => 'Đăng nhập thành công',
                'user'    => $userData,
                'token'   => $token,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Admin login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi đăng nhập. Vui lòng thử lại.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function logout()
    {
        $user = Auth::user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Đăng xuất thành công']);
    }
}
