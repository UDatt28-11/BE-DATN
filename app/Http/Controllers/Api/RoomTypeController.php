<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Http\Requests\StoreRoomTypeRequest;
use App\Http\Requests\UpdateRoomTypeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // <-- Thêm Storage
use Exception; // <-- Thêm Exception
use Illuminate\Support\Facades\Auth;
use App\Services\GoogleDriveService;

class RoomTypeController extends Controller
{
    // private function uploadToGoogleDrive($file)
    // {
    //     $path = null;

    //     try {
    //         // Upload file lên Google Drive
    //         $path = $file->store('room_type_images', 'google');

    //         // Lấy File ID và tạo public URL
    //         $adapter = Storage::disk('google')->getAdapter();
    //         $fileId = $adapter->getMetadata($path)->extraMetadata()['id'];

    //         // Set quyền public read
    //         $service = $adapter->getService();
    //         $permission = new \Google_Service_Drive_Permission([
    //             'type' => 'anyone',
    //             'role' => 'reader',
    //         ]);
    //         $service->permissions->create($fileId, $permission);

    //         // Tạo link nhúng
    //         $embedLink = "https://drive.google.com/uc?id={$fileId}";

    //         Log::info('File uploaded to Google Drive successfully: ' . $path);

    //         return ['path' => $path, 'url' => $embedLink];

    //     } catch (Exception $e) {
    //         Log::error('Lỗi khi tải file lên Google Drive: ' . $e->getMessage(), [
    //             'file' => $file->getClientOriginalName(),
    //             'error_details' => $e->getTraceAsString()
    //         ]);

    //         // Dọn dẹp file nếu upload thành công nhưng set permission thất bại
    //         if ($path && Storage::disk('google')->exists($path)) {
    //             Storage::disk('google')->delete($path);
    //         }

    //         throw new Exception('Lỗi khi tải file lên Google Drive: ' . $e->getMessage());
    //     }
    // }

    // // --- Hàm xóa file cũ trên Google Drive ---
    // private function deleteFromGoogleDrive($path) {
    //     if ($path) {
    //         try {
    //             Storage::disk('google')->delete($path);
    //         } catch (Exception $e) {
    //             // Log lỗi nhưng không chặn request
    //             \Log::error('Lỗi khi xóa file cũ trên Google Drive: ' . $e->getMessage());
    //         }
    //     }
    // }
    private function storeLocalFile($file)
    {
        try {
            // 1. Lưu file vào 'storage/app/public/room_type_images'
            // Laravel tự động tạo tên file duy nhất
            $path = $file->store('room_type_images', 'public');

            // 2. Lấy URL công khai (ví dụ: http://127.0.0.1:8000/storage/room_type_images/file.jpg)
            // Storage::url($path) sẽ tạo ra /storage/room_type_images/file.jpg
            // asset() sẽ thêm APP_URL (http://127.0.0.1:8000) vào
            $url = asset(Storage::url($path));

            // Trả về cả path (để xóa) và url (để lưu)
            return ['path' => $path, 'url' => $url];

        } catch (Exception $e) {
            throw new Exception('Lỗi khi tải file lên server: ' . $e->getMessage());
        }
    }
    private function deleteLocalFile($url)
    {
        if (!$url) return; // Không làm gì nếu không có URL

        try {
            // Chuyển URL (http://.../storage/file.jpg)
            // thành path mà Storage hiểu (room_type_images/file.jpg)
            $storageUrlPath = Storage::url(''); // Lấy /storage/
            $relativePath = str_replace($storageUrlPath, '', parse_url($url, PHP_URL_PATH));

            // Kiểm tra và xóa file trên disk 'public'
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } catch (Exception $e) {
            // Log lỗi nhưng không chặn request
             \Log::error('Lỗi khi xóa file cũ: ' . $e->getMessage());
        }
    }
    public function index(Request $request)
    {
        // Vẫn giữ validate phòng trường hợp cần lọc
        $request->validate(['property_id' => 'sometimes|exists:properties,id']);

        $roomTypes = RoomType::query()
            // THÊM DÒNG NÀY: Tải thông tin property (chỉ id và name)
            ->with('property:id,name')
            ->when($request->property_id, function ($query, $propertyId) {
                $query->where('property_id', $propertyId);
            })
            ->latest()
            ->get();

        return $roomTypes; // Trả về Mảng (đã đúng)
    }


    public function show(RoomType $roomType)
    {
        // Trả về model trực tiếp
        return $roomType;
    }

    public function store(StoreRoomTypeRequest $request)
    {
        $validatedData = $request->validated();
        $imageUrl = null;

        if ($request->hasFile('image_file')) {
            try {
                // Mặc định: lưu LOCAL. Nếu truyền use_drive=1 và đã đăng nhập + có Google token -> lưu Drive
                if ($request->boolean('use_drive') && \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->googleToken) {
                    $drive = app(\App\Services\GoogleDriveService::class);
                    $uploaded = $drive->upload($request->file('image_file')->getRealPath(), $request->file('image_file')->getClientOriginalName());
                    $imageUrl = $uploaded->getWebViewLink();
                } else {
                    $uploadResult = $this->storeLocalFile($request->file('image_file'));
                    $imageUrl = $uploadResult['url'];
                }

                // Lưu ý: Chúng ta vẫn không lưu $uploadResult['path']
                // Chúng ta sẽ suy ngược path từ URL khi xóa

            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        // Thêm image_url vào CSDL
        $validatedData['image_url'] = $imageUrl;
        $roomType = RoomType::create($validatedData);

        // Trả về model (load thêm property để hiển thị ngay)
        return $roomType->load('property:id,name');
    }


    // --- Cập nhật update() ---
    public function update(UpdateRoomTypeRequest $request, RoomType $roomType)
    {
        $validatedData = $request->validated();
        $imageUrl = $roomType->image_url; // Giữ URL cũ mặc định

        if ($request->hasFile('image_file')) {
            try {
                // Xóa file cũ nếu là LOCAL
                $this->deleteLocalFile($roomType->image_url);

                if ($request->boolean('use_drive') && \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->googleToken) {
                    $drive = app(\App\Services\GoogleDriveService::class);
                    $uploaded = $drive->upload($request->file('image_file')->getRealPath(), $request->file('image_file')->getClientOriginalName());
                    $imageUrl = $uploaded->getWebViewLink();
                } else {
                    $uploadResult = $this->storeLocalFile($request->file('image_file'));
                    $imageUrl = $uploadResult['url'];
                }

            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        // Cập nhật CSDL với URL mới (hoặc URL cũ)
        $validatedData['image_url'] = $imageUrl;
        $roomType->update($validatedData);

        // Trả về model đã cập nhật
        return $roomType->load('property:id,name');
    }

    // --- Cập nhật destroy() ---
    public function destroy(RoomType $roomType)
    {
        // 1. Xóa file ảnh liên quan (nếu có)
        $this->deleteLocalFile($roomType->image_url);

        // 2. Xóa bản ghi trong CSDL
        $roomType->delete();

        return response()->noContent();
    }
}
