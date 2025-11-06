<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use App\Mail\VerifyEmailMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @OA\Tag(name="User Auth", description="Authentication for regular users")
     */

    /**
     * @OA\Post(
     *     path="/api/user/register",
     *     tags={"User Auth"},
     *     summary="Đăng ký người dùng",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="full_name", type="string", example="Nguyen Van A"),
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", example="secret123"),
     *             @OA\Property(property="phone_number", type="string", example="0912345678")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Đăng ký thành công"),
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
        ], [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'phone_number.regex' => 'Số điện thoại không hợp lệ.',
            'phone_number.unique' => 'Số điện thoại đã tồn tại.',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'user';

        $user = User::create($data);

        // Gửi email xác minh
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(15),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::to($user->email)->send(new VerifyEmailMail($user, $verifyUrl));

        return response()->json([
            'message' => 'Đăng ký thành công! Vui lòng kiểm tra email để xác nhận tài khoản.'
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/user/forgot-password",
     *     tags={"User Auth"},
     *     summary="Quên mật khẩu",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(@OA\Property(property="email", type="string", example="user@example.com"))
     *     ),
     *     @OA\Response(response=200, description="Email đã được gửi"),
     *     @OA\Response(response=422, description="Email không tồn tại")
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.exists' => 'Email này chưa được đăng ký.'
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Email khôi phục mật khẩu đã được gửi.'])
            : response()->json(['message' => 'Không thể gửi email.'], 500);
    }

    /**
     * @OA\Post(
     *     path="/api/user/reset-password",
     *     tags={"User Auth"},
     *     summary="Đặt lại mật khẩu",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Đặt lại thành công"),
     *     @OA\Response(response=400, description="Token không hợp lệ")
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
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
