<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rules = [
            'property_id' => 'sometimes|required|exists:properties,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ];
        
        // Chỉ validate image_file nếu nó thực sự được gửi lên (có file)
        if ($this->hasFile('image_file')) {
            $rules['image_file'] = 'required|image|mimes:jpeg,png,jpg,gif|max:2048';
        }
        
        return $rules;
    }
}
