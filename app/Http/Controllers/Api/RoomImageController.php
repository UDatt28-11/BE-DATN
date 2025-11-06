<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room; // <-- Import Room
use App\Models\RoomImage; // <-- Import RoomImage
use App\Http\Requests\StoreRoomImagesRequest; // <-- Import Request
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class RoomImageController extends Controller
{
    // --- Copy các hàm Helper từ RoomTypeController ---
    private function storeLocalFile($file)
    {
        try {
            // Lưu vào 'storage/app/public/room_images'
            $path = $file->store('room_images', 'public');
            $url = asset(Storage::url($path));
            return ['path' => $path, 'url' => $url];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi tải file ảnh phòng: ' . $e->getMessage());
        }
    }
    private function deleteLocalFile($url)
    {
        if (!$url) return;
        try {
            $storageUrlPath = Storage::url('');
            $relativePath = str_replace($storageUrlPath, '', parse_url($url, PHP_URL_PATH));
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } catch (Exception $e) {
             \Log::error('Lỗi khi xóa file ảnh phòng: ' . $e->getMessage());
        }
    }

    /**
     * Store (lưu) một hoặc nhiều ảnh cho 1 phòng.
     * (Route: POST /api/v1/rooms/{room}/upload-images)
     */
    public function store(StoreRoomImagesRequest $request, Room $room)
    {
        $uploadedImages = [];

        foreach ($request->file('images') as $file) {
            try {
                $uploadResult = $this->storeLocalFile($file);

                // Tạo record trong bảng 'room_images'
                $imageRecord = $room->images()->create([
                    'image_url' => $uploadResult['url'],
                    // TODO: Logic set ảnh đầu tiên là 'is_primary' = true
                ]);

                $uploadedImages[] = $imageRecord;

            } catch (Exception $e) {
                // Nếu 1 file lỗi, báo lỗi và dừng lại
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        // Trả về mảng các ảnh vừa được upload
        return response()->json($uploadedImages, 201);
    }

    /**
     * Xóa 1 ảnh.
     * (Route: DELETE /api/v1/room-images/{roomImage})
     * (Dùng {roomImage} thay vì {id} để dùng Route-Model Binding)
     */
    public function destroy(RoomImage $roomImage)
    {
        // 1. Xóa file trên server
        $this->deleteLocalFile($roomImage->image_url);

        // 2. Xóa record trong CSDL
        $roomImage->delete();

        return response()->noContent(); // 204
    }
}
