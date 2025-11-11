<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Http\Requests\Admin\StoreAmenityRequest;
use App\Http\Requests\Admin\UpdateAmenityRequest;
use App\Http\Resources\AmenityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Exception;

/**
 * @OA\Tag(
 *     name="Amenities",
 *     description="API Endpoints for Amenity Management"
 * )
 */
class AmenityController extends Controller
{
    use AuthorizesRequests;

    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of amenities
     *
     * @OA\Get(
     *     path="/api/admin/amenities",
     *     operationId="getAmenities",
     *     tags={"Amenities"},
     *     summary="Danh sách tiện ích",
     *     description="Lấy danh sách tất cả tiện ích với hỗ trợ lọc và phân trang",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Lọc theo loại (basic, advanced, safety)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"basic", "advanced", "safety"})
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
     *         description="Danh sách tiện ích",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Amenity")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
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
            // Additional policy check if needed: $this->authorize('viewAny', Amenity::class);
            
            // Validate query parameters
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'type' => 'sometimes|string|in:basic,advanced,safety',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'type.in' => 'Loại tiện ích không hợp lệ. Chỉ chấp nhận: basic, advanced, safety.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Amenity::query()->with('property:id,name');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sort by latest
            $query->latest();

            // Paginate results
            $amenities = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => AmenityResource::collection($amenities),
                'meta' => [
                    'pagination' => [
                        'current_page' => $amenities->currentPage(),
                        'per_page' => $amenities->perPage(),
                        'total' => $amenities->total(),
                        'last_page' => $amenities->lastPage(),
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
            Log::error('AmenityController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách tiện ích.',
            ], 500);
        }
    }

    /**
     * Store a newly created amenity
     *
     * @OA\Post(
     *     path="/api/admin/amenities",
     *     operationId="storeAmenity",
     *     tags={"Amenities"},
     *     summary="Tạo tiện ích mới",
     *     description="Tạo tiện ích mới với thông tin và icon",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"property_id", "name", "type"},
     *                 @OA\Property(property="property_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Wi-Fi miễn phí"),
     *                 @OA\Property(property="type", type="string", enum={"basic", "advanced", "safety"}, example="basic"),
     *                 @OA\Property(property="icon_file", type="string", format="binary", description="File icon (png, svg, jpg, jpeg, max 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo tiện ích thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tạo tiện ích thành công"),
     *             @OA\Property(property="data", ref="#/components/schemas/Amenity")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(StoreAmenityRequest $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            // Additional policy check if needed: $this->authorize('create', Amenity::class);
            
            $validatedData = $request->validated();
            $iconUrl = null;

            // Handle file upload
            if ($request->hasFile('icon_file')) {
                $iconUrl = $this->storeLocalFile($request->file('icon_file'));
            }

            $validatedData['icon_url'] = $iconUrl;
            $amenity = Amenity::create($validatedData);

            Log::info('Amenity created', [
                'amenity_id' => $amenity->id,
                'name' => $amenity->name,
                'property_id' => $amenity->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo tiện ích thành công',
                'data' => new AmenityResource($amenity->load('property:id,name')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AmenityController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo tiện ích: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified amenity
     *
     * @OA\Get(
     *     path="/api/admin/amenities/{id}",
     *     operationId="getAmenity",
     *     tags={"Amenities"},
     *     summary="Chi tiết tiện ích",
     *     description="Lấy thông tin chi tiết của một tiện ích",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID tiện ích",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết tiện ích",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Amenity")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Amenity not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Amenity $amenity): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            // Additional policy check if needed: $this->authorize('view', $amenity);
            
            return response()->json([
                'success' => true,
                'data' => new AmenityResource($amenity->load('property:id,name')),
            ]);
        } catch (\Exception $e) {
            Log::error('AmenityController@show failed', [
                'amenity_id' => $amenity->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin tiện ích.',
            ], 500);
        }
    }

    /**
     * Update the specified amenity
     *
     * @OA\Put(
     *     path="/api/admin/amenities/{id}",
     *     operationId="updateAmenity",
     *     tags={"Amenities"},
     *     summary="Cập nhật tiện ích",
     *     description="Cập nhật thông tin tiện ích",
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
     *                 @OA\Property(property="type", type="string", enum={"basic", "advanced", "safety"}),
     *                 @OA\Property(property="icon_file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật tiện ích thành công"),
     *             @OA\Property(property="data", ref="#/components/schemas/Amenity")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Amenity not found")
     * )
     */
    public function update(UpdateAmenityRequest $request, Amenity $amenity): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            // Additional policy check if needed: $this->authorize('update', $amenity);
            
            $validatedData = $request->validated();
            $iconUrl = $amenity->icon_url;

            // Handle file upload (replace old file if new file is uploaded)
            if ($request->hasFile('icon_file')) {
                $this->deleteLocalFile($amenity->icon_url);
                $iconUrl = $this->storeLocalFile($request->file('icon_file'));
            }

            $validatedData['icon_url'] = $iconUrl;
            $amenity->update($validatedData);

            Log::info('Amenity updated', [
                'amenity_id' => $amenity->id,
                'name' => $amenity->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tiện ích thành công',
                'data' => new AmenityResource($amenity->load('property:id,name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AmenityController@update failed', [
                'amenity_id' => $amenity->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật tiện ích: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified amenity
     *
     * @OA\Delete(
     *     path="/api/admin/amenities/{id}",
     *     operationId="deleteAmenity",
     *     tags={"Amenities"},
     *     summary="Xóa tiện ích",
     *     description="Xóa tiện ích và file icon liên quan",
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
     *             @OA\Property(property="message", type="string", example="Xóa tiện ích thành công")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Amenity not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(Amenity $amenity): JsonResponse
    {
        try {
            // Policy check: only admin can delete (staff can view/update but not delete)
            $this->authorize('delete', $amenity);
            
            $amenityId = $amenity->id;
            $amenityName = $amenity->name;

            // Delete associated icon file
            $this->deleteLocalFile($amenity->icon_url);

            // Delete amenity (relationships will be handled by database cascade)
            $amenity->delete();

            Log::info('Amenity deleted', [
                'amenity_id' => $amenityId,
                'name' => $amenityName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa tiện ích thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('AmenityController@destroy failed', [
                'amenity_id' => $amenity->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa tiện ích: ' . $e->getMessage(),
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
            $path = $file->store('amenity_icons', 'public');
            
            // Generate full URL (e.g., http://example.com/storage/amenity_icons/file.png)
            $url = asset(Storage::url($path));
            
            return $url;
        } catch (Exception $e) {
            Log::error('Failed to store amenity icon', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw new Exception('Lỗi khi tải file icon: ' . $e->getMessage());
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
            // URL format: http://example.com/storage/amenity_icons/file.png
            // We need: amenity_icons/file.png
            $storageUrlPath = Storage::url('');
            $path = parse_url($url, PHP_URL_PATH);
            $relativePath = str_replace($storageUrlPath, '', $path);

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } catch (Exception $e) {
            Log::error('Failed to delete amenity icon', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }
}
