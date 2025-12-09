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
     * Frontend callback URL
     */
    protected function getFrontendCallbackUrl(): string
    {
        return env('FRONTEND_URL', 'http://localhost:5173') . '/auth/google/callback';
    }

    /**
     * @OA\Get(
     *     path="/api/google/redirect/{role}",
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

        // Tạo state chứa role để lưu trữ qua OAuth flow
        $state = base64_encode(json_encode(['role' => $role]));

        $redirectUrl = Socialite::driver('google')
            ->stateless()
            ->with([
                'state' => $state,
                'prompt' => 'select_account', // Luôn hiển thị màn hình chọn tài khoản
            ])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
            'role' => $role
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/google/callback/{role}",
     *     summary="Xử lý callback từ Google sau khi đăng nhập",
     *     description="Sau khi người dùng đăng nhập Google, Google sẽ redirect về URL này. Hệ thống sẽ tự động tạo tài khoản nếu chưa có, và redirect về frontend với token.",
     *     tags={"Google Login"},
     *     @OA\Parameter(
     *         name="role",
     *         in="path",
     *         required=true,
     *         description="Vai trò của người dùng (user, staff, admin)",
     *         @OA\Schema(type="string", enum={"user", "staff", "admin"})
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirect về frontend với token"
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
    public function handleGoogleCallback(Request $request, $role = null)
    {
        $frontendCallbackUrl = $this->getFrontendCallbackUrl();

        try {
            // Lấy role từ state parameter nếu không có trong URL
            $state = $request->get('state');
            if ($state) {
                try {
                    $stateData = json_decode(base64_decode($state), true);
                    if (!$role || !in_array($role, ['user', 'staff', 'admin'])) {
                        $role = $stateData['role'] ?? 'user';
                    }
                } catch (\Exception $e) {
                    // Ignore state parse error
                }
            }
            
            // Default role nếu vẫn chưa có
            if (!$role || !in_array($role, ['user', 'staff', 'admin'])) {
                $role = 'user';
            }

            // Kiểm tra nếu có lỗi từ Google
            if ($request->has('error')) {
                return redirect($frontendCallbackUrl . '?error=' . urlencode($request->get('error')));
            }

            // Lấy user info từ Google
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Tạo hoặc cập nhật user
            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'full_name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                    'password' => bcrypt(Str::random(16)),
                    'role' => $role,
                ]
            );

            // Tạo token với role abilities
            $token = $user->createToken('auth_token', ['role:' . $user->role])->plainTextToken;

            // Encode user data để truyền qua URL
            $userData = base64_encode(json_encode([
                'id' => $user->id,
                'full_name' => $user->full_name,
                'name' => $user->full_name, // Alias for frontend compatibility
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar_url,
                'avatar_url' => $user->avatar_url,
            ]));

            // Redirect về frontend với token và user data
            return redirect($frontendCallbackUrl . '?' . http_build_query([
                'token' => $token,
                'user' => $userData,
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công!',
            ]));

        } catch (\Exception $e) {
            \Log::error('Google OAuth Error: ' . $e->getMessage());
            
            return redirect($frontendCallbackUrl . '?' . http_build_query([
                'status' => 'error',
                'message' => 'Đăng nhập Google thất bại: ' . $e->getMessage(),
            ]));
        }
    }
}
