<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Lấy danh sách người dùng
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->get('per_page', 20));
        $search = $request->get('search');
        $status = $request->get('status');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = User::query();

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

        // Sắp xếp
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($perPage);

        return response()->json([
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
    }

    /**
     * Lấy chi tiết người dùng
     */
    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Tạo người dùng mới
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,locked',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'phone_number' => $validated['phone_number'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Tạo người dùng thành công',
        ], 201);
    }

    /**
     * Cập nhật thông tin người dùng
     */
    public function update(int $id, Request $request): JsonResponse
    {
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
        ]);

        // Chỉ hash password nếu có
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Cập nhật người dùng thành công',
        ]);
    }

    /**
     * Xóa người dùng
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Xóa người dùng thành công',
        ]);
    }
}
