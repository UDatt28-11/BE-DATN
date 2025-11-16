<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCheckedInGuestRequest;
use App\Http\Requests\Admin\UpdateCheckedInGuestRequest;
use App\Http\Resources\Admin\CheckedInGuestResource;
use App\Models\BookingDetail;
use App\Models\CheckedInGuest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckedInGuestController extends Controller
{
    /**
     * Lấy danh sách khách lưu trú theo booking_detail
     */
    public function getByBookingDetail(BookingDetail $bookingDetail): JsonResponse
    {
        $bookingDetail->load(['room', 'bookingOrder']);
        
        $guests = $bookingDetail->checkedInGuests()
            ->with('bookingDetail.room')
            ->get();

        return response()->json([
            'success' => true,
            'data' => CheckedInGuestResource::collection($guests),
            'booking_detail' => [
                'id' => $bookingDetail->id,
                'room_id' => $bookingDetail->room_id,
                'room_name' => $bookingDetail->room->name ?? null,
                'check_in_date' => $bookingDetail->check_in_date->format('Y-m-d'),
                'check_out_date' => $bookingDetail->check_out_date->format('Y-m-d'),
                'num_adults' => $bookingDetail->num_adults,
                'num_children' => $bookingDetail->num_children,
                'booking_code' => $bookingDetail->bookingOrder->order_code ?? null,
            ],
        ]);
    }

    /**
     * Thêm khách lưu trú cho booking_detail
     */
    public function storeForBookingDetail(StoreCheckedInGuestRequest $request, BookingDetail $bookingDetail): JsonResponse
    {
        try {
            $guests = [];
            
            foreach ($request->input('guests') as $guestData) {
                $guest = $bookingDetail->checkedInGuests()->create($guestData);
                $guests[] = $guest;
            }

            return response()->json([
                'success' => true,
                'message' => 'Thêm khách lưu trú thành công',
                'data' => CheckedInGuestResource::collection(collect($guests)),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin 1 khách lưu trú
     */
    public function update(UpdateCheckedInGuestRequest $request, CheckedInGuest $checkedInGuest): JsonResponse
    {
        $checkedInGuest->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin khách thành công',
            'data' => new CheckedInGuestResource($checkedInGuest),
        ]);
    }

    /**
     * Xóa 1 khách lưu trú
     */
    public function destroy(CheckedInGuest $checkedInGuest): JsonResponse
    {
        $checkedInGuest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa khách lưu trú thành công',
        ]);
    }

    /**
     * Lấy danh sách tổng khách lưu trú trên toàn hệ thống (có filter)
     */
    public function index(Request $request): JsonResponse
    {
        $query = CheckedInGuest::query()
            ->with(['bookingDetail.room', 'bookingDetail.bookingOrder']);

        // Filter by full_name
        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . $request->input('full_name') . '%');
        }

        // Filter by identity_number
        if ($request->filled('identity_number')) {
            $query->where('identity_number', 'like', '%' . $request->input('identity_number') . '%');
        }

        // Filter by identity_type
        if ($request->filled('identity_type')) {
            $query->where('identity_type', $request->input('identity_type'));
        }

        // Filter by check_in_time range
        if ($request->filled('check_in_from')) {
            $query->where('check_in_time', '>=', $request->input('check_in_from'));
        }
        if ($request->filled('check_in_to')) {
            $query->where('check_in_time', '<=', $request->input('check_in_to'));
        }

        // Filter by room_number (through relationship)
        if ($request->filled('room_number')) {
            $query->whereHas('bookingDetail.room', function ($q) use ($request) {
                $q->where('room_number', 'like', '%' . $request->input('room_number') . '%');
            });
        }

        // Filter by booking_id
        if ($request->filled('booking_id')) {
            $query->whereHas('bookingDetail', function ($q) use ($request) {
                $q->where('booking_order_id', $request->input('booking_id'));
            });
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $guests = $query->latest('check_in_time')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CheckedInGuestResource::collection($guests),
            'meta' => [
                'current_page' => $guests->currentPage(),
                'last_page' => $guests->lastPage(),
                'per_page' => $guests->perPage(),
                'total' => $guests->total(),
            ],
        ]);
    }
}
