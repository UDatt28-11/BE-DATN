<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Http\Requests\Admin\StorePropertyImagesRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class PropertyImageController extends Controller
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
            // Ensure directory exists
            $directory = 'property_images';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            // Generate unique filename to avoid overwriting
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Store file in public disk with unique filename
            $path = $file->storeAs($directory, $filename, 'public');
            
            // Verify file was actually saved
            if (!Storage::disk('public')->exists($path)) {
                throw new Exception('File không được lưu vào storage. Path: ' . $path);
            }
            
            // Get file size for logging
            $fileSize = Storage::disk('public')->size($path);
            
            // Generate full URL
            // Path format: property_images/filename.jpg
            // We need: http://localhost:8000/storage/property_images/filename.jpg
            $appUrl = rtrim(config('app.url'), '/');
            $url = $appUrl . '/storage/' . $path;
            
            // Log successful upload for debugging
            Log::info('PropertyImageController@storeLocalFile - File uploaded successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'file_size' => $fileSize,
                'full_url' => $url,
                'storage_path' => storage_path('app/public/' . $path),
            ]);
            
            return ['path' => $path, 'url' => $url];
        } catch (Exception $e) {
            Log::error('PropertyImageController@storeLocalFile failed', [
                'original_name' => $file->getClientOriginalName() ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Lỗi khi tải file ảnh property: ' . $e->getMessage());
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
            Log::error('PropertyImageController@deleteLocalFile failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }

    /**
     * Store (lưu) một hoặc nhiều ảnh cho 1 property.
     * (Route: POST /api/admin/properties/{property}/upload-images)
     */
    public function store(StorePropertyImagesRequest $request, Property $property): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $uploadedImages = [];
            
            // Kiểm tra xem property này đã có ảnh primary chưa
            $hasPrimaryImage = $property->images()->where('is_primary', true)->exists();
            $isFirstImage = !$hasPrimaryImage;

            foreach ($request->file('images') as $file) {
                try {
                    $uploadResult = $this->storeLocalFile($file);

                    // Tạo record trong bảng 'property_images'
                    $imageRecord = $property->images()->create([
                        'image_url' => $uploadResult['url'],
                        'is_primary' => $isFirstImage,
                    ]);

                    // Nếu đây là ảnh đầu tiên và được set làm primary, set tất cả ảnh khác thành false
                    if ($isFirstImage) {
                        $property->images()
                            ->where('id', '!=', $imageRecord->id)
                            ->update(['is_primary' => false]);
                        $isFirstImage = false; // Chỉ set ảnh đầu tiên trong batch làm primary
                    }

                    $uploadedImages[] = $imageRecord;
                } catch (Exception $e) {
                    Log::error('PropertyImageController@store - File upload failed', [
                        'property_id' => $property->id,
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
            Log::error('PropertyImageController@store failed', [
                'property_id' => $property->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi upload ảnh property.',
            ], 500);
        }
    }

    /**
     * Xóa 1 ảnh.
     * (Route: DELETE /api/admin/property-images/{propertyImage})
     */
    public function destroy(PropertyImage $propertyImage): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $propertyId = $propertyImage->property_id;
            $isPrimary = $propertyImage->is_primary;

            // 1. Nếu đây là ảnh primary, tìm ảnh khác để set làm primary trước khi xóa
            if ($isPrimary) {
                $firstImage = PropertyImage::where('property_id', $propertyId)
                    ->where('id', '!=', $propertyImage->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }

            // 2. Xóa file trên server
            $this->deleteLocalFile($propertyImage->image_url);

            // 3. Xóa record trong CSDL
            $propertyImage->delete();

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
            Log::error('PropertyImageController@destroy failed', [
                'property_image_id' => $propertyImage->id,
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
     * Set primary image
     * (Route: POST /api/admin/property-images/{propertyImage}/set-primary)
     */
    public function setPrimary(PropertyImage $propertyImage): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            // Set all other images of this property to non-primary
            PropertyImage::where('property_id', $propertyImage->property_id)
                ->where('id', '!=', $propertyImage->id)
                ->update(['is_primary' => false]);

            // Set this image as primary
            $propertyImage->update(['is_primary' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Đã đặt ảnh làm ảnh chính thành công.',
                'data' => $propertyImage->fresh()
            ], 200);
        } catch (\Exception $e) {
            Log::error('PropertyImageController@setPrimary failed', [
                'property_image_id' => $propertyImage->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đặt ảnh chính.',
            ], 500);
        }
    }
}
