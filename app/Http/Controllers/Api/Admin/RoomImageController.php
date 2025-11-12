<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomImage;
use App\Http\Requests\StoreRoomImagesRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class RoomImageController extends Controller
{
    /**
     * Store uploaded file to local storage with unique filename
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array ['path' => string, 'url' => string]
     * @throws Exception
     */
    private function storeLocalFile($file)
    {
        try {
            // Generate unique filename to avoid overwriting
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Store file in public disk with unique filename
            $path = $file->storeAs('room_images', $filename, 'public');
            
            // Generate full URL using Storage disk URL (more reliable than asset())
            $url = Storage::disk('public')->url($path);
            
            return ['path' => $path, 'url' => $url];
        } catch (Exception $e) {
            Log::error('RoomImageController@storeLocalFile failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw new Exception('Lỗi khi tải file ảnh phòng: ' . $e->getMessage());
        }
    }

    /**
     * Delete file from local storage
     *
     * @param string|null $url Full URL of the file to delete
     * @return void
     */
    private function deleteLocalFile($url)
    {
        if (!$url) {
            return;
        }

        try {
            // Extract relative path from URL
            // URL format: http://example.com/storage/room_images/file.png
            // We need: room_images/file.png
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';
            
            // Remove /storage prefix if present
            $path = ltrim($path, '/');
            if (Str::startsWith($path, 'storage/')) {
                $path = Str::after($path, 'storage/');
            }
            
            // Delete file if exists
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (Exception $e) {
            Log::error('RoomImageController@deleteLocalFile failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }

    /**
     * Store (lưu) một hoặc nhiều ảnh cho 1 phòng.
     * (Route: POST /api/v1/rooms/{room}/upload-images)
     */
    public function store(StoreRoomImagesRequest $request, Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

        $uploadedImages = [];
            
            // Kiểm tra xem phòng này đã có ảnh primary chưa
            $hasPrimaryImage = $room->images()->where('is_primary', true)->exists();
            $isFirstImage = !$hasPrimaryImage;

        foreach ($request->file('images') as $file) {
            try {
                $uploadResult = $this->storeLocalFile($file);

                // Tạo record trong bảng 'room_images'
                $imageRecord = $room->images()->create([
                    'image_url' => $uploadResult['url'],
                        'is_primary' => $isFirstImage,
                    ]);

                    // Nếu đây là ảnh đầu tiên và được set làm primary, set tất cả ảnh khác thành false
                    if ($isFirstImage) {
                        $room->images()
                            ->where('id', '!=', $imageRecord->id)
                            ->update(['is_primary' => false]);
                        $isFirstImage = false; // Chỉ set ảnh đầu tiên trong batch làm primary
                    }

                $uploadedImages[] = $imageRecord;
            } catch (Exception $e) {
                    Log::error('RoomImageController@store - File upload failed', [
                        'room_id' => $room->id,
                        'file_name' => $file->getClientOriginalName(),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);

                    // Xóa các file đã upload nếu có lỗi
                    foreach ($uploadedImages as $uploadedImage) {
                        $this->deleteLocalFile($uploadedImage->image_url);
                        $uploadedImage->delete();
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Có lỗi xảy ra khi upload ảnh: ' . $e->getMessage(),
                    ], 500);
            }
        }

            return response()->json([
                'success' => true,
                'message' => 'Đã upload ' . count($uploadedImages) . ' ảnh thành công',
                'data' => $uploadedImages
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomImageController@store failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi upload ảnh phòng.',
            ], 500);
        }
    }

    /**
     * Xóa 1 ảnh.
     * (Route: DELETE /api/v1/room-images/{roomImage})
     * (Dùng {roomImage} thay vì {id} để dùng Route-Model Binding)
     */
    public function destroy(RoomImage $roomImage): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $roomId = $roomImage->room_id;
            $isPrimary = $roomImage->is_primary;

            // 1. Nếu đây là ảnh primary, tìm ảnh khác để set làm primary trước khi xóa
            if ($isPrimary) {
                $firstImage = RoomImage::where('room_id', $roomId)
                    ->where('id', '!=', $roomImage->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }

            // 2. Xóa file trên server
        $this->deleteLocalFile($roomImage->image_url);

            // 3. Xóa record trong CSDL
        $roomImage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa ảnh thành công.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy ảnh.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('RoomImageController@destroy failed', [
                'room_image_id' => $roomImage->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa ảnh.',
            ], 500);
        }
    }
}
