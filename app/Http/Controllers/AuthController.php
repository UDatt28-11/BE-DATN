<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for Authentication"
 * )
 */
class AuthController extends Controller
{
    /**
     * Register new user
     * 
     * @OA\Post(
     *     path="/register",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *     summary="Đăng ký tài khoản",
     *     description="Đăng ký tài khoản mới",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Dữ liệu đăng ký",
     *         @OA\JsonContent(
     *             required={"full_name","email","password","password_confirmation"},
     *             @OA\Property(property="full_name", type="string", example="Nguyễn Văn A", description="Tên đầy đủ"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com", description="Email"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Mật khẩu (ít nhất 8 ký tự)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="Xác nhận mật khẩu"),
     *             @OA\Property(property="phone_number", type="string", example="0987654321", description="Số điện thoại (optional)"),
     *             @OA\Property(property="address", type="string", example="123 Nguyễn Huệ, HCM", description="Địa chỉ (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Đăng ký thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đăng ký thành công"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="full_name", type="string", example="Nguyễn Văn A"),
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="role", type="string", example="user")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc...")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:100',
                'email' => 'required|string|email|unique:users,email|max:100',
                'password' => 'required|string|min:8|confirmed',
                'phone_number' => 'nullable|string|unique:users,phone_number|max:20',
                'address' => 'nullable|string|max:255',
            ]);

            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'role' => 'user',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký thành công',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi đăng ký: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     * 
     * @OA\Post(
     *     path="/login",
     *     operationId="loginUser",
     *     tags={"Authentication"},
     *     summary="Đăng nhập",
     *     description="Đăng nhập với email và mật khẩu",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Thông tin đăng nhập",
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com", description="Email"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Mật khẩu")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Đăng nhập thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đăng nhập thành công"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="full_name", type="string", example="Nguyễn Văn A"),
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="role", type="string", example="user")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc...")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Email hoặc mật khẩu không đúng")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email hoặc mật khẩu không đúng'
                ], 401);
            }

            if ($user->status === 'locked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản của bạn đã bị khóa'
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi đăng nhập: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user profile
     * 
     * @OA\Get(
     *     path="/me",
     *     operationId="getCurrentUser",
     *     tags={"Authentication"},
     *     summary="Lấy thông tin người dùng hiện tại",
     *     description="Lấy thông tin profile của người dùng đã đăng nhập",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Thông tin người dùng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="full_name", type="string", example="Nguyễn Văn A"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="phone_number", type="string", example="0987654321"),
     *                 @OA\Property(property="role", type="string", example="user"),
     *                 @OA\Property(property="status", type="string", example="active")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
                'status' => $user->status,
            ]
        ]);
    }

    /**
     * Logout user
     * 
     * @OA\Post(
     *     path="/logout",
     *     operationId="logoutUser",
     *     tags={"Authentication"},
     *     summary="Đăng xuất",
     *     description="Đăng xuất tài khoản hiện tại",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Đăng xuất thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đăng xuất thành công")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công'
        ]);
    }
}
