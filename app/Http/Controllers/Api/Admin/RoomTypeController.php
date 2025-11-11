<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;

class RoomTypeController extends Controller
{
    /**
     * Lấy danh sách loại phòng
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
     */
    public function show(int $id): JsonResponse
    {
        $roomType = RoomType::findOrFail($id);

        return response()->json([
            'data' => $roomType,
        ]);
    }
}
