<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for User Management"
 * )
 */
class UserController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * Lấy danh sách người dùng
     *
     * @OA\Get(
     *     path="/api/admin/users",
     *     operationId="getUsers",
     *     tags={"Users"},
     *     summary="Danh sách người dùng",
     *     description="Lấy danh sách tất cả người dùng với hỗ trợ tìm kiếm, lọc và phân trang",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo tên, email, số điện thoại",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Lọc theo role",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sắp xếp theo (mặc định: created_at)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Thứ tự sắp xếp (asc/desc, mặc định: desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (mặc định 1)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Số lượng bản ghi mỗi trang (mặc định 20)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách người dùng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
        $search = $request->get('search');
        $status = $request->get('status');
            $role = $request->get('role');
            $identityVerified = $request->get('identity_verified');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = User::query()->with('verifier:id,full_name');

        // Tìm kiếm theo tên, email, số điện thoại
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Lọc theo trạng thái
        if ($status) {
            $query->where('status', $status);
        }

            // Lọc theo role
            if ($role) {
                $query->where('role', $role);
            }

            // Lọc theo identity_verified
            if ($identityVerified !== null) {
                $query->where('identity_verified', filter_var($identityVerified, FILTER_VALIDATE_BOOLEAN));
            }

        // Sắp xếp
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($perPage);

        return response()->json([
                'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ],
            ],
        ]);
        } catch (\Exception $e) {
            Log::error('UserController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách người dùng.',
            ], 500);
        }
    }

    /**
     * Lookup users (for dropdowns)
     *
     * @OA\Get(
     *     path="/api/admin/users/lookup",
     *     operationId="lookupUsers",
     *     tags={"Users"},
     *     summary="Tìm kiếm người dùng (cho dropdown)",
     *     description="Lấy danh sách người dùng để hiển thị trong dropdown",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách người dùng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function lookup(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            // Note: This logic seems incomplete - it references roles but User model might not have roles relationship
            // For now, returning all users with id and full_name
            $users = User::select('id', 'full_name')
                ->whereIn('role', ['admin', 'user']) // Assuming these are the owner roles
            ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('UserController@lookup failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tìm kiếm người dùng.',
            ], 500);
        }
    }

    /**
     * Lấy chi tiết người dùng
     *
     * @OA\Get(
     *     path="/api/admin/users/{id}",
     *     operationId="getUser",
     *     tags={"Users"},
     *     summary="Chi tiết người dùng",
     *     description="Lấy thông tin chi tiết của một người dùng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết người dùng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $user = User::with('verifier:id,full_name')->findOrFail($id);

        return response()->json([
                'success' => true,
            'data' => new UserResource($user),
        ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Người dùng không tồn tại.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('UserController@show failed', [
                'user_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin người dùng.',
            ], 500);
        }
    }

    /**
     * Tạo người dùng mới
     *
     * @OA\Post(
     *     path="/api/admin/users",
     *     operationId="storeUser",
     *     tags={"Users"},
     *     summary="Tạo người dùng mới",
     *     description="Tạo người dùng mới",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"full_name", "email", "password"},
     *             @OA\Property(property="full_name", type="string", example="Nguyễn Văn A"),
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="date_of_birth", type="string", format="date"),
     *             @OA\Property(property="gender", type="string", enum={"male", "female", "other"}),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active", "locked"}),
     *             @OA\Property(property="role", type="string", enum={"admin", "staff", "user"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo người dùng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tạo người dùng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,locked',
                'role' => 'nullable|in:admin,staff,user',
            ], [
                'full_name.required' => 'Vui lòng nhập họ và tên.',
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không hợp lệ.',
                'email.unique' => 'Email đã được sử dụng.',
                'password.required' => 'Vui lòng nhập mật khẩu.',
                'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
                'phone_number.unique' => 'Số điện thoại đã được sử dụng.',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            'phone_number' => $validated['phone_number'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'] ?? 'active',
                'role' => $validated['role'] ?? 'user',
            ]);

            Log::info('User created', [
                'user_id' => $user->id,
                'email' => $user->email,
        ]);

        return response()->json([
                'success' => true,
                'message' => 'Tạo người dùng thành công',
            'data' => new UserResource($user),
        ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo người dùng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin người dùng
     *
     * @OA\Put(
     *     path="/api/admin/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     summary="Cập nhật người dùng",
     *     description="Cập nhật thông tin người dùng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="full_name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="date_of_birth", type="string", format="date"),
     *             @OA\Property(property="gender", type="string", enum={"male", "female", "other"}),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active", "locked"}),
     *             @OA\Property(property="avatar_url", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật người dùng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number,' . $id,
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'status' => 'sometimes|in:active,locked',
            'avatar_url' => 'nullable|string|max:255',
            'role' => 'sometimes|in:admin,staff,user',
            ], [
                'email.email' => 'Email không hợp lệ.',
                'email.unique' => 'Email đã được sử dụng.',
                'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
                'phone_number.unique' => 'Số điện thoại đã được sử dụng.',
                'role.in' => 'Vai trò không hợp lệ. Chỉ chấp nhận: admin, staff, user.',
        ]);

        // Chỉ hash password nếu có
        if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

            Log::info('User updated', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

        return response()->json([
                'success' => true,
                'message' => 'Cập nhật người dùng thành công',
            'data' => new UserResource($user),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Người dùng không tồn tại.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserController@update failed', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật người dùng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa người dùng
     *
     * @OA\Delete(
     *     path="/api/admin/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     summary="Xóa người dùng",
     *     description="Xóa người dùng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Xóa người dùng thành công")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $user = User::findOrFail($id);
            $userId = $user->id;
            $userEmail = $user->email;

        $user->delete();

            Log::info('User deleted', [
                'user_id' => $userId,
                'email' => $userEmail,
            ]);

        return response()->json([
                'success' => true,
            'message' => 'Xóa người dùng thành công',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Người dùng không tồn tại.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('UserController@destroy failed', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa người dùng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get locked users
     */
    public function locked(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:id,full_name,email,created_at,updated_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = User::where('status', 'locked');

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('full_name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%')
                        ->orWhere('phone_number', 'like', '%' . $request->search . '%');
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($users),
                'meta' => [
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('UserController@locked failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách tài khoản khóa.',
            ], 500);
        }
    }

    /**
     * Bulk lock users
     */
    public function bulkLock(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'required|integer|exists:users,id',
            ], [
                'user_ids.required' => 'Vui lòng chọn ít nhất một người dùng.',
                'user_ids.*.exists' => 'Một trong các người dùng không tồn tại.',
            ]);

            $count = User::whereIn('id', $validatedData['user_ids'])
                ->update(['status' => 'locked']);

            Log::info('Users bulk locked', [
                'user_ids' => $validatedData['user_ids'],
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Đã khóa {$count} tài khoản thành công",
                'data' => ['locked_count' => $count],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserController@bulkLock failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi khóa tài khoản.',
            ], 500);
        }
    }

    /**
     * Bulk unlock users
     */
    public function bulkUnlock(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'required|integer|exists:users,id',
            ], [
                'user_ids.required' => 'Vui lòng chọn ít nhất một người dùng.',
                'user_ids.*.exists' => 'Một trong các người dùng không tồn tại.',
            ]);

            $count = User::whereIn('id', $validatedData['user_ids'])
                ->update(['status' => 'active']);

            Log::info('Users bulk unlocked', [
                'user_ids' => $validatedData['user_ids'],
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Đã bỏ khóa {$count} tài khoản thành công",
                'data' => ['unlocked_count' => $count],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserController@bulkUnlock failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi bỏ khóa tài khoản.',
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, User $user): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:active,locked',
            ], [
                'status.required' => 'Vui lòng chọn trạng thái.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, locked.',
            ]);

            $user->update(['status' => $validatedData['status']]);

            Log::info('User status updated', [
                'user_id' => $user->id,
                'status' => $user->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => new UserResource($user),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserController@updateStatus failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái.',
            ], 500);
        }
    }

    /**
     * Verify user identity
     */
    public function verifyIdentity(Request $request, User $user): JsonResponse
    {
        try {
            $admin = $request->user();

            $user->update([
                'identity_verified' => true,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('User identity verified', [
                'user_id' => $user->id,
                'verified_by' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xác minh danh tính thành công',
                'data' => new UserResource($user->load('verifier:id,full_name')),
            ]);
        } catch (\Exception $e) {
            Log::error('UserController@verifyIdentity failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác minh danh tính.',
            ], 500);
        }
    }

    /**
     * Reject user identity verification
     */
    public function rejectIdentity(Request $request, User $user): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'sometimes|string|max:1000',
            ]);

            $admin = $request->user();

            $user->update([
                'identity_verified' => false,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('User identity verification rejected', [
                'user_id' => $user->id,
                'verified_by' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Từ chối xác minh danh tính thành công',
                'data' => new UserResource($user->load('verifier:id,full_name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserController@rejectIdentity failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối xác minh danh tính.',
            ], 500);
        }
    }
}
