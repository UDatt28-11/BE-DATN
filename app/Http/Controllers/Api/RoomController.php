<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        // Lọc theo property_id hoặc room_type_id (nếu cần)
        $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'room_type_id' => 'sometimes|exists:room_types,id',
        ]);

        $rooms = Room::query()
            // Tải các quan hệ lồng nhau
            ->with(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images'])
            ->when($request->property_id, fn($q, $id) => $q->where('property_id', $id))
            ->when($request->room_type_id, fn($q, $id) => $q->where('room_type_id', $id))
            ->latest()
            ->get();

        return $rooms;
    }

    public function store(StoreRoomRequest $request)
    {
        $validatedData = $request->validated();

        // Tách mảng 'amenities' ra khỏi dữ liệu chính
        $amenityIds = $validatedData['amenities'] ?? [];
        unset($validatedData['amenities']); // Xóa nó khỏi mảng chính

        // 1. Tạo phòng
        $room = Room::create($validatedData);

        // 2. Đồng bộ các tiện ích vào bảng 'room_amenities'
        if (!empty($amenityIds)) {
            $room->amenities()->sync($amenityIds);
        }

        // Tải lại các quan hệ để trả về JSON đầy đủ
        return $room->load(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images']);
    }

    public function show(Room $room)
    {
        // Tải đầy đủ thông tin khi xem chi tiết
        return $room->load(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images']);
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $validatedData = $request->validated();

        $amenityIds = $validatedData['amenities'] ?? [];
        unset($validatedData['amenities']);

        // 1. Cập nhật phòng
        $room->update($validatedData);

        // 2. Đồng bộ lại tiện ích (sync = tự động thêm/xóa)
        $room->amenities()->sync($amenityIds);

        return $room->load(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images']);
    }

    public function destroy(Room $room)
    {
        // TODO: Xóa các ảnh liên quan (RoomImage) trên server trước

        $room->delete(); // Tự động xóa các liên kết trong 'room_amenities'
        return response()->noContent();
    }
}
