<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'property_id' => 'sometimes|required|exists:properties,id',
            'room_type_id' => 'sometimes|required|exists:room_types,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'max_adults' => 'sometimes|required|integer|min:1',
            'max_children' => 'sometimes|required|integer|min:0',
            'price_per_night' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:available,maintenance,occupied',

            // Validation cho mảng các tiện ích (React sẽ gửi lên 1 mảng ID)
            'amenities' => 'nullable|array',
            'amenities.*' => 'integer|exists:amenities,id', // Mỗi phần tử phải là ID amenity hợp lệ

            // (Chúng ta sẽ xử lý file ảnh ở 1 API riêng)
        ];
    }
}
