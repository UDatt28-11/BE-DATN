<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomImage;
use App\Http\Requests\Admin\StoreRoomImagesRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class RoomImageController extends Controller
{
    /**
     * Store uploaded file to S3 with unique filename
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array ['path' => string, 'url' => string]
     * @throws Exception
     */
    private function storeLocalFile($file)
    {
        try {
            $directory = 'room_images';

            // Generate unique filename to avoid overwriting
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;

            // Store file to S3 (publicly readable or via configured URL)
            $path = Storage::disk('s3')->putFileAs($directory, $file, $filename);

            if (!$path) {
                throw new Exception('File không được lưu lên S3.');
            }

            // Generate public URL using S3 disk configuration
            $url = Storage::disk('s3')->url($path);

            Log::info('RoomImageController@storeLocalFile - File uploaded to S3 successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'full_url' => $url,
            ]);

            return ['path' => $path, 'url' => $url];
        } catch (Exception $e) {
            Log::error('RoomImageController@storeLocalFile failed (S3)', [
                'original_name' => $file->getClientOriginalName() ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Lỗi khi tải file ảnh phòng lên S3: ' . $e->getMessage());
        }
    }

    /**
     * Delete file from S3
     *
     * @param string|null $urlOrPath Stored S3 path or URL
     * @return void
     */
    private function deleteLocalFile($urlOrPath)
    {
        if (!$urlOrPath) {
            return;
        }

        try {
            // If full URL is stored, extract path part after bucket domain
            $path = $urlOrPath;

            // If looks like a URL, parse it
            if (filter_var($urlOrPath, FILTER_VALIDATE_URL)) {
                $parsedUrl = parse_url($urlOrPath);
                $path = $parsedUrl['path'] ?? '';
                $path = ltrim($path, '/');
            }

            if ($path && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        } catch (Exception $e) {
            Log::error('RoomImageController@deleteLocalFile failed (S3)', [
                'input' => $urlOrPath,
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

    /**
     * Xóa nhiều ảnh phòng cùng lúc
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:room_images,id',
            ]);

            $ids = $validated['ids'];

            $images = RoomImage::whereIn('id', $ids)->get();

            if ($images->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy ảnh để xóa.',
                ], 404);
            }

            // Nhóm theo room_id để xử lý ảnh primary
            $imagesByRoom = $images->groupBy('room_id');

            foreach ($imagesByRoom as $roomId => $roomImages) {
                $primaryDeleted = $roomImages->contains(function (RoomImage $img) {
                    return $img->is_primary;
                });

                // Xóa từng ảnh (file + record)
                foreach ($roomImages as $image) {
                    $this->deleteLocalFile($image->image_url);
                    $image->delete();
                }

                // Nếu ảnh primary bị xóa, chọn ảnh khác làm primary
                if ($primaryDeleted) {
                    $newPrimary = RoomImage::where('room_id', $roomId)
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($newPrimary) {
                        $newPrimary->update(['is_primary' => true]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa ' . $images->count() . ' ảnh thành công.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomImageController@bulkDestroy failed', [
                'ids' => $request->get('ids'),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa nhiều ảnh: ' . $e->getMessage(),
            ], 500);
        }
    }
}
