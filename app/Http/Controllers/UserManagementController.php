<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="Users",
 *   description="User management APIs"
 * )
 */
class UserManagementController extends Controller
{
    /**
     * List users with optional filters.
     *
     * @OA\Get(
     *   path="/api/users",
     *   summary="List users",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="role", in="query", @OA\Schema(type="string", enum={"admin","staff","user"})),
     *   @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active","locked"})),
     *   @OA\Parameter(name="search", in="query", description="Search by name or email", @OA\Schema(type="string")),
     *   @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_number', 'like', '%' . $search . '%');
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(15);
        return response()->json($users);
    }

    /**
     * Get user detail by ID.
     *
     * @OA\Get(
     *   path="/api/users/{id}",
     *   summary="Get user detail",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    /**
     * Create a new user.
     *
     * @OA\Post(
     *   path="/api/users",
     *   summary="Create user",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"full_name","email","password"},
     *       @OA\Property(property="full_name", type="string", example="Nguyen Van A"),
     *       @OA\Property(property="email", type="string", example="user@example.com"),
     *       @OA\Property(property="password", type="string", example="secret123"),
     *       @OA\Property(property="phone_number", type="string", example="0901234567"),
     *       @OA\Property(property="avatar_url", type="string", example="https://..."),
     *       @OA\Property(property="status", type="string", enum={"active","locked"}, example="active"),
     *       @OA\Property(property="date_of_birth", type="string", format="date", example="1999-01-01"),
     *       @OA\Property(property="gender", type="string", enum={"male","female","other"}),
     *       @OA\Property(property="address", type="string"),
     *       @OA\Property(property="preferred_language", type="string", example="vi"),
     *       @OA\Property(property="google_id", type="string"),
     *       @OA\Property(property="facebook_id", type="string"),
     *       @OA\Property(property="role", type="string", enum={"admin","staff","user"}, example="user")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created")
     * )
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user = User::create($data);
        return response()->json($user, 201);
    }

    /**
     * Update an existing user.
     *
     * @OA\Put(
     *   path="/api/users/{id}",
     *   summary="Update user",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="full_name", type="string"),
     *       @OA\Property(property="email", type="string"),
     *       @OA\Property(property="password", type="string"),
     *       @OA\Property(property="phone_number", type="string"),
     *       @OA\Property(property="avatar_url", type="string"),
     *       @OA\Property(property="status", type="string", enum={"active","locked"}),
     *       @OA\Property(property="date_of_birth", type="string", format="date"),
     *       @OA\Property(property="gender", type="string", enum={"male","female","other"}),
     *       @OA\Property(property="address", type="string"),
     *       @OA\Property(property="preferred_language", type="string"),
     *       @OA\Property(property="google_id", type="string"),
     *       @OA\Property(property="facebook_id", type="string"),
     *       @OA\Property(property="role", type="string", enum={"admin","staff","user"})
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->fill($data);
        $user->save();

        return response()->json($user);
    }

    /**
     * Delete a user.
     *
     * @OA\Delete(
     *   path="/api/users/{id}",
     *   summary="Delete user",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=204, description="No Content"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
     public function destroy(int $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    /**
     * Update user status.
     *
     * @OA\Patch(
     *   path="/api/users/{id}/status",
     *   summary="Update user status",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"status"},
     *       @OA\Property(property="status", type="string", enum={"active","locked"})
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', 'in:active,locked'],
        ]);
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $user->status = $request->string('status');
        $user->save();
        return response()->json($user);
    }

    /**
     * Update user role.
     *
     * @OA\Patch(
     *   path="/api/users/{id}/role",
     *   summary="Update user role",
     *   tags={"Users"},
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"role"},
     *       @OA\Property(property="role", type="string", enum={"admin","staff","user"})
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateRole(Request $request, int $id)
    {
        $request->validate([
            'role' => ['required', 'in:admin,staff,user'],
        ]);
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $user->role = $request->string('role');
        $user->save();
        return response()->json($user);
    }
}


