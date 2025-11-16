<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexBookingOrderRequest;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\Admin\BookingOrderResource;
use App\Models\BookingOrder;
use App\Services\BookingOrder\QueryService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Booking Orders",
 *     description="API Endpoints for Booking Order Management"
 * )
 */
class BookingOrderController extends Controller
{
    /**
     * Lấy danh sách đơn đặt phòng
     * 
     * @OA\Get(
     *     path="/api/admin/booking-orders",
     *     operationId="getBookingOrders",
     *     tags={"Booking Orders"},
     *     summary="Danh sách đơn đặt phòng",
     *     description="Lấy danh sách tất cả đơn đặt phòng với bộ lọc",
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
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "confirmed", "completed", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách đơn đặt phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order_code", type="string", example="BK20240101120000001"),
     *                     @OA\Property(property="customer_name", type="string", example="Nguyễn Văn A"),
     *                     @OA\Property(property="customer_phone", type="string", example="0123456789"),
     *                     @OA\Property(property="customer_email", type="string", example="example@email.com"),
     *                     @OA\Property(property="total_amount", type="number", example=1000000),
     *                     @OA\Property(property="status", type="string", example="pending")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(IndexBookingOrderRequest $request, QueryService $service): JsonResponse
    {
        // Use raw query params to avoid dropping filters when validation is lenient
        $result = $service->index($request->query());
        return response()->json($result);
    }

    /**
     * Lấy chi tiết đơn đặt phòng
     * 
     * @OA\Get(
     *     path="/api/admin/booking-orders/{id}",
     *     operationId="getBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Chi tiết đơn đặt phòng",
     *     description="Lấy thông tin chi tiết một đơn đặt phòng",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID của đơn đặt phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relationships (details, details.room, details.guests)",
     *         required=false,
     *         @OA\Schema(type="string", example="details,details.room,details.guests")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết đơn đặt phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_code", type="string", example="BK20240101120000001"),
     *                 @OA\Property(property="customer_name", type="string", example="Nguyễn Văn A"),
     *                 @OA\Property(property="customer_phone", type="string", example="0123456789"),
     *                 @OA\Property(property="total_amount", type="number", example=1000000),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy đơn đặt phòng"
     *     )
     * )
     */
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

    /**
     * Cập nhật trạng thái đơn đặt phòng
     * 
     * @OA\Patch(
     *     path="/api/admin/booking-orders/{id}/status",
     *     operationId="updateBookingOrderStatus",
     *     tags={"Booking Orders"},
     *     summary="Cập nhật trạng thái đơn",
     *     description="Cập nhật trạng thái đơn đặt phòng theo state machine",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID của đơn đặt phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "confirmed", "completed", "cancelled"}, example="confirmed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật trạng thái thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="confirmed")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Trạng thái không hợp lệ"
     *     )
     * )
     */
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

    /**
     * Tạo đơn đặt phòng mới
     * 
     * @OA\Post(
     *     path="/api/admin/booking-orders",
     *     operationId="createBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Tạo đơn đặt phòng mới",
     *     description="Tạo đơn đặt phòng mới với chi tiết phòng",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_name", "customer_phone", "total_amount", "details"},
     *             @OA\Property(property="guest_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="customer_name", type="string", example="Nguyễn Văn A"),
     *             @OA\Property(property="customer_phone", type="string", example="0123456789"),
     *             @OA\Property(property="customer_email", type="string", nullable=true, example="example@email.com"),
     *             @OA\Property(property="total_amount", type="number", example=1000000),
     *             @OA\Property(property="payment_method", type="string", nullable=true, example="cash"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Ghi chú đặc biệt"),
     *             @OA\Property(
     *                 property="details",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"room_id", "check_in_date", "check_out_date", "num_adults", "num_children", "sub_total"},
     *                     @OA\Property(property="room_id", type="integer", example=1),
     *                     @OA\Property(property="check_in_date", type="string", format="date", example="2024-01-15"),
     *                     @OA\Property(property="check_out_date", type="string", format="date", example="2024-01-17"),
     *                     @OA\Property(property="num_adults", type="integer", example=2),
     *                     @OA\Property(property="num_children", type="integer", example=0),
     *                     @OA\Property(property="sub_total", type="number", example=500000)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo đặt phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Tạo đặt phòng thành công")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_id' => 'nullable|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.room_id' => 'required|exists:rooms,id',
            'details.*.check_in_date' => 'required|date',
            'details.*.check_out_date' => 'required|date|after:details.*.check_in_date',
            'details.*.num_adults' => 'required|integer|min:1',
            'details.*.num_children' => 'required|integer|min:0',
            'details.*.sub_total' => 'required|numeric|min:0',
        ]);

        // Tạo mã đơn hàng tự động
        $orderCode = 'BK' . date('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Tạo booking order
        $order = BookingOrder::create([
            'guest_id' => $validated['guest_id'] ?? null, // Lưu guest_id nếu có (khách đã đăng ký)
            'order_code' => $orderCode,
            'customer_name' => $validated['customer_name'], // Luôn lưu tên (có thể khác tên tài khoản)
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'total_amount' => $validated['total_amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        // Tạo booking details
        foreach ($validated['details'] as $detail) {
            $order->details()->create([
                'room_id' => $detail['room_id'],
                'check_in_date' => $detail['check_in_date'],
                'check_out_date' => $detail['check_out_date'],
                'num_adults' => $detail['num_adults'],
                'num_children' => $detail['num_children'],
                'sub_total' => $detail['sub_total'],
                'status' => 'active',
            ]);
        }

        $order->load('details');

        return response()->json([
            'data' => new BookingOrderResource($order),
            'message' => 'Tạo đặt phòng thành công',
        ], 201);
    }

    /**
     * Cập nhật thông tin đơn đặt phòng
     * 
     * @OA\Put(
     *     path="/api/admin/booking-orders/{id}",
     *     operationId="updateBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Cập nhật đơn đặt phòng",
     *     description="Cập nhật thông tin đơn đặt phòng (không thay đổi guest_id)",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID của đơn đặt phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_name", type="string", example="Nguyễn Văn A"),
     *             @OA\Property(property="customer_phone", type="string", example="0123456789"),
     *             @OA\Property(property="customer_email", type="string", nullable=true, example="example@email.com"),
     *             @OA\Property(property="total_amount", type="number", example=1000000),
     *             @OA\Property(property="payment_method", type="string", nullable=true, example="cash"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Ghi chú đặc biệt")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật đặt phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Cập nhật đặt phòng thành công")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy đơn đặt phòng"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(int $id, \Illuminate\Http\Request $request): JsonResponse
    {
        $order = BookingOrder::findOrFail($id);

        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'customer_phone' => 'sometimes|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'total_amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        // Cập nhật mà KHÔNG động vào guest_id
        // Cho phép customer_name ghi đè hiển thị từ users.full_name
        $order->update($validated);

        return response()->json([
            'data' => new BookingOrderResource($order),
            'message' => 'Cập nhật đặt phòng thành công',
        ]);
    }

    /**
     * Xóa đơn đặt phòng
     * 
     * @OA\Delete(
     *     path="/api/admin/booking-orders/{id}",
     *     operationId="deleteBookingOrder",
     *     tags={"Booking Orders"},
     *     summary="Xóa đơn đặt phòng",
     *     description="Xóa đơn đặt phòng (chỉ cho phép với trạng thái pending hoặc cancelled)",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID của đơn đặt phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa đặt phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Xóa đặt phòng thành công")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy đơn đặt phòng"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Không thể xóa đơn với trạng thái hiện tại"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $order = BookingOrder::findOrFail($id);

        // Chỉ cho phép xóa nếu trạng thái là pending hoặc cancelled
        if (!in_array($order->status, ['pending', 'cancelled'], true)) {
            return response()->json([
                'error' => [
                    'code' => 'CANNOT_DELETE',
                    'message' => 'Chỉ có thể xóa đơn đang chờ hoặc đã hủy',
                ],
            ], 422);
        }

        $order->delete();

        return response()->json([
            'message' => 'Xóa đặt phòng thành công',
        ]);
    }
}
