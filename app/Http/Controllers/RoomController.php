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
        $rooms = Room::select('id', 'name', 'room_type', 'capacity', 'price_per_night', 'status', 'description')
            ->orderBy('name')
            ->get();

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
        $room = Room::findOrFail($id);

        return response()->json([
            'data' => $room,
        ]);
    }
}
