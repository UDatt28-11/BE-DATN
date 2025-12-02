<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomImagesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // 'images' phải là 1 mảng
            'images' => 'required|array|min:1',
            // Mỗi phần tử trong mảng 'images' phải là file ảnh
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Tối đa 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'Vui lòng chọn ít nhất một ảnh để tải lên.',
            'images.array' => 'Dữ liệu ảnh không hợp lệ.',
            'images.min' => 'Vui lòng chọn ít nhất một ảnh để tải lên.',
            'images.*.required' => 'Một hoặc nhiều ảnh không hợp lệ.',
            'images.*.image' => 'File phải là ảnh (jpeg, png, jpg, gif, webp).',
            'images.*.mimes' => 'File ảnh phải có định dạng: jpeg, png, jpg, gif, webp.',
            'images.*.max' => 'Kích thước ảnh không được vượt quá 5MB.',
        ];
    }
}
