<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Models\Room;
use App\Http\Requests\Admin\StoreRoomTypeRequest;
use App\Http\Requests\Admin\UpdateRoomTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

/**
 * @OA\Tag(
 *     name="Room Types",
 *     description="API Endpoints for Room Type Management"
 * )
 */
class RoomTypeController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of room types
     *
     * @OA\Get(
     *     path="/api/admin/room-types",
     *     operationId="getRoomTypes",
     *     tags={"Room Types"},
     *     summary="Danh sách loại phòng",
     *     description="Lấy danh sách tất cả loại phòng với hỗ trợ lọc và phân trang",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo tên",
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
     *         description="Danh sách loại phòng",
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
            
            // Validate query parameters
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'status' => 'sometimes|string|in:active,inactive',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:id,name,status,created_at,updated_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, inactive.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = RoomType::query()->with('property:id,name');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $roomTypes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $roomTypes->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $roomTypes->currentPage(),
                        'per_page' => $roomTypes->perPage(),
                        'total' => $roomTypes->total(),
                        'last_page' => $roomTypes->lastPage(),
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
            Log::error('RoomTypeController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách loại phòng.',
            ], 500);
        }
    }

    /**
     * Danh sách loại phòng đã bị xóa mềm (lịch sử/thùng rác)
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'status' => 'sometimes|string|in:active,inactive',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:id,name,status,created_at,updated_at,deleted_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = RoomType::onlyTrashed()->with('property:id,name');

            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $sortBy = $request->get('sort_by', 'deleted_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $roomTypes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $roomTypes->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $roomTypes->currentPage(),
                        'per_page' => $roomTypes->perPage(),
                        'total' => $roomTypes->total(),
                        'last_page' => $roomTypes->lastPage(),
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
            Log::error('RoomTypeController@history failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách lịch sử loại phòng.',
            ], 500);
        }
    }

    /**
     * Display the specified room type
     *
     * @OA\Get(
     *     path="/api/admin/room-types/{id}",
     *     operationId="getRoomType",
     *     tags={"Room Types"},
     *     summary="Chi tiết loại phòng",
     *     description="Lấy thông tin chi tiết của một loại phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết loại phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Room type not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(RoomType $roomType): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            return response()->json([
                'success' => true,
                'data' => $roomType->load('property:id,name'),
            ]);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@show failed', [
                'room_type_id' => $roomType->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin loại phòng.',
            ], 500);
        }
    }

    /**
     * Store a newly created room type
     *
     * @OA\Post(
     *     path="/api/admin/room-types",
     *     operationId="storeRoomType",
     *     tags={"Room Types"},
     *     summary="Tạo loại phòng mới",
     *     description="Tạo loại phòng mới với thông tin và hình ảnh",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"property_id", "name"},
     *                 @OA\Property(property="property_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Standard Room"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="image_file", type="string", format="binary", description="File hình ảnh (jpeg, png, jpg, gif, max 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo loại phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tạo loại phòng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(StoreRoomTypeRequest $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $validatedData = $request->validated();
        $imageUrl = null;

            // Handle file upload
        if ($request->hasFile('image_file')) {
                $imageUrl = $this->storeLocalFile($request->file('image_file'));
                }

        $validatedData['image_url'] = $imageUrl;
        $roomType = RoomType::create($validatedData);

            Log::info('RoomType created', [
                'room_type_id' => $roomType->id,
                'name' => $roomType->name,
                'property_id' => $roomType->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo loại phòng thành công',
                'data' => $roomType->load('property:id,name'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo loại phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified room type
     *
     * @OA\Put(
     *     path="/api/admin/room-types/{id}",
     *     operationId="updateRoomType",
     *     tags={"Room Types"},
     *     summary="Cập nhật loại phòng",
     *     description="Cập nhật thông tin loại phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="property_id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="image_file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật loại phòng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Room type not found")
     * )
     */
    public function update(UpdateRoomTypeRequest $request, RoomType $roomType): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $validatedData = $request->validated();
            $imageUrl = $roomType->image_url;

            // Handle file upload (replace old file if new file is uploaded)
        if ($request->hasFile('image_file')) {
                $this->deleteLocalFile($roomType->image_url);
                $imageUrl = $this->storeLocalFile($request->file('image_file'));
                }

        $validatedData['image_url'] = $imageUrl;
        $roomType->update($validatedData);

            Log::info('RoomType updated', [
                'room_type_id' => $roomType->id,
                'name' => $roomType->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật loại phòng thành công',
                'data' => $roomType->load('property:id,name'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@update failed', [
                'room_type_id' => $roomType->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật loại phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified room type
     *
     * @OA\Delete(
     *     path="/api/admin/room-types/{id}",
     *     operationId="deleteRoomType",
     *     tags={"Room Types"},
     *     summary="Xóa loại phòng",
     *     description="Xóa loại phòng và file hình ảnh liên quan",
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
     *             @OA\Property(property="message", type="string", example="Xóa loại phòng thành công")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Room type not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(RoomType $roomType): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            $roomTypeId = $roomType->id;
            $roomTypeName = $roomType->name;

            // Soft delete room type (không xóa file ngay để có thể khôi phục)
            $roomType->delete();

            Log::info('RoomType soft deleted', [
                'room_type_id' => $roomTypeId,
                'name' => $roomTypeName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã chuyển loại phòng vào lịch sử (thùng rác)',
            ], 200);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@destroy failed', [
                'room_type_id' => $roomType->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa loại phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Khôi phục loại phòng từ lịch sử (soft deleted)
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $roomType = RoomType::onlyTrashed()->findOrFail($id);

            $roomType->restore();

            Log::info('RoomType restored', [
                'room_type_id' => $roomType->id,
                'name' => $roomType->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Khôi phục loại phòng thành công',
                'data' => $roomType->fresh('property:id,name'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy loại phòng trong lịch sử.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@restore failed', [
                'room_type_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi khôi phục loại phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa vĩnh viễn loại phòng (force delete) khỏi hệ thống
     */
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $roomType = RoomType::onlyTrashed()->findOrFail($id);

            // Xóa file ảnh trên S3 nếu có
            $this->deleteLocalFile($roomType->image_url);

            $roomType->forceDelete();

            Log::info('RoomType force deleted', [
                'room_type_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa vĩnh viễn loại phòng.',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy loại phòng trong lịch sử.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@forceDelete failed', [
                'room_type_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa vĩnh viễn loại phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update room type status
     */
    public function updateStatus(Request $request, RoomType $roomType): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:active,inactive',
            ], [
                'status.required' => 'Vui lòng chọn trạng thái.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, inactive.',
            ]);

            $roomType->update(['status' => $validatedData['status']]);

            Log::info('RoomType status updated', [
                'room_type_id' => $roomType->id,
                'status' => $roomType->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $roomType->load('property:id,name'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@updateStatus failed', [
                'room_type_id' => $roomType->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái.',
            ], 500);
        }
    }

    /**
     * Get room type with amenities
     */
    public function showWithAmenities(RoomType $roomType): JsonResponse
    {
        try {
            $roomType->load([
                'property:id,name',
                'rooms.amenities:id,name,type,icon_url'
            ]);

            $amenities = $roomType->rooms->flatMap->amenities->unique('id')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'room_type' => $roomType,
                    'amenities' => $amenities,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@showWithAmenities failed', [
                'room_type_id' => $roomType->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin loại phòng.',
            ], 500);
        }
    }

    /**
     * Store uploaded file to S3 with unique filename
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string|null S3 path of the stored file
     * @throws Exception
     */
    private function storeLocalFile($file): ?string
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            $path = Storage::disk('s3')->putFileAs('room_type_images', $file, $filename);

            if (!$path) {
                throw new Exception('File không được lưu lên S3.');
            }
            
            $url = Storage::disk('s3')->url($path);

            Log::info('RoomTypeController@storeLocalFile - File uploaded to S3 successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'full_url' => $url,
            ]);
            
            // Lưu full URL vào DB (giống local trước đây, FE dễ dùng)
            return $url;
        } catch (Exception $e) {
            Log::error('Failed to store room type image (S3)', [
                'original_name' => $file->getClientOriginalName() ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Lỗi khi tải file hình ảnh lên S3: ' . $e->getMessage());
        }
    }

    /**
     * Delete file from S3
     *
     * @param string|null $urlOrPath Stored S3 path or full URL
     * @return void
     */
    private function deleteLocalFile(?string $urlOrPath): void
    {
        if (!$urlOrPath) {
            return;
        }

        try {
            $path = $urlOrPath;

            if (filter_var($urlOrPath, FILTER_VALIDATE_URL)) {
                $parsedUrl = parse_url($urlOrPath);
            $path = $parsedUrl['path'] ?? '';
            $path = ltrim($path, '/');
            }
            
            if ($path && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        } catch (Exception $e) {
            Log::error('Failed to delete room type image (S3)', [
                'input' => $urlOrPath,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }

    /**
     * Public method: Display a listing of room types (không cần đăng nhập)
     * Chỉ trả về room types active
     */
    public function indexPublic(Request $request): JsonResponse
    {
        try {
            // Validate query parameters
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:20',
            ], [
                'limit.max' => 'Số lượng bản ghi không được vượt quá 20.',
            ]);

            $limit = (int) ($request->get('limit', 6));
            $query = RoomType::query();
            
            // Thêm điều kiện status nếu cột tồn tại
            if (Schema::hasColumn('room_types', 'status')) {
                $query->where('status', 'active');
            }
            
            // Load relationship nếu property_id tồn tại
            if (Schema::hasColumn('room_types', 'property_id')) {
                $query->with('property:id,name');
            }
            
            $query->orderBy('created_at', 'desc')
                ->limit($limit);

            $roomTypes = $query->get();

            // Đếm số lượng rooms cho mỗi room type
            $roomTypesWithCount = $roomTypes->map(function ($roomType) {
                $roomsQuery = Room::where('room_type_id', $roomType->id);
                
                // Thêm điều kiện verification_status nếu cột tồn tại
                if (Schema::hasColumn('rooms', 'verification_status')) {
                    $roomsQuery->where('verification_status', 'verified');
                }
                
                $roomsCount = $roomsQuery->where('status', 'available')->count();
                
                return [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'description' => $roomType->description,
                    'image_url' => $roomType->image_url,
                    'rooms_count' => $roomsCount,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $roomTypesWithCount,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomTypeController@indexPublic failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách loại phòng.',
            ], 500);
        }
    }
}
