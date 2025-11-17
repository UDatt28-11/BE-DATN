<?php
// app/Http/Controllers/Api/Admin/BookingOrderController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BookingOrderResource;
use App\Http\Requests\Admin\StoreBookingOrderRequest;
use App\Http\Requests\Admin\UpdateBookingOrderRequest;
use App\Http\Requests\Admin\IndexBookingOrderRequest;
use App\Models\BookingOrder;
use App\Services\BookingOrder\QueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            
        $order = BookingOrder::create($request->validated());

            Log::info('BookingOrder created', [
                'booking_order_id' => $order->id,
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
            $with = ['guest:id,full_name,email']; // Always load guest
            
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
                    } elseif ($include === 'promotions') {
                        $with[] = 'promotions:id,name';
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
                    'guest:id,full_name,email',
                    'details.room:id,name,room_type_id,property_id',
                    'details.room.roomType:id,name',
                    'details.room.property:id,name',
                    'details.bookingServices.service:id,name',
                    'details.checkedInGuests',
                    'invoices',
                    'promotions:id,name'
                ];
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
}
