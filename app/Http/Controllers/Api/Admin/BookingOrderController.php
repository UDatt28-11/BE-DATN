<?php

namespace App\Http\Controllers\Api\Admin;

/*
 FILE GUARD – ADMIN BOOKING MODULE ONLY
 - KHÔNG chạm schema/migrations; dùng đúng tên cột từ migrations.
 - Guests FK: 'booking_details_id'.
 - Chỉ index/show/updateStatus; bắt buộc middleware/policy admin.
*/

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
}


