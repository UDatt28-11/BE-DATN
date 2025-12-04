<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'sometimes|integer|exists:properties,id',
            'is_active' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:id,code,name,is_active,created_at,updated_at',
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

