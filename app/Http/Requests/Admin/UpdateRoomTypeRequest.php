<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rules = [
            'property_id' => 'sometimes|nullable|exists:properties,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            // Các trường mới: cho phép cập nhật nếu gửi lên
            'base_price' => 'sometimes|required|numeric|gt:0',
            'max_adults' => 'sometimes|required|integer|min:1',
            'max_children' => 'sometimes|nullable|integer|min:0',
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|exists:services,id',
        ];
        
        // Chỉ validate image_file nếu nó thực sự được gửi lên (có file)
        if ($this->hasFile('image_file')) {
            $rules['image_file'] = 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
        }
        
        return $rules;
    }

    public function messages(): array
    {
        return [
            'base_price.required' => 'Vui lòng nhập giá / đêm.',
            'base_price.numeric' => 'Giá / đêm phải là số.',
            'base_price.gt' => 'Giá / đêm phải lớn hơn 0.',
            'max_adults.required' => 'Vui lòng nhập số người lớn tối đa.',
            'max_adults.integer' => 'Số người lớn tối đa phải là số nguyên.',
            'max_adults.min' => 'Số người lớn tối đa phải lớn hơn hoặc bằng 1.',
            'max_children.integer' => 'Số trẻ em tối đa phải là số nguyên.',
            'max_children.min' => 'Số trẻ em tối đa phải lớn hơn hoặc bằng 0.',
            'image_file.image' => 'File phải là hình ảnh hợp lệ.',
            'image_file.mimes' => 'File ảnh phải có định dạng: jpeg, png, jpg, gif, hoặc webp.',
            'image_file.max' => 'Kích thước file ảnh không được vượt quá 2MB.',
        ];
    }
}
