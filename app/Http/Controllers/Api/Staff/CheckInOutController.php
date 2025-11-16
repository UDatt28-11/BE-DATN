<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use App\Models\BookingDetail;
use App\Models\CheckedInGuest;
use App\Models\Room;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Check-in/Check-out Controller for Staff
 */
class CheckInOutController extends Controller
{
    /**
     * Get list of bookings ready for check-in
     * Lấy danh sách booking sẵn sàng để check-in (status: confirmed, check_in_date = today)
     */
    public function getCheckInList(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'sometimes|date',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $date = $request->get('date', now()->toDateString());
            $perPage = (int) ($request->get('per_page', 15));

            $query = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'details.room:id,name,property_id',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
            ])
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereHas('details', function ($q) use ($date) {
                $q->whereDate('check_in_date', '<=', $date)
                  ->where('status', 'active');
            });

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                      ->orWhere('customer_name', 'like', '%' . $search . '%')
                      ->orWhere('customer_phone', 'like', '%' . $search . '%')
                      ->orWhere('customer_email', 'like', '%' . $search . '%');
                });
            }

            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bookings->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'total' => $bookings->total(),
                        'last_page' => $bookings->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckInList failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách check-in.',
            ], 500);
        }
    }

    /**
     * Get booking details for check-in
     */
    public function getCheckInDetails(string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'staff:id,full_name,email',
                'details.room:id,name,property_id,status',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
                'details.bookingServices.service:id,name,unit_price',
            ])->findOrFail($id);

            // Kiểm tra booking có thể check-in không
            if (!in_array($booking->status, ['confirmed', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-in. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckInDetails failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin check-in.',
            ], 500);
        }
    }

    /**
     * Process check-in
     * Xử lý check-in: lưu thông tin khách, upload giấy tờ, cập nhật trạng thái
     */
    public function checkIn(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'guests' => 'required|array|min:1',
                'guests.*.full_name' => 'required|string|max:255',
                'guests.*.date_of_birth' => 'nullable|date',
                'guests.*.identity_type' => 'required|in:cccd,passport',
                'guests.*.identity_number' => 'required|string|max:50',
                'guests.*.identity_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB
                'guests.*.booking_detail_id' => 'required|exists:booking_details,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details.room'])->findOrFail($id);

            // Kiểm tra booking có thể check-in không
            if (!in_array($booking->status, ['confirmed', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-in.',
                ], 400);
            }

            // Xử lý từng guest
            foreach ($request->guests as $index => $guestData) {
                $bookingDetail = BookingDetail::findOrFail($guestData['booking_detail_id']);

                // Kiểm tra booking detail thuộc về booking order này
                if ($bookingDetail->booking_order_id != $booking->id) {
                    throw new \Exception('Booking detail không thuộc về booking order này.');
                }

                // Upload identity image nếu có
                // File được gửi với key: guests[0][identity_image] hoặc guests.0.identity_image
                $identityImageUrl = null;
                $fileKey = "guests.{$index}.identity_image";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $identityImageUrl = $this->storeIdentityImage($file);
                }

                // Tạo CheckedInGuest record
                CheckedInGuest::create([
                    'booking_details_id' => $bookingDetail->id,
                    'full_name' => $guestData['full_name'],
                    'date_of_birth' => $guestData['date_of_birth'] ?? null,
                    'identity_type' => $guestData['identity_type'],
                    'identity_number' => $guestData['identity_number'],
                    'identity_image_url' => $identityImageUrl,
                    'check_in_time' => now(),
                ]);
            }

            // Cập nhật trạng thái booking order và booking details
            $booking->update([
                'status' => 'checked_in',
                'staff_id' => Auth::id(),
                'notes' => $request->notes ?? $booking->notes,
            ]);

            // Cập nhật trạng thái booking details
            $booking->details()->update(['status' => 'checked_in']);

            // Cập nhật trạng thái phòng thành "occupied"
            foreach ($booking->details as $detail) {
                $detail->room->update(['status' => 'occupied']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-in thành công.',
                'data' => $booking->fresh(['guest', 'details.room', 'details.checkedInGuests']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CheckInOutController@checkIn failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi check-in: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of bookings ready for check-out
     */
    public function getCheckOutList(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'sometimes|date',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $date = $request->get('date', now()->toDateString());
            $perPage = (int) ($request->get('per_page', 15));

            $query = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'details.room:id,name,property_id',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
                'invoices',
            ])
            ->where('status', 'checked_in')
            ->whereHas('details', function ($q) use ($date) {
                $q->whereDate('check_out_date', '<=', $date)
                  ->where('status', 'checked_in');
            });

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                      ->orWhere('customer_name', 'like', '%' . $search . '%')
                      ->orWhere('customer_phone', 'like', '%' . $search . '%');
                });
            }

            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bookings->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'total' => $bookings->total(),
                        'last_page' => $bookings->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckOutList failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách check-out.',
            ], 500);
        }
    }

    /**
     * Get booking details for check-out
     */
    public function getCheckOutDetails(string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'staff:id,full_name,email',
                'details.room:id,name,property_id,status',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
                'details.bookingServices.service:id,name,unit_price',
                'invoices.invoiceItems',
            ])->findOrFail($id);

            // Kiểm tra booking có thể check-out không
            if ($booking->status !== 'checked_in') {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-out. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckOutDetails failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin check-out.',
            ], 500);
        }
    }

    /**
     * Process check-out
     * Xử lý check-out: kiểm tra vật tư/dịch vụ, tính phí phát sinh, tạo invoice, cập nhật trạng thái phòng
     */
    public function checkOut(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'room_status' => 'required|in:available,maintenance',
                'additional_services' => 'nullable|array',
                'additional_services.*.service_id' => 'required|exists:services,id',
                'additional_services.*.quantity' => 'required|integer|min:1',
                'damaged_supplies' => 'nullable|array',
                'damaged_supplies.*.supply_id' => 'required|exists:supplies,id',
                'damaged_supplies.*.quantity' => 'required|integer|min:1',
                'damaged_supplies.*.notes' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:1000',
                'create_invoice' => 'nullable|boolean',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details.room', 'invoices'])->findOrFail($id);

            // Kiểm tra booking có thể check-out không
            if ($booking->status !== 'checked_in') {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-out.',
                ], 400);
            }

            // Cập nhật trạng thái booking order
            $booking->update([
                'status' => 'checked_out',
                'staff_id' => Auth::id(),
                'notes' => $request->notes ?? $booking->notes,
            ]);

            // Cập nhật trạng thái booking details
            $booking->details()->update(['status' => 'checked_out']);

            // Cập nhật trạng thái phòng
            foreach ($booking->details as $detail) {
                $detail->room->update(['status' => $request->room_status]);
            }

            // Xử lý dịch vụ phát sinh (nếu có)
            // TODO: Implement logic để thêm dịch vụ vào booking và tính phí

            // Xử lý vật tư bị hỏng (nếu có)
            // TODO: Implement logic để ghi nhận vật tư bị hỏng và trừ vào tồn kho

            // Tạo invoice nếu chưa có và được yêu cầu
            if ($request->get('create_invoice', true) && !$booking->invoices()->exists()) {
                // TODO: Gọi InvoiceController@createFromBooking hoặc tạo invoice trực tiếp
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-out thành công.',
                'data' => $booking->fresh(['guest', 'details.room', 'invoices']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CheckInOutController@checkOut failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi check-out: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store identity image
     */
    private function storeIdentityImage($file): string
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $path = $file->storeAs('identity_images', $filename, 'public');
            
            $relativeUrl = Storage::disk('public')->url($path);
            $appUrl = rtrim(config('app.url'), '/');
            $url = $appUrl . $relativeUrl;
            
            return $url;
        } catch (\Exception $e) {
            Log::error('CheckInOutController@storeIdentityImage failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Lỗi khi tải file giấy tờ tùy thân: ' . $e->getMessage());
        }
    }
}

