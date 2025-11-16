<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckedInGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests' => 'required|array|min:1',
            'guests.*.full_name' => 'required|string|max:255',
            'guests.*.date_of_birth' => 'nullable|date|before:today',
            'guests.*.identity_type' => 'nullable|in:cccd,passport',
            'guests.*.identity_number' => 'nullable|string|max:50',
            'guests.*.identity_image_url' => 'nullable|string|max:500',
            'guests.*.check_in_time' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'guests.required' => 'Danh sách khách không được trống',
            'guests.*.full_name.required' => 'Họ tên khách là bắt buộc',
            'guests.*.date_of_birth.before' => 'Ngày sinh phải trước hôm nay',
            'guests.*.identity_type.in' => 'Loại giấy tờ phải là CCCD hoặc Passport',
        ];
    }
}
