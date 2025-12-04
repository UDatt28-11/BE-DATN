<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminPasswordReset;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminPasswordResetController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send OTP to admin email
     */
    public function sendOtp(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'email' => 'required|email|exists:users,email',
            ], [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không hợp lệ.',
                'email.exists' => 'Email không tồn tại trong hệ thống.',
            ]);

            // Check if user is admin
            $user = User::where('email', $validatedData['email'])
                ->where('role', 'admin')
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email này không phải là tài khoản admin.',
                ], 404);
            }

            // Generate OTP
            $otp = AdminPasswordReset::generateOtp($validatedData['email']);

            // Send OTP via email
            $sent = $this->emailService->sendEmail(
                $validatedData['email'],
                'Mã OTP đặt lại mật khẩu',
                "Mã OTP của bạn là: <strong>{$otp}</strong><br>Mã này có hiệu lực trong 10 phút."
            );

            if (!$sent) {
                Log::error('Failed to send OTP email', [
                    'email' => $validatedData['email'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Không thể gửi email OTP. Vui lòng thử lại sau.',
                ], 500);
            }

            Log::info('OTP sent to admin', [
                'email' => $validatedData['email'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mã OTP đã được gửi đến email của bạn.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AdminPasswordResetController@sendOtp failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi OTP.',
            ], 500);
        }
    }

    /**
     * Verify OTP and reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:6|confirmed',
            ], [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không hợp lệ.',
                'email.exists' => 'Email không tồn tại trong hệ thống.',
                'otp.required' => 'Vui lòng nhập mã OTP.',
                'otp.size' => 'Mã OTP phải có 6 chữ số.',
                'password.required' => 'Vui lòng nhập mật khẩu mới.',
                'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
                'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            ]);

            // Check if user is admin
            $user = User::where('email', $validatedData['email'])
                ->where('role', 'admin')
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email này không phải là tài khoản admin.',
                ], 404);
            }

            // Verify OTP
            $passwordReset = AdminPasswordReset::where('email', $validatedData['email'])
                ->where('otp', $validatedData['otp'])
                ->valid()
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn.',
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($validatedData['password']),
            ]);

            // Mark OTP as used
            $passwordReset->markAsUsed();

            Log::info('Admin password reset successful', [
                'email' => $validatedData['email'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đặt lại mật khẩu thành công.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AdminPasswordResetController@resetPassword failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đặt lại mật khẩu.',
            ], 500);
        }
    }
}

