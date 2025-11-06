<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingOrderController extends Controller
{
    /**
     * Lấy danh sách booking orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = BookingOrder::with(['guest', 'details.room', 'details.room.roomType']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('guest_id')) {
            $query->where('guest_id', $request->guest_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Lấy chi tiết booking order
     */
    public function show(int $id): JsonResponse
    {
        $order = BookingOrder::with([
            'guest',
            'details.room',
            'details.room.roomType',
            'details.room.property',
            'details.guests',
        ])->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    /**
     * Tạo booking mới
     */
    public function store(Request $request): JsonResponse
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
            'guest_id' => $validated['guest_id'] ?? null,
            'order_code' => $orderCode,
            'customer_name' => $validated['customer_name'],
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
            'data' => $order,
            'message' => 'Tạo đặt phòng thành công',
        ], 201);
    }

    /**
     * Cập nhật booking
     */
    public function update(int $id, Request $request): JsonResponse
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

        $order->update($validated);

        return response()->json([
            'data' => $order,
            'message' => 'Cập nhật đặt phòng thành công',
        ]);
    }

    /**
     * Cập nhật trạng thái booking
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $order = BookingOrder::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
        ]);

        $from = $order->status;
        $to = $validated['status'];

        // State machine validation
        $valid = match ($from) {
            'pending' => in_array($to, ['confirmed', 'cancelled'], true),
            'confirmed' => in_array($to, ['completed', 'cancelled'], true),
            'completed', 'cancelled' => false,
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
            'message' => 'Cập nhật trạng thái thành công',
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

