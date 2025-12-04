<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Cho phép các trạng thái của hệ thống hiện tại,
            // logic state machine ở controller sẽ quyết định chuyển đổi nào hợp lệ
            'status' => ['required','in:pending,confirmed,checked_in,checked_out,cancelled,completed'],
            'reason' => ['sometimes','string','max:255'],
        ];
    }
}




