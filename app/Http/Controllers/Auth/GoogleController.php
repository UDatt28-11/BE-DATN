<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Google Login",
 *     description="Đăng nhập và đăng ký bằng tài khoản Google cho các vai trò khác nhau (user, staff, admin)"
 * )
 */
class GoogleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/{role}/google/redirect",
     *     summary="Lấy URL đăng nhập Google (tự động redirect sang Google)",
     *     description="Trả về URL mà frontend dùng để redirect người dùng sang trang đăng nhập Google. Role có thể là user, staff hoặc admin.",
     *     tags={"Google Login"},
     *     @OA\Parameter(
     *         name="role",
     *         in="path",
     *         required=true,
     *         description="Vai trò của người dùng (user, staff, admin)",
     *         @OA\Schema(type="string", enum={"user", "staff", "admin"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="URL redirect thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", example="https://accounts.google.com/o/oauth2/auth?..."),
     *             @OA\Property(property="role", type="string", example="user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Role không hợp lệ"
     *     )
     * )
     */
    public function redirectToGoogle($role)
    {
        if (!in_array($role, ['user', 'staff', 'admin'])) {
            return response()->json(['message' => 'Role không hợp lệ'], 400);
        }

        $redirectUrl = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
            'role' => $role
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/{role}/google/callback",
     *     summary="Xử lý callback từ Google sau khi đăng nhập",
     *     description="Sau khi người dùng đăng nhập Google, Google sẽ redirect về URL này. Hệ thống sẽ tự động tạo tài khoản nếu chưa có, và trả về token đăng nhập.",
     *     tags={"Google Login"},
     *     @OA\Parameter(
     *         name="role",
     *         in="path",
     *         required=true,
     *         description="Vai trò của người dùng (user, staff, admin)",
     *         @OA\Schema(type="string", enum={"user", "staff", "admin"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Đăng nhập thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đăng nhập Google thành công!"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Nguyễn Văn A"),
     *                 @OA\Property(property="email", type="string", example="vana@gmail.com"),
     *                 @OA\Property(property="role", type="string", example="user"),
     *                 @OA\Property(property="avatar", type="string", example="https://lh3.googleusercontent.com/a/..."),
     *             ),
     *             @OA\Property(property="token", type="string", example="2|Kq0d8gZyR3YhF...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Role không hợp lệ"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Lỗi hệ thống hoặc lỗi xác thực Google"
     *     )
     * )
     */
    public function handleGoogleCallback(Request $request, $role)
    {
        try {
            if (!in_array($role, ['user', 'staff', 'admin'])) {
                return response()->json(['message' => 'Role không hợp lệ'], 400);
            }

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => bcrypt(Str::random(16)),
                    'role' => $role,
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Đăng nhập Google thành công!',
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi Google login: ' . $e->getMessage(),
            ], 500);
        }
    }
}
