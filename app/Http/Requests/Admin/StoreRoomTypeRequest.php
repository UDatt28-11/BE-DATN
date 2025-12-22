<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Cho phép property_id rỗng, sẽ gán property mặc định ở controller
            'property_id' => 'nullable|exists:properties,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // Giá & sức chứa chung cho loại phòng
            'base_price' => 'required|numeric|gt:0',
            'max_adults' => 'required|integer|min:1',
            'max_children' => 'nullable|integer|min:0',
            // Danh sách dịch vụ áp dụng cho loại phòng
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            // Cho phép không có file (nullable)
        ];
    }
    
    /**
     * Prepare the data for validation.
     * Convert string numbers to actual numbers for FormData
     */
    protected function prepareForValidation(): void
    {
        // Convert string numbers to integers/floats for FormData
        if ($this->has('base_price')) {
            $this->merge([
                'base_price' => is_numeric($this->base_price) ? (float) $this->base_price : $this->base_price,
            ]);
        }
        
        if ($this->has('max_adults')) {
            $this->merge([
                'max_adults' => is_numeric($this->max_adults) ? (int) $this->max_adults : $this->max_adults,
            ]);
        }
        
        if ($this->has('max_children') && $this->max_children !== null) {
            $this->merge([
                'max_children' => is_numeric($this->max_children) ? (int) $this->max_children : $this->max_children,
            ]);
        }
        
        // Handle service_ids array from FormData
        if ($this->has('service_ids') && !is_array($this->service_ids)) {
            // If service_ids is not an array, try to convert it
            $serviceIds = $this->service_ids;
            if (is_string($serviceIds)) {
                // Try to decode if it's JSON
                $decoded = json_decode($serviceIds, true);
                if (is_array($decoded)) {
                    $this->merge(['service_ids' => $decoded]);
                } else {
                    // If it's a single value, make it an array
                    $this->merge(['service_ids' => [$serviceIds]]);
                }
            } else {
                $this->merge(['service_ids' => []]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'base_price.required' => 'Vui lòng nhập giá / đêm.',
            'base_price.numeric' => 'Giá / đêm phải là số.',
            'base_price.gt' => 'Giá / đêm phải lớn hơn 0.',
            'max_adults.required' => 'Vui lòng nhập số người lớn tối đa.',
            'max_adults.integer' => 'Số người lớn tối đa phải là số nguyên.',
            'max_adults.min' => 'Số người lớn tối đa phải lớn hơn hoặc bằng 1.',
            'max_children.integer' => 'Số trẻ em tối đa phải là số nguyên.',
            'max_children.min' => 'Số trẻ em tối đa phải lớn hơn hoặc bằng 0.',
            'image_file.image' => 'File phải là hình ảnh hợp lệ.',
            'image_file.mimes' => 'File ảnh phải có định dạng: jpeg, png, jpg, gif, hoặc webp.',
            'image_file.max' => 'Kích thước file ảnh không được vượt quá 2MB.',
        ];
    }
}
