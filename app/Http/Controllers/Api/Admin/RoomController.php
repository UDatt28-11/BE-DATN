<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    /**
     * Lấy danh sách phòng
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
     */
    public function show(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        return response()->json([
            'data' => $room,
        ]);
    }
}
