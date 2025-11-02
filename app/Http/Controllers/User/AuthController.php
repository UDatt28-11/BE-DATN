<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use App\Mail\VerifyEmailMail;

class AuthController extends Controller
{
    /**
     * @OA\Tag(
     *     name="User Auth",
     *     description="Authentication endpoints for users"
     * )
     */
    /**
     * Đăng ký người dùng
     */
    /**
     * @OA\Post(
     *     path="/api/user/register",
     *     tags={"User Auth"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="full_name", type="string", example="Nguyen Van A"),
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", example="secret123"),
     *             @OA\Property(property="phone_number", type="string", example="0912345678")
     *         )
     *     ),
     *     @OA\Response(response=201, description="User registered", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="user", type="object"))),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone_number' => 'nullable|regex:/^[0-9]{9,11}$/|unique:users,phone_number',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'user';

        $user = User::create($data);

        // Tạo link xác minh hợp lệ trong 15 phút
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(15),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Gửi email xác nhận
        Mail::to($user->email)->send(new VerifyEmailMail($user, $verifyUrl));

        return response()->json([
            'message' => 'Đăng ký thành công! Vui lòng kiểm tra email để xác nhận tài khoản.'
        ], 201);
    }

    /**
     * Đăng nhập người dùng
     */
    /**
     * @OA\Post(
     *     path="/api/user/login",
     *     tags={"User Auth"},
     *     summary="User login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful login", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="user", type="object"), @OA\Property(property="token", type="string"))),
     *     @OA\Response(response=401, description="Unauthorized - invalid credentials"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'user')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.']
            ]);
        }

        // Xóa token cũ (nếu có)
        $user->tokens()->delete();

        // Tạo token mới
        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Đăng xuất
     */
    /**
     * @OA\Post(
     *     path="/api/user/logout",
     *     tags={"User Auth"},
     *     summary="Logout user",
     *     description="Invalidate current access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out", @OA\JsonContent(@OA\Property(property="message", type="string")))
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    /**
     * Gửi email quên mật khẩu
     */
    /**
     * @OA\Post(
     *     path="/api/user/forgot-password",
     *     tags={"User Auth"},
     *     summary="Gửi email chứa liên kết đặt lại mật khẩu",
     *     description="Người dùng nhập email, hệ thống sẽ gửi một liên kết đặt lại mật khẩu có chứa token hợp lệ qua email.",
     *
     *     @OA\Parameter(
     *         name="Accept",
     *         in="header",
     *         required=true,
     *         description="Định dạng phản hồi, luôn là application/json",
     *         @OA\Schema(type="string", default="application/json")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email người dùng cần khôi phục mật khẩu",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"email"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="user@example.com"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email khôi phục mật khẩu đã được gửi thành công."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Email không tồn tại hoặc không hợp lệ."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Lỗi hệ thống hoặc lỗi khi gửi email."
     *     )
     * )
     */

    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ], [
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không hợp lệ.',
                'email.exists' => 'Email này chưa được đăng ký.'
            ]);

            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json(['message' => 'Email khôi phục mật khẩu đã được gửi.']);
            }

            Log::error('Failed to send password reset link: ' . $status);
            return response()->json([
                'message' => 'Không thể gửi email khôi phục.',
                'error' => $status
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error in forgotPassword: ' . $e->getMessage());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi xử lý yêu cầu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/user/reset-password",
     *     tags={"User Auth"},
     *     summary="Đặt lại mật khẩu bằng token",
     *     description="Người dùng gửi token + email + mật khẩu mới để đặt lại mật khẩu.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="abc123"),
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="newpassword"),
     *             @OA\Property(property="password_confirmation", type="string", example="newpassword")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Đặt lại mật khẩu thành công."),
     *     @OA\Response(response=400, description="Token không hợp lệ hoặc đã hết hạn."),
     *     @OA\Response(response=422, description="Dữ liệu không hợp lệ.")
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ], [
            'token.required' => 'Thiếu mã xác nhận.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.exists' => 'Email này chưa được đăng ký.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.'
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Đặt lại mật khẩu thành công.'])
            : response()->json(['message' => 'Mã xác nhận không hợp lệ hoặc đã hết hạn.'], 400);
    }
}
