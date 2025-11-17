<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Loosen validation để tránh lỗi 422 khi FE gửi query rỗng/không chuẩn
        return [];
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        if (is_string($status)) {
            $this->merge([
                'status' => array_values(array_filter(explode(',', $status))),
            ]);
        }
    }
}


