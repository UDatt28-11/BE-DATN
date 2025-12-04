<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'sometimes|integer|exists:properties,id',
            'room_type_id' => 'sometimes|integer|exists:room_types,id',
            'status' => 'sometimes|string|in:available,maintenance,occupied',
            'verification_status' => 'sometimes|string|in:pending,verified,rejected',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:id,name,price_per_night,created_at,updated_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean empty strings
        $data = $this->all();
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                unset($data[$key]);
            }
        }
        $this->merge($data);
    }
}

