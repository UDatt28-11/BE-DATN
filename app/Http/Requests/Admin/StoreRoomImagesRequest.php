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
            'images' => 'required|array',
            // Mỗi phần tử trong mảng 'images' phải là file ảnh
            'images.*' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Tối đa 5MB
        ];
    }
}
