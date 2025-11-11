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
            
            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            
            // Load đầy đủ relationships để tránh N+1 query
            $orders = BookingOrder::with([
                'guest:id,full_name,email',
                'details.room:id,name,room_type_id,property_id',
                'details.room.roomType:id,name',
                'details.room.property:id,name'
            ])
                ->latest()
                ->paginate($perPage);

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
        } catch (\Exception $e) {
            Log::error('BookingOrderController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đơn đặt phòng: ' . $e->getMessage(),
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
}
