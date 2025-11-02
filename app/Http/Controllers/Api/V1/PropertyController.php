<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index() {
        // Tải 'owner' và CHỈ chọn 2 trường 'id' và 'full_name'
        $properties = Property::with('owner:id,full_name')
                        ->latest()
                        ->get(); // <-- SỬA TỪ 'paginate(100)' THÀNH 'get()'

        // Bây giờ biến này trả về một Mảng (Array)
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
