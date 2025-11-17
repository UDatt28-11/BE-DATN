<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BookingOrderResource;
use App\Models\BookingDetail;
use App\Models\BookingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;

class BookingController extends Controller
{
    /**
     * Danh sách đơn đặt phòng cho nhân viên
     *
     * Staff có thể lọc theo mã đơn, tên khách, trạng thái, ngày check-in/out.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_code' => 'sometimes|string|max:255',
                'customer_name' => 'sometimes|string|max:255',
                'status' => 'sometimes|string|in:pending,confirmed,checked_in,checked_out,cancelled,completed',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'check_in_from' => 'sometimes|date',
                'check_in_to' => 'sometimes|date|after_or_equal:check_in_from',
                'check_out_from' => 'sometimes|date',
                'check_out_to' => 'sometimes|date|after_or_equal:check_out_from',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $perPage = (int) $request->get('per_page', 15);

            $query = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'staff:id,full_name,email',
                'details.room:id,name,property_id',
                'details.room.property:id,name,address',
            ]);

            // Filter cơ bản
            if ($request->filled('order_code')) {
                $query->where('order_code', 'like', '%' . $request->order_code . '%');
            }
            if ($request->filled('customer_name')) {
                $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            if ($request->filled('check_in_from') || $request->filled('check_in_to')) {
                $query->whereHas('details', function ($q) use ($request) {
                    if ($request->filled('check_in_from')) {
                        $q->whereDate('check_in_date', '>=', $request->check_in_from);
                    }
                    if ($request->filled('check_in_to')) {
                        $q->whereDate('check_in_date', '<=', $request->check_in_to);
                    }
                });
            }
            if ($request->filled('check_out_from') || $request->filled('check_out_to')) {
                $query->whereHas('details', function ($q) use ($request) {
                    if ($request->filled('check_out_from')) {
                        $q->whereDate('check_out_date', '>=', $request->check_out_from);
                    }
                    if ($request->filled('check_out_to')) {
                        $q->whereDate('check_out_date', '<=', $request->check_out_to);
                    }
                });
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_email', 'like', '%' . $search . '%')
                        ->orWhere('customer_phone', 'like', '%' . $search . '%');
                });
            }

            $query->orderByDesc('created_at');

            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => BookingOrderResource::collection($orders),
                'meta' => [
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'per_page' => $orders->PerPage(),
                        'total' => $orders->total(),
                        'last_page' => $orders->lastPage(),
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Staff\\BookingController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đặt phòng.',
            ], 500);
        }
    }

    /**
     * Chi tiết 1 đơn đặt phòng cho staff
     */
    public function show(string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'staff:id,full_name,email',
                'details.room:id,name,property_id',
                'details.room.property:id,name,address',
                'details.bookingServices.service:id,name,unit_price',
                'invoices.invoiceItems',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new BookingOrderResource($booking),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Staff\\BookingController@show failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin đơn đặt phòng.',
            ], 500);
        }
    }

    /**
     * Staff cập nhật trạng thái đơn đặt phòng
     * Allowed: pending, confirmed, checked_in, checked_out, cancelled, completed
     */
    public function updateStatus(UpdateBookingStatusRequest $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $booking = BookingOrder::findOrFail($id);

            $from = $booking->status;
            $to = $validated['status'];

            // State machine giống Admin: đảm bảo luồng trạng thái hợp lệ
            $valid = match ($from) {
                'pending'   => in_array($to, ['confirmed', 'cancelled'], true),
                'confirmed' => in_array($to, ['checked_in', 'cancelled'], true),
                'checked_in' => in_array($to, ['checked_out', 'cancelled'], true),
                'checked_out' => in_array($to, ['completed'], true),
                default     => false,
            };

            if (!$valid) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TRANSITION',
                        'message' => "Trạng thái không hợp lệ từ {$from} → {$to}",
                    ],
                ], 422);
            }

            $booking->status = $to;
            if (isset($validated['notes'])) {
                $booking->notes = $validated['notes'];
            }
            $booking->staff_id = $request->user()->id;
            $booking->save();

            Log::info('Staff updated booking status', [
                'booking_id' => $booking->id,
                'from' => $from,
                'to' => $to,
                'staff_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái đơn đặt phòng thành công',
                'data' => new BookingOrderResource($booking),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Staff\\BookingController@updateStatus failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái đơn đặt phòng.',
            ], 500);
        }
    }

    /**
     * Staff đổi phòng / đổi ngày cho một booking detail
     *
     * Body:
     * - booking_detail_id (bắt buộc)
     * - room_id (tùy chọn: nếu muốn đổi sang phòng khác)
     * - check_in_date, check_out_date (tùy chọn: nếu muốn đổi ngày)
     * - notes (ghi chú nội bộ thêm vào booking)
     */
    public function changeDetail(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'booking_detail_id' => 'required|integer|exists:booking_details,id',
                'room_id' => 'sometimes|integer|exists:rooms,id',
                'check_in_date' => 'sometimes|date',
                'check_out_date' => 'sometimes|date|after_or_equal:check_in_date',
                'notes' => 'sometimes|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with('details')->findOrFail($id);

            $detail = BookingDetail::where('id', $validated['booking_detail_id'])
                ->where('booking_order_id', $booking->id)
                ->firstOrFail();

            $updateData = [];
            if (isset($validated['room_id'])) {
                $updateData['room_id'] = $validated['room_id'];
            }
            if (isset($validated['check_in_date'])) {
                $updateData['check_in_date'] = $validated['check_in_date'];
            }
            if (isset($validated['check_out_date'])) {
                $updateData['check_out_date'] = $validated['check_out_date'];
            }

            if (!empty($updateData)) {
                $detail->update($updateData);
            }

            if (isset($validated['notes'])) {
                $booking->notes = trim(($booking->notes ?? '') . "\n[STAFF] " . $validated['notes']);
                $booking->staff_id = $request->user()->id;
                $booking->save();
            }

            DB::commit();

            Log::info('Staff changed booking detail', [
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail->id,
                'staff_id' => $request->user()->id,
                'changes' => $updateData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật chi tiết đặt phòng thành công',
                'data' => new BookingOrderResource(
                    $booking->fresh(['guest', 'details.room.property', 'staff'])
                ),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng hoặc chi tiết đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff\\BookingController@changeDetail failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật chi tiết đặt phòng: ' . $e->getMessage(),
            ], 500);
        }
    }
}


