<?php

namespace App\Http\Controllers\Api\Admin;


use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexBookingOrderRequest;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\Admin\BookingOrderResource;
use App\Models\BookingOrder;
use App\Services\BookingOrder\QueryService;
use Illuminate\Http\JsonResponse;

class BookingOrderController extends Controller
{
    public function index(IndexBookingOrderRequest $request, QueryService $service): JsonResponse
    {
        // Use raw query params to avoid dropping filters when validation is lenient
        $result = $service->index($request->query());
        return response()->json($result);
    }

    public function show(int $id, IndexBookingOrderRequest $request): JsonResponse
    {
        $include = array_filter(explode(',', (string)($request->validated()['include'] ?? '')));

        $query = BookingOrder::query();
        if (in_array('details', $include, true)) {
            $relations = ['details'];
            if (in_array('details.room', $include, true)) {
                $relations[] = 'details.room';
            }
            if (in_array('details.guests', $include, true)) {
                $relations[] = 'details.guests';
            }
            $query->with($relations);
        }

        $order = $query->withCount('details')->findOrFail($id);

        return response()->json([
            'data' => new BookingOrderResource($order),
        ]);
    }

    public function updateStatus(int $id, UpdateBookingStatusRequest $request): JsonResponse
    {
        $order = BookingOrder::findOrFail($id);

        $from = $order->status;
        $to = $request->validated()['status'];

        // State machine
        $valid = match ($from) {
            'pending' => in_array($to, ['confirmed', 'cancelled'], true),
            'confirmed' => in_array($to, ['completed', 'cancelled'], true),
            default => false,
        };

        if (!$valid) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TRANSITION',
                    'message' => 'Trạng thái không hợp lệ từ ' . $from . ' → ' . $to,
                ],
            ], 422);
        }

        $order->update(['status' => $to]);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * Tạo booking mới
     */
    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_id' => 'nullable|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.room_id' => 'required|exists:rooms,id',
            'details.*.check_in_date' => 'required|date',
            'details.*.check_out_date' => 'required|date|after:details.*.check_in_date',
            'details.*.num_adults' => 'required|integer|min:1',
            'details.*.num_children' => 'required|integer|min:0',
            'details.*.sub_total' => 'required|numeric|min:0',
        ]);

        // Tạo mã đơn hàng tự động
        $orderCode = 'BK' . date('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Tạo booking order
        $order = BookingOrder::create([
            'guest_id' => $validated['guest_id'] ?? null, // Lưu guest_id nếu có (khách đã đăng ký)
            'order_code' => $orderCode,
            'customer_name' => $validated['customer_name'], // Luôn lưu tên (có thể khác tên tài khoản)
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'total_amount' => $validated['total_amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        // Tạo booking details
        foreach ($validated['details'] as $detail) {
            $order->details()->create([
                'room_id' => $detail['room_id'],
                'check_in_date' => $detail['check_in_date'],
                'check_out_date' => $detail['check_out_date'],
                'num_adults' => $detail['num_adults'],
                'num_children' => $detail['num_children'],
                'sub_total' => $detail['sub_total'],
                'status' => 'active',
            ]);
        }

        $order->load('details');

        return response()->json([
            'data' => new BookingOrderResource($order),
            'message' => 'Tạo đặt phòng thành công',
        ], 201);
    }

    /**
     * Cập nhật thông tin booking
     * CHO PHÉP sửa customer_name/phone/email mà KHÔNG XÓA guest_id
     * Điều này hỗ trợ:
     * - Khách có tài khoản nhưng muốn giấu danh tính
     * - Admin sửa thông tin khách vãng lai
     */
    public function update(int $id, \Illuminate\Http\Request $request): JsonResponse
    {
        $order = BookingOrder::findOrFail($id);

        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'customer_phone' => 'sometimes|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'total_amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        // Cập nhật mà KHÔNG động vào guest_id
        // Cho phép customer_name ghi đè hiển thị từ users.full_name
        $order->update($validated);

        return response()->json([
            'data' => new BookingOrderResource($order),
            'message' => 'Cập nhật đặt phòng thành công',
        ]);
    }

    /**
     * Xóa booking
     */
    public function destroy(int $id): JsonResponse
    {
        $order = BookingOrder::findOrFail($id);

        // Chỉ cho phép xóa nếu trạng thái là pending hoặc cancelled
        if (!in_array($order->status, ['pending', 'cancelled'], true)) {
            return response()->json([
                'error' => [
                    'code' => 'CANNOT_DELETE',
                    'message' => 'Chỉ có thể xóa đơn đang chờ hoặc đã hủy',
                ],
            ], 422);
        }

        $order->delete();

        return response()->json([
            'message' => 'Xóa đặt phòng thành công',
        ]);
    }
}


