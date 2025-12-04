<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @OA\Tag(
     *     name="Staff Auth",
     *     description="Authentication endpoints for staff users"
     * )
     */

    /**
     * Đăng ký tài khoản nhân viên
     * 
     * @OA\Post(
     *     path="/api/staff/register",
     *     tags={"Staff Auth"},
     *     summary="Register staff",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="full_name", type="string", example="Nguyen Van B"),
     *             @OA\Property(property="email", type="string", example="staff@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", example="secret123"),
     *             @OA\Property(property="phone_number", type="string", example="0912345678")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Staff registered", @OA\JsonContent(
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="user", type="object")
     *     )),
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
        $data['role'] = 'staff';

        $user = User::create($data);

        return response()->json([
            'message' => 'Đăng ký tài khoản nhân viên thành công',
            'user' => $user
        ], 201);
    }

    /**
     * Đăng nhập dành cho nhân viên
     * 
     * @OA\Post(
     *     path="/api/staff/login",
     *     tags={"Staff Auth"},
     *     summary="Login staff",
     *     description="Authenticate staff and return a bearer token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email", type="string", example="staff@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful login", @OA\JsonContent(
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="user", type="object"),
     *         @OA\Property(property="token", type="string")
     *     )),
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
            ->where('role', 'staff')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác hoặc bạn không phải là nhân viên.']
            ]);
        }

        // Xóa token cũ (nếu có)
        $user->tokens()->delete();

        // Tạo token mới
        $token = $user->createToken('staff_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Đăng xuất nhân viên
     * 
     * @OA\Post(
     *     path="/api/staff/logout",
     *     tags={"Staff Auth"},
     *     summary="Logout staff",
     *     description="Invalidate current staff access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out", @OA\JsonContent(
     *         @OA\Property(property="message", type="string")
     *     ))
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }
}
