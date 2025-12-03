<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Models\RoomTypeImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class RoomTypeImageController extends Controller
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
            $directory = 'room_type_images';

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

            Log::info('RoomTypeImageController@storeLocalFile - File uploaded to S3 successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'full_url' => $url,
            ]);

            return ['path' => $path, 'url' => $url];
        } catch (Exception $e) {
            Log::error('RoomTypeImageController@storeLocalFile failed (S3)', [
                'original_name' => $file->getClientOriginalName() ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Lỗi khi tải file ảnh loại phòng lên S3: ' . $e->getMessage());
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
            Log::error('RoomTypeImageController@deleteLocalFile failed (S3)', [
                'input' => $urlOrPath,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }

    /**
     * Store (lưu) một hoặc nhiều ảnh cho 1 loại phòng.
     * (Route: POST /api/admin/room-types/{roomType}/upload-images)
     */
    public function store(Request $request, RoomType $roomType): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $request->validate([
                'images' => 'required|array|min:1|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            Log::info('RoomTypeImageController@store - Request received', [
                'room_type_id' => $roomType->id,
                'images_count' => count($request->file('images')),
            ]);

            $uploadedImages = [];
            
            // Kiểm tra xem loại phòng này đã có ảnh primary chưa
            $hasPrimaryImage = $roomType->images()->where('is_primary', true)->exists();
            $isFirstImage = !$hasPrimaryImage;

            foreach ($request->file('images') as $file) {
                try {
                    $uploadResult = $this->storeLocalFile($file);

                    // Tạo record trong bảng 'room_type_images'
                    $imageRecord = $roomType->images()->create([
                        'image_url' => $uploadResult['url'],
                        'is_primary' => $isFirstImage,
                    ]);

                    // Nếu đây là ảnh đầu tiên và được set làm primary, set tất cả ảnh khác thành false
                    if ($isFirstImage) {
                        $roomType->images()
                            ->where('id', '!=', $imageRecord->id)
                            ->update(['is_primary' => false]);
                        $isFirstImage = false; // Chỉ set ảnh đầu tiên trong batch làm primary
                    }

                    $uploadedImages[] = $imageRecord;
                } catch (Exception $e) {
                    Log::error('RoomTypeImageController@store - File upload failed', [
                        'room_type_id' => $roomType->id,
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
            Log::error('RoomTypeImageController@store failed', [
                'room_type_id' => $roomType->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi upload ảnh loại phòng.',
            ], 500);
        }
    }

    /**
     * Xóa 1 ảnh.
     * (Route: DELETE /api/admin/room-type-images/{roomTypeImage})
     */
    public function destroy(RoomTypeImage $roomTypeImage): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $roomTypeId = $roomTypeImage->room_type_id;
            $isPrimary = $roomTypeImage->is_primary;

            // 1. Nếu đây là ảnh primary, tìm ảnh khác để set làm primary trước khi xóa
            if ($isPrimary) {
                $firstImage = RoomTypeImage::where('room_type_id', $roomTypeId)
                    ->where('id', '!=', $roomTypeImage->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }

            // 2. Xóa file trên server
            $this->deleteLocalFile($roomTypeImage->image_url);

            // 3. Xóa record trong CSDL
            $roomTypeImage->delete();

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
            Log::error('RoomTypeImageController@destroy failed', [
                'room_type_image_id' => $roomTypeImage->id,
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
     * Xóa nhiều ảnh loại phòng cùng lúc
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:room_type_images,id',
            ]);

            $ids = $validated['ids'];

            $images = RoomTypeImage::whereIn('id', $ids)->get();

            if ($images->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy ảnh để xóa.',
                ], 404);
            }

            // Nhóm theo room_type_id để xử lý ảnh primary
            $imagesByRoomType = $images->groupBy('room_type_id');

            foreach ($imagesByRoomType as $roomTypeId => $roomTypeImages) {
                $primaryDeleted = $roomTypeImages->contains(function (RoomTypeImage $img) {
                    return $img->is_primary;
                });

                // Xóa từng ảnh (file + record)
                foreach ($roomTypeImages as $image) {
                    $this->deleteLocalFile($image->image_url);
                    $image->delete();
                }

                // Nếu ảnh primary bị xóa, chọn ảnh khác làm primary
                if ($primaryDeleted) {
                    $newPrimary = RoomTypeImage::where('room_type_id', $roomTypeId)
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
            Log::error('RoomTypeImageController@bulkDestroy failed', [
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

