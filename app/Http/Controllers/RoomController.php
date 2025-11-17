<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Rooms",
 *     description="API Endpoints for Room Management"
 * )
 */
class RoomController extends Controller
{
    /**
     * Lấy danh sách phòng
     * 
     * @OA\Get(
     *     path="/api/admin/rooms",
     *     operationId="getRooms",
     *     tags={"Rooms"},
     *     summary="Danh sách phòng",
     *     description="Lấy danh sách tất cả phòng với thông tin cơ bản",
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Phòng 101"),
     *                     @OA\Property(property="room_type", type="string", example="Standard", nullable=true),
     *                     @OA\Property(property="capacity", type="integer", example=2, nullable=true),
     *                     @OA\Property(property="price_per_night", type="number", example=500000),
     *                     @OA\Property(property="status", type="string", example="available", nullable=true),
     *                     @OA\Property(property="description", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $rooms = Room::with('roomType:id,name')
            ->select('id', 'name', 'room_type_id', 'max_adults', 'max_children', 'price_per_night', 'status', 'description')
            ->orderBy('name')
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'room_type' => $room->roomType ? $room->roomType->name : null,
                    'room_type_id' => $room->room_type_id,
                    'capacity' => ($room->max_adults ?? 0) + ($room->max_children ?? 0),
                    'max_adults' => $room->max_adults,
                    'max_children' => $room->max_children,
                    'price_per_night' => (float) $room->price_per_night,
                    'status' => $room->status,
                    'description' => $room->description,
                ];
            });

        return response()->json([
            'data' => $rooms,
        ]);
    }

    /**
     * Lấy chi tiết phòng
     * 
     * @OA\Get(
     *     path="/api/admin/rooms/{id}",
     *     operationId="getRoom",
     *     tags={"Rooms"},
     *     summary="Chi tiết phòng",
     *     description="Lấy thông tin chi tiết một phòng",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID của phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Phòng 101"),
     *                 @OA\Property(property="room_type", type="string", example="Standard", nullable=true),
     *                 @OA\Property(property="capacity", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="price_per_night", type="number", example=500000),
     *                 @OA\Property(property="status", type="string", example="available", nullable=true),
     *                 @OA\Property(property="description", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy phòng"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $room = Room::with('roomType:id,name')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $room->id,
                'name' => $room->name,
                'property_id' => $room->property_id,
                'room_type_id' => $room->room_type_id,
                'room_type' => $room->roomType ? $room->roomType->name : null,
                'max_adults' => $room->max_adults,
                'max_children' => $room->max_children,
                'capacity' => ($room->max_adults ?? 0) + ($room->max_children ?? 0),
                'price_per_night' => (float) $room->price_per_night,
                'status' => $room->status,
                'description' => $room->description,
            ],
        ]);
    }

    /**
     * Tạo phòng mới
     */
    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'room_type_id' => 'required|integer|exists:room_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_adults' => 'required|integer|min:1',
            'max_children' => 'nullable|integer|min:0',
            'price_per_night' => 'required|numeric|min:0',
            'status' => 'nullable|string|in:available,occupied,maintenance',
        ]);

        $room = Room::create($validated);

        return response()->json([
            'data' => $room,
            'message' => 'Phòng đã được tạo thành công',
        ], 201);
    }

    /**
     * Cập nhật phòng
     */
    public function update(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'property_id' => 'sometimes|integer|exists:properties,id',
            'room_type_id' => 'sometimes|integer|exists:room_types,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'max_adults' => 'sometimes|integer|min:1',
            'max_children' => 'nullable|integer|min:0',
            'price_per_night' => 'sometimes|numeric|min:0',
            'status' => 'nullable|string|in:available,occupied,maintenance',
        ]);

        $room->update($validated);

        return response()->json([
            'data' => $room,
            'message' => 'Phòng đã được cập nhật thành công',
        ]);
    }

    /**
     * Xóa phòng
     */
    public function destroy(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json([
            'message' => 'Phòng đã được xóa thành công',
        ]);
    }
}
