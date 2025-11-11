<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Lấy danh sách phòng
     */
    public function index(Request $request): JsonResponse
    {
        $query = Room::with('roomType:id,name')
            ->select('id', 'name', 'room_type_id', 'max_adults', 'max_children', 'price_per_night', 'status', 'description');
        
        // Filter by room_type_id if provided
        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->input('room_type_id'));
        }
        
        $rooms = $query->orderBy('name')->get();

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
