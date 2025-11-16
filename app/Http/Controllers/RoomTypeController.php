<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Room Types",
 *     description="API Endpoints for Room Type Management"
 * )
 */
class RoomTypeController extends Controller
{
    /**
     * Lấy danh sách loại phòng
     * 
     * @OA\Get(
     *     path="/api/admin/room-types",
     *     operationId="getRoomTypes",
     *     tags={"Room Types"},
     *     summary="Danh sách loại phòng",
     *     description="Lấy danh sách tất cả loại phòng với thông tin cơ bản",
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách loại phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Standard Room"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Phòng tiêu chuẩn"),
     *                     @OA\Property(property="property_id", type="integer", nullable=true, example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $roomTypes = RoomType::select('id', 'name', 'description', 'property_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roomTypes,
        ]);
    }

    /**
     * Lấy chi tiết loại phòng
     * 
     * @OA\Get(
     *     path="/api/admin/room-types/{id}",
     *     operationId="getRoomType",
     *     tags={"Room Types"},
     *     summary="Chi tiết loại phòng",
     *     description="Lấy thông tin chi tiết một loại phòng",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID của loại phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết loại phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Standard Room"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Phòng tiêu chuẩn"),
     *                 @OA\Property(property="property_id", type="integer", nullable=true, example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy loại phòng"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $roomType = RoomType::findOrFail($id);

        return response()->json([
            'data' => $roomType,
        ]);
    }
}
