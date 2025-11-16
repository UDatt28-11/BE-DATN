<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCheckedInGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'identity_type' => 'nullable|in:cccd,passport',
            'identity_number' => 'nullable|string|max:50',
            'identity_image_url' => 'nullable|string|max:500',
            'check_in_time' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên khách là bắt buộc',
            'date_of_birth.before' => 'Ngày sinh phải trước hôm nay',
            'identity_type.in' => 'Loại giấy tờ phải là CCCD hoặc Passport',
        ];
    }
}
