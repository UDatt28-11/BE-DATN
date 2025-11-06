<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/admin/properties",
     * tags={"Properties"},
     * summary="Lấy danh sách properties (phân trang)",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Thành công")
     * )
     */ //
    public function index() {
        // Tải 'owner' và CHỈ chọn 2 trường 'id' và 'full_name'
        // Sử dụng paginate để phân trang, ví dụ 15 mục mỗi trang
        $properties = Property::with('owner:id,full_name')
                        ->latest()
                        ->paginate(15); // <-- Nên dùng paginate

        // Trả về một đối tượng phân trang (bao gồm data, total, current_page,...)
        return $properties;
    }

    // (Các hàm store, show, update vẫn giữ nguyên)
    public function store(StorePropertyRequest $request) {
        $property = Property::create($request->validated());
        return $property->load('owner:id,full_name');
    }
    public function show(Property $property) {
        return $property->load('owner:id,full_name');
    }
    public function update(UpdatePropertyRequest $request, Property $property) {
        $property->update($request->validated());
        return $property->load('owner:id,full_name');
    }
    public function destroy(Property $property) {
        $property->delete();
        return response()->noContent();
    }
}
