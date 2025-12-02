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

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Load lại relationships để trả về đầy đủ
            $order->load(['guest', 'details', 'details.room', 'details.room.images']);

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

            $booking->load(['guest', 'details', 'details.room', 'details.room.images']);

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

            $booking->load(['guest', 'details', 'details.room', 'details.room.images']);

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
     * User hủy đơn đặt phòng của chính mình
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

            $booking = BookingOrder::findOrFail($id);

            // Chỉ cho phép hủy đơn của chính mình (hoặc admin)
            if ($booking->guest_id !== $user->id && ($user->role ?? null) !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền hủy đơn đặt phòng này.',
                ], 403);
            }

            // Chỉ cho phép hủy khi đang pending hoặc confirmed
            if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể hủy đơn ở trạng thái hiện tại.',
                ], 400);
            }

            // Nếu đã có thanh toán (paid_amount > 0) thì không cho user tự hủy
            if (($booking->paid_amount ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đã có thanh toán, vui lòng liên hệ hỗ trợ để hủy.',
                ], 400);
            }

            $from = $booking->status;
            $booking->update([
                'status' => 'cancelled',
            ]);

            Log::info('BookingOrder cancelled by user', [
                'booking_order_id' => $booking->id,
                'user_id' => $user->id,
                'from' => $from,
                'to' => 'cancelled',
            ]);

            $booking->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Hủy đặt phòng thành công.',
                'data' => new BookingOrderResource($booking),
            ]);
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

            $booking->load(['guest', 'details', 'details.room', 'details.room.images']);

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
                if (in_array('details.room.images', $include, true)) {
                    $relations[] = 'details.room.images';
                }
                if (in_array('details.guests', $include, true)) {
                    $relations[] = 'details.guests';
                }
            }
            
            if (in_array('checkInRequests', $include, true)) {
                $relations[] = 'checkInRequests';
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
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy chi tiết đơn đặt phòng.',
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
                ->where('status', 'confirmed')
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
                foreach ($readyBookings as $booking) {
                    foreach ($booking->details as $detail) {
                        // Chỉ thêm nếu detail chưa có checked-in guests
                        if ($detail->checkedInGuests->isEmpty()) {
                            $result[] = (object) [
                                'id' => 'booking_' . $booking->id . '_detail_' . $detail->id,
                                'type' => 'ready_booking', // Đánh dấu đây là booking sẵn sàng check-in
                                'booking_order_id' => $booking->id,
                                'booking_detail_id' => $detail->id,
                                'booking_order' => $booking,
                                'booking_detail' => $detail,
                                'full_name' => $booking->customer_name,
                                'status' => 'ready_for_checkin',
                                'created_at' => $booking->created_at,
                            ];
                        }
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
            $totalDetails = $booking->details()->count();
            $checkedInCount = $booking->details()->where('status', 'checked_in')->count();
            
            // Cập nhật trạng thái booking order
            $newStatus = ($checkedInCount >= $totalDetails) ? 'checked_in' : 'partially_checked_in';
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

            // Kiểm tra xem tất cả phòng đã check-in chưa
            $totalDetails = $booking->details()->count();
            $checkedInCount = $booking->details()->where('status', 'checked_in')->count();
            
            // Cập nhật trạng thái booking order
            $newStatus = ($checkedInCount >= $totalDetails) ? 'checked_in' : 'partially_checked_in';
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

            $booking = BookingOrder::with(['details.room', 'invoices'])->findOrFail($id);

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

            // Tạo invoice nếu chưa có
            $invoice = null;
            if (!$booking->invoices()->exists()) {
                $invoiceModel = \App\Models\Invoice::class;
                $invoiceItemModel = \App\Models\InvoiceItem::class;
                
                $invoice = $invoiceModel::create([
                    'booking_order_id' => $booking->id,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'total_amount' => 0, // Will be updated after calculating items
                    'status' => 'pending',
                ]);

                // Tạo invoice items từ booking details
                $totalAmount = 0;
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

                // Trừ tiền cọc đã thanh toán (nếu có)
                $paidAmount = $booking->paid_amount ?? 0;
                if ($paidAmount > 0) {
                    // Thêm invoice item để hiển thị tiền cọc đã thanh toán
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
            } else {
                $invoice = $booking->invoices()->first();
            }

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
     * Store identity image
     */
    private function storeIdentityImage($file): string
    {
        $filename = 'identity_' . time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('identity_images', $filename, 'public');
        return Storage::url($path);
    }
}
