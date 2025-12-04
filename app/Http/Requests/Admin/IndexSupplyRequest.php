<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => 'sometimes|integer|exists:rooms,id',
            'category' => 'sometimes|string|max:100',
            'status' => 'sometimes|string|in:active,inactive,discontinued',
            'stock_status' => 'sometimes|string|in:low_stock,out_of_stock,in_stock',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:id,name,category,status,current_stock,unit_price,created_at,updated_at',
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

