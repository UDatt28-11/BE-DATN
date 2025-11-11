<?php
// app/Http/Controllers/Api/Admin/BookingOrderController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BookingOrderResource;
use App\Http\Requests\Admin\StoreBookingOrderRequest;
use App\Http\Requests\Admin\UpdateBookingOrderRequest;
use App\Models\BookingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     *     description="Lấy danh sách tất cả đơn đặt phòng với hỗ trợ phân trang",
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
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách đơn đặt phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            $request->validate([
                'order_code' => 'sometimes|string|max:255',
                'customer_name' => 'sometimes|string|max:255',
                'customer_email' => 'sometimes|string|email',
                'property_id' => 'sometimes|integer|exists:properties,id',
                'status' => 'sometimes|string|in:pending,confirmed,cancelled,completed',
                'staff_id' => 'sometimes|integer|exists:users,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'check_in_from' => 'sometimes|date',
                'check_in_to' => 'sometimes|date|after_or_equal:check_in_from',
                'check_out_from' => 'sometimes|date',
                'check_out_to' => 'sometimes|date|after_or_equal:check_out_from',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:id,order_code,total_amount,status,created_at,updated_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'staff_id.exists' => 'Staff không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: pending, confirmed, cancelled, completed.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = BookingOrder::with([
                'guest:id,full_name,email',
                'staff:id,full_name,email',
                'details.room:id,name,room_type_id,property_id',
                'details.room.roomType:id,name',
                'details.room.property:id,name'
            ]);

            // Filter by order_code
            if ($request->has('order_code')) {
                $query->where('order_code', 'like', '%' . $request->order_code . '%');
            }

            // Filter by customer_name
            if ($request->has('customer_name')) {
                $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
            }

            // Filter by customer_email
            if ($request->has('customer_email')) {
                $query->where('customer_email', $request->customer_email);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by staff_id
            if ($request->has('staff_id')) {
                $query->where('staff_id', $request->staff_id);
            }

            // Filter by property_id (via details.room.property_id)
            if ($request->has('property_id')) {
                $query->whereHas('details.room', function ($q) use ($request) {
                    $q->where('property_id', $request->property_id);
                });
            }

            // Filter by date range (created_at)
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filter by check-in date range
            if ($request->has('check_in_from') || $request->has('check_in_to')) {
                $query->whereHas('details', function ($q) use ($request) {
                    if ($request->has('check_in_from')) {
                        $q->whereDate('check_in_date', '>=', $request->check_in_from);
                    }
                    if ($request->has('check_in_to')) {
                        $q->whereDate('check_in_date', '<=', $request->check_in_to);
                    }
                });
            }

            // Filter by check-out date range
            if ($request->has('check_out_from') || $request->has('check_out_to')) {
                $query->whereHas('details', function ($q) use ($request) {
                    if ($request->has('check_out_from')) {
                        $q->whereDate('check_out_date', '>=', $request->check_out_from);
                    }
                    if ($request->has('check_out_to')) {
                        $q->whereDate('check_out_date', '<=', $request->check_out_to);
                    }
                });
            }

            // Search (order_code, customer_name, customer_email)
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_email', 'like', '%' . $search . '%')
                        ->orWhereHas('guest', function ($q) use ($search) {
                            $q->where('full_name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => BookingOrderResource::collection($orders),
                'meta' => [
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'per_page' => $orders->perPage(),
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
            Log::error('BookingOrderController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
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
            // Authorization is handled by route middleware (role:admin)
            
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
     *     description="Lấy thông tin chi tiết của một đơn đặt phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
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
    public function show(BookingOrder $booking_order): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            // Load đầy đủ relationships
            $booking_order->load([
                'guest:id,full_name,email',
                'details.room:id,name,room_type_id,property_id',
                'details.room.roomType:id,name',
                'details.room.property:id,name',
                'details.bookingServices.service:id,name',
                'invoices',
                'promotions:id,name'
            ]);
            
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
            // Authorization is handled by route middleware (role:admin)
            
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
            // Authorization is handled by route middleware (role:admin)
            
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
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            $request->validate([
                'status' => 'required|string',
            ]);

            $bookingOrder = BookingOrder::findOrFail($id);
            $bookingOrder->update(['status' => $request->status]);

            Log::info('BookingOrder status updated', [
                'booking_order_id' => $bookingOrder->id,
                'status' => $request->status,
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
}
