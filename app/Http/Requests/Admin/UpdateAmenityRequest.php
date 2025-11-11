<?php
// app/Http/Requests/Admin/UpdateAmenityRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAmenityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Policy will handle authorization
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'property_id' => 'sometimes|required|integer|exists:properties,id',
            'name'        => 'sometimes|required|string|max:255',
            'type'        => 'sometimes|required|string|in:basic,advanced,safety',
            'icon_file'   => 'nullable|file|image|mimes:png,svg,jpg,jpeg|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'Vui lòng chọn property.',
            'property_id.integer' => 'Property ID phải là số nguyên.',
            'property_id.exists' => 'Property không tồn tại.',
            'name.required' => 'Vui lòng nhập tên tiện ích.',
            'name.string' => 'Tên tiện ích phải là chuỗi ký tự.',
            'name.max' => 'Tên tiện ích không được vượt quá 255 ký tự.',
            'type.required' => 'Vui lòng chọn loại tiện ích.',
            'type.in' => 'Loại tiện ích không hợp lệ. Chỉ chấp nhận: basic, advanced, safety.',
            'icon_file.file' => 'File icon phải là file hợp lệ.',
            'icon_file.image' => 'File icon phải là hình ảnh.',
            'icon_file.mimes' => 'File icon phải có định dạng: png, svg, jpg, jpeg.',
            'icon_file.max' => 'File icon không được vượt quá 2MB.',
        ];
    }
}
