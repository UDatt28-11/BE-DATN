<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Http\Requests\Admin\StoreRoomTypeRequest;
use App\Http\Requests\Admin\UpdateRoomTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = RoomType::query()->with('property:id,name');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sort by latest
            $query->latest();

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

            // Delete associated image file
            $this->deleteLocalFile($roomType->image_url);

            // Delete room type
            $roomType->delete();

            Log::info('RoomType deleted', [
                'room_type_id' => $roomTypeId,
                'name' => $roomTypeName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa loại phòng thành công',
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
     * Store uploaded file to local storage
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string|null Full URL of the stored file
     * @throws Exception
     */
    private function storeLocalFile($file): ?string
    {
        try {
            // Store file in public disk
            $path = $file->store('room_type_images', 'public');
            
            // Generate full URL (e.g., http://example.com/storage/room_type_images/file.png)
            $url = asset(Storage::url($path));
            
            return $url;
        } catch (Exception $e) {
            Log::error('Failed to store room type image', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw new Exception('Lỗi khi tải file hình ảnh: ' . $e->getMessage());
        }
    }

    /**
     * Delete file from local storage
     *
     * @param string|null $url Full URL of the file to delete
     * @return void
     */
    private function deleteLocalFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        try {
            // Extract relative path from URL
            // URL format: http://example.com/storage/room_type_images/file.png
            // We need: room_type_images/file.png
            $storageUrlPath = Storage::url('');
            $path = parse_url($url, PHP_URL_PATH);
            $relativePath = str_replace($storageUrlPath, '', $path);

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } catch (Exception $e) {
            Log::error('Failed to delete room type image', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }
}
