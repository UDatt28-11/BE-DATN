<?php
// app/Http/Controllers/Api/Admin/BookingOrderController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BookingOrderResource;
use App\Http\Requests\Admin\StoreBookingOrderRequest;
use App\Http\Requests\Admin\UpdateBookingOrderRequest;
use App\Http\Requests\Admin\IndexBookingOrderRequest;
use App\Models\BookingOrder;
use App\Models\BookingDetail;
use App\Services\BookingOrder\QueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;

/**
 * @OA\Tag(
 *     name="Booking Orders",
 *     description="API Endpoints for Booking Order Management"
 * )
 */
class BookingOrderController extends Controller
{
    use AuthorizesRequests;
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of booking orders
     *
     * @OA\Get(
     *     path="/api/admin/booking-orders",
     *     operationId="getBookingOrders",
     *     tags={"Booking Orders"},
     *     summary="Danh sách đơn đặt phòng",
     *     description="Lấy danh sách tất cả đơn đặt phòng với hỗ trợ phân trang và bộ lọc",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (mặc định 1)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Số lượng bản ghi mỗi trang (mặc định 15)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relationships (details, details.room, details.guests)",
     *         required=false,
     *         @OA\Schema(type="string", example="details,details.room,details.guests")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (có thể dùng comma-separated: pending,confirmed)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         description="Tìm kiếm theo order_code, customer name hoặc phone",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách đơn đặt phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(IndexBookingOrderRequest $request, QueryService $service): JsonResponse
    {
        try {
            // Authorization: Route middleware checks role, Policy checks permissions
            $this->authorize('viewAny', BookingOrder::class);
            
            // Use QueryService để xử lý logic query phức tạp
            // Use raw query params to avoid dropping filters when validation is lenient
            $result = $service->index($request->query());

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đơn đặt phòng.',
            ], 500);
        }
    }

    /**
     * Store a newly created booking order
     *
     * @OA\Post(
     *     path="/api/admin/booking-orders",
     *     operationId="storeBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Tạo đơn đặt phòng mới",
     *     description="Tạo đơn đặt phòng mới",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=201,
     *         description="Tạo đơn đặt phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(StoreBookingOrderRequest $request): JsonResponse
    {
        try {
            // Authorization: Route middleware checks role, Policy checks permissions
            $this->authorize('create', BookingOrder::class);
            
            $validated = $request->validated();
            
            // Tách details ra khỏi validated data
            $details = $validated['details'] ?? [];
            unset($validated['details']);
            
            // Đảm bảo guest_id là null nếu không có (nullable)
            if (!isset($validated['guest_id'])) {
                $validated['guest_id'] = null;
            }
            
            // Tự động tạo order_code nếu chưa có
            if (empty($validated['order_code'])) {
                $validated['order_code'] = $this->generateOrderCode();
            }
            
            // Đảm bảo status có giá trị mặc định
            // Tạo booking với status 'pending' khi chưa thanh toán
            // Chỉ chuyển sang 'confirmed' khi thanh toán cọc thành công
            if (empty($validated['status'])) {
                $validated['status'] = 'pending';
            }
            
            // Tạo BookingOrder
            $order = BookingOrder::create($validated);
            
            // Tạo các BookingDetail
            if (!empty($details)) {
                foreach ($details as $detail) {
                    $detail['booking_order_id'] = $order->id;
                    BookingDetail::create($detail);
                }
            }

            // Load lại relationships để trả về đầy đủ
            $order->load(['details', 'details.room']);

            Log::info('BookingOrder created', [
                'booking_order_id' => $order->id,
                'details_count' => count($details),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn đặt phòng thành công',
                'data' => new BookingOrderResource($order),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đơn đặt phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified booking order
     *
     * @OA\Get(
     *     path="/api/admin/booking-orders/{id}",
     *     operationId="getBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Chi tiết đơn đặt phòng",
     *     description="Lấy thông tin chi tiết của một đơn đặt phòng. Có thể sử dụng 'include' parameter để chỉ load relationships cần thiết.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relationships (details, details.room, details.room.roomType, details.guests, invoice, promotions). Ví dụ: 'details,details.room,invoice'",
     *         required=false,
     *         @OA\Schema(type="string", example="details,details.room,invoice")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết đơn đặt phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Booking order not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Request $request, BookingOrder $booking_order): JsonResponse
    {
        try {
            // Authorization: Route middleware checks role, Policy checks permissions
            $this->authorize('view', $booking_order);
            
            // Parse include parameter
            $includes = $request->get('include', '');
            $with = []; // Không load guest mặc định vì có thể null
            
            if ($includes) {
                // Parse include string (e.g., "details,details.room,invoice")
                $includeArray = array_map('trim', explode(',', $includes));
                $hasDetails = false;
                
                foreach ($includeArray as $include) {
                    if ($include === 'details') {
                        $with[] = 'details';
                        $hasDetails = true;
                    } elseif ($include === 'details.room') {
                        $with[] = 'details.room:id,name,room_type_id,property_id';
                        $hasDetails = true;
                    } elseif ($include === 'details.room.roomType') {
                        $with[] = 'details.room.roomType:id,name';
                        $hasDetails = true;
                    } elseif ($include === 'details.room.property') {
                        $with[] = 'details.room.property:id,name';
                        $hasDetails = true;
                    } elseif ($include === 'details.guests' || $include === 'details.checkedInGuests') {
                        $with[] = 'details.checkedInGuests';
                        $hasDetails = true;
                    } elseif ($include === 'details.bookingServices') {
                        $with[] = 'details.bookingServices.service:id,name';
                        $hasDetails = true;
                    } elseif ($include === 'invoice' || $include === 'invoices') {
                        $with[] = 'invoices';
                        $with[] = 'invoices.payments';
                        $with[] = 'invoices.invoiceItems';
                    } elseif ($include === 'promotions') {
                        $with[] = 'promotions:id,code,description';
                    }
                }
                
                // Đảm bảo luôn load room và roomType nếu có details
                if ($hasDetails) {
                    // Nếu có details.room nhưng chưa có details, tự động thêm
                    if (in_array('details.room:id,name,room_type_id,property_id', $with) && !in_array('details', $with)) {
                        $with[] = 'details';
                    }
                    // Nếu có details nhưng chưa có room, tự động thêm room và roomType
                    if (in_array('details', $with) && !in_array('details.room:id,name,room_type_id,property_id', $with)) {
                        $with[] = 'details.room:id,name,room_type_id,property_id';
                        $with[] = 'details.room.roomType:id,name';
                    }
                    // Luôn load roomType nếu có room
                    if (in_array('details.room:id,name,room_type_id,property_id', $with) && !in_array('details.room.roomType:id,name', $with)) {
                        $with[] = 'details.room.roomType:id,name';
                    }
                }
            } else {
                // Default: Load tất cả relationships (backward compatibility)
                $with = [
                    'details.room:id,name,room_type_id,property_id',
                    'details.room.roomType:id,name',
                    'details.room.property:id,name',
                    'details.bookingServices.service:id,name',
                    'details.checkedInGuests',
                    'invoices',
                    'invoices.payments',
                    'invoices.invoiceItems',
                    'promotions:id,code,description'
                ];
            }
            
            // Chỉ load guest nếu booking_order có guest_id
            if ($booking_order->guest_id) {
                if (!in_array('guest:id,full_name,email', $with)) {
                    $with[] = 'guest:id,full_name,email';
                }
            }
            
            // Loại bỏ duplicates
            $with = array_unique($with);
            
            // Load relationships
            $booking_order->load($with);
            
            return response()->json([
                'success' => true,
                'data' => new BookingOrderResource($booking_order),
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@show failed', [
                'booking_order_id' => $booking_order->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin đơn đặt phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified booking order
     *
     * @OA\Put(
     *     path="/api/admin/booking-orders/{id}",
     *     operationId="updateBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Cập nhật đơn đặt phòng",
     *     description="Cập nhật thông tin đơn đặt phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Booking order not found")
     * )
     */
    public function update(UpdateBookingOrderRequest $request, BookingOrder $booking_order): JsonResponse
    {
        try {
            // Authorization: Route middleware checks role, Policy checks permissions
            $this->authorize('update', $booking_order);
            
        $booking_order->update($request->validated());

            Log::info('BookingOrder updated', [
                'booking_order_id' => $booking_order->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn đặt phòng thành công',
                'data' => new BookingOrderResource($booking_order),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@update failed', [
                'booking_order_id' => $booking_order->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật đơn đặt phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified booking order
     *
     * @OA\Delete(
     *     path="/api/admin/booking-orders/{id}",
     *     operationId="deleteBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Xóa đơn đặt phòng",
     *     description="Xóa đơn đặt phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Booking order not found")
     * )
     */
    public function destroy(BookingOrder $booking_order): JsonResponse
    {
        try {
            // Authorization: Route middleware checks role, Policy checks permissions
            $this->authorize('delete', $booking_order);
            
            $bookingOrderId = $booking_order->id;
            
        $booking_order->delete();

            Log::info('BookingOrder deleted', [
                'booking_order_id' => $bookingOrderId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa đơn đặt phòng thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@destroy failed', [
                'booking_order_id' => $booking_order->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa đơn đặt phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update booking order status
     *
     * @OA\Patch(
     *     path="/api/admin/booking-orders/{id}/status",
     *     operationId="updateBookingOrderStatus",
     *     tags={"Booking Orders"},
     *     summary="Cập nhật trạng thái đơn đặt phòng",
     *     description="Cập nhật trạng thái đơn đặt phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật trạng thái thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function updateStatus(UpdateBookingStatusRequest $request, $id): JsonResponse
    {
        try {
            // Authorization: Route middleware checks role, Policy checks permissions
            $bookingOrder = BookingOrder::findOrFail($id);
            $this->authorize('update', $bookingOrder);

            $from = $bookingOrder->status;
            $to = $request->validated()['status'];

            // State machine: chỉ cho phép các bước chuyển hợp lệ
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

            $bookingOrder->update(['status' => $to]);

            Log::info('BookingOrder status updated', [
                'booking_order_id' => $bookingOrder->id,
                'from' => $from,
                'to' => $to,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => new BookingOrderResource($bookingOrder),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@updateStatus failed', [
                'booking_order_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get booking statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'period' => 'sometimes|string|in:day,week,month',
            ], [
                'period.in' => 'Chu kỳ không hợp lệ. Chỉ chấp nhận: day, week, month.',
            ]);

            $query = BookingOrder::query();

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Total statistics
            $total = $query->count();
            $pending = (clone $query)->where('status', 'pending')->count();
            $confirmed = (clone $query)->where('status', 'confirmed')->count();
            $cancelled = (clone $query)->where('status', 'cancelled')->count();
            $completed = (clone $query)->where('status', 'completed')->count();

            // Revenue statistics
            $totalRevenue = (clone $query)->where('status', 'completed')->sum('total_amount');
            $expectedRevenue = (clone $query)->whereIn('status', ['confirmed', 'completed'])->sum('total_amount');
            $cancelledRevenue = (clone $query)->where('status', 'cancelled')->sum('total_amount');

            // Cancellation rate
            $cancellationRate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;

            // Statistics by period
            $period = $request->get('period', 'day');
            $byPeriod = $this->getStatisticsByPeriod($query, $period);

            // Statistics by property
            $byProperty = $this->getStatisticsByProperty($query);

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_status' => [
                        'pending' => $pending,
                        'confirmed' => $confirmed,
                        'cancelled' => $cancelled,
                        'completed' => $completed,
                    ],
                    'revenue' => [
                        'total' => (float) $totalRevenue,
                        'expected' => (float) $expectedRevenue,
                        'cancelled' => (float) $cancelledRevenue,
                    ],
                    'cancellation_rate' => $cancellationRate,
                    'by_period' => $byPeriod,
                    'by_property' => $byProperty,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@statistics failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thống kê đặt phòng.',
            ], 500);
        }
    }

    /**
     * Get statistics by period
     */
    private function getStatisticsByPeriod($baseQuery, string $period): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $query = clone $baseQuery;
        return $query->selectRaw("DATE_FORMAT(booking_orders.created_at, '{$format}') as period, COUNT(*) as count, SUM(booking_orders.total_amount) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'count' => (int) $item->count,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    /**
     * Get statistics by property
     */
    /**
     * Tạo mã đơn đặt phòng tự động
     * Format: BK-YYYYMMDD-XXXX (XXXX là số thứ tự trong ngày)
     */
    private function generateOrderCode(): string
    {
        $prefix = 'BK-' . now()->format('Ymd');
        
        // Đếm số đơn đã tạo trong ngày hôm nay
        $countToday = BookingOrder::whereDate('created_at', now()->toDateString())
            ->where('order_code', 'like', $prefix . '-%')
            ->count();
        
        // Tạo số thứ tự (4 chữ số, bắt đầu từ 0001)
        $sequence = str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);
        
        $orderCode = $prefix . '-' . $sequence;
        
        // Đảm bảo unique (nếu trùng thì tăng sequence)
        while (BookingOrder::where('order_code', $orderCode)->exists()) {
            $countToday++;
            $sequence = str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);
            $orderCode = $prefix . '-' . $sequence;
        }
        
        return $orderCode;
    }

    private function getStatisticsByProperty($baseQuery): array
    {
        $query = clone $baseQuery;
        return $query->join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->selectRaw('properties.id, properties.name, COUNT(DISTINCT booking_orders.id) as booking_count, SUM(booking_orders.total_amount) as revenue, SUM(CASE WHEN booking_orders.status = "cancelled" THEN 1 ELSE 0 END) as cancelled_count')
            ->groupBy('properties.id', 'properties.name')
            ->get()
            ->map(function ($item) {
                return [
                    'property_id' => $item->id,
                    'property_name' => $item->name,
                    'booking_count' => (int) $item->booking_count,
                    'revenue' => (float) $item->revenue,
                    'cancelled_count' => (int) $item->cancelled_count,
                    'cancellation_rate' => $item->booking_count > 0 ? round(($item->cancelled_count / $item->booking_count) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Export booking orders to CSV (for Excel)
     *
     * @OA\Get(
     *     path="/api/admin/booking-orders/export",
     *     operationId="exportBookingOrders",
     *     tags={"Booking Orders"},
     *     summary="Xuất danh sách đơn đặt phòng ra CSV",
     *     description="Xuất danh sách đơn đặt phòng (sử dụng cùng filter như API index) ra file CSV để mở bằng Excel.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="File CSV",
     *         @OA\Schema(type="string", format="binary")
     *     )
     * )
     */
    public function export(Request $request): StreamedResponse
    {
        // Tái sử dụng logic filter từ index, nhưng không phân trang
        $query = BookingOrder::with([
            'guest:id,full_name,email',
            'staff:id,full_name,email',
            'details.room:id,name,property_id',
            'details.room.property:id,name',
        ]);

        // Áp dụng các filter giống index (giữ code ngắn gọn bằng cách gọi lại index-like logic)
        // Để tránh lặp lại quá nhiều, chỉ lấy subset filter quan trọng cho export
        if ($request->filled('order_code')) {
            $query->where('order_code', 'like', '%' . $request->order_code . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_name')) {
            $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $fileName = 'booking_orders_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'ID',
                'Mã đơn',
                'Khách hàng',
                'Email',
                'Số điện thoại',
                'Homestay',
                'Tổng tiền',
                'Trạng thái',
                'Ngày tạo',
                'Nhân viên xử lý',
            ]);

            $query->orderBy('created_at', 'desc')->chunk(500, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    $propertyName = optional($order->details->first()->room->property ?? null)->name ?? '';
                    fputcsv($handle, [
                        $order->id,
                        $order->order_code,
                        $order->customer_name ?? optional($order->guest)->full_name,
                        $order->customer_email ?? optional($order->guest)->email,
                        $order->customer_phone,
                        $propertyName,
                        (float) $order->total_amount,
                        $order->status,
                        optional($order->created_at)->format('Y-m-d H:i'),
                        optional($order->staff)->full_name,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Lấy danh sách bookings của user hiện tại
     * Tự động filter theo guest_id của user đang đăng nhập
     */
    public function indexUser(Request $request, QueryService $service): JsonResponse
    {
        try {
            // Log để debug
            Log::info('BookingOrderController@indexUser called', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role,
                'headers' => $request->headers->all(),
                'query_params' => $request->query(),
            ]);

            $user = $request->user();
            
            if (!$user) {
                Log::warning('BookingOrderController@indexUser: User not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Tự động thêm guest_id vào query params
            $queryParams = $request->query();
            $queryParams['guest_id'] = $user->id;

            Log::info('BookingOrderController@indexUser: Query params', $queryParams);

            // Use QueryService để xử lý logic query
            $result = $service->index($queryParams);

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@indexUser failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đơn đặt phòng.',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Lấy số lượng bookings theo status cho user hiện tại
     */
    public function getBookingCounts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Đếm số lượng bookings theo từng status
            $counts = [
                'all' => BookingOrder::where('guest_id', $user->id)->count(),
                'pending' => BookingOrder::where('guest_id', $user->id)->where('status', 'pending')->count(),
                'confirmed' => BookingOrder::where('guest_id', $user->id)->where('status', 'confirmed')->count(),
                'checked_in' => BookingOrder::where('guest_id', $user->id)->where('status', 'checked_in')->count(),
                'partially_checked_in' => BookingOrder::where('guest_id', $user->id)->where('status', 'partially_checked_in')->count(),
                'checked_out' => BookingOrder::where('guest_id', $user->id)->where('status', 'checked_out')->count(),
                'partially_checked_out' => BookingOrder::where('guest_id', $user->id)->where('status', 'partially_checked_out')->count(),
                'completed' => BookingOrder::where('guest_id', $user->id)->where('status', 'completed')->count(),
                'cancelled' => BookingOrder::where('guest_id', $user->id)->where('status', 'cancelled')->count(),
            ];

            // Active bookings = confirmed + checked_in + partially_checked_in
            $counts['active'] = $counts['confirmed'] + $counts['checked_in'] + $counts['partially_checked_in'];

            return response()->json([
                'success' => true,
                'data' => $counts,
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getBookingCounts failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy số lượng đơn đặt phòng.',
            ], 500);
        }
    }

    /**
     * Tạo booking mới cho user hiện tại
     * Tự động set guest_id = user hiện tại
     */
    public function storeUser(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Log request data for debugging
            Log::info('BookingOrderController@storeUser request', [
                'user_id' => $user->id,
                'request_data' => $request->all(),
            ]);

            // Validate request
            $validated = $request->validate([
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:20',
                'customer_email' => 'nullable|email|max:255',
                'total_amount' => 'required|numeric|min:0',
                'payment_method' => 'nullable|string|max:50|in:cash,bank,momo,card',
                'notes' => 'nullable|string',
                'voucher_id' => 'nullable|integer|exists:vouchers,id',
                'discount_amount' => 'nullable|numeric|min:0',
                'original_total_amount' => 'nullable|numeric|min:0',
                'details' => 'required|array|min:1',
                'details.*.room_id' => 'nullable|exists:rooms,id',
                'details.*.room_type_id' => 'nullable|exists:room_types,id',
                'details.*.check_in_date' => 'required|date',
                'details.*.check_out_date' => 'required|date|after:details.*.check_in_date',
                'details.*.num_adults' => 'required|integer|min:1',
                'details.*.num_children' => 'required|integer|min:0',
                'details.*.sub_total' => 'required|numeric|min:0',
            ]);

            // Validate: Mỗi detail phải có room_id hoặc room_type_id
            foreach ($validated['details'] as $index => $detail) {
                if (empty($detail['room_id']) && empty($detail['room_type_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dữ liệu không hợp lệ',
                        'errors' => [
                            "details.{$index}" => ['Mỗi detail phải có room_id hoặc room_type_id.']
                        ],
                    ], 422);
                }
            }

            // Tách details ra khỏi validated data
            $details = $validated['details'] ?? [];
            unset($validated['details']);

            // Tự động set guest_id = user hiện tại
            $validated['guest_id'] = $user->id;

            // Tự động tạo order_code nếu chưa có
            if (empty($validated['order_code'])) {
                $validated['order_code'] = $this->generateOrderCode();
            }

            // Đảm bảo status có giá trị mặc định
            // Tạo booking với status 'pending' khi chưa thanh toán
            // Chỉ chuyển sang 'confirmed' khi thanh toán cọc thành công
            if (empty($validated['status'])) {
                $validated['status'] = 'pending';
            }

            DB::beginTransaction();

            try {
            // Tạo BookingOrder
            $order = BookingOrder::create($validated);

            // Tạo các BookingDetail
            if (!empty($details)) {
                foreach ($details as $detail) {
                        $detailData = [
                            'booking_order_id' => $order->id,
                            'check_in_date' => $detail['check_in_date'],
                            'check_out_date' => $detail['check_out_date'],
                            'num_adults' => $detail['num_adults'],
                            'num_children' => $detail['num_children'],
                            'sub_total' => $detail['sub_total'],
                        ];

                        // Nếu có room_id, dùng trực tiếp
                        if (isset($detail['room_id'])) {
                            $detailData['room_id'] = $detail['room_id'];
                        } 
                        // Nếu có room_type_id, tự động chọn room available ngẫu nhiên từ room type
                        elseif (isset($detail['room_type_id'])) {
                            $roomTypeId = $detail['room_type_id'];

                            // Tạm thời: Chọn một phòng ngẫu nhiên đang trống từ RoomType
                            // Chỉ check status = 'available', không check availability theo ngày (sẽ xử lý sau)
                            $availableRoom = \App\Models\Room::where('room_type_id', $roomTypeId)
                                ->where('status', 'available')
                                ->when(\Illuminate\Support\Facades\Schema::hasColumn('rooms', 'verification_status'), function($q) {
                                    $q->where('verification_status', 'verified');
                                })
                                ->inRandomOrder() // Chọn ngẫu nhiên
                                ->first();

                            if (!$availableRoom) {
                                DB::rollBack();
                                $roomType = \App\Models\RoomType::find($roomTypeId);
                                $roomTypeName = $roomType ? $roomType->name : 'loại phòng này';
                                return response()->json([
                                    'success' => false,
                                    'message' => "Không tìm thấy phòng trống cho {$roomTypeName}. Vui lòng thử lại sau.",
                                ], 400);
                            }

                            $detailData['room_id'] = $availableRoom->id;
                        } else {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Mỗi detail phải có room_id hoặc room_type_id.',
                            ], 422);
                        }

                        BookingDetail::create($detailData);
                }
                }

                // Tạo Invoice ngay khi tạo booking (không chờ đến khi tạo payment link)
                $invoice = \App\Models\Invoice::create([
                    'booking_order_id' => $order->id,
                    'issue_date' => now(),
                    'due_date' => now()->addDays(7),
                    'total_amount' => 0, // Sẽ tính lại sau khi tạo items
                    'status' => 'pending',
                ]);

                // Tạo invoice items từ booking details (tiền phòng)
                $totalAmount = 0;
                $order->load('details.room'); // Eager load để tránh N+1 query
                
                foreach ($order->details as $detail) {
                    if ($detail->room) {
                        // Calculate nights
                        $checkIn = \Carbon\Carbon::parse($detail->check_in_date);
                        $checkOut = \Carbon\Carbon::parse($detail->check_out_date);
                        $nights = max(1, $checkOut->diffInDays($checkIn));

                        $roomPrice = ($detail->room->price_per_night ?? 0) * $nights;

                        \App\Models\InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'description' => "Phòng {$detail->room->name} - {$nights} đêm",
                            'quantity' => 1,
                            'unit_price' => $detail->room->price_per_night ?? 0,
                            'total_line' => $roomPrice,
                            'item_type' => 'room_charge',
                        ]);
                        $totalAmount += $roomPrice;
                    }
                }

                // Cập nhật invoice total_amount
                $invoice->update(['total_amount' => $totalAmount]);

                // Xử lý voucher nếu có
                if (isset($validated['voucher_id']) && $validated['voucher_id']) {
                    $voucher = \App\Models\Voucher::find($validated['voucher_id']);
                    if ($voucher) {
                        // Tìm user_voucher chưa sử dụng
                        $userVoucher = \App\Models\UserVoucher::where('voucher_id', $voucher->id)
                            ->where('user_id', $user->id)
                            ->whereNull('used_at')
                            ->first();

                        if ($userVoucher) {
                            $discountAmount = $validated['discount_amount'] ?? 0;
                            
                            // Đánh dấu voucher đã sử dụng
                            $userVoucher->update([
                                'used_at' => now(),
                                'booking_order_id' => $order->id,
                                'applied_discount_amount' => $discountAmount,
                            ]);

                            // Tăng usage_count của voucher
                            $voucher->increment('usage_count');

                            // Thêm invoice item cho voucher discount (số tiền âm)
                            if ($discountAmount > 0) {
                                \App\Models\InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'description' => "Giảm giá voucher ({$voucher->code})",
                                    'quantity' => 1,
                                    'unit_price' => -$discountAmount,
                                    'total_line' => -$discountAmount,
                                    'item_type' => 'voucher_discount',
                                ]);

                                // Cập nhật lại invoice total_amount sau khi giảm giá
                                $newInvoiceTotal = max(0, $totalAmount - $discountAmount);
                                $invoice->update([
                                    'total_amount' => $newInvoiceTotal,
                                    'discount_amount' => $discountAmount,
                                ]);
                            }

                            Log::info('Voucher applied to booking', [
                                'user_id' => $user->id,
                                'voucher_id' => $voucher->id,
                                'voucher_code' => $voucher->code,
                                'booking_order_id' => $order->id,
                                'discount_amount' => $discountAmount,
                                'invoice_id' => $invoice->id,
                            ]);
                        }
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Load lại relationships để trả về đầy đủ
            // Note: Room không có images, images thuộc về RoomType
            $order->load(['guest', 'details', 'details.room', 'details.room.roomType', 'details.room.roomType.images']);

            Log::info('BookingOrder created by user', [
                'booking_order_id' => $order->id,
                'user_id' => $user->id,
                'details_count' => count($details),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đặt phòng thành công!',
                'data' => (new BookingOrderResource($order))->toArray($request),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@storeUser failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đặt phòng.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cập nhật payment_method cho booking của user
     */
    public function updatePayment(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validated = $request->validate([
                'payment_method' => 'required|string|in:cash,bank,momo,card',
            ]);

            $booking = BookingOrder::findOrFail($id);
            
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật đơn đặt phòng này.',
                ], 403);
            }

            $booking->update([
                'payment_method' => $validated['payment_method'],
                'status' => 'confirmed', // Tự động confirm khi đã chọn phương thức thanh toán
            ]);

            // Note: Room không có images, images thuộc về RoomType
            $booking->load(['guest', 'details', 'details.room', 'details.room.roomType', 'details.room.roomType.images']);

            Log::info('BookingOrder payment updated by user', [
                'booking_order_id' => $booking->id,
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phương thức thanh toán thành công!',
                'data' => (new BookingOrderResource($booking))->toArray($request),
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
            Log::error('BookingOrderController@updatePayment failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật phương thức thanh toán.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * User thanh toán tiền cọc cho booking
     * Tính toán deposit_amount dựa trên total_amount (mặc định 30%)
     */
    public function payDeposit(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validated = $request->validate([
                'payment_method' => 'required|string|in:cash,bank,momo,card',
                'deposit_amount' => 'nullable|numeric|min:0',
                'transaction_id' => 'nullable|string|max:255',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::findOrFail($id);
            
            // Kiểm tra quyền
            if ($booking->guest_id !== $user->id) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thanh toán đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra booking đã được thanh toán đầy đủ chưa
            if ($booking->payment_status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này đã được thanh toán đầy đủ.',
                ], 400);
            }

            // Chính sách cọc theo tổng tiền:
            // - Nếu tổng tiền < 1.000.000đ  => cọc 100% (thanh toán full)
            // - Ngược lại                   => cọc 50%
            $totalAmount = (float) ($booking->total_amount ?? 0);
            $isLowValue = $totalAmount > 0 && $totalAmount < 1000000; // < 1 triệu

            if (isset($validated['deposit_amount']) && $validated['deposit_amount'] !== null) {
                $depositAmount = (float) $validated['deposit_amount'];
            } else {
                if ($isLowValue) {
                    $depositAmount = $totalAmount;
                } else {
                    $depositAmount = $totalAmount * 0.5;
                }
            }

            // Với đơn giá trị thấp (<1 triệu), luôn ép về 100% dù client gửi ít hơn
            if ($isLowValue && $depositAmount < $totalAmount) {
                $depositAmount = $totalAmount;
            }
            
            // Đảm bảo deposit_amount không vượt quá total_amount
            if ($depositAmount > $booking->total_amount) {
                $depositAmount = $booking->total_amount;
            }

            // Tính toán paid_amount mới
            $newPaidAmount = ($booking->paid_amount ?? 0) + $depositAmount;
            
            // Xác định payment_status
            $paymentStatus = 'partial';
            if ($newPaidAmount >= $booking->total_amount) {
                $paymentStatus = 'paid';
                $newPaidAmount = $booking->total_amount; // Đảm bảo không vượt quá
            }

            // Cập nhật booking
            $booking->update([
                'deposit_amount' => $depositAmount,
                'paid_amount' => $newPaidAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $validated['payment_method'],
                'status' => 'confirmed', // Tự động confirm khi đã đặt cọc
            ]);

            // Tạo payment record nếu có Payment model
            if (class_exists(\App\Models\Payment::class)) {
                // Tìm hoặc tạo invoice cho booking này
                $invoice = $booking->invoices()->first();
                
                if (!$invoice) {
                    // Tạo invoice nếu chưa có
                    $invoice = \App\Models\Invoice::create([
                        'booking_order_id' => $booking->id,
                        'issue_date' => now(),
                        'due_date' => now()->addDays(7), // Hạn thanh toán 7 ngày
                        'total_amount' => $booking->total_amount,
                        'status' => $paymentStatus === 'paid' ? 'paid' : 'pending',
                    ]);
                }

                // Tạo payment record
                \App\Models\Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $depositAmount,
                    'payment_method' => $validated['payment_method'],
                    'transaction_id' => $validated['transaction_id'] ?? null,
                    'status' => 'success',
                    'paid_at' => now(),
                ]);

                // Cập nhật invoice status nếu đã thanh toán đầy đủ
                if ($paymentStatus === 'paid') {
                    $invoice->update(['status' => 'paid']);
                }
            }

            DB::commit();

            // Note: Room không có images, images thuộc về RoomType
            $booking->load(['guest', 'details', 'details.room', 'details.room.roomType', 'details.room.roomType.images']);

            Log::info('BookingOrder deposit paid by user', [
                'booking_order_id' => $booking->id,
                'user_id' => $user->id,
                'deposit_amount' => $depositAmount,
                'paid_amount' => $newPaidAmount,
                'payment_status' => $paymentStatus,
            ]);

            return response()->json([
                'success' => true,
                'message' => $paymentStatus === 'paid' 
                    ? 'Thanh toán thành công! Đơn đặt phòng đã được thanh toán đầy đủ.' 
                    : 'Đặt cọc thành công! Số tiền còn lại: ' . number_format($booking->total_amount - $newPaidAmount, 0, ',', '.') . ' VNĐ',
                'data' => [
                    'booking' => (new BookingOrderResource($booking))->toArray($request),
                    'deposit_amount' => $depositAmount,
                    'paid_amount' => $newPaidAmount,
                    'remaining_amount' => $booking->total_amount - $newPaidAmount,
                    'payment_status' => $paymentStatus,
                ],
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
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@payDeposit failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thanh toán tiền cọc.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Tính toán chính sách hủy phòng
     * - Hủy trước 7 ngày: hoàn 100% tiền cọc
     * - Hủy trong vòng 3-6 ngày: hoàn 50% tiền cọc
     * - Hủy trong vòng 0-2 ngày hoặc không đến: mất 100% tiền cọc
     */
    private function calculateCancellationPolicy(BookingOrder $booking): array
    {
        // Lấy ngày check-in sớm nhất từ booking details
        $firstCheckInDate = $booking->details()->min('check_in_date');
        
        if (!$firstCheckInDate) {
            return [
                'days_until_checkin' => 0,
                'refund_percentage' => 0,
                'refund_amount' => 0,
                'deposit_amount' => $booking->paid_amount ?? $booking->deposit_amount ?? 0,
                'policy_text' => 'Không tìm thấy thông tin ngày check-in',
            ];
        }

        $checkInDate = \Carbon\Carbon::parse($firstCheckInDate);
        $today = \Carbon\Carbon::now()->startOfDay();
        $daysUntilCheckIn = $today->diffInDays($checkInDate, false); // false để có số âm nếu đã qua

        $depositAmount = $booking->paid_amount ?? $booking->deposit_amount ?? 0;
        $refundPercentage = 0;
        $policyText = '';

        if ($daysUntilCheckIn >= 7) {
            // Hủy trước 7 ngày: hoàn 100%
            $refundPercentage = 100;
            $policyText = 'Hủy trước 7 ngày - Hoàn lại 100% tiền cọc';
        } elseif ($daysUntilCheckIn >= 3) {
            // Hủy trong vòng 3-6 ngày: hoàn 50%
            $refundPercentage = 50;
            $policyText = 'Hủy trong vòng 3-6 ngày - Hoàn lại 50% tiền cọc';
        } else {
            // Hủy trong vòng 0-2 ngày hoặc đã qua: mất 100%
            $refundPercentage = 0;
            $policyText = 'Hủy trong vòng 0-2 ngày - Không hoàn lại tiền cọc';
        }

        $refundAmount = ($depositAmount * $refundPercentage) / 100;

        return [
            'days_until_checkin' => max(0, $daysUntilCheckIn),
            'refund_percentage' => $refundPercentage,
            'refund_amount' => round($refundAmount, 0),
            'deposit_amount' => round($depositAmount, 0),
            'forfeited_amount' => round($depositAmount - $refundAmount, 0),
            'policy_text' => $policyText,
            'check_in_date' => $checkInDate->format('Y-m-d'),
        ];
    }

    /**
     * API để xem trước chính sách hủy trước khi hủy
     */
    public function getCancellationPolicy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $booking = BookingOrder::with('details')->findOrFail($id);

            // Kiểm tra quyền
            if ($booking->guest_id !== $user->id && ($user->role ?? null) !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem thông tin đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra trạng thái có thể hủy
            $canCancel = in_array($booking->status, ['pending', 'confirmed'], true);
            
            $policy = $this->calculateCancellationPolicy($booking);
            $policy['can_cancel'] = $canCancel;
            $policy['booking_status'] = $booking->status;
            
            if (!$canCancel) {
                $policy['cancel_reason'] = 'Không thể hủy đơn đã check-in hoặc đã hoàn tất.';
            }

            return response()->json([
                'success' => true,
                'data' => $policy,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getCancellationPolicy failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra.',
            ], 500);
        }
    }

    /**
     * User hủy đơn đặt phòng của chính mình
     * Áp dụng chính sách hủy:
     * - Hủy trước 7 ngày: hoàn 100% tiền cọc
     * - Hủy trong vòng 3-6 ngày: hoàn 50% tiền cọc  
     * - Hủy trong vòng 0-2 ngày hoặc không đến: mất 100% tiền cọc
     */
    public function cancelUserBooking(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:1000',
            ]);

            $booking = BookingOrder::with('details')->findOrFail($id);

            // Chỉ cho phép hủy đơn của chính mình (hoặc admin)
            if ($booking->guest_id !== $user->id && ($user->role ?? null) !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền hủy đơn đặt phòng này.',
                ], 403);
            }

            // Chỉ chặn hủy nếu đơn đã có check-in / checkout hoàn tất
            if (in_array($booking->status, [
                'checked_in',
                'partially_checked_in',
                'checked_out',
                'partially_checked_out',
                'completed',
            ], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể hủy đơn đã check-in hoặc đã hoàn tất.',
                ], 400);
            }

            // Tính toán chính sách hủy
            $policy = $this->calculateCancellationPolicy($booking);

            $from = $booking->status;
            $booking->update([
                'status' => 'cancelled',
                'refund_amount' => $policy['refund_amount'],
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_at' => now(),
            ]);

            Log::info('BookingOrder cancelled by user', [
                'booking_order_id' => $booking->id,
                'user_id' => $user->id,
                'from' => $from,
                'to' => 'cancelled',
                'refund_policy' => $policy,
            ]);

            $booking->refresh();

            // Tạo message với thông tin hoàn tiền
            $message = 'Hủy đặt phòng thành công.';
            if ($policy['refund_amount'] > 0) {
                $message .= ' Số tiền hoàn lại: ' . number_format($policy['refund_amount'], 0, ',', '.') . ' VNĐ';
                $message .= ' (' . $policy['refund_percentage'] . '% tiền cọc).';
            } else {
                $message .= ' Không có tiền hoàn lại do ' . strtolower($policy['policy_text']) . '.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new BookingOrderResource($booking),
                'refund_info' => $policy,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@cancelUserBooking failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi hủy đơn đặt phòng.',
            ], 500);
        }
    }

    /**
     * User đổi ngày đặt phòng (1 lần miễn phí)
     */
    public function changeDates(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validated = $request->validate([
                'new_check_in_date' => 'required|date|after_or_equal:today',
                'new_check_out_date' => 'required|date|after:new_check_in_date',
            ]);

            $booking = BookingOrder::with('details.room')->findOrFail($id);

            // Kiểm tra quyền
            if ($booking->guest_id !== $user->id && ($user->role ?? null) !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thay đổi đơn đặt phòng này.',
                ], 403);
            }

            // Chỉ cho phép đổi ngày khi chưa check-in
            if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể đổi ngày cho đơn đã check-in hoặc đã hoàn tất.',
                ], 400);
            }

            // Kiểm tra số lần đổi ngày (1 lần miễn phí)
            $dateChangeCount = $booking->date_change_count ?? 0;
            if ($dateChangeCount >= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã sử dụng hết số lần đổi ngày miễn phí (1 lần). Vui lòng hủy và đặt lại nếu cần.',
                ], 400);
            }

            $newCheckIn = \Carbon\Carbon::parse($validated['new_check_in_date']);
            $newCheckOut = \Carbon\Carbon::parse($validated['new_check_out_date']);
            $newNights = $newCheckIn->diffInDays($newCheckOut);

            // Kiểm tra phòng có trống trong khoảng thời gian mới không
            $roomIds = $booking->details->pluck('room_id')->toArray();
            $conflictingBookings = \App\Models\BookingDetail::whereIn('room_id', $roomIds)
                ->where('booking_order_id', '!=', $booking->id)
                ->whereHas('bookingOrder', function($q) {
                    $q->whereNotIn('status', ['cancelled', 'completed', 'checked_out']);
                })
                ->where(function($query) use ($newCheckIn, $newCheckOut) {
                    $query->where(function($q) use ($newCheckIn, $newCheckOut) {
                        $q->where('check_in_date', '<', $newCheckOut)
                          ->where('check_out_date', '>', $newCheckIn);
                    });
                })
                ->exists();

            if ($conflictingBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phòng không còn trống trong khoảng thời gian mới. Vui lòng chọn ngày khác.',
                ], 400);
            }

            // Cập nhật tất cả booking details
            $oldTotalAmount = $booking->total_amount;
            $newTotalAmount = 0;

            foreach ($booking->details as $detail) {
                $pricePerNight = $detail->room->roomType->price_per_night ?? $detail->price_per_night ?? 0;
                $newSubtotal = $pricePerNight * $newNights;
                $newTotalAmount += $newSubtotal;

                $detail->update([
                    'check_in_date' => $newCheckIn->format('Y-m-d'),
                    'check_out_date' => $newCheckOut->format('Y-m-d'),
                    'nights' => $newNights,
                    'subtotal' => $newSubtotal,
                ]);
            }

            // Cập nhật booking order
            $booking->update([
                'total_amount' => $newTotalAmount,
                'date_change_count' => $dateChangeCount + 1,
            ]);

            Log::info('BookingOrder dates changed by user', [
                'booking_order_id' => $booking->id,
                'user_id' => $user->id,
                'old_total_amount' => $oldTotalAmount,
                'new_total_amount' => $newTotalAmount,
                'new_check_in' => $newCheckIn->format('Y-m-d'),
                'new_check_out' => $newCheckOut->format('Y-m-d'),
            ]);

            $booking->refresh();
            $booking->load('details.room.roomType');

            $message = 'Đổi ngày thành công!';
            if ($newTotalAmount != $oldTotalAmount) {
                $diff = $newTotalAmount - $oldTotalAmount;
                if ($diff > 0) {
                    $message .= ' Tổng tiền tăng ' . number_format($diff, 0, ',', '.') . ' VNĐ.';
                } else {
                    $message .= ' Tổng tiền giảm ' . number_format(abs($diff), 0, ',', '.') . ' VNĐ.';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new BookingOrderResource($booking),
                'change_info' => [
                    'old_total_amount' => (int) round($oldTotalAmount),
                    'new_total_amount' => (int) round($newTotalAmount),
                    'difference' => (int) round($newTotalAmount - $oldTotalAmount),
                    'new_check_in_date' => $newCheckIn->format('Y-m-d'),
                    'new_check_out_date' => $newCheckOut->format('Y-m-d'),
                    'nights' => $newNights,
                    'date_changes_remaining' => 0,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@changeDates failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đổi ngày.',
            ], 500);
        }
    }

    /**
     * Admin xác nhận đã cọc cho booking
     * Chỉ admin mới có quyền xác nhận
     */
    public function confirmDeposit(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ admin mới có quyền xác nhận đã cọc.',
                ], 403);
            }

            $validated = $request->validate([
                'deposit_amount' => 'nullable|numeric|min:0',
                'transaction_id' => 'nullable|string|max:255',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::findOrFail($id);

            // Kiểm tra booking đã được thanh toán đầy đủ chưa
            if ($booking->payment_status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này đã được thanh toán đầy đủ.',
                ], 400);
            }

            // Tính toán deposit_amount (mặc định 30% của total_amount)
            $depositAmount = $validated['deposit_amount'] ?? ($booking->total_amount * 0.3);
            
            // Đảm bảo deposit_amount không vượt quá total_amount
            if ($depositAmount > $booking->total_amount) {
                $depositAmount = $booking->total_amount;
            }

            // Tính toán paid_amount mới
            $newPaidAmount = ($booking->paid_amount ?? 0) + $depositAmount;
            
            // Xác định payment_status
            $paymentStatus = 'partial';
            if ($newPaidAmount >= $booking->total_amount) {
                $paymentStatus = 'paid';
                $newPaidAmount = $booking->total_amount; // Đảm bảo không vượt quá
            }

            // Cập nhật notes để ghi nhận admin đã xác nhận
            $notes = $booking->notes ? ($booking->notes . PHP_EOL) : '';
            $notes .= sprintf(
                '[Admin] %s đã xác nhận đã cọc %s VNĐ lúc %s',
                $user->full_name ?? $user->email ?? ('Admin#' . $user->id),
                number_format($depositAmount, 0, ',', '.'),
                now()->format('d/m/Y H:i')
            );

            // Cập nhật booking
            $booking->update([
                'deposit_amount' => $depositAmount,
                'paid_amount' => $newPaidAmount,
                'payment_status' => $paymentStatus,
                'status' => 'confirmed', // Tự động confirm khi admin xác nhận đã cọc
                'notes' => $notes,
            ]);

            // Tạo payment record nếu có Payment model
            if (class_exists(\App\Models\Payment::class)) {
                // Tìm hoặc tạo invoice cho booking này
                $invoice = $booking->invoices()->first();
                
                if (!$invoice) {
                    // Tạo invoice nếu chưa có
                    $invoice = \App\Models\Invoice::create([
                        'booking_order_id' => $booking->id,
                        'issue_date' => now(),
                        'due_date' => now()->addDays(7), // Hạn thanh toán 7 ngày
                        'total_amount' => $booking->total_amount,
                        'status' => $paymentStatus === 'paid' ? 'paid' : 'pending',
                    ]);
                }

                // Tạo payment record
                \App\Models\Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $depositAmount,
                    'payment_method' => $booking->payment_method ?? 'payos',
                    'transaction_id' => $validated['transaction_id'] ?? null,
                    'status' => 'success',
                    'paid_at' => now(),
                ]);

                // Cập nhật invoice status nếu đã thanh toán đầy đủ
                if ($paymentStatus === 'paid') {
                    $invoice->update(['status' => 'paid']);
                }
            }

            DB::commit();

            // Note: Room không có images, images thuộc về RoomType
            $booking->load(['guest', 'details', 'details.room', 'details.room.roomType', 'details.room.roomType.images']);

            Log::info('BookingOrder deposit confirmed by admin', [
                'booking_order_id' => $booking->id,
                'admin_id' => $user->id,
                'deposit_amount' => $depositAmount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xác nhận đã cọc thành công!',
                'data' => (new BookingOrderResource($booking))->toArray($request),
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
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@confirmDeposit failed', [
                'booking_order_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác nhận đã cọc.',
            ], 500);
        }
    }

    /**
     * Lấy chi tiết booking của user hiện tại
     * Chỉ cho phép xem booking của chính user đó
     */
    public function showUser(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $include = array_filter(explode(',', (string)($request->get('include', ''))));
            $relations = ['guest'];
            
            if (in_array('details', $include, true)) {
                $relations[] = 'details';
                $relations[] = 'details.room';
                $relations[] = 'details.room.property'; // Load property để có thể access property_id
                $relations[] = 'details.room.roomType'; // Load roomType để có thể access images
                $relations[] = 'details.room.roomType.property'; // Load property từ roomType nếu cần
                // Load booking services để hiển thị trong checkout
                $relations[] = 'details.bookingServices.service';
                // Nếu có details.room.images hoặc details.room.roomType.images, load thêm images từ roomType
                if (in_array('details.room.images', $include, true) || in_array('details.room.roomType.images', $include, true)) {
                    $relations[] = 'details.room.roomType.images';
                }
                if (in_array('details.guests', $include, true)) {
                    $relations[] = 'details.guests';
                }
                // Alias cho checkedInGuests
                if (in_array('details.checkedInGuests', $include, true)) {
                    $relations[] = 'details.checkedInGuests';
                }
                // Load reviews để kiểm tra xem đã review chưa
                if (in_array('details.review', $include, true)) {
                    $relations[] = 'details.review';
                }
            }
            
            if (in_array('checkInRequests', $include, true)) {
                $relations[] = 'checkInRequests';
            }
            
            if (in_array('checkoutRequests', $include, true)) {
                $relations[] = 'checkoutRequests';
            }
            
            $booking = BookingOrder::with($relations)->findOrFail($id);

            // Kiểm tra booking có thuộc về user hiện tại không
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem đơn đặt phòng này.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => (new BookingOrderResource($booking))->toArray($request),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@showUser failed', [
                'booking_id' => $id,
                'user_id' => $user->id ?? null,
                'include' => $request->get('include'),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy chi tiết đơn đặt phòng.',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * User tạo check-in request
     * User gửi yêu cầu check-in, admin/staff sẽ xác minh và approve
     */
    public function checkInUser(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Validate request
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

            // Kiểm tra booking có thuộc về user hiện tại không
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền tạo yêu cầu check-in cho đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra booking có thể check-in không
            if (!in_array($booking->status, ['confirmed', 'pending', 'partially_checked_in'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-in. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            $checkInRequestModel = \App\Models\CheckInRequest::class;
            $createdRequests = [];

            // Tạo check-in request cho từng guest
            foreach ($request->guests as $index => $guestData) {
                $bookingDetail = \App\Models\BookingDetail::findOrFail($guestData['booking_detail_id']);

                // Kiểm tra booking detail thuộc về booking order này
                if ($bookingDetail->booking_order_id != $booking->id) {
                    throw new \Exception('Booking detail không thuộc về booking order này.');
                }

                // Upload identity image nếu có
                $identityImageUrl = null;
                $fileKey = "guests.{$index}.identity_image";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $identityImageUrl = $this->storeIdentityImage($file);
                }

                // Tạo CheckInRequest với status = pending
                $requestRecord = $checkInRequestModel::create([
                    'booking_order_id' => $booking->id,
                    'booking_detail_id' => $bookingDetail->id,
                    'full_name' => $guestData['full_name'],
                    'date_of_birth' => $guestData['date_of_birth'] ?? null,
                    'identity_type' => $guestData['identity_type'],
                    'identity_number' => $guestData['identity_number'],
                    'identity_image_url' => $identityImageUrl,
                    'status' => 'pending',
                    'notes' => $request->notes,
                ]);

                $createdRequests[] = $requestRecord;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu check-in đã được gửi. Vui lòng chờ admin/staff xác minh.',
                'data' => [
                    'booking_id' => $booking->id,
                    'requests' => $createdRequests,
                ],
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
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@checkInUser failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo yêu cầu check-in.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin/Staff: Xem danh sách check-in requests
     */
    public function getCheckInRequests(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'sometimes|in:pending,approved,rejected',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'booking_id' => 'sometimes|exists:booking_orders,id',
                'include_ready_bookings' => 'sometimes|string|in:true,false,1,0', // Chấp nhận string từ query
            ]);

            $perPage = (int) ($request->get('per_page', 15));
            // Sử dụng boolean() để tự động convert string "true"/"false" thành boolean
            $includeReadyBookings = $request->boolean('include_ready_bookings', false);

            // Lấy CheckInRequests
            $query = \App\Models\CheckInRequest::with([
                'bookingOrder.guest',
                'bookingDetail.room',
                'reviewer',
            ])
            ->orderBy('created_at', 'desc');

            // Filter by status - chỉ filter CheckInRequests, không filter ready bookings
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by booking_id
            if ($request->has('booking_id')) {
                $query->where('booking_order_id', $request->booking_id);
            }

            $requests = $query->paginate($perPage);

            $result = $requests->items();

            // Nếu include_ready_bookings = true, thêm các booking đã confirmed và chưa check-in
            // Nếu filter status = 'pending' hoặc 'all', cũng bao gồm ready bookings
            if ($includeReadyBookings && (!$request->has('status') || $request->status === 'pending' || $request->status === 'all')) {
                $readyBookings = BookingOrder::with([
                    'guest:id,full_name,email,phone_number',
                    'details.room:id,name',
                    'details.checkedInGuests', // Eager load để kiểm tra
                ])
                // Bao gồm cả đơn đã check-in một phần, miễn là vẫn còn phòng chưa check-in
                ->whereIn('status', ['confirmed', 'partially_checked_in'])
                ->whereIn('payment_status', ['partial', 'paid'])
                // Bỏ filter ngày check-in để hiển thị tất cả booking đã confirmed và sẵn sàng check-in
                // Admin có thể xử lý check-in cho bất kỳ booking nào đã được xác nhận cọc
                ->get()
                ->sortBy(function ($booking) {
                    // Sort by earliest check_in_date trong details
                    $earliestDate = $booking->details->min('check_in_date');
                    return $earliestDate ? $earliestDate->timestamp : 0;
                })
                ->values(); // Reset keys sau khi sort

                // Chuyển đổi booking thành format tương tự CheckInRequest để hiển thị
                // Chỉ thêm booking nếu có ít nhất 1 detail chưa check-in
                foreach ($readyBookings as $booking) {
                    $hasUncheckedInDetail = false;
                    foreach ($booking->details as $detail) {
                        if ($detail->checkedInGuests->isEmpty()) {
                            $hasUncheckedInDetail = true;
                            break;
                        }
                    }
                    
                    // Chỉ thêm booking vào result nếu còn phòng chưa check-in
                    // Nhưng booking_order sẽ chứa TẤT CẢ details (kể cả đã check-in) để frontend hiển thị đúng
                    if ($hasUncheckedInDetail) {
                        // Serialize booking qua BookingOrderResource để đảm bảo checkedInGuests được map thành guests
                        $bookingArray = (new BookingOrderResource($booking))->toArray($request);
                        
                        $result[] = (object) [
                            'id' => 'ready_booking_' . $booking->id,
                            'type' => 'ready_booking', // Đánh dấu đây là booking sẵn sàng check-in
                            'booking_order_id' => $booking->id,
                            'booking_detail_id' => null, // Không có detail cụ thể vì đây là booking-level
                            'booking_order' => $bookingArray, // Sử dụng serialized data để đảm bảo guests được map đúng
                            'booking_detail' => null,
                            'full_name' => $booking->customer_name,
                            'status' => 'ready_for_checkin',
                            'created_at' => $booking->created_at,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'pagination' => [
                        'page' => $requests->currentPage(),
                        'per_page' => $requests->perPage(),
                        'total' => count($result), // Tổng số bao gồm cả ready bookings
                        'last_page' => $requests->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getCheckInRequests failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách yêu cầu check-in.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin/Staff: Xem chi tiết check-in request
     */
    public function getCheckInRequest(string $id): JsonResponse
    {
        try {
            $checkInRequest = \App\Models\CheckInRequest::with([
                'bookingOrder.guest',
                'bookingDetail.room',
                'reviewer',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $checkInRequest,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu check-in.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getCheckInRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy chi tiết yêu cầu check-in.',
            ], 500);
        }
    }

    /**
     * Admin/Staff: Approve check-in request
     * Khi approve, sẽ tạo CheckedInGuest và cập nhật trạng thái booking
     */
    public function approveCheckInRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            DB::beginTransaction();

            $checkInRequest = \App\Models\CheckInRequest::with(['bookingOrder.details.room', 'bookingDetail'])->findOrFail($id);

            // Kiểm tra request đã được xử lý chưa
            if ($checkInRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu check-in này đã được xử lý.',
                ], 400);
            }

            // Tạo CheckedInGuest từ request
            \App\Models\CheckedInGuest::create([
                'booking_details_id' => $checkInRequest->booking_detail_id,
                'full_name' => $checkInRequest->full_name,
                'date_of_birth' => $checkInRequest->date_of_birth,
                'identity_type' => $checkInRequest->identity_type,
                'identity_number' => $checkInRequest->identity_number,
                'identity_image_url' => $checkInRequest->identity_image_url,
                'check_in_time' => now(),
            ]);

            // Cập nhật trạng thái booking detail
            $bookingDetail = $checkInRequest->bookingDetail;
            $bookingDetail->update(['status' => 'checked_in']);

            // Cập nhật trạng thái phòng thành "occupied"
            if ($bookingDetail->room) {
                $bookingDetail->room->update(['status' => 'occupied']);
            }

            // Cập nhật trạng thái check-in request
            $checkInRequest->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Kiểm tra xem tất cả phòng đã check-in chưa
            $booking = $checkInRequest->bookingOrder;
            // Refresh booking model để có dữ liệu mới nhất
            $booking->refresh();
            $booking->load('details');
            
            $totalDetails = $booking->details()->count();
            $checkedInCount = $booking->details()->where('status', 'checked_in')->count();
            
            // Cập nhật trạng thái booking order
            // CHỈ set thành checked_in hoặc partially_checked_in, KHÔNG BAO GIỜ set thành checked_out
            $newStatus = ($checkedInCount >= $totalDetails) ? 'checked_in' : 'partially_checked_in';
            
            // Log để debug
            Log::info('BookingOrderController@approveCheckInRequest - Updating booking status', [
                'booking_id' => $booking->id,
                'old_status' => $booking->status,
                'new_status' => $newStatus,
                'total_details' => $totalDetails,
                'checked_in_count' => $checkedInCount,
            ]);
            
            $booking->update(['status' => $newStatus]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu check-in đã được duyệt thành công.',
                'data' => $checkInRequest->fresh(['bookingOrder', 'bookingDetail', 'reviewer']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu check-in.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@approveCheckInRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi duyệt yêu cầu check-in.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin/Staff: Reject check-in request
     */
    public function rejectCheckInRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            DB::beginTransaction();

            $checkInRequest = \App\Models\CheckInRequest::findOrFail($id);

            // Kiểm tra request đã được xử lý chưa
            if ($checkInRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu check-in này đã được xử lý.',
                ], 400);
            }

            // Cập nhật trạng thái check-in request
            $checkInRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu check-in đã bị từ chối.',
                'data' => $checkInRequest->fresh(['reviewer']),
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
                'message' => 'Không tìm thấy yêu cầu check-in.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@rejectCheckInRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối yêu cầu check-in.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Check-in trực tiếp (giống staff)
     * Cho phép admin check-in trực tiếp mà không cần qua request
     */
    public function checkInDirect(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || $admin->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ admin mới có quyền check-in trực tiếp.',
                ], 403);
            }

            // Validate request (giống như staff check-in)
            $request->validate([
                'guests' => 'required|array|min:1',
                'guests.*.full_name' => 'required|string|max:255',
                'guests.*.date_of_birth' => 'nullable|date',
                'guests.*.identity_type' => 'required|in:cccd,passport',
                'guests.*.identity_number' => 'required|string|max:50',
                'guests.*.identity_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
                'guests.*.booking_detail_id' => 'required|exists:booking_details,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details.room'])->findOrFail($id);

            // Kiểm tra booking có thể check-in không
            if (!in_array($booking->status, ['confirmed', 'pending', 'partially_checked_in'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-in. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            $checkedInDetailIds = [];

            // Xử lý từng guest (giống như staff check-in)
            foreach ($request->guests as $index => $guestData) {
                $bookingDetail = BookingDetail::findOrFail($guestData['booking_detail_id']);

                if ($bookingDetail->booking_order_id != $booking->id) {
                    throw new \Exception('Booking detail không thuộc về booking order này.');
                }

                // Upload identity image nếu có
                $identityImageUrl = null;
                $fileKey = "guests.{$index}.identity_image";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $identityImageUrl = $this->storeIdentityImage($file);
                }

                // Tạo CheckedInGuest record
                \App\Models\CheckedInGuest::create([
                    'booking_details_id' => $bookingDetail->id,
                    'full_name' => $guestData['full_name'],
                    'date_of_birth' => $guestData['date_of_birth'] ?? null,
                    'identity_type' => $guestData['identity_type'],
                    'identity_number' => $guestData['identity_number'],
                    'identity_image_url' => $identityImageUrl,
                    'check_in_time' => now(),
                ]);

                if (!in_array($bookingDetail->id, $checkedInDetailIds)) {
                    $checkedInDetailIds[] = $bookingDetail->id;
                }
            }

            // Cập nhật trạng thái booking details
            BookingDetail::whereIn('id', $checkedInDetailIds)->update(['status' => 'checked_in']);

            // Cập nhật trạng thái phòng thành "occupied"
            $checkedInDetails = BookingDetail::whereIn('id', $checkedInDetailIds)->with('room')->get();
            foreach ($checkedInDetails as $detail) {
                if ($detail->room) {
                    $detail->room->update(['status' => 'occupied']);
                }
            }

            // Refresh booking model để có dữ liệu mới nhất
            $booking->refresh();
            $booking->load('details');

            // Kiểm tra xem tất cả phòng đã check-in chưa
            $totalDetails = $booking->details()->count();
            $checkedInCount = $booking->details()->where('status', 'checked_in')->count();
            
            // Cập nhật trạng thái booking order
            // CHỈ set thành checked_in hoặc partially_checked_in, KHÔNG BAO GIỜ set thành checked_out
            $newStatus = ($checkedInCount >= $totalDetails) ? 'checked_in' : 'partially_checked_in';
            
            // Log để debug
            Log::info('BookingOrderController@checkInDirect - Updating booking status', [
                'booking_id' => $booking->id,
                'old_status' => $booking->status,
                'new_status' => $newStatus,
                'total_details' => $totalDetails,
                'checked_in_count' => $checkedInCount,
            ]);
            
            $booking->update([
                'status' => $newStatus,
                'staff_id' => $admin->id,
                'notes' => $request->notes ?? $booking->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-in thành công.',
                'data' => (new BookingOrderResource($booking->fresh(['guest', 'details.room', 'details.checkedInGuests'])))->toArray($request),
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
            Log::error('BookingOrderController@checkInDirect failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi check-in.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * User tạo checkout request
     * User gửi yêu cầu checkout, admin/staff sẽ xử lý và approve
     */
    public function requestCheckOut(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Validate request
            $request->validate([
                'booking_detail_ids' => 'required|array|min:1',
                'booking_detail_ids.*' => 'required|exists:booking_details,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details.room'])->findOrFail($id);

            // Kiểm tra booking có thuộc về user hiện tại không
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền tạo yêu cầu checkout cho đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra booking có thể checkout không (phải đã check-in)
            if (!in_array($booking->status, ['checked_in', 'partially_checked_in', 'partially_checked_out'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể checkout. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            $checkoutRequestModel = \App\Models\CheckoutRequest::class;
            $createdRequests = [];

            // Tạo checkout request cho từng booking detail
            foreach ($request->booking_detail_ids as $bookingDetailId) {
                $bookingDetail = \App\Models\BookingDetail::findOrFail($bookingDetailId);

                // Kiểm tra booking detail thuộc về booking order này
                if ($bookingDetail->booking_order_id != $booking->id) {
                    throw new \Exception('Booking detail không thuộc về booking order này.');
                }

                // Kiểm tra booking detail đã check-in chưa
                if ($bookingDetail->status !== 'checked_in') {
                    continue; // Bỏ qua nếu chưa check-in
                }

                // Kiểm tra xem đã có checkout request pending cho booking detail này chưa
                $existingRequest = $checkoutRequestModel::where('booking_detail_id', $bookingDetailId)
                    ->where('status', 'pending')
                    ->first();

                if ($existingRequest) {
                    continue; // Bỏ qua nếu đã có request pending
                }

                // Tạo CheckoutRequest với status = pending
                $requestRecord = $checkoutRequestModel::create([
                    'booking_order_id' => $booking->id,
                    'booking_detail_id' => $bookingDetailId,
                    'status' => 'pending',
                    'notes' => $request->notes,
                ]);

                $createdRequests[] = $requestRecord;
            }

            if (empty($createdRequests)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phòng nào hợp lệ để tạo yêu cầu checkout. Có thể các phòng chưa check-in hoặc đã có yêu cầu checkout đang chờ xử lý.',
                ], 400);
            }

            DB::commit();

            // Gửi thông báo cho admin (log)
            Log::info('New checkout request created', [
                'booking_id' => $booking->id,
                'booking_order_code' => $booking->order_code,
                'request_count' => count($createdRequests),
                'requested_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu checkout đã được gửi. Vui lòng chờ admin/staff xử lý.',
                'data' => [
                    'booking_id' => $booking->id,
                    'requests' => $createdRequests,
                ],
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
            Log::error('BookingOrderController@requestCheckOut failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo yêu cầu checkout.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * User checkout booking
     * User checkout và tự động tạo invoice để thanh toán
     */
    public function checkOutUser(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            DB::beginTransaction();

            $booking = BookingOrder::with([
                'details.room',
                'details.bookingServices.service', // Load approved services
                'invoices.invoiceItems', // Load invoice items
            ])->findOrFail($id);

            // Kiểm tra booking có thuộc về user hiện tại không
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền checkout đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra booking có thể checkout không
            if (!in_array($booking->status, ['checked_in', 'partially_checked_in', 'partially_checked_out'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể checkout. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            // Lấy danh sách booking_detail_ids đã check-in để checkout
            $checkOutDetailIds = $booking->details()->where('status', 'checked_in')->pluck('id')->toArray();
            
            if (empty($checkOutDetailIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phòng nào đã check-in để checkout.',
                ], 400);
            }

            // Cập nhật trạng thái booking details
            BookingDetail::whereIn('id', $checkOutDetailIds)->update(['status' => 'checked_out']);

            // Cập nhật trạng thái phòng thành "available"
            $checkOutDetails = BookingDetail::whereIn('id', $checkOutDetailIds)->with('room')->get();
            foreach ($checkOutDetails as $detail) {
                if ($detail->room) {
                    $detail->room->update(['status' => 'available']);
                }
            }

            // Kiểm tra xem tất cả phòng đã checkout chưa
            $totalDetails = $booking->details()->count();
            $checkedOutCount = $booking->details()->where('status', 'checked_out')->count();
            
            // Cập nhật trạng thái booking order
            $newStatus = ($checkedOutCount >= $totalDetails) ? 'checked_out' : 'partially_checked_out';
            $booking->update([
                'status' => $newStatus,
            ]);

            // Tìm hoặc tạo invoice
            $invoice = $booking->invoices()->first();
            $invoiceModel = \App\Models\Invoice::class;
            $invoiceItemModel = \App\Models\InvoiceItem::class;
            
            if (!$invoice) {
                // Tạo invoice mới
                $invoice = $invoiceModel::create([
                    'booking_order_id' => $booking->id,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'total_amount' => 0, // Will be updated after calculating items
                    'status' => 'pending',
                ]);
            }

            // Kiểm tra xem invoice đã có room charges chưa (để tránh duplicate)
            $hasRoomCharges = $invoice->invoiceItems()->where('item_type', 'room_charge')->exists();
            $totalAmount = $invoice->total_amount ?? 0;

            // Thêm room charges nếu chưa có
            if (!$hasRoomCharges) {
                foreach ($booking->details as $detail) {
                    if ($detail->room) {
                        // Calculate nights
                        $checkIn = \Carbon\Carbon::parse($detail->check_in_date);
                        $checkOut = \Carbon\Carbon::parse($detail->check_out_date);
                        $nights = max(1, $checkOut->diffInDays($checkIn));

                        $roomPrice = ($detail->room->price_per_night ?? 0) * $nights;

                        $invoiceItemModel::create([
                            'invoice_id' => $invoice->id,
                            'description' => "Phòng {$detail->room->name} - {$nights} đêm",
                            'quantity' => 1,
                            'unit_price' => $detail->room->price_per_night ?? 0,
                            'total_line' => $roomPrice,
                            'item_type' => 'room_charge',
                        ]);
                        $totalAmount += $roomPrice;
                    }
                }
            }

            // Thêm các service charges đã được approve (nếu chưa có trong invoice)
            // Lấy tất cả booking detail IDs của booking này (không chỉ những cái checkout)
            $allBookingDetailIds = $booking->details->pluck('id')->toArray();
            $approvedServices = \App\Models\BookingService::whereIn('booking_details_id', $allBookingDetailIds)
                ->where('status', 'approved')
                ->with('service')
                ->get();

            foreach ($approvedServices as $bookingService) {
                // Kiểm tra xem service này đã có trong invoice chưa
                $serviceExists = $invoice->invoiceItems()
                    ->where('item_type', 'service_charge')
                    ->where('description', 'like', '%' . $bookingService->service->name . '%')
                    ->exists();

                if (!$serviceExists) {
                    $serviceTotal = $bookingService->price_at_booking * $bookingService->quantity;
                    $invoiceItemModel::create([
                        'invoice_id' => $invoice->id,
                        'description' => "Dịch vụ: {$bookingService->service->name} (SL: {$bookingService->quantity})",
                        'quantity' => $bookingService->quantity,
                        'unit_price' => $bookingService->price_at_booking,
                        'total_line' => $serviceTotal,
                        'item_type' => 'service_charge',
                    ]);
                    $totalAmount += $serviceTotal;
                }
            }

            // Trừ tiền cọc đã thanh toán (nếu có và chưa được thêm vào invoice)
            $hasDeposit = $invoice->invoiceItems()->where('item_type', 'deposit')->exists();
            $paidAmount = $booking->paid_amount ?? 0;
            if ($paidAmount > 0 && !$hasDeposit) {
                $invoiceItemModel::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Tiền cọc đã thanh toán',
                    'quantity' => 1,
                    'unit_price' => -$paidAmount, // Giá trị âm để trừ
                    'total_line' => -$paidAmount, // Giá trị âm để trừ
                    'item_type' => 'deposit',
                ]);
                $totalAmount -= $paidAmount; // Trừ tiền cọc vào tổng tiền
            }

            // Đảm bảo total_amount không âm
            $finalAmount = max(0, $totalAmount);

            // Update invoice total
            $invoice->update(['total_amount' => $finalAmount]);
            
            // Refresh invoice để có invoiceItems mới nhất
            $invoice->refresh();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Checkout thành công. Vui lòng thanh toán hóa đơn.',
                'data' => [
                    'booking' => (new BookingOrderResource($booking->fresh(['guest', 'details.room'])))->toArray($request),
                    'invoice' => $invoice ? [
                        'id' => $invoice->id,
                        'total_amount' => $invoice->total_amount,
                        'status' => $invoice->status,
                        'issue_date' => $invoice->issue_date,
                        'due_date' => $invoice->due_date,
                    ] : null,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@checkOutUser failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi checkout.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get list of checked-in guests (Quản lý lưu trú)
     */
    public function getCheckedInGuests(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'search' => 'sometimes|string|max:255',
                'booking_id' => 'sometimes|exists:booking_orders,id',
                'room_id' => 'sometimes|exists:rooms,id',
            ]);

            $perPage = (int) ($request->get('per_page', 15));
            
            $query = \App\Models\CheckedInGuest::with([
                'detail.bookingOrder:id,order_code,customer_name,customer_phone',
                'detail.room:id,name,room_type_id',
                'detail.room.roomType:id,name',
            ])
            ->whereHas('detail.bookingOrder', function($q) {
                // Chỉ lấy guests từ booking đã check-in (không bị hủy)
                $q->whereNotIn('status', ['cancelled']);
            })
            ->orderBy('check_in_time', 'desc');

            // Filter by search (tên khách, số CMND/CCCD, mã booking)
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('identity_number', 'like', "%{$search}%")
                      ->orWhereHas('detail.bookingOrder', function($q) use ($search) {
                          $q->where('order_code', 'like', "%{$search}%");
                      });
                });
            }

            // Filter by booking_id
            if ($request->has('booking_id')) {
                $query->whereHas('detail', function($q) use ($request) {
                    $q->where('booking_order_id', $request->booking_id);
                });
            }

            // Filter by room_id
            if ($request->has('room_id')) {
                $query->whereHas('detail', function($q) use ($request) {
                    $q->where('room_id', $request->room_id);
                });
            }

            $guests = $query->paginate($perPage);

            $result = collect($guests->items())->map(function($guest) {
                $detail = $guest->detail;
                $booking = $detail->bookingOrder ?? null;
                $room = $detail->room ?? null;

                return [
                    'id' => $guest->id,
                    'full_name' => $guest->full_name,
                    'date_of_birth' => $guest->date_of_birth?->format('Y-m-d'),
                    'identity_type' => $guest->identity_type,
                    'identity_number' => $guest->identity_number,
                    'identity_image_url' => $guest->identity_image_url,
                    'check_in_time' => $guest->check_in_time?->toISOString(),
                    'booking' => $booking ? [
                        'id' => $booking->id,
                        'order_code' => $booking->order_code,
                        'customer_name' => $booking->customer_name,
                        'customer_phone' => $booking->customer_phone,
                    ] : null,
                    'room' => $room ? [
                        'id' => $room->id,
                        'name' => $room->name,
                        'room_type' => $room->roomType->name ?? null,
                    ] : null,
                    'check_in_date' => $detail->check_in_date?->format('Y-m-d'),
                    'check_out_date' => $detail->check_out_date?->format('Y-m-d'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'pagination' => [
                        'page' => $guests->currentPage(),
                        'per_page' => $guests->perPage(),
                        'total' => $guests->total(),
                        'last_page' => $guests->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getCheckedInGuests failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách khách lưu trú.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store identity image to private bucket
     */
    private function storeIdentityImage($file): string
    {
        $filename = 'identity_' . time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $file->getClientOriginalExtension();
        
        // Upload to private bucket (s3_private disk) if configured
        if (config('filesystems.disks.s3_private.bucket')) {
            $path = $file->storeAs('identity_images', $filename, 's3_private');
            
            // For private bucket, return the path
            // The path can be used later to generate signed URL if needed for viewing
            return $path;
        }
        
        // Fallback to public disk if s3_private is not configured
        $path = $file->storeAs('identity_images', $filename, 'public');
        return Storage::url($path);
    }

    /**
     * User: Yêu cầu dịch vụ cho booking
     * Tạo BookingService với status = 'pending'
     */
    public function requestService(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'booking_detail_id' => 'required|exists:booking_details,id',
                'service_id' => 'required|exists:services,id',
                'quantity' => 'required|integer|min:1',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details'])->findOrFail($id);

            // Kiểm tra booking có thuộc về user hiện tại không
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền yêu cầu dịch vụ cho đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra booking detail có thuộc về booking này không
            $bookingDetail = $booking->details->firstWhere('id', $request->booking_detail_id);
            if (!$bookingDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking detail không thuộc về booking này.',
                ], 400);
            }

            // Kiểm tra booking có thể yêu cầu dịch vụ không (phải đã check-in)
            if (!in_array($booking->status, ['checked_in', 'partially_checked_in'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể yêu cầu dịch vụ khi đã check-in.',
                ], 400);
            }

            // Lấy thông tin service
            $service = \App\Models\Service::findOrFail($request->service_id);

            // Tạo BookingService với status = 'pending'
            $bookingService = \App\Models\BookingService::create([
                'booking_details_id' => $request->booking_detail_id,
                'service_id' => $request->service_id,
                'quantity' => $request->quantity,
                'price_at_booking' => $service->price,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            // Gửi thông báo cho admin (log để admin có thể xem)
            Log::info('New service request created', [
                'booking_service_id' => $bookingService->id,
                'booking_id' => $booking->id,
                'booking_order_code' => $booking->order_code,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'quantity' => $request->quantity,
                'total_amount' => $service->price * $request->quantity,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu dịch vụ đã được gửi. Vui lòng chờ admin xác nhận.',
                'data' => $bookingService->load('service'),
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
            Log::error('BookingOrderController@requestService failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi yêu cầu dịch vụ.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Lấy danh sách service requests (pending)
     */
    public function getServiceRequests(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'sometimes|in:pending,approved,rejected',
                'booking_id' => 'sometimes|exists:booking_orders,id',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $perPage = (int) ($request->get('per_page', 15));
            $query = \App\Models\BookingService::with([
                'service',
                'detail.room',
                'detail.bookingOrder.guest',
            ])
            ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Mặc định chỉ lấy pending
                $query->where('status', 'pending');
            }

            // Filter by booking_id
            if ($request->has('booking_id')) {
                $query->whereHas('detail', function($q) use ($request) {
                    $q->where('booking_order_id', $request->booking_id);
                });
            }

            $serviceRequests = $query->paginate($perPage);

            $result = collect($serviceRequests->items())->map(function($bookingService) {
                $detail = $bookingService->detail;
                $booking = $detail->bookingOrder ?? null;
                $service = $bookingService->service;
                $room = $detail->room ?? null;

                return [
                    'id' => $bookingService->id,
                    'booking_id' => $booking->id ?? null,
                    'booking_order_code' => $booking->order_code ?? null,
                    'room_name' => $room->name ?? null,
                    'service' => $service ? [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => $service->price,
                        'unit' => $service->unit,
                    ] : null,
                    'quantity' => $bookingService->quantity,
                    'price_at_booking' => $bookingService->price_at_booking,
                    'total_amount' => $bookingService->price_at_booking * $bookingService->quantity,
                    'status' => $bookingService->status,
                    'notes' => $bookingService->notes,
                    'customer_name' => $booking->customer_name ?? $booking->guest->full_name ?? null,
                    'created_at' => $bookingService->created_at?->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'pagination' => [
                        'page' => $serviceRequests->currentPage(),
                        'per_page' => $serviceRequests->perPage(),
                        'total' => $serviceRequests->total(),
                        'last_page' => $serviceRequests->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getServiceRequests failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách yêu cầu dịch vụ.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Approve service request và thêm vào invoice
     */
    public function approveServiceRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $bookingService = \App\Models\BookingService::with([
                'service',
                'detail.bookingOrder.invoices', // Eager load invoices
                'detail.room',
            ])->findOrFail($id);

            // Kiểm tra status
            if ($bookingService->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu dịch vụ này đã được xử lý.',
                ], 400);
            }

            $booking = $bookingService->detail->bookingOrder;
            $service = $bookingService->service;

            if (!$service) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy dịch vụ.',
                ], 404);
            }

            // Tìm hoặc tạo invoice cho booking
            $invoice = $booking->invoices()->first();
            if (!$invoice) {
                $invoice = \App\Models\Invoice::create([
                    'booking_order_id' => $booking->id,
                    'issue_date' => now(),
                    'due_date' => now()->addDays(7),
                    'total_amount' => 0,
                    'status' => 'pending',
                ]);
            }

            // Kiểm tra invoice chưa được thanh toán
            if ($invoice->status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này đã được thanh toán. Không thể thêm dịch vụ.',
                ], 400);
            }

            // Thêm dịch vụ vào invoice
            $totalAmount = $bookingService->price_at_booking * $bookingService->quantity;
            \App\Models\InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Dịch vụ: {$service->name} (SL: {$bookingService->quantity})",
                'quantity' => $bookingService->quantity,
                'unit_price' => $bookingService->price_at_booking,
                'total_line' => $totalAmount,
                'item_type' => 'service_charge', // Sử dụng 'service_charge' để nhất quán với code trước
            ]);

            // Cập nhật invoice total_amount
            $invoice->increment('total_amount', $totalAmount);

            // Cập nhật booking total_amount
            $booking->increment('total_amount', $totalAmount);

            // Cập nhật status của BookingService
            $updatedNotes = $bookingService->notes ?? '';
            if ($request->has('admin_notes') && !empty($request->admin_notes)) {
                $updatedNotes .= ($updatedNotes ? PHP_EOL : '') . '[Admin] ' . $request->admin_notes;
            }
            $bookingService->update([
                'status' => 'approved',
                'notes' => $updatedNotes,
            ]);

            Log::info('Service request approved', [
                'booking_service_id' => $bookingService->id,
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
                'admin_id' => $admin->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã duyệt yêu cầu dịch vụ và thêm vào hóa đơn.',
                'data' => $bookingService->fresh(['service', 'detail.bookingOrder', 'detail.room']),
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
            Log::error('BookingOrderController@approveServiceRequest failed', [
                'service_request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi duyệt yêu cầu dịch vụ.',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Admin: Reject service request
     */
    public function rejectServiceRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            DB::beginTransaction();

            $bookingService = \App\Models\BookingService::findOrFail($id);

            // Kiểm tra status
            if ($bookingService->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu dịch vụ này đã được xử lý.',
                ], 400);
            }

            // Cập nhật status
            $bookingService->update([
                'status' => 'rejected',
                'notes' => $bookingService->notes . PHP_EOL . '[Admin] Từ chối: ' . $request->rejection_reason,
            ]);

            Log::info('Service request rejected', [
                'booking_service_id' => $bookingService->id,
                'admin_id' => $admin->id,
                'reason' => $request->rejection_reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã từ chối yêu cầu dịch vụ.',
                'data' => $bookingService->fresh(['service', 'detail.bookingOrder', 'detail.room']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@rejectServiceRequest failed', [
                'service_request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối yêu cầu dịch vụ.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Lấy danh sách checkout requests
     */
    public function getCheckoutRequests(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'sometimes|in:pending,approved,rejected,all',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'booking_id' => 'sometimes|exists:booking_orders,id',
            ]);

            $perPage = (int) ($request->get('per_page', 15));

            $query = \App\Models\CheckoutRequest::with([
                'bookingOrder.guest',
                'bookingDetail.room.roomType',
                'reviewer',
            ])
            ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by booking_id
            if ($request->has('booking_id')) {
                $query->where('booking_order_id', $request->booking_id);
            }

            $checkoutRequests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $checkoutRequests->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $checkoutRequests->currentPage(),
                        'per_page' => $checkoutRequests->perPage(),
                        'total' => $checkoutRequests->total(),
                        'last_page' => $checkoutRequests->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getCheckoutRequests failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách yêu cầu checkout.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Approve checkout request with damage items support
     */
    public function approveCheckoutRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Validate request với damaged_supplies
            $request->validate([
                'room_status' => 'sometimes|in:available,maintenance',
                'notes' => 'nullable|string|max:1000',
                'damaged_supplies' => 'nullable|array',
                'damaged_supplies.*.supply_id' => 'required|exists:supplies,id',
                'damaged_supplies.*.quantity' => 'required|integer|min:1',
                'damaged_supplies.*.unit_price' => 'nullable|numeric|min:0',
                'damaged_supplies.*.notes' => 'nullable|string|max:500',
            ]);

            DB::beginTransaction();

            $checkoutRequest = \App\Models\CheckoutRequest::with([
                'bookingOrder.details.room',
                'bookingDetail',
            ])->findOrFail($id);

            // Kiểm tra request đã được xử lý chưa
            if ($checkoutRequest->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu checkout này đã được xử lý.',
                ], 400);
            }

            $booking = $checkoutRequest->bookingOrder;
            $bookingDetail = $checkoutRequest->bookingDetail;

            // Kiểm tra booking detail đã check-in chưa
            if ($bookingDetail->status !== 'checked_in') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Phòng này chưa check-in, không thể checkout.',
                ], 400);
            }

            // Cập nhật trạng thái booking detail
            $bookingDetail->update(['status' => 'checked_out']);

            // Cập nhật trạng thái phòng (mặc định "available", có thể là "maintenance")
            $roomStatus = $request->get('room_status', 'available');
            if ($bookingDetail->room) {
                $bookingDetail->room->update(['status' => $roomStatus]);
            }

            // Kiểm tra xem tất cả phòng đã checkout chưa
            $booking->refresh();
            $booking->load('details');
            $totalDetails = $booking->details->count();
            $checkedOutCount = $booking->details->where('status', 'checked_out')->count();
            
            // Cập nhật trạng thái booking order
            $newStatus = ($checkedOutCount >= $totalDetails) ? 'checked_out' : 'partially_checked_out';
            $booking->update([
                'status' => $newStatus,
                'staff_id' => $admin->id,
                'notes' => $request->notes ?? $booking->notes,
            ]);

            // Tạo hoặc cập nhật invoice
            $invoice = $booking->invoices()->first();
            $invoiceModel = \App\Models\Invoice::class;
            $invoiceItemModel = \App\Models\InvoiceItem::class;
            
            if (!$invoice) {
                $invoice = $invoiceModel::create([
                    'booking_order_id' => $booking->id,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'total_amount' => 0,
                    'status' => 'pending',
                ]);
            }

            // Kiểm tra xem invoice đã có room charges cho booking detail này chưa
            $hasRoomCharge = $invoice->invoiceItems()
                ->where('item_type', 'room_charge')
                ->where('description', 'like', '%' . $bookingDetail->room->name . '%')
                ->exists();

            $totalAmount = $invoice->total_amount ?? 0;

            // Thêm room charge nếu chưa có
            if (!$hasRoomCharge && $bookingDetail->room) {
                $checkIn = \Carbon\Carbon::parse($bookingDetail->check_in_date);
                $checkOut = \Carbon\Carbon::parse($bookingDetail->check_out_date);
                $nights = max(1, $checkOut->diffInDays($checkIn));
                $roomPrice = ($bookingDetail->room->price_per_night ?? 0) * $nights;

                $invoiceItemModel::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Phòng {$bookingDetail->room->name} - {$nights} đêm",
                    'quantity' => 1,
                    'unit_price' => $bookingDetail->room->price_per_night ?? 0,
                    'total_line' => $roomPrice,
                    'item_type' => 'room_charge',
                ]);
                $totalAmount += $roomPrice;
            }

            // Thêm các service charges đã được approve
            $allBookingDetailIds = $booking->details->pluck('id')->toArray();
            $approvedServices = \App\Models\BookingService::whereIn('booking_details_id', $allBookingDetailIds)
                ->where('status', 'approved')
                ->with('service')
                ->get();

            foreach ($approvedServices as $bookingService) {
                $serviceExists = $invoice->invoiceItems()
                    ->where('item_type', 'service_charge')
                    ->where('description', 'like', '%' . $bookingService->service->name . '%')
                    ->exists();

                if (!$serviceExists) {
                    $serviceTotal = $bookingService->price_at_booking * $bookingService->quantity;
                    $invoiceItemModel::create([
                        'invoice_id' => $invoice->id,
                        'description' => "Dịch vụ: {$bookingService->service->name} (SL: {$bookingService->quantity})",
                        'quantity' => $bookingService->quantity,
                        'unit_price' => $bookingService->price_at_booking,
                        'total_line' => $serviceTotal,
                        'item_type' => 'service_charge',
                    ]);
                    $totalAmount += $serviceTotal;
                }
            }

            // =====================================================
            // XỬ LÝ THIỆT HẠI VẬT TƯ (DAMAGED SUPPLIES)
            // =====================================================
            $totalDamageFee = 0;
            $damageItems = [];
            
            if ($request->has('damaged_supplies') && !empty($request->damaged_supplies)) {
                foreach ($request->damaged_supplies as $damagedItem) {
                    $supply = \App\Models\Supply::findOrFail($damagedItem['supply_id']);
                    $quantity = (int) $damagedItem['quantity'];
                    $unitPrice = $damagedItem['unit_price'] ?? $supply->unit_price;
                    $totalLine = $quantity * $unitPrice;
                    $notes = $damagedItem['notes'] ?? '';

                    // Tạo InvoiceItem cho thiệt hại
                    $invoiceItemModel::create([
                        'invoice_id' => $invoice->id,
                        'description' => "Thiệt hại vật tư: {$supply->name}" . ($notes ? " - {$notes}" : ''),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_line' => $totalLine,
                        'item_type' => 'damage_fee',
                    ]);

                    // Ghi log vào supply_logs
                    \App\Models\SupplyLog::create([
                        'supply_id' => $supply->id,
                        'user_id' => $admin->id,
                        'change_quantity' => -$quantity,
                        'reason' => "Thiệt hại khi checkout request #{$checkoutRequest->id} - booking #{$booking->order_code}. " . ($notes ? "Ghi chú: {$notes}" : ''),
                    ]);

                    // Cập nhật stock của supply
                    $supply->update([
                        'current_stock' => max(0, $supply->current_stock - $quantity),
                    ]);

                    $totalDamageFee += $totalLine;
                    $damageItems[] = [
                        'supply' => $supply->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $totalLine,
                        'notes' => $notes,
                    ];
                }
                
                $totalAmount += $totalDamageFee;
            }

            // Trừ tiền cọc đã thanh toán (nếu có và chưa được thêm vào invoice)
            $hasDeposit = $invoice->invoiceItems()->where('item_type', 'deposit')->exists();
            $paidAmount = $booking->paid_amount ?? 0;
            if ($paidAmount > 0 && !$hasDeposit) {
                $invoiceItemModel::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Tiền cọc đã thanh toán',
                    'quantity' => 1,
                    'unit_price' => -$paidAmount,
                    'total_line' => -$paidAmount,
                    'item_type' => 'deposit',
                ]);
                $totalAmount -= $paidAmount;
            }

            $finalAmount = max(0, $totalAmount);
            $invoice->update(['total_amount' => $finalAmount]);

            // Cập nhật checkout request
            $checkoutRequest->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            Log::info('Checkout request approved with damage items', [
                'checkout_request_id' => $checkoutRequest->id,
                'booking_id' => $booking->id,
                'booking_detail_id' => $bookingDetail->id,
                'admin_id' => $admin->id,
                'damage_items_count' => count($damageItems),
                'total_damage_fee' => $totalDamageFee,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu checkout đã được duyệt. Hóa đơn đã được tạo/cập nhật.',
                'data' => $checkoutRequest->fresh(['bookingOrder', 'bookingDetail', 'reviewer']),
                'damage_summary' => [
                    'total_damage_fee' => $totalDamageFee,
                    'items' => $damageItems,
                ],
                'invoice' => $invoice->fresh(['invoiceItems']),
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
            Log::error('BookingOrderController@approveCheckoutRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi duyệt yêu cầu checkout.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Reject checkout request
     */
    public function rejectCheckoutRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            DB::beginTransaction();

            $checkoutRequest = \App\Models\CheckoutRequest::findOrFail($id);

            // Kiểm tra request đã được xử lý chưa
            if ($checkoutRequest->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu checkout này đã được xử lý.',
                ], 400);
            }

            // Cập nhật checkout request
            $checkoutRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            Log::info('Checkout request rejected', [
                'checkout_request_id' => $checkoutRequest->id,
                'admin_id' => $admin->id,
                'reason' => $request->rejection_reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu checkout đã bị từ chối.',
                'data' => $checkoutRequest->fresh(['bookingOrder', 'bookingDetail', 'reviewer']),
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
            Log::error('BookingOrderController@rejectCheckoutRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối yêu cầu checkout.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // AMENITY REQUESTS - Yêu cầu tiện ích
    // =========================================================================

    /**
     * User: Yêu cầu tiện ích cho booking
     * Tạo AmenityRequest với status = 'pending'
     */
    public function requestAmenity(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'booking_detail_id' => 'required|exists:booking_details,id',
                'amenity_id' => 'required|exists:amenities,id',
                'quantity' => 'required|integer|min:1',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details'])->findOrFail($id);

            // Kiểm tra booking có thuộc về user hiện tại không
            if ($booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền yêu cầu tiện ích cho đơn đặt phòng này.',
                ], 403);
            }

            // Kiểm tra booking detail có thuộc về booking này không
            $bookingDetail = $booking->details->firstWhere('id', $request->booking_detail_id);
            if (!$bookingDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking detail không thuộc về booking này.',
                ], 400);
            }

            // Kiểm tra booking có thể yêu cầu tiện ích không (phải đã check-in)
            if (!in_array($booking->status, ['checked_in', 'partially_checked_in'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể yêu cầu tiện ích khi đã check-in.',
                ], 400);
            }

            // Lấy thông tin amenity
            $amenity = \App\Models\Amenity::findOrFail($request->amenity_id);

            // Kiểm tra xem đã có yêu cầu pending cho amenity này chưa
            $existingRequest = \App\Models\AmenityRequest::where('booking_details_id', $request->booking_detail_id)
                ->where('amenity_id', $request->amenity_id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã có yêu cầu tiện ích này đang chờ xử lý.',
                ], 400);
            }

            // Tạo AmenityRequest với status = 'pending'
            $amenityRequest = \App\Models\AmenityRequest::create([
                'booking_details_id' => $request->booking_detail_id,
                'amenity_id' => $request->amenity_id,
                'quantity' => $request->quantity,
                'price_at_request' => null, // Tiện ích thường không tính phí trực tiếp
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            // Gửi thông báo cho admin (log để admin có thể xem)
            Log::info('New amenity request created', [
                'amenity_request_id' => $amenityRequest->id,
                'booking_id' => $booking->id,
                'booking_order_code' => $booking->order_code,
                'amenity_id' => $amenity->id,
                'amenity_name' => $amenity->name,
                'quantity' => $request->quantity,
                'customer_name' => $booking->customer_name ?? $user->full_name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu tiện ích đã được gửi. Vui lòng chờ admin xác nhận.',
                'data' => $amenityRequest->load('amenity'),
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
            Log::error('BookingOrderController@requestAmenity failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi yêu cầu tiện ích.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Lấy danh sách amenity requests
     */
    public function getAmenityRequests(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'sometimes|in:pending,approved,rejected,completed',
                'booking_id' => 'sometimes|exists:booking_orders,id',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $perPage = (int) ($request->get('per_page', 15));
            $query = \App\Models\AmenityRequest::with([
                'amenity',
                'detail.room',
                'detail.bookingOrder.guest',
                'processedBy:id,full_name',
            ])
            ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Mặc định chỉ lấy pending
                $query->where('status', 'pending');
            }

            // Filter by booking_id
            if ($request->has('booking_id')) {
                $query->whereHas('detail', function($q) use ($request) {
                    $q->where('booking_order_id', $request->booking_id);
                });
            }

            $amenityRequests = $query->paginate($perPage);

            $result = collect($amenityRequests->items())->map(function($amenityRequest) {
                $detail = $amenityRequest->detail;
                $booking = $detail->bookingOrder ?? null;
                $amenity = $amenityRequest->amenity;
                $room = $detail->room ?? null;

                return [
                    'id' => $amenityRequest->id,
                    'booking_id' => $booking->id ?? null,
                    'booking_order_code' => $booking->order_code ?? null,
                    'room_name' => $room->name ?? null,
                    'amenity' => $amenity ? [
                        'id' => $amenity->id,
                        'name' => $amenity->name,
                        'icon_url' => $amenity->icon_url,
                        'type' => $amenity->type,
                        'category' => $amenity->category,
                    ] : null,
                    'quantity' => $amenityRequest->quantity,
                    'status' => $amenityRequest->status,
                    'notes' => $amenityRequest->notes,
                    'admin_notes' => $amenityRequest->admin_notes,
                    'customer_name' => $booking->customer_name ?? $booking->guest->full_name ?? null,
                    'processed_by' => $amenityRequest->processedBy ? [
                        'id' => $amenityRequest->processedBy->id,
                        'name' => $amenityRequest->processedBy->full_name,
                    ] : null,
                    'created_at' => $amenityRequest->created_at?->toISOString(),
                    'approved_at' => $amenityRequest->approved_at?->toISOString(),
                    'rejected_at' => $amenityRequest->rejected_at?->toISOString(),
                    'completed_at' => $amenityRequest->completed_at?->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'pagination' => [
                        'page' => $amenityRequests->currentPage(),
                        'per_page' => $amenityRequests->perPage(),
                        'total' => $amenityRequests->total(),
                        'last_page' => $amenityRequests->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BookingOrderController@getAmenityRequests failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách yêu cầu tiện ích.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Approve amenity request
     */
    public function approveAmenityRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $amenityRequest = \App\Models\AmenityRequest::with([
                'amenity',
                'detail.bookingOrder',
                'detail.room',
            ])->findOrFail($id);

            // Kiểm tra status
            if ($amenityRequest->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu tiện ích này đã được xử lý.',
                ], 400);
            }

            // Cập nhật status
            $amenityRequest->update([
                'status' => 'approved',
                'admin_notes' => $request->admin_notes,
                'approved_at' => now(),
                'processed_by' => $admin->id,
            ]);

            $booking = $amenityRequest->detail->bookingOrder;
            $amenity = $amenityRequest->amenity;

            Log::info('Amenity request approved', [
                'amenity_request_id' => $amenityRequest->id,
                'booking_id' => $booking->id,
                'amenity_name' => $amenity->name ?? 'N/A',
                'approved_by' => $admin->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu tiện ích đã được chấp nhận.',
                'data' => $amenityRequest->fresh(['amenity', 'detail.bookingOrder', 'processedBy']),
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
            Log::error('BookingOrderController@approveAmenityRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi chấp nhận yêu cầu tiện ích.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Reject amenity request
     */
    public function rejectAmenityRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $amenityRequest = \App\Models\AmenityRequest::with([
                'amenity',
                'detail.bookingOrder',
            ])->findOrFail($id);

            // Kiểm tra status
            if ($amenityRequest->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Yêu cầu tiện ích này đã được xử lý.',
                ], 400);
            }

            // Cập nhật status
            $amenityRequest->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
                'rejected_at' => now(),
                'processed_by' => $admin->id,
            ]);

            $booking = $amenityRequest->detail->bookingOrder;
            $amenity = $amenityRequest->amenity;

            Log::info('Amenity request rejected', [
                'amenity_request_id' => $amenityRequest->id,
                'booking_id' => $booking->id,
                'amenity_name' => $amenity->name ?? 'N/A',
                'rejected_by' => $admin->id,
                'reason' => $request->admin_notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu tiện ích đã bị từ chối.',
                'data' => $amenityRequest->fresh(['amenity', 'detail.bookingOrder', 'processedBy']),
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
            Log::error('BookingOrderController@rejectAmenityRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối yêu cầu tiện ích.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Admin: Mark amenity request as completed
     */
    public function completeAmenityRequest(Request $request, string $id): JsonResponse
    {
        try {
            $admin = $request->user();
            
            if (!$admin || !in_array($admin->role, ['admin', 'staff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $request->validate([
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $amenityRequest = \App\Models\AmenityRequest::with([
                'amenity',
                'detail.bookingOrder',
            ])->findOrFail($id);

            // Kiểm tra status - chỉ có thể complete nếu đã approved
            if ($amenityRequest->status !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể hoàn thành yêu cầu đã được chấp nhận.',
                ], 400);
            }

            // Cập nhật status
            $amenityRequest->update([
                'status' => 'completed',
                'admin_notes' => $request->admin_notes ?? $amenityRequest->admin_notes,
                'completed_at' => now(),
                'processed_by' => $admin->id,
            ]);

            Log::info('Amenity request completed', [
                'amenity_request_id' => $amenityRequest->id,
                'completed_by' => $admin->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu tiện ích đã được hoàn thành.',
                'data' => $amenityRequest->fresh(['amenity', 'detail.bookingOrder', 'processedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingOrderController@completeAmenityRequest failed', [
                'request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi hoàn thành yêu cầu tiện ích.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
