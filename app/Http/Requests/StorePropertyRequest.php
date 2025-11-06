<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            // Khớp CSDL
            'owner_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,pending_approval',
            'check_in_time' => 'nullable|date_format:H:i:s',
            'check_out_time' => 'nullable|date_format:H:i:s',
        ];
    }
}
