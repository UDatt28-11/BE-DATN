<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Cho phép property_id rỗng, sẽ gán property mặc định ở controller
            'property_id' => 'nullable|exists:properties,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // Giá & sức chứa chung cho loại phòng
            'base_price' => 'required|numeric|min:0',
            'max_adults' => 'required|integer|min:1',
            'max_children' => 'nullable|integer|min:0',
            // Danh sách dịch vụ áp dụng cho loại phòng
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}
