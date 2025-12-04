<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Thêm thư viện Storage
use Illuminate\Support\Str; // Thêm thư viện Str để tạo tên file ngẫu nhiên (tùy chọn)

class FileController extends Controller
{
    /**
     * Lưu file được upload lên S3.
     */
    public function store(Request $request)
    {
        // 1. Kiểm tra xem file có hợp lệ không
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:20480', // Tối đa 20MB
        ]);

        // 2. Kiểm tra xem có file được gửi lên không
        if ($request->hasFile('file')) {

            $file = $request->file('file');

            // 3. Tạo tên file mới để tránh trùng lặp
            // Ví dụ: my-image.png -> uploads/1678886400_my-image.png
            $fileName = time() . '_' . $file->getClientOriginalName();
            $folder = 'uploads'; // Tên thư mục bạn muốn lưu trên S3

            // 4. Lưu file lên S3
            // Laravel sẽ tự động dùng 's3' disk đã cấu hình trong .env
            $path = Storage::disk('s3')->putFileAs($folder, $file, $fileName);

            // (Tùy chọn) Nếu bạn muốn file public (ai cũng xem được link)
            // $path = Storage::disk('s3')->putFileAs($folder, $file, $fileName, 'public');

            // 5. Lấy URL của file vừa upload
            // Quan trọng: Vì bucket của chúng ta là private (chặn public)
            // Chúng ta phải tạo một URL tạm thời (có chữ ký)
            $temporaryUrl = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));

            // 6. Trả về thông báo thành công cho React
            return response()->json([
                'message' => 'File uploaded successfully!',
                'path' => $path, // Đường dẫn file trên S3 (ví dụ: uploads/1678886400_my-image.png)
                'url' => $temporaryUrl // URL để React có thể hiển thị/dùng file
            ], 201);
        }

        // Nếu không có file
        return response()->json(['error' => 'No file uploaded.'], 400);
    }
}
