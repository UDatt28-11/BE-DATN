<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'code' => 'required|string|unique:promotions,code|max:50',
            'description' => 'nullable|string|max:500',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_usage_limit' => 'nullable|integer|min:1',
            'max_usage_per_user' => 'nullable|integer|min:1|default:1',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'applicable_to' => 'required|in:all,specific_rooms,specific_room_types',
            'room_ids' => 'nullable|array|required_if:applicable_to,specific_rooms',
            'room_ids.*' => 'exists:rooms,id',
            'room_type_ids' => 'nullable|array|required_if:applicable_to,specific_room_types',
            'room_type_ids.*' => 'exists:room_types,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã giảm giá là bắt buộc',
            'code.unique' => 'Mã giảm giá này đã tồn tại',
            'discount_value.required' => 'Giá trị giảm giá là bắt buộc',
            'start_date.before_or_equal' => 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu',
            'applicable_to.required' => 'Loại áp dụng là bắt buộc',
        ];
    }
}


