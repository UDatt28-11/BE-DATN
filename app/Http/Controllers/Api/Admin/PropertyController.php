<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Http\Requests\Admin\StorePropertyRequest;
use App\Http\Requests\Admin\UpdatePropertyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Properties",
 *     description="API Endpoints for Property Management"
 * )
 */
class PropertyController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of properties
     *
     * @OA\Get(
     *     path="/api/admin/properties",
     *     operationId="getProperties",
     *     tags={"Properties"},
     *     summary="Danh sách properties",
     *     description="Lấy danh sách tất cả properties với hỗ trợ lọc và phân trang",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="owner_id",
     *         in="query",
     *         description="Lọc theo owner ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái (active, inactive, pending_approval)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "pending_approval"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo tên, địa chỉ",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *         description="Số lượng bản ghi mỗi trang (mặc định 15)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách properties",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="pagination", type="object")
     *             )
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
            
            // Validate query parameters
            $request->validate([
                'owner_id' => 'sometimes|integer|exists:users,id',
                'status' => 'sometimes|string|in:active,inactive,pending_approval',
                'verification_status' => 'sometimes|string|in:pending,verified,rejected',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'owner_id.exists' => 'Owner không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ.',
                'verification_status.in' => 'Trạng thái xác minh không hợp lệ.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Property::query()->with(['owner:id,full_name', 'verifier:id,full_name', 'images']);

            // Filter by owner_id
            if ($request->has('owner_id')) {
                $query->where('owner_id', $request->owner_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by verification_status
            if ($request->has('verification_status')) {
                $query->where('verification_status', $request->verification_status);
            }

            // Search by name or address
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('address', 'like', '%' . $request->search . '%');
                });
            }

            // Sort by latest
            $query->latest();

            // Paginate results
            $properties = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $properties->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $properties->currentPage(),
                        'per_page' => $properties->perPage(),
                        'total' => $properties->total(),
                        'last_page' => $properties->lastPage(),
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PropertyController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách properties.',
            ], 500);
        }
    }

    /**
     * Store a newly created property
     *
     * @OA\Post(
     *     path="/api/admin/properties",
     *     operationId="storeProperty",
     *     tags={"Properties"},
     *     summary="Tạo property mới",
     *     description="Tạo property mới",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"owner_id", "name", "address", "status"},
     *             @OA\Property(property="owner_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Sunset Homestay"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="description", type="string", example="Beautiful homestay"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "pending_approval"}, example="active"),
     *             @OA\Property(property="check_in_time", type="string", format="time", example="14:00:00"),
     *             @OA\Property(property="check_out_time", type="string", format="time", example="12:00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo property thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tạo property thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $property = Property::create($request->validated());

            Log::info('Property created', [
                'property_id' => $property->id,
                'name' => $property->name,
                'owner_id' => $property->owner_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo property thành công',
                'data' => $property->load('owner:id,full_name'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PropertyController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo property: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified property
     *
     * @OA\Get(
     *     path="/api/admin/properties/{id}",
     *     operationId="getProperty",
     *     tags={"Properties"},
     *     summary="Chi tiết property",
     *     description="Lấy thông tin chi tiết của một property",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID property",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết property",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Property not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Property $property): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            return response()->json([
                'success' => true,
                'data' => $property->load(['owner:id,full_name', 'verifier:id,full_name', 'images']),
            ]);
        } catch (\Exception $e) {
            Log::error('PropertyController@show failed', [
                'property_id' => $property->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin property.',
            ], 500);
        }
    }

    /**
     * Update the specified property
     *
     * @OA\Put(
     *     path="/api/admin/properties/{id}",
     *     operationId="updateProperty",
     *     tags={"Properties"},
     *     summary="Cập nhật property",
     *     description="Cập nhật thông tin property",
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
     *             @OA\Property(property="owner_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "pending_approval"}),
     *             @OA\Property(property="check_in_time", type="string", format="time"),
     *             @OA\Property(property="check_out_time", type="string", format="time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật property thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $property->update($request->validated());

            Log::info('Property updated', [
                'property_id' => $property->id,
                'name' => $property->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật property thành công',
                'data' => $property->load('owner:id,full_name'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PropertyController@update failed', [
                'property_id' => $property->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật property: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified property
     *
     * @OA\Delete(
     *     path="/api/admin/properties/{id}",
     *     operationId="deleteProperty",
     *     tags={"Properties"},
     *     summary="Xóa property",
     *     description="Xóa property",
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
     *             @OA\Property(property="message", type="string", example="Xóa property thành công")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Property not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(Property $property): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            $propertyId = $property->id;
            $propertyName = $property->name;

        $property->delete();

            Log::info('Property deleted', [
                'property_id' => $propertyId,
                'name' => $propertyName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa property thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PropertyController@destroy failed', [
                'property_id' => $property->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa property: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify property
     */
    public function verify(Request $request, Property $property): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'sometimes|string|max:1000',
            ]);

            $admin = $request->user();

            $property->update([
                'verification_status' => 'verified',
                'verification_notes' => $validatedData['notes'] ?? null,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('Property verified', [
                'property_id' => $property->id,
                'verified_by' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xác minh property thành công',
                'data' => $property->load(['owner:id,full_name,email', 'verifier:id,full_name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PropertyController@verify failed', [
                'property_id' => $property->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác minh property.',
            ], 500);
        }
    }

    /**
     * Reject property verification
     */
    public function reject(Request $request, Property $property): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'required|string|max:1000',
            ], [
                'notes.required' => 'Vui lòng nhập lý do từ chối.',
            ]);

            $admin = $request->user();

            $property->update([
                'verification_status' => 'rejected',
                'verification_notes' => $validatedData['notes'],
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('Property verification rejected', [
                'property_id' => $property->id,
                'verified_by' => $admin->id,
                'notes' => $validatedData['notes'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Từ chối xác minh property thành công',
                'data' => $property->load(['owner:id,full_name,email', 'verifier:id,full_name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PropertyController@reject failed', [
                'property_id' => $property->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối xác minh property.',
            ], 500);
        }
    }
}
