<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_details_id' => 'required|exists:booking_details,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:100',
            'comment' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'booking_details_id.required' => 'ID chi tiết booking là bắt buộc',
            'booking_details_id.exists' => 'Chi tiết booking không tồn tại',
            'rating.required' => 'Đánh giá (sao) là bắt buộc',
            'rating.min' => 'Đánh giá phải từ 1 đến 5 sao',
            'rating.max' => 'Đánh giá phải từ 1 đến 5 sao',
            'title.required' => 'Tiêu đề đánh giá là bắt buộc',
            'title.max' => 'Tiêu đề không vượt quá 100 ký tự',
            'comment.max' => 'Bình luận không vượt quá 2000 ký tự',
            'photos.max' => 'Không thể tải lên quá 10 ảnh',
        ];
    }
}
