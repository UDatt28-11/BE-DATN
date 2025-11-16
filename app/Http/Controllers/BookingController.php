<?php

namespace App\Http\Controllers;

use App\Models\BookingOrder;
use App\Models\BookingDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Display a listing of booking orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BookingOrder::with(['guest', 'property', 'bookingDetails.room', 'invoice']);

            // Search by keyword
            if ($request->has('keyword') && $request->keyword) {
                $keyword = $request->keyword;
                $query->where(function ($q) use ($keyword) {
                    $q->where('order_code', 'like', "%{$keyword}%")
                        ->orWhere('customer_name', 'like', "%{$keyword}%")
                        ->orWhere('customer_phone', 'like', "%{$keyword}%")
                        ->orWhere('customer_email', 'like', "%{$keyword}%");
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                if (is_string($request->status)) {
                    $statuses = explode(',', $request->status);
                    $query->whereIn('status', $statuses);
                } else {
                    $query->where('status', $request->status);
                }
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filter by check-in/check-out dates
            if ($request->has('date_field') && $request->has('date_from')) {
                $dateField = $request->date_field;
                if ($dateField === 'check_in_date' || $dateField === 'check_out_date') {
                    $query->whereHas('bookingDetails', function ($q) use ($dateField, $request) {
                        $q->whereDate($dateField, '>=', $request->date_from);
                        if ($request->has('date_to')) {
                            $q->whereDate($dateField, '<=', $request->date_to);
                        }
                    });
                }
            }

            // Filter by total amount
            if ($request->has('min_total')) {
                $query->where('total_amount', '>=', $request->min_total);
            }
            if ($request->has('max_total')) {
                $query->where('total_amount', '<=', $request->max_total);
            }

            // Sort
            $sort = $request->get('sort', '-created_at');
            if ($sort === '-created_at') {
                $query->orderBy('created_at', 'desc');
            } elseif ($sort === 'created_at') {
                $query->orderBy('created_at', 'asc');
            } elseif ($sort === '-checkin_date') {
                $query->whereHas('bookingDetails', function ($q) {
                    $q->orderBy('check_in_date', 'desc');
                });
            } elseif ($sort === 'checkin_date') {
                $query->whereHas('bookingDetails', function ($q) {
                    $q->orderBy('check_in_date', 'asc');
                });
            } elseif ($sort === '-total_amount') {
                $query->orderBy('total_amount', 'desc');
            } elseif ($sort === 'total_amount') {
                $query->orderBy('total_amount', 'asc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $bookings = $query->paginate($perPage);

            // Format response
            $bookings->getCollection()->transform(function ($booking) {
                // Tính toán checkin_date và checkout_date từ bookingDetails
                $details = $booking->bookingDetails;
                $checkinDate = $details->isNotEmpty() ? $details->min('check_in_date') : null;
                $checkoutDate = $details->isNotEmpty() ? $details->max('check_out_date') : null;
                
                // Thêm các field tính toán
                $booking->checkin_date = $checkinDate ? $checkinDate->format('Y-m-d') : null;
                $booking->checkout_date = $checkoutDate ? $checkoutDate->format('Y-m-d') : null;
                $booking->code = $booking->order_code;
                $booking->details_count = $details->count();
                
                return $booking;
            });

            return response()->json([
                'success' => true,
                'data' => $bookings->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'total' => $bookings->total(),
                        'last_page' => $bookings->lastPage(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified booking order
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $includes = $request->get('include', '');
            $with = ['guest', 'property'];
            
            if ($includes) {
                $includeArray = array_map('trim', explode(',', $includes));
                $hasDetails = false;
                
                foreach ($includeArray as $include) {
                    if ($include === 'details') {
                        $with[] = 'bookingDetails';
                        $hasDetails = true;
                    } elseif ($include === 'details.room') {
                        $with[] = 'bookingDetails.room';
                        $hasDetails = true;
                    } elseif ($include === 'details.room.roomType') {
                        $with[] = 'bookingDetails.room.roomType';
                        $hasDetails = true;
                    } elseif ($include === 'details.guests') {
                        $with[] = 'bookingDetails.guests';
                        $hasDetails = true;
                    } elseif ($include === 'invoice') {
                        $with[] = 'invoice';
                    }
                }
                
                // Đảm bảo luôn load room và roomType nếu có details
                if ($hasDetails) {
                    if (!in_array('bookingDetails.room.roomType', $with)) {
                        $with[] = 'bookingDetails.room.roomType';
                    }
                    if (!in_array('bookingDetails', $with)) {
                        $with[] = 'bookingDetails';
                    }
                }
            } else {
                // Mặc định load đầy đủ
                $with[] = 'bookingDetails';
                $with[] = 'bookingDetails.room';
                $with[] = 'bookingDetails.room.roomType';
                $with[] = 'bookingDetails.guests';
                $with[] = 'checkedInGuests'; // Giữ lại để tương thích
                $with[] = 'invoice';
            }
            
            // Loại bỏ duplicates
            $with = array_unique($with);

            $booking = BookingOrder::with($with)->findOrFail($id);

            // Format response
            $details = $booking->bookingDetails;
            $checkinDate = $details->isNotEmpty() ? $details->min('check_in_date') : null;
            $checkoutDate = $details->isNotEmpty() ? $details->max('check_out_date') : null;
            
            $booking->checkin_date = $checkinDate ? $checkinDate->format('Y-m-d') : null;
            $booking->checkout_date = $checkoutDate ? $checkoutDate->format('Y-m-d') : null;
            $booking->code = $booking->order_code;
            $booking->details_count = $details->count();
            
            // Đảm bảo details được trả về với tên 'details' để frontend dễ sử dụng
            // Và đảm bảo mỗi detail có đầy đủ thông tin room
            $booking->details = $details->map(function ($detail) {
                // Đảm bảo room được load
                if (!$detail->relationLoaded('room')) {
                    $detail->load('room.roomType');
                }
                return $detail;
            });

            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải thông tin đặt phòng: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Store a newly created booking order
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'guest_id' => 'nullable|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:credit_card,bank_transfer,cash,e_wallet',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.room_id' => 'required|exists:rooms,id',
            'details.*.check_in_date' => 'required|date',
            'details.*.check_out_date' => 'required|date|after:details.*.check_in_date',
            'details.*.num_adults' => 'required|integer|min:1',
            'details.*.num_children' => 'required|integer|min:0',
            'details.*.sub_total' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate order code
            $orderCode = 'BK-' . strtoupper(Str::random(8));

            // Create booking order
            $booking = BookingOrder::create([
                'guest_id' => $request->guest_id ?? null,
                'property_id' => $request->property_id ?? null,
                'order_code' => $orderCode,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email ?? null,
                'total_amount' => $request->total_amount,
                'payment_method' => $request->payment_method ?? 'cash',
                'notes' => $request->notes ?? null,
                'status' => 'pending',
            ]);

            // Create booking details
            foreach ($request->details as $detailData) {
                BookingDetail::create([
                    'booking_order_id' => $booking->id,
                    'room_id' => $detailData['room_id'],
                    'check_in_date' => $detailData['check_in_date'],
                    'check_out_date' => $detailData['check_out_date'],
                    'num_adults' => $detailData['num_adults'],
                    'num_children' => $detailData['num_children'],
                    'sub_total' => $detailData['sub_total'],
                    'status' => 'active',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đặt phòng đã được tạo thành công',
                'data' => $booking->load(['bookingDetails.room', 'guest', 'property'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đặt phòng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified booking order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'customer_phone' => 'sometimes|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'total_amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'nullable|in:credit_card,bank_transfer,cash,e_wallet',
            'notes' => 'nullable|string',
        ]);

        try {
            $booking = BookingOrder::findOrFail($id);
            $booking->update($request->only([
                'customer_name',
                'customer_phone',
                'customer_email',
                'total_amount',
                'payment_method',
                'notes',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Đặt phòng đã được cập nhật',
                'data' => $booking->load(['bookingDetails.room', 'guest', 'property'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật đặt phòng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update booking status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled'
        ]);

        try {
            $booking = BookingOrder::findOrFail($id);
            $booking->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Trạng thái đặt phòng đã được cập nhật',
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified booking order
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::findOrFail($id);
            $booking->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đặt phòng đã được xóa'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa đặt phòng: ' . $e->getMessage()
            ], 500);
        }
    }
}

