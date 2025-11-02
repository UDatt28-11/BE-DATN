<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Http\Requests\StoreAmenityRequest;
use App\Http\Requests\UpdateAmenityRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // <-- Thêm Storage
use Exception; // <-- Thêm Exception

class AmenityController extends Controller
{
    // --- Hàm xử lý upload file Icon LÊN SERVER LOCAL ---
    private function storeLocalFile($file)
    {
        try {
            // 1. Lưu file vào 'storage/app/public/amenity_icons'
            $path = $file->store('amenity_icons', 'public');
            // 2. Lấy URL công khai
            $url = asset(Storage::url($path));
            return ['url' => $url];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi tải file icon: ' . $e->getMessage());
        }
    }

    // --- Hàm xóa file cũ trên server local ---
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
             \Log::error('Lỗi khi xóa file icon cũ: ' . $e->getMessage());
        }
    }

    // --- Cập nhật Controller Methods ---

    public function index(Request $request)
    {
        $request->validate(['property_id' => 'sometimes|exists:properties,id']);

        $amenities = Amenity::query()
            ->with('property:id,name') // <-- Thêm 'with' để lấy tên Homestay
            ->when($request->property_id, function ($query, $propertyId) {
                $query->where('property_id', $propertyId);
            })
            ->latest()
            ->get();

        return $amenities;
    }

    public function store(StoreAmenityRequest $request)
    {
        $validatedData = $request->validated();
        $iconUrl = null;

        // Key 'icon_file' phải khớp với Request và FormData (React)
        if ($request->hasFile('icon_file')) {
            try {
                $uploadResult = $this->storeLocalFile($request->file('icon_file'));
                $iconUrl = $uploadResult['url'];
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
        $validatedData['icon_url'] = $iconUrl;
        $amenity = Amenity::create($validatedData);

        return $amenity->load('property:id,name');
    }

    public function show(Amenity $amenity)
    {
        return $amenity->load('property:id,name');
    }

    public function update(UpdateAmenityRequest $request, Amenity $amenity)
    {
        $validatedData = $request->validated();
        $iconUrl = $amenity->icon_url;

        if ($request->hasFile('icon_file')) {
            try {
                // 1. Xóa icon cũ
                $this->deleteLocalFile($amenity->icon_url);
                // 2. Upload icon mới
                $uploadResult = $this->storeLocalFile($request->file('icon_file'));
                $iconUrl = $uploadResult['url'];
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
        $validatedData['icon_url'] = $iconUrl;
        $amenity->update($validatedData);

        return $amenity->load('property:id,name');
    }

    public function destroy(Amenity $amenity)
    {
        // 1. Xóa file icon
        $this->deleteLocalFile($amenity->icon_url);
        // 2. Xóa record
        $amenity->delete();

        return response()->noContent();
    }
}
