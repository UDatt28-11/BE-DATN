<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Rooms",
 *     description="API Endpoints for Room Management"
 * )
 */
class RoomController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of rooms
     *
     * @OA\Get(
     *     path="/api/admin/rooms",
     *     operationId="getRooms",
     *     tags={"Rooms"},
     *     summary="Danh sách phòng",
     *     description="Lấy danh sách tất cả phòng với hỗ trợ lọc và phân trang",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="room_type_id",
     *         in="query",
     *         description="Lọc theo room type ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái (available, maintenance, occupied)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"available", "maintenance", "occupied"})
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
     *         description="Danh sách phòng",
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
                'room_type_id' => 'sometimes|integer|exists:room_types,id',
                'status' => 'sometimes|string|in:available,maintenance,occupied',
                'verification_status' => 'sometimes|string|in:pending,verified,rejected',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:id,name,price_per_night,created_at,updated_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'room_type_id.exists' => 'Room type không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ.',
                'verification_status.in' => 'Trạng thái xác minh không hợp lệ.',
                'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
                'sort_order.in' => 'Thứ tự sắp xếp không hợp lệ. Chỉ chấp nhận: asc, desc.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Room::query()
                ->with(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images', 'verifier:id,full_name']);

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by room_type_id
            if ($request->has('room_type_id')) {
                $query->where('room_type_id', $request->room_type_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by verification_status
            if ($request->has('verification_status')) {
                $query->where('verification_status', $request->verification_status);
            }

            // Search by name or property address
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhereHas('property', function ($q) use ($search) {
                            $q->where('address', 'like', '%' . $search . '%');
                        });
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $rooms = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $rooms->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $rooms->currentPage(),
                        'per_page' => $rooms->perPage(),
                        'total' => $rooms->total(),
                        'last_page' => $rooms->lastPage(),
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
            Log::error('RoomController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách phòng.',
            ], 500);
    }
    }

    /**
     * Store a newly created room
     *
     * @OA\Post(
     *     path="/api/admin/rooms",
     *     operationId="storeRoom",
     *     tags={"Rooms"},
     *     summary="Tạo phòng mới",
     *     description="Tạo phòng mới với thông tin và amenities",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"property_id", "room_type_id", "name", "max_adults", "max_children", "price_per_night", "status"},
     *             @OA\Property(property="property_id", type="integer", example=1),
     *             @OA\Property(property="room_type_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Phòng 101"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="max_adults", type="integer", example=2),
     *             @OA\Property(property="max_children", type="integer", example=1),
     *             @OA\Property(property="price_per_night", type="number", example=500000),
     *             @OA\Property(property="status", type="string", enum={"available", "maintenance", "occupied"}, example="available"),
     *             @OA\Property(property="amenities", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tạo phòng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $validatedData = $request->validated();

        // Tách mảng 'amenities' ra khỏi dữ liệu chính
        $amenityIds = $validatedData['amenities'] ?? [];
            unset($validatedData['amenities']);

            // Tạo phòng
        $room = Room::create($validatedData);

            // Đồng bộ các tiện ích vào bảng 'room_amenities'
        if (!empty($amenityIds)) {
            $room->amenities()->sync($amenityIds);
        }

            Log::info('Room created', [
                'room_id' => $room->id,
                'name' => $room->name,
                'property_id' => $room->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified room
     *
     * @OA\Get(
     *     path="/api/admin/rooms/{id}",
     *     operationId="getRoom",
     *     tags={"Rooms"},
     *     summary="Chi tiết phòng",
     *     description="Lấy thông tin chi tiết của một phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID phòng",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Room not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            return response()->json([
                'success' => true,
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images', 'verifier:id,full_name']),
            ]);
        } catch (\Exception $e) {
            Log::error('RoomController@show failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin phòng.',
            ], 500);
        }
    }

    /**
     * Update the specified room
     *
     * @OA\Put(
     *     path="/api/admin/rooms/{id}",
     *     operationId="updateRoom",
     *     tags={"Rooms"},
     *     summary="Cập nhật phòng",
     *     description="Cập nhật thông tin phòng",
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
     *             @OA\Property(property="property_id", type="integer"),
     *             @OA\Property(property="room_type_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="max_adults", type="integer"),
     *             @OA\Property(property="max_children", type="integer"),
     *             @OA\Property(property="price_per_night", type="number"),
     *             @OA\Property(property="status", type="string", enum={"available", "maintenance", "occupied"}),
     *             @OA\Property(property="amenities", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật phòng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Room not found")
     * )
     */
    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
        $validatedData = $request->validated();

        $amenityIds = $validatedData['amenities'] ?? [];
        unset($validatedData['amenities']);

            // Cập nhật phòng
        $room->update($validatedData);

            // Đồng bộ lại tiện ích (sync = tự động thêm/xóa)
        $room->amenities()->sync($amenityIds);

            Log::info('Room updated', [
                'room_id' => $room->id,
                'name' => $room->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@update failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified room
     *
     * @OA\Delete(
     *     path="/api/admin/rooms/{id}",
     *     operationId="deleteRoom",
     *     tags={"Rooms"},
     *     summary="Xóa phòng",
     *     description="Xóa phòng và các liên kết liên quan",
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
     *             @OA\Property(property="message", type="string", example="Xóa phòng thành công")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Room not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
        // TODO: Xóa các ảnh liên quan (RoomImage) trên server trước

            $roomId = $room->id;
            $roomName = $room->name;

            // Xóa phòng (tự động xóa các liên kết trong 'room_amenities' do cascade)
            $room->delete();

            Log::info('Room deleted', [
                'room_id' => $roomId,
                'name' => $roomName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa phòng thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('RoomController@destroy failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update room status
     */
    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:available,maintenance,occupied',
            ], [
                'status.required' => 'Vui lòng chọn trạng thái.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: available, maintenance, occupied.',
            ]);

            $room->update(['status' => $validatedData['status']]);

            Log::info('Room status updated', [
                'room_id' => $room->id,
                'status' => $room->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@updateStatus failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái.',
            ], 500);
        }
    }

    /**
     * Verify room
     */
    public function verify(Request $request, Room $room): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'sometimes|string|max:1000',
            ]);

            $admin = $request->user();

            $room->update([
                'verification_status' => 'verified',
                'verification_notes' => $validatedData['notes'] ?? null,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('Room verified', [
                'room_id' => $room->id,
                'verified_by' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xác minh phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'verifier:id,full_name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@verify failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác minh phòng.',
            ], 500);
        }
    }

    /**
     * Reject room verification
     */
    public function reject(Request $request, Room $room): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'required|string|max:1000',
            ], [
                'notes.required' => 'Vui lòng nhập lý do từ chối.',
            ]);

            $admin = $request->user();

            $room->update([
                'verification_status' => 'rejected',
                'verification_notes' => $validatedData['notes'],
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('Room verification rejected', [
                'room_id' => $room->id,
                'verified_by' => $admin->id,
                'notes' => $validatedData['notes'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Từ chối xác minh phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'verifier:id,full_name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@reject failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối xác minh phòng.',
            ], 500);
        }
    }
}
