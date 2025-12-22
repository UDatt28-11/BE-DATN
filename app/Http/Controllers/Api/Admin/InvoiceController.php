<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexInvoiceRequest;
use App\Models\Invoice;
use App\Models\BookingOrder;
use App\Models\InvoiceItem;
use App\Models\InvoiceConfig;
use App\Models\RefundPolicy;
use App\Models\InvoiceDiscount;
use App\Models\SplitInvoice;
use App\Models\BookingDetail;
use App\Services\Invoice\QueryService;
use App\Support\LogHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(
 *     name="Invoices",
 *     description="API Endpoints for Invoice Management"
 * )
 */

class InvoiceController extends Controller
{

    /**
     * Display a listing of invoices
     *
     * @OA\Get(
     *     path="/api/invoices",
     *     operationId="getInvoices",
     *     tags={"Invoices"},
     *     summary="Danh sách hóa đơn",
     *     description="Lấy danh sách tất cả hóa đơn với hỗ trợ lọc, tìm kiếm và phân trang",
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái (pending, approved, cancelled)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         description="Lọc theo trạng thái thanh toán (paid, unpaid, overdue)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo mã hóa đơn",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Lọc từ ngày (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Lọc đến ngày (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (mặc định 1, 15 kết quả/trang)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách hóa đơn",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(IndexInvoiceRequest $request, QueryService $service): JsonResponse
    {
        // Filter sensitive data before logging
        Log::info('Invoices#index called', ['query' => LogHelper::filterQuery($request)]);
        try {
            // Use QueryService để xử lý logic query phức tạp
            // Use raw query params to avoid dropping filters when validation is lenient
            $result = $service->index($request->query());

            Log::info('Invoices#index success', ['count' => count($result['data'])]);
            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            Log::error('Invoices#index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách hóa đơn.',
            ], 500);
        }
    }

    /**
     * Create invoice from booking order
     *
     * @OA\Post(
     *     path="/api/invoices/create-from-booking",
     *     operationId="createInvoiceFromBooking",
     *     tags={"Invoices"},
     *     summary="Tạo hóa đơn từ đơn đặt phòng",
     *     description="Tạo hóa đơn mới dựa trên đơn đặt phòng",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_order_id"},
     *             @OA\Property(property="booking_order_id", type="integer", example=1),
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-12-20"),
     *             @OA\Property(property="notes", type="string", example="Ghi chú hóa đơn")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Hóa đơn được tạo thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Lỗi xác thực"
     *     )
     * )
     */
    public function createFromBooking(Request $request): JsonResponse
    {
        $request->validate([
            'booking_order_id' => 'required|exists:booking_orders,id',
            'due_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $bookingOrder = BookingOrder::with(['bookingDetails.room', 'bookingServices.service'])->findOrFail($request->booking_order_id);

            // Check if invoice already exists
            if ($bookingOrder->invoice()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn cho đơn đặt phòng này đã tồn tại'
                ], 400);
            }

            // Create invoice
            $invoice = Invoice::create([
                'booking_order_id' => $bookingOrder->id,
                'issue_date' => now()->toDateString(),
                'due_date' => $request->due_date ?? now()->addDays(7)->toDateString(),
                'total_amount' => 0,  // Will be updated after calculating items
                'status' => 'pending'
            ]);

            // Create invoice items from booking details
            $totalAmount = 0;
            $totalRoomPrice = 0;
            $roomPrices = [];
            
            foreach ($bookingOrder->bookingDetails as $detail) {
                // Calculate nights from check-in and check-out
                $nights = $detail->check_out_date->diffInDays($detail->check_in_date);
                if ($nights <= 0) $nights = 1; // At least 1 night

                $roomPrice = $detail->room->price_per_night * $nights;
                $roomPrices[$detail->id] = $roomPrice;
                $totalRoomPrice += $roomPrice;

                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'booking_detail_id' => $detail->id, // Đảm bảo gán booking_detail_id
                    'description' => "Phòng {$detail->room->name} - {$nights} đêm",
                    'quantity' => 1,
                    'unit_price' => $detail->room->price_per_night,
                    'total_line' => $roomPrice,
                    'item_type' => 'room_charge'
                ]);
                $totalAmount += $roomPrice;
            }

            // Create invoice items from booking services
            foreach ($bookingOrder->bookingServices as $bookingService) {
                $servicePrice = $bookingService->service->unit_price * $bookingService->quantity;
                $bookingDetailId = $bookingService->booking_details_id ?? null;
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'booking_detail_id' => $bookingDetailId, // Gán booking_detail_id từ bookingService
                    'description' => $bookingService->service->name . ' - ' . ($bookingService->service->description ?? ''),
                    'quantity' => $bookingService->quantity,
                    'unit_price' => $bookingService->service->unit_price,
                    'total_line' => $servicePrice,
                    'item_type' => 'service_charge'
                ]);
                $totalAmount += $servicePrice;
            }

            // Trừ tiền cọc đã thanh toán (nếu có) - TÁCH THÀNH NHIỀU DÒNG THEO TỪNG PHÒNG
            $paidAmount = $bookingOrder->paid_amount ?? 0;
            if ($paidAmount > 0 && $totalRoomPrice > 0) {
                // Tính tiền cọc cho từng phòng dựa trên giá phòng và policy
                foreach ($bookingOrder->bookingDetails as $detail) {
                    $roomPrice = $roomPrices[$detail->id] ?? 0;
                    $roomName = $detail->room->name ?? '';
                    
                    // Tính tiền cọc theo policy: < 1,000,000 VND = 100%, >= 1,000,000 VND = 50%
                    if ($roomPrice < 1000000) {
                        $roomDeposit = $roomPrice; // 100% giá phòng
                    } else {
                        $roomDeposit = $roomPrice * 0.5; // 50% giá phòng
                    }
                    $roomDeposit = round($roomDeposit, 2);
                    
                    if ($roomDeposit > 0) {
                        $depositPercentage = $roomPrice < 1000000 ? '100%' : '50%';
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'booking_detail_id' => $detail->id, // Đảm bảo gán booking_detail_id
                            'description' => "Tiền cọc đã thanh toán - Phòng {$roomName} ({$depositPercentage} giá phòng)",
                            'quantity' => 1,
                            'unit_price' => -$roomDeposit, // Giá trị âm để trừ
                            'total_line' => -$roomDeposit, // Giá trị âm để trừ
                            'item_type' => 'deposit',
                        ]);
                        $totalAmount -= $roomDeposit; // Trừ tiền cọc vào tổng tiền
                    }
                }
            }

            // Đảm bảo total_amount không âm
            $finalAmount = max(0, $totalAmount);

            // Update invoice totals
            $invoice->update([
                'total_amount' => $finalAmount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được tạo thành công',
                'data' => $invoice->load(['bookingOrder', 'invoiceItems.damageImages'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified invoice
     *
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     operationId="getInvoice",
     *     tags={"Invoices"},
     *     summary="Chi tiết hóa đơn",
     *     description="Lấy thông tin chi tiết của một hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID hóa đơn",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết hóa đơn",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Hóa đơn không tìm thấy"
     *     )
     * )
     */
    /**
     * Display the specified invoice
     *
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     operationId="getInvoice",
     *     tags={"Invoices"},
     *     summary="Chi tiết hóa đơn",
     *     description="Lấy thông tin chi tiết của một hóa đơn. Có thể sử dụng 'include' parameter để chỉ load relationships cần thiết.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relationships (bookingOrder, bookingOrder.guest, invoiceItems). Ví dụ: 'bookingOrder,invoiceItems'",
     *         required=false,
     *         @OA\Schema(type="string", example="bookingOrder,invoiceItems")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết hóa đơn",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Invoice not found")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // Parse include parameter
            $includes = $request->get('include', '');
            $with = [];
            
            if ($includes) {
                // Parse include string (e.g., "bookingOrder,invoiceItems")
                $includeArray = array_map('trim', explode(',', $includes));
                
                foreach ($includeArray as $include) {
                    if ($include === 'bookingOrder') {
                        $with[] = 'bookingOrder';
                    } elseif ($include === 'bookingOrder.guest') {
                        $with[] = 'bookingOrder.guest';
                    } elseif ($include === 'invoiceItems') {
                        $with[] = 'invoiceItems';
                        $with[] = 'invoiceItems.damageImages';
                    }
                }
            } else {
                // Default: Load tất cả relationships (backward compatibility)
                $with = [
                    'bookingOrder.guest',
                    'invoiceItems',
                    'invoiceItems.damageImages'
                ];
            }
            
            // Loại bỏ duplicates
            $with = array_unique($with);
            
            // Load invoice with relationships
            $invoice = Invoice::with($with)->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('InvoiceController@show failed', [
                'invoice_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin hóa đơn.',
            ], 500);
        }
    }

    /**
     * Mark invoice as paid
     *
     * @OA\Patch(
     *     path="/api/invoices/{id}/mark-paid",
     *     operationId="markInvoiceAsPaid",
     *     tags={"Invoices"},
     *     summary="Đánh dấu hóa đơn là đã thanh toán",
     *     description="Cập nhật trạng thái hóa đơn sang đã thanh toán",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="payment_method", type="string", example="bank_transfer"),
     *             @OA\Property(property="payment_notes", type="string", example="Thanh toán qua ngân hàng")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công"
     *     )
     * )
     */
    public function markAsPaid(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'payment_notes' => 'nullable|string|max:500'
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Hóa đơn đã được thanh toán'
            ], 400);
        }

        $invoice->update([
            'status' => 'paid'
        ]);

        // Cập nhật booking order status thành completed nếu đã checkout và thanh toán
        if ($invoice->bookingOrder) {
            $booking = $invoice->bookingOrder;
            if (in_array($booking->status, ['checked_out', 'partially_checked_out'])) {
                $booking->update([
                    'status' => 'completed',
                    'payment_method' => $request->payment_method ?? $booking->payment_method,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Hóa đơn đã được đánh dấu là đã thanh toán',
            'data' => $invoice
        ]);
    }

    /**
     * Admin/Staff xác nhận invoice sẵn sàng thanh toán
     * Sau khi đã kiểm tra và thêm service/damage (nếu có)
     */
    public function approveForPayment(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $invoice = Invoice::findOrFail($id);

            // Kiểm tra invoice chưa được thanh toán
            if ($invoice->status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này đã được thanh toán.',
                ], 400);
            }

            // Cập nhật trạng thái thành 'paid' khi admin xác nhận
            $invoice->update([
                'status' => 'paid',
            ]);

            // Cập nhật booking order status thành completed nếu đã checkout
            if ($invoice->bookingOrder) {
                $booking = $invoice->bookingOrder;
                if (in_array($booking->status, ['checked_out', 'partially_checked_out'])) {
                    $booking->update([
                        'status' => 'completed',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được xác nhận và đánh dấu là đã thanh toán.',
                'data' => $invoice->fresh(['invoiceItems.damageImages', 'bookingOrder']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('InvoiceController@approveForPayment failed', [
                'invoice_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác nhận hóa đơn.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * User thanh toán invoice
     * User có thể thanh toán invoice của chính mình
     * Chỉ thanh toán được khi invoice đã được admin/staff xác nhận
     */
    public function payInvoice(Request $request, string $id): JsonResponse
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
                'payment_method' => 'required|string|in:cash,bank,momo,card,payos,vnpay',
                'payment_notes' => 'nullable|string|max:500',
            ]);

            DB::beginTransaction();

            $invoice = Invoice::with('bookingOrder')->findOrFail($id);

            // Kiểm tra invoice có thuộc về user hiện tại không
            if ($invoice->bookingOrder && $invoice->bookingOrder->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thanh toán hóa đơn này.',
                ], 403);
            }

            // Kiểm tra invoice đã được thanh toán chưa
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này đã được thanh toán.',
                ], 400);
            }

            // Kiểm tra invoice có status là 'pending' mới cho phép thanh toán
            if ($invoice->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này không thể thanh toán. Trạng thái hiện tại: ' . $invoice->status,
                ], 400);
            }

            // Cập nhật trạng thái invoice
            $invoice->update([
                'status' => 'paid',
            ]);

            // Tạo payment record nếu có Payment model
            if (class_exists(\App\Models\Payment::class)) {
                \App\Models\Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                    'payment_method' => $request->payment_method,
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
            }

            // Cập nhật booking order
            if ($invoice->bookingOrder) {
                $booking = $invoice->bookingOrder;
                $booking->update([
                    'payment_method' => $request->payment_method,
                    'status' => 'completed', // Hoàn thành sau khi thanh toán
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thanh toán thành công!',
                'data' => [
                    'invoice' => $invoice->fresh(['bookingOrder']),
                    'booking' => $invoice->bookingOrder ? (new \App\Http\Resources\Admin\BookingOrderResource($invoice->bookingOrder->fresh(['guest', 'details.room'])))->toArray($request) : null,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('InvoiceController@payInvoice failed', [
                'invoice_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thanh toán.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * User: Lấy danh sách invoices của user hiện tại
     */
    public function getUserInvoices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $perPage = (int) ($request->get('per_page', 15));
            $invoices = Invoice::with([
                'bookingOrder.guest', 
                'splitInvoices.bookingDetail.room',
                'splitFrom.bookingDetail.room'
            ])
                ->whereHas('bookingOrder', function ($q) use ($user) {
                    $q->where('guest_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $invoices->items(),
                'meta' => [
                    'pagination' => [
                        'page' => $invoices->currentPage(),
                        'per_page' => $invoices->perPage(),
                        'total' => $invoices->total(),
                        'last_page' => $invoices->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@getUserInvoices failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách hóa đơn.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * User: Lấy chi tiết invoice của user hiện tại
     */
    public function getUserInvoice(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Luôn load bookingOrder + invoiceItems để có đủ dữ liệu
            $invoice = Invoice::with(['bookingOrder', 'bookingOrder.guest', 'invoiceItems.damageImages'])
                ->whereHas('bookingOrder', function ($q) use ($user) {
                    $q->where('guest_id', $user->id);
                })
                ->findOrFail($id);

            // ĐẢM BẢO: luôn có 1 dòng "Tiền cọc đã thanh toán" nếu booking đã trả cọc
            $booking = $invoice->bookingOrder;
            if ($booking) {
                // Ưu tiên lấy từ booking.paid_amount; nếu chưa có thì lấy tổng từ payments của invoice
                $paidAmountFromBooking = (float) ($booking->paid_amount ?? 0);
                $paidAmountFromPayments = (float) $invoice->payments()
                    ->whereIn('status', ['success', 'paid'])
                    ->sum('amount');

                $effectivePaidAmount = $paidAmountFromBooking > 0
                    ? $paidAmountFromBooking
                    : $paidAmountFromPayments;

                $paidAmount = (int) round($effectivePaidAmount);

                if ($paidAmount > 0) {

                    // Kiểm tra xem đã có invoice item kiểu deposit chưa
                    $existingDepositItem = $invoice->invoiceItems()
                        ->where('item_type', 'deposit')
                        ->first();

                    if (!$existingDepositItem) {
                        // Nếu database enum chưa có 'deposit' thì tránh gây lỗi
                        try {
                            $depositItem = \App\Models\InvoiceItem::create([
                                'invoice_id'   => $invoice->id,
                                'description'  => 'Tiền cọc đã thanh toán',
                                'quantity'     => 1,
                                'unit_price'   => -$paidAmount,
                                'total_line'   => -$paidAmount,
                                'item_type'    => 'deposit',
                            ]);

                            // Gắn thêm vào collection hiện tại để trả về cho frontend
                            $invoice->setRelation(
                                'invoiceItems',
                                $invoice->invoiceItems->push($depositItem)
                            );
                        } catch (\Throwable $e) {
                            Log::error('InvoiceController@getUserInvoice: failed to create deposit invoice item', [
                                'invoice_id' => $invoice->id,
                                'booking_id' => $booking->id,
                                'paid_amount' => $paidAmount,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Cập nhật lại total_amount (chỉ tính các item chưa thanh toán)
                    try {
                        $invoice->recalculateTotalAmount();
                    } catch (\Throwable $e) {
                        Log::error('InvoiceController@getUserInvoice: failed to recalc invoice total_amount', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $invoice->fresh(['bookingOrder.guest', 'invoiceItems.damageImages']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('InvoiceController@getUserInvoice failed', [
                'invoice_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy chi tiết hóa đơn.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Thêm service vào invoice
     */
    public function addService(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'service_id' => 'required|exists:services,id',
                'quantity' => 'required|integer|min:1',
                'description' => 'nullable|string|max:500',
                'is_paid' => 'nullable|boolean', // Dịch vụ đã thanh toán hay chưa
                'booking_detail_id' => 'nullable|integer|exists:booking_details,id', // ID phòng để hiển thị trong description
            ]);

            DB::beginTransaction();

            $invoice = Invoice::with('invoiceItems.damageImages')->findOrFail($id);

            // Kiểm tra invoice chưa được thanh toán (chỉ khi dịch vụ chưa thanh toán)
            $isPaid = $request->boolean('is_paid', false);
            if (!$isPaid && $invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể thêm dịch vụ vào hóa đơn đã thanh toán.',
                ], 400);
            }

            // Lấy thông tin service
            $service = \App\Models\Service::findOrFail($request->service_id);
            $unitPrice = $service->price ?? 0;
            $quantity = $request->quantity;
            $totalLine = $unitPrice * $quantity;

            // Tạo description với thông tin phòng nếu có
            $description = $request->description;
            if (!$description && $request->has('booking_detail_id')) {
                $bookingDetail = \App\Models\BookingDetail::with('room')->find($request->booking_detail_id);
                $roomName = $bookingDetail && $bookingDetail->room ? $bookingDetail->room->name : 'N/A';
                $description = "Dịch vụ: {$service->name} - Phòng {$roomName} (SL: {$quantity})";
                if ($isPaid) {
                    $description .= " [Đã thanh toán]";
                }
            } elseif (!$description) {
                $description = "Dịch vụ: {$service->name}";
                if ($isPaid) {
                    $description .= " [Đã thanh toán]";
                }
            } elseif ($isPaid && !str_contains($description, '[Đã thanh toán]')) {
                $description .= " [Đã thanh toán]";
            }

            // Lấy booking_detail_id từ request hoặc từ invoice
            $bookingDetailId = $request->input('booking_detail_id');
            if (!$bookingDetailId && $invoice->booking_order_id) {
                // Nếu không có booking_detail_id, tìm booking detail đầu tiên của booking này
                $bookingDetail = \App\Models\BookingDetail::where('booking_order_id', $invoice->booking_order_id)->first();
                if ($bookingDetail) {
                    $bookingDetailId = $bookingDetail->id;
                }
            }

            // Tạo invoice item với booking_detail_id
            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'booking_detail_id' => $bookingDetailId, // Đảm bảo gán booking_detail_id
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_line' => $totalLine,
                'item_type' => 'service_charge',
            ]);

            // Tạo BookingService record để tracking dịch vụ đã sử dụng
            if ($bookingDetailId) {
                $bookingDetail = \App\Models\BookingDetail::find($bookingDetailId);
                if ($bookingDetail) {
                    // Kiểm tra xem đã có BookingService cho dịch vụ này chưa
                    $existingBookingService = \App\Models\BookingService::where('booking_details_id', $bookingDetailId)
                        ->where('service_id', $service->id)
                        ->where('status', 'in_use')
                        ->first();
                    
                    if (!$existingBookingService) {
                        \App\Models\BookingService::create([
                            'booking_details_id' => $bookingDetailId,
                            'service_id' => $service->id,
                            'quantity' => $quantity,
                            'price_at_booking' => $unitPrice,
                            'actual_price' => $unitPrice,
                            'actual_quantity' => $quantity,
                            'status' => $isPaid ? 'completed' : 'in_use',
                            'started_at' => now(),
                            'completed_at' => $isPaid ? now() : null,
                        ]);
                    }
                }
            }
            
            // Cập nhật tổng tiền invoice (recalculateTotalAmount sẽ tự động bỏ qua items có "[Đã thanh toán]")
            $invoice->recalculateTotalAmount();
            
            // Refresh invoice để có total_amount mới nhất
            $invoice->refresh();

            DB::commit();
            
            // Load lại invoice với items để trả về đúng total_amount
            $invoice->load('invoiceItems.damageImages');
            
            Log::info('InvoiceController@addService: Service added', [
                'invoice_id' => $invoice->id,
                'service_id' => $request->service_id,
                'is_paid' => $isPaid,
                'total_line' => $totalLine,
                'invoice_total_amount' => $invoice->total_amount,
                'description' => $description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm dịch vụ vào hóa đơn.',
                'data' => [
                    'item' => $item,
                    'invoice' => $invoice,
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
            Log::error('InvoiceController@addService failed', [
                'invoice_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm dịch vụ.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Thêm thiệt hại vật tư vào invoice
     */
    public function addDamage(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'supply_id' => 'required|exists:supplies,id',
                'quantity' => 'required|integer|min:1',
                'description' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:1000',
                'booking_detail_id' => 'nullable|integer|exists:booking_details,id',
                'is_paid' => 'nullable|boolean',
                'damage_images' => 'nullable|array',
                'damage_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB per image
            ]);

            DB::beginTransaction();

            $invoice = Invoice::with('invoiceItems.damageImages')->findOrFail($id);

            // Kiểm tra invoice chưa được thanh toán
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể thêm thiệt hại vào hóa đơn đã thanh toán.',
                ], 400);
            }

            // Lấy booking_detail_id từ request hoặc từ invoice
            $bookingDetailId = $request->input('booking_detail_id');
            if (!$bookingDetailId && $invoice->booking_order_id) {
                // Nếu không có booking_detail_id, tìm booking detail đầu tiên của booking này
                $bookingDetail = \App\Models\BookingDetail::where('booking_order_id', $invoice->booking_order_id)->first();
                if ($bookingDetail) {
                    $bookingDetailId = $bookingDetail->id;
                }
            }

            // Lấy thông tin supply
            $supply = \App\Models\Supply::findOrFail($request->supply_id);
            $unitPrice = $supply->unit_price ?? 0;
            $quantity = $request->quantity;
            $totalLine = $unitPrice * $quantity;

            // Kiểm tra dịch vụ đã thanh toán hay chưa
            $isPaid = $request->boolean('is_paid', false);
            
            // Tạo description với thông tin phòng nếu có
            $description = $request->description;
            if (!$description && $bookingDetailId) {
                $bookingDetail = \App\Models\BookingDetail::with('room')->find($bookingDetailId);
                $roomName = $bookingDetail && $bookingDetail->room ? $bookingDetail->room->name : 'N/A';
                $description = "Thiệt hại vật tư: {$supply->name} - Phòng {$roomName}";
                if ($request->notes) {
                    $description .= " ({$request->notes})";
                }
                if ($isPaid) {
                    $description .= " [Đã thanh toán]";
                }
            } elseif (!$description) {
                $description = "Thiệt hại: {$supply->name}";
                if ($request->notes) {
                    $description .= " ({$request->notes})";
                }
                if ($isPaid) {
                    $description .= " [Đã thanh toán]";
                }
            } elseif ($isPaid && !str_contains($description, '[Đã thanh toán]')) {
                $description .= " [Đã thanh toán]";
            }

            // Tạo invoice item với booking_detail_id
            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'booking_detail_id' => $bookingDetailId, // Đảm bảo gán booking_detail_id
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_line' => $totalLine,
                'item_type' => 'damage_fee',
            ]);

            // Xử lý upload ảnh minh chứng thiệt hại
            // Khi gửi FormData với damage_images[], Laravel sẽ nhận được như một array
            $allFiles = $request->allFiles();
            if (isset($allFiles['damage_images']) && is_array($allFiles['damage_images'])) {
                $files = $allFiles['damage_images'];
                
                foreach ($files as $fileIndex => $file) {
                    if ($file && $file->isValid()) {
                        try {
                            $directory = 'damage_images';
                            $extension = $file->getClientOriginalExtension();
                            $filename = \Illuminate\Support\Str::uuid() . '.' . $extension;
                            $path = Storage::disk('s3')->putFileAs($directory, $file, $filename);
                            
                            if ($path) {
                                $url = Storage::disk('s3')->url($path);
                                
                                // Tạo DamageImage record
                                \App\Models\DamageImage::create([
                                    'invoice_item_id' => $item->id,
                                    'image_url' => $url,
                                    'order' => $fileIndex,
                                ]);
                                
                                Log::info('Damage image uploaded successfully', [
                                    'invoice_item_id' => $item->id,
                                    'supply_id' => $supply->id,
                                    'image_url' => $url,
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to upload damage image', [
                                'invoice_item_id' => $item->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // Không throw error, chỉ log để không làm gián đoạn việc tạo damage item
                        }
                    }
                }
            }

            // Cập nhật tổng tiền invoice
            $invoice->recalculateTotalAmount();

            // Trừ stock của supply nếu cần
            if ($supply->current_stock >= $quantity) {
                $supply->decrement('current_stock', $quantity);
                
                // Tạo supply log
                if (class_exists(\App\Models\SupplyLog::class)) {
                    \App\Models\SupplyLog::create([
                        'supply_id' => $supply->id,
                        'user_id' => $request->user()->id ?? null,
                        'change_quantity' => -$quantity,
                        'reason' => "Thiệt hại từ hóa đơn #{$invoice->id}: " . ($request->notes ?? ''),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm thiệt hại vào hóa đơn.',
                'data' => [
                    'item' => $item,
                    'invoice' => $invoice->fresh(['invoiceItems.damageImages']),
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
            Log::error('InvoiceController@addDamage failed', [
                'invoice_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm thiệt hại: ' . $e->getMessage(),
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Xóa item khỏi invoice
     */
    public function removeItem(Request $request, string $id, string $itemId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $invoice = Invoice::with('invoiceItems.damageImages')->findOrFail($id);

            // Kiểm tra invoice chưa được thanh toán
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa item khỏi hóa đơn đã thanh toán.',
                ], 400);
            }

            $item = InvoiceItem::where('invoice_id', $invoice->id)
                ->findOrFail($itemId);

            $item->delete();

            // Cập nhật tổng tiền invoice (chỉ tính các item chưa thanh toán)
            $invoice->recalculateTotalAmount();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa item khỏi hóa đơn.',
                'data' => $invoice->fresh(['invoiceItems.damageImages']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('InvoiceController@removeItem failed', [
                'invoice_id' => $id,
                'item_id' => $itemId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa item.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update invoice status
     *
     * @OA\Patch(
     *     path="/api/invoices/{id}/status",
     *     operationId="updateInvoiceStatus",
     *     tags={"Invoices"},
     *     summary="Cập nhật trạng thái hóa đơn",
     *     description="Cập nhật trạng thái của hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "paid", "overdue", "cancelled"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công"
     *     )
     * )
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,paid,overdue,cancelled'
        ]);

        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Trạng thái hóa đơn đã được cập nhật',
            'data' => $invoice
        ]);
    }

    /**
     * Get invoice statistics
     *
     * @OA\Get(
     *     path="/api/invoices/statistics/overview",
     *     operationId="getInvoiceStatistics",
     *     tags={"Invoices"},
     *     summary="Thống kê hóa đơn",
     *     description="Lấy thống kê về hóa đơn (số lượng, doanh thu)",
     *     @OA\Response(
     *         response=200,
     *         description="Thống kê hóa đơn",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_invoices", type="integer"),
     *                 @OA\Property(property="paid_invoices", type="integer"),
     *                 @OA\Property(property="unpaid_invoices", type="integer"),
     *                 @OA\Property(property="overdue_invoices", type="integer"),
     *                 @OA\Property(property="total_revenue", type="number"),
     *                 @OA\Property(property="pending_revenue", type="number"),
     *                 @OA\Property(property="overdue_revenue", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        // Đếm invoices dựa trên status trong database
        $totalInvoices = Invoice::count();
        
        // Đếm invoices đã hủy: 
        // 1. Invoice có status = 'cancelled' HOẶC
        // 2. Invoice có booking order đã cancelled
        $cancelledInvoices = Invoice::where(function($query) {
            $query->where('status', 'cancelled')
                ->orWhereHas('bookingOrder', function($q) {
                    $q->where('status', 'cancelled');
                });
        })->count();
        
        // Đếm invoices đã thanh toán dựa trên payments thực tế
        // Một invoice được coi là "paid" nếu:
        // 1. status = 'paid' HOẶC
        // 2. tổng payments thành công >= total_amount
        // VÀ không phải cancelled (cả invoice và booking order)
        $paidInvoices = Invoice::where(function($query) {
            $query->where('status', 'paid')
                ->orWhereRaw('(
                    SELECT COALESCE(SUM(amount), 0)
                    FROM payments
                    WHERE payments.invoice_id = invoices.id
                    AND payments.status IN ("success", "paid")
                ) >= invoices.total_amount');
        })
        ->where('status', '!=', 'cancelled')
        ->whereDoesntHave('bookingOrder', function($q) {
            $q->where('status', 'cancelled');
        })
        ->count();
        
        // Đếm invoices chưa thanh toán (không phải cancelled và chưa paid)
        $unpaidInvoices = Invoice::where('status', '!=', 'cancelled')
            ->whereDoesntHave('bookingOrder', function($q) {
                $q->where('status', 'cancelled');
            })
            ->where(function($query) {
                $query->where('status', '!=', 'paid')
                    ->whereRaw('(
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments
                        WHERE payments.invoice_id = invoices.id
                        AND payments.status IN ("success", "paid")
                    ) < invoices.total_amount');
            })
            ->count();
        
        // Đếm overdue invoices (status = overdue)
        $overdueInvoices = Invoice::where('status', 'overdue')
            ->whereDoesntHave('bookingOrder', function($q) {
                $q->where('status', 'cancelled');
            })
            ->count();
        
        // Tính tổng doanh thu từ payments thực tế
        // Doanh thu = tổng payments thành công
        // Loại trừ invoices/booking orders đã cancelled
        $totalRevenue = \App\Models\Payment::whereIn('status', ['success', 'paid'])
            ->whereHas('invoice', function($query) {
                $query->where('status', '!=', 'cancelled')
                    ->whereDoesntHave('bookingOrder', function($q) {
                        $q->where('status', 'cancelled');
                    });
            })
            ->sum('amount');
        
        // Trừ refunds từ booking orders (nếu có)
        // refund_amount nằm trong booking_orders, không phải invoices
        $totalRefunds = BookingOrder::where('status', '!=', 'cancelled')
            ->whereNotNull('refund_amount')
            ->whereHas('invoices', function($query) {
                $query->where(function($q) {
                    $q->where('status', 'paid')
                        ->orWhereRaw('(
                            SELECT COALESCE(SUM(amount), 0)
                            FROM payments
                            WHERE payments.invoice_id = invoices.id
                            AND payments.status IN ("success", "paid")
                        ) >= invoices.total_amount');
                });
            })
            ->sum('refund_amount');
        
        $netRevenue = max(0, $totalRevenue - $totalRefunds);
        
        // Tính pending revenue (tổng tiền chưa thanh toán)
        $pendingRevenue = Invoice::where('status', '!=', 'cancelled')
            ->whereDoesntHave('bookingOrder', function($q) {
                $q->where('status', 'cancelled');
            })
            ->where(function($query) {
                $query->where('status', '!=', 'paid')
                    ->whereRaw('(
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments
                        WHERE payments.invoice_id = invoices.id
                        AND payments.status IN ("success", "paid")
                    ) < invoices.total_amount');
            })
            ->sum('total_amount');
        
        // Tính overdue revenue
        $overdueRevenue = Invoice::where('status', 'overdue')
            ->whereDoesntHave('bookingOrder', function($q) {
                $q->where('status', 'cancelled');
            })
            ->sum('total_amount');
        
        $stats = [
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'unpaid_invoices' => $unpaidInvoices,
            'overdue_invoices' => $overdueInvoices,
            'cancelled_invoices' => $cancelledInvoices,
            'total_revenue' => $netRevenue,
            'pending_revenue' => $pendingRevenue,
            'overdue_revenue' => $overdueRevenue
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Create a new invoice directly
     *
     * @OA\Post(
     *     path="/api/invoices",
     *     operationId="storeInvoice",
     *     tags={"Invoices"},
     *     summary="Tạo hóa đơn mới",
     *     description="Tạo hóa đơn mới trực tiếp (không từ đơn đặt phòng)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_order_id", "issue_date", "due_date", "total_amount"},
     *             @OA\Property(property="booking_order_id", type="integer", example=1),
     *             @OA\Property(property="issue_date", type="string", format="date", example="2025-11-03"),
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-11-10"),
     *             @OA\Property(property="total_amount", type="number", format="float", example=5000000),
     *             @OA\Property(property="status", type="string", enum={"pending", "paid", "overdue", "cancelled"}),
     *             @OA\Property(property="calculation_method", type="string", enum={"automatic", "manual"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Hóa đơn được tạo thành công"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Lỗi xác thực"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'booking_order_id' => 'required|exists:booking_orders,id',
                'issue_date' => 'required|date',
                'due_date' => 'required|date|after:issue_date',
                'total_amount' => 'required|numeric|min:0',
                'status' => 'nullable|in:pending,paid,overdue,cancelled',
                'calculation_method' => 'nullable|in:automatic,manual',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Kiểm tra xem đơn đặt phòng đã có hóa đơn chưa
            if (Invoice::where('booking_order_id', $request->booking_order_id)->exists()) {
                throw new \Exception('Đơn đặt phòng này đã có hóa đơn.');
            }

            // Lấy thông tin đơn đặt phòng
            $bookingOrder = BookingOrder::findOrFail($request->booking_order_id);

            // Kiểm tra ngày hóa đơn so với ngày đặt phòng
            if (strtotime($request->issue_date) < strtotime($bookingOrder->check_in_date)) {
                throw new \Exception('Ngày hóa đơn không thể trước ngày nhận phòng.');
            }

            $invoice = Invoice::create([
                'booking_order_id' => $request->booking_order_id,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'total_amount' => $request->total_amount,
                'status' => $request->status ?? 'pending',
                // 'calculation_method' => $request->calculation_method ?? 'automatic', // Cột không tồn tại trong database
                'notes' => $request->notes ?? null,
                'discount_amount' => 0,
                'refund_amount' => 0,
            ]);

            DB::commit();

            // Load relationships và trả về response
            $invoice->load([
                'bookingOrder.guest',
                'bookingOrder.bookingDetails.room',
                'invoiceItems'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được tạo thành công',
                'data' => $invoice
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xác thực dữ liệu',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo hóa đơn: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update an existing invoice
     *
     * @OA\Put(
     *     path="/api/invoices/{id}",
     *     operationId="updateInvoice",
     *     tags={"Invoices"},
     *     summary="Cập nhật hóa đơn",
     *     description="Cập nhật thông tin hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"pending", "paid", "overdue", "cancelled"}),
     *             @OA\Property(property="calculation_method", type="string", enum={"automatic", "manual"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công"
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'due_date' => 'nullable|date',
            'status' => 'in:pending,paid,overdue,cancelled',
            'calculation_method' => 'in:automatic,manual',
        ]);

        try {
            $invoice = Invoice::findOrFail($id);

            $invoice->update($request->only([
                'due_date',
                'status',
                'calculation_method'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được cập nhật',
                'data' => $invoice->load(['bookingOrder', 'invoiceItems.damageImages'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an invoice
     *
     * @OA\Delete(
     *     path="/api/invoices/{id}",
     *     operationId="deleteInvoice",
     *     tags={"Invoices"},
     *     summary="Xóa hóa đơn",
     *     description="Xóa một hóa đơn (chỉ pending hoặc cancelled)",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Không thể xóa hóa đơn này"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($id);

            // Only allow deletion of pending or cancelled invoices
            if (!in_array($invoice->status, ['pending', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xóa hóa đơn chưa thanh toán'
                ], 400);
            }

            // Delete related discounts
            $invoice->discounts()->delete();

            // Delete related invoice items
            $invoice->invoiceItems()->delete();

            // Delete the invoice
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được xóa thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calculation configuration
     *
     * @OA\Get(
     *     path="/api/invoices/config/calculation",
     *     operationId="getCalculationConfig",
     *     tags={"Invoices"},
     *     summary="Lấy cấu hình tính hóa đơn",
     *     description="Lấy cấu hình tính toán hóa đơn (thuế, phí...).",
     *     @OA\Response(
     *         response=200,
     *         description="Cấu hình tính toán"
     *     )
     * )
     */
    public function getCalculationConfig(): JsonResponse
    {
        $config = InvoiceConfig::getConfig();

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Set calculation configuration
     *
     * @OA\Post(
     *     path="/api/invoices/config/calculation",
     *     operationId="setCalculationConfig",
     *     tags={"Invoices"},
     *     summary="Cập nhật cấu hình tính hóa đơn",
     *     description="Cập nhật cấu hình tính toán hóa đơn",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="calculation_method", type="string", enum={"automatic", "manual"}),
     *             @OA\Property(property="auto_calculate", type="boolean"),
     *             @OA\Property(property="tax_rate", type="number", example=10),
     *             @OA\Property(property="service_charge_rate", type="number", example=5),
     *             @OA\Property(property="late_fee_percent", type="number", example=2),
     *             @OA\Property(property="late_fee_per_day", type="number", example=50000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công"
     *     )
     * )
     */
    public function setCalculationConfig(Request $request): JsonResponse
    {
        $request->validate([
            'calculation_method' => 'in:automatic,manual',
            'auto_calculate' => 'boolean',
            'tax_rate' => 'numeric|min:0|max:100',
            'service_charge_rate' => 'numeric|min:0|max:100',
            'late_fee_percent' => 'numeric|min:0|max:100',
            'late_fee_per_day' => 'numeric|min:0',
        ]);

        try {
            $config = InvoiceConfig::first() ?? new InvoiceConfig();
            $config->update($request->only([
                'calculation_method',
                'auto_calculate',
                'tax_rate',
                'service_charge_rate',
                'late_fee_percent',
                'late_fee_per_day'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Cấu hình tính hóa đơn đã được cập nhật',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get refund policy configuration
     *
     * @OA\Get(
     *     path="/api/invoices/config/refund-policies",
     *     operationId="getRefundPolicyConfig",
     *     tags={"Invoices"},
     *     summary="Lấy các chính sách hoàn tiền",
     *     description="Lấy danh sách các chính sách hoàn tiền",
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách chính sách hoàn tiền"
     *     )
     * )
     */
    public function getRefundPolicyConfig(): JsonResponse
    {
        $policies = RefundPolicy::active()->get();

        return response()->json([
            'success' => true,
            'data' => $policies
        ]);
    }

    /**
     * Create refund policy configuration
     *
     * @OA\Post(
     *     path="/api/invoices/config/refund-policies",
     *     operationId="createRefundPolicy",
     *     tags={"Invoices"},
     *     summary="Tạo chính sách hoàn tiền",
     *     description="Tạo chính sách hoàn tiền mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "refund_percent", "days_before_checkin"},
     *             @OA\Property(property="name", type="string", example="Hoàn 100%"),
     *             @OA\Property(property="refund_percent", type="number", example=100),
     *             @OA\Property(property="days_before_checkin", type="integer", example=7),
     *             @OA\Property(property="penalty_percent", type="number", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Chính sách được tạo thành công"
     *     )
     * )
     */
    public function createRefundPolicy(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'refund_percent' => 'required|numeric|min:0|max:100',
            'days_before_checkin' => 'required|integer|min:0',
            'penalty_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $policy = RefundPolicy::create([
                'name' => $request->name,
                'refund_percent' => $request->refund_percent,
                'days_before_checkin' => $request->days_before_checkin,
                'penalty_percent' => $request->penalty_percent ?? 0,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Chính sách hoàn tiền đã được tạo',
                'data' => $policy
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update refund policy configuration
     *
     * @OA\Put(
     *     path="/api/invoices/config/refund-policies/{policyId}",
     *     operationId="updateRefundPolicy",
     *     tags={"Invoices"},
     *     summary="Cập nhật chính sách hoàn tiền",
     *     description="Cập nhật thông tin chính sách hoàn tiền",
     *     @OA\Parameter(
     *         name="policyId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Hoàn 100%"),
     *             @OA\Property(property="refund_percent", type="number", example=100),
     *             @OA\Property(property="days_before_checkin", type="integer", example=7),
     *             @OA\Property(property="penalty_percent", type="number", example=0),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chính sách được cập nhật thành công"
     *     )
     * )
     */
    public function updateRefundPolicy(Request $request, $policyId): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'refund_percent' => 'nullable|numeric|min:0|max:100',
            'days_before_checkin' => 'nullable|integer|min:0',
            'penalty_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $policy = RefundPolicy::findOrFail($policyId);
            $policy->update($request->only(['name', 'refund_percent', 'days_before_checkin', 'penalty_percent', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Chính sách hoàn tiền đã được cập nhật',
                'data' => $policy
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply discount to invoice
     *
     * @OA\Post(
     *     path="/api/invoices/{id}/discounts",
     *     operationId="applyDiscount",
     *     tags={"Invoices"},
     *     summary="Áp dụng giảm giá cho hóa đơn",
     *     description="Áp dụng giảm giá (phần trăm hoặc số tiền cố định) cho hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"discount_type", "discount_value"},
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed_amount"}),
     *             @OA\Property(property="discount_value", type="number", example=10),
     *             @OA\Property(property="reason", type="string", example="Khuyến mãi đặc biệt")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Giảm giá được áp dụng"
     *     )
     * )
     */
    public function applyDiscount(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $invoice = Invoice::findOrFail($id);

            // Calculate discount amount
            if ($request->discount_type === 'percentage') {
                $discountAmount = ($invoice->total_amount * $request->discount_value) / 100;
            } else {
                $discountAmount = $request->discount_value;
            }

            // Create discount record
            $discount = InvoiceDiscount::create([
                'invoice_id' => $invoice->id,
                'discount_type' => $request->discount_type,
                'discount_value' => $request->discount_value,
                'discount_amount' => $discountAmount,
                'reason' => $request->reason,
                'approved_at' => now(),
            ]);

            // Update invoice discount amount
            $invoice->increment('discount_amount', $discountAmount);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Giảm giá đã được áp dụng',
                'data' => [
                    'discount' => $discount,
                    'invoice' => $invoice->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove discount from invoice
     *
     * @OA\Delete(
     *     path="/api/invoices/{id}/discounts/{discountId}",
     *     operationId="removeDiscount",
     *     tags={"Invoices"},
     *     summary="Xóa giảm giá",
     *     description="Xóa giảm giá khỏi hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="discountId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Giảm giá được xóa"
     *     )
     * )
     */
    public function removeDiscount(string $id, string $discountId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $invoice = Invoice::findOrFail($id);
            $discount = InvoiceDiscount::findOrFail($discountId);

            // Verify discount belongs to invoice
            if ($discount->invoice_id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Giảm giá không thuộc hóa đơn này'
                ], 400);
            }

            // Update invoice
            $invoice->decrement('discount_amount', (float) $discount->discount_amount);

            // Delete discount
            $discount->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Giảm giá đã được xóa',
                'data' => $invoice->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply refund policy to invoice
     *
     * @OA\Post(
     *     path="/api/invoices/{id}/apply-refund-policy",
     *     operationId="applyRefundPolicy",
     *     tags={"Invoices"},
     *     summary="Áp dụng chính sách hoàn tiền",
     *     description="Áp dụng chính sách hoàn tiền cho hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refund_policy_id"},
     *             @OA\Property(property="refund_policy_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chính sách hoàn tiền được áp dụng"
     *     )
     * )
     */
    public function applyRefundPolicy(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'refund_policy_id' => 'required|exists:refund_policies,id',
        ]);

        try {
            DB::beginTransaction();

            $invoice = Invoice::findOrFail($id);
            $policy = RefundPolicy::findOrFail($request->refund_policy_id);

            // Calculate refund amount
            $refundAmount = ($invoice->total_amount * $policy->refund_percent) / 100;

            // Apply penalty if applicable
            if ($policy->penalty_percent > 0) {
                $penalty = ($refundAmount * $policy->penalty_percent) / 100;
                $refundAmount -= $penalty;
            }

            // Update invoice
            $invoice->update([
                'refund_policy_id' => $policy->id,
                'refund_amount' => $refundAmount,
                'refund_date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Chính sách hoàn tiền đã được áp dụng',
                'data' => $invoice->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Split an invoice into multiple invoices
     *
     * @OA\Post(
     *     path="/api/invoices/{id}/split",
     *     operationId="splitInvoice",
     *     tags={"Invoices"},
     *     summary="Tách hóa đơn",
     *     description="Tách hóa đơn thành nhiều hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items_for_new_invoice"},
     *             @OA\Property(property="items_for_new_invoice", type="array", items={"type": "integer"}, example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hóa đơn được tách thành công"
     *     )
     * )
     */
    public function splitInvoice(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'items_for_new_invoice' => 'required|array|min:1',
            'items_for_new_invoice.*' => 'integer|exists:invoice_items,id',
        ]);

        try {
            DB::beginTransaction();

            $originalInvoice = Invoice::with('invoiceItems.damageImages')->findOrFail($id);

            // Validate all items belong to this invoice
            $itemIds = $request->items_for_new_invoice;
            $items = InvoiceItem::whereIn('id', $itemIds)
                ->where('invoice_id', $id)
                ->get();

            if ($items->count() != count($itemIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Một số hạng mục không thuộc hóa đơn này'
                ], 400);
            }

            // Calculate amounts for new invoice
            $newInvoiceAmount = $items->sum('total_line');
            $remainingAmount = $originalInvoice->total_amount - $newInvoiceAmount;

            // Create new invoice
            $newInvoiceData = [
                'booking_order_id' => $originalInvoice->booking_order_id,
                'issue_date' => $originalInvoice->issue_date,
                'due_date' => $originalInvoice->due_date,
                'total_amount' => $newInvoiceAmount,
                'status' => 'pending',
            ];
            
            // Chỉ thêm calculation_method nếu cột tồn tại (không tồn tại trong database hiện tại)
            // if (isset($originalInvoice->calculation_method)) {
            //     $newInvoiceData['calculation_method'] = $originalInvoice->calculation_method;
            // }
            
            $newInvoice = Invoice::create($newInvoiceData);

            // Move items to new invoice
            InvoiceItem::whereIn('id', $itemIds)->update(['invoice_id' => $newInvoice->id]);

            // Update original invoice
            $originalInvoice->update(['total_amount' => $remainingAmount]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được tách thành công',
                'data' => [
                    'original_invoice' => $originalInvoice->fresh(),
                    'new_invoice' => $newInvoice->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tách hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tách hóa đơn theo phòng (mỗi phòng một hóa đơn riêng)
     * Phân bổ voucher và tiền cọc theo tỷ lệ giá phòng
     *
     * @OA\Post(
     *     path="/api/invoices/{id}/split-by-rooms",
     *     operationId="splitInvoiceByRooms",
     *     tags={"Invoices"},
     *     summary="Tách hóa đơn theo phòng",
     *     description="Tách hóa đơn thành nhiều hóa đơn, mỗi phòng một hóa đơn riêng. Tự động phân bổ voucher và tiền cọc theo tỷ lệ giá phòng.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hóa đơn được tách thành công"
     *     )
     * )
     */
    public function splitInvoiceByRooms(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $originalInvoice = Invoice::with([
                'bookingOrder.details.room',
                'bookingOrder.details.bookingServices.service',
                'invoiceItems.damageImages',
                'bookingOrder'
            ])->findOrFail($id);

            $bookingOrder = $originalInvoice->bookingOrder;

            // Kiểm tra xem có nhiều hơn 1 phòng không
            $bookingDetails = $bookingOrder->details;
            if ($bookingDetails->count() <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này chỉ có 1 phòng, không thể tách'
                ], 400);
            }

            // Kiểm tra xem đã tách chưa
            $existingSplit = SplitInvoice::where('original_invoice_id', $id)->exists();
            if ($existingSplit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này đã được tách rồi'
                ], 400);
            }

            // Tính tổng giá phòng (trước voucher) để phân bổ voucher
            $totalRoomPrice = 0;
            $roomPrices = [];
            foreach ($bookingDetails as $detail) {
                // Calculate nights - đảm bảo tính chính xác số đêm
                $checkIn = \Carbon\Carbon::parse($detail->check_in_date)->startOfDay();
                $checkOut = \Carbon\Carbon::parse($detail->check_out_date)->startOfDay();
                // Tính số đêm: checkOut - checkIn (luôn dương)
                $nights = max(1, abs($checkOut->diffInDays($checkIn)));
                $roomPrice = ($detail->room->price_per_night ?? 0) * $nights;
                $roomPrices[$detail->id] = $roomPrice;
                $totalRoomPrice += $roomPrice;
            }

            // Lấy thông tin voucher và tiền cọc
            $voucherAmount = $bookingOrder->discount_amount ?? 0;
            $depositAmount = $bookingOrder->deposit_amount ?? 0;
            $paidAmount = $bookingOrder->paid_amount ?? 0;

            // Tạo hóa đơn mới cho từng phòng
            // LƯU Ý: Hóa đơn gốc sẽ được GIỮ NGUYÊN, không xóa items, không cancelled
            $splitInvoices = [];

            foreach ($bookingDetails as $detail) {
                $roomPrice = $roomPrices[$detail->id] ?? 0;
                $roomName = $detail->room->name ?? '';

                // Phân bổ voucher theo tỷ lệ giá phòng (theo info.text)
                $voucherDiscount = $totalRoomPrice > 0 
                    ? round(($roomPrice / $totalRoomPrice) * $voucherAmount, 2)
                    : 0;

                // Tính tiền cọc theo từng phòng dựa trên giá phòng và policy
                // Policy: < 1,000,000 VND = 100% cọc, >= 1,000,000 VND = 50% cọc
                if ($roomPrice < 1000000) {
                    $roomDeposit = $roomPrice; // 100% giá phòng
                } else {
                    $roomDeposit = $roomPrice * 0.5; // 50% giá phòng
                }
                $roomDeposit = round($roomDeposit, 2);

                // Tạo hóa đơn mới cho phòng này (vẫn có booking_order_id giống booking gốc)
                $newInvoice = Invoice::create([
                    'booking_order_id' => $bookingOrder->id,
                    'issue_date' => $originalInvoice->issue_date,
                    'due_date' => $originalInvoice->due_date,
                    'total_amount' => 0, // Sẽ tính lại sau
                    'status' => $originalInvoice->status,
                    'discount_amount' => $voucherDiscount,
                ]);

                // 1. Tạo room charge item
                // Calculate nights - đảm bảo tính chính xác số đêm
                $checkIn = \Carbon\Carbon::parse($detail->check_in_date)->startOfDay();
                $checkOut = \Carbon\Carbon::parse($detail->check_out_date)->startOfDay();
                // Tính số đêm: checkOut - checkIn (luôn dương)
                $nights = max(1, abs($checkOut->diffInDays($checkIn)));
                
                InvoiceItem::create([
                    'invoice_id' => $newInvoice->id,
                    'booking_detail_id' => $detail->id,
                    'description' => "Phòng {$roomName} - {$nights} đêm",
                    'quantity' => 1,
                    'unit_price' => $detail->room->price_per_night ?? 0,
                    'total_line' => $roomPrice,
                    'item_type' => 'room_charge',
                ]);

                // 2. Tạo service charge items từ bookingServices của phòng này
                $servicePrice = 0;
                foreach ($detail->bookingServices as $bookingService) {
                    $service = $bookingService->service;
                    $actualPrice = $bookingService->actual_price ?? $bookingService->price_at_booking ?? $service->price ?? 0;
                    $actualQuantity = $bookingService->actual_quantity ?? $bookingService->quantity ?? 1;
                    $serviceItemPrice = $actualPrice * $actualQuantity;
                    $servicePrice += $serviceItemPrice;

                    InvoiceItem::create([
                        'invoice_id' => $newInvoice->id,
                        'booking_detail_id' => $detail->id, // Đảm bảo gán đúng booking_detail_id
                        'description' => "Dịch vụ: {$service->name} - Phòng {$roomName} (SL: {$actualQuantity})",
                        'quantity' => $actualQuantity,
                        'unit_price' => $actualPrice,
                        'total_line' => $serviceItemPrice,
                        'item_type' => 'service_charge',
                    ]);
                }

                // 3. Tạo damage fee items từ invoice items có booking_detail_id của phòng này
                $damagePrice = 0;
                $damageItems = $originalInvoice->invoiceItems()
                    ->where('booking_detail_id', $detail->id)
                    ->where('item_type', 'damage_fee')
                    ->get();
                
                foreach ($damageItems as $damageItem) {
                    $damagePrice += $damageItem->total_line ?? 0;
                    
                    // Tạo mới damage item (không di chuyển từ hóa đơn gốc)
                    $newDamageItem = InvoiceItem::create([
                        'invoice_id' => $newInvoice->id,
                        'booking_detail_id' => $detail->id, // Đảm bảo gán đúng booking_detail_id
                        'description' => $damageItem->description,
                        'quantity' => $damageItem->quantity,
                        'unit_price' => $damageItem->unit_price,
                        'total_line' => $damageItem->total_line,
                        'item_type' => 'damage_fee',
                    ]);

                    // Copy damage images nếu có
                    foreach ($damageItem->damageImages as $damageImage) {
                        \App\Models\DamageImage::create([
                            'invoice_item_id' => $newDamageItem->id,
                            'image_url' => $damageImage->image_url,
                            'order' => $damageImage->order ?? 0,
                        ]);
                    }
                }

                // 4. Thêm item voucher discount nếu có (phân bổ theo tỷ lệ giá phòng)
                if ($voucherDiscount > 0) {
                    InvoiceItem::create([
                        'invoice_id' => $newInvoice->id,
                        'booking_detail_id' => $detail->id,
                        'description' => 'Giảm giá voucher (phân bổ theo giá phòng)',
                        'quantity' => 1,
                        'unit_price' => -$voucherDiscount,
                        'total_line' => -$voucherDiscount,
                        'item_type' => 'voucher_discount',
                    ]);
                }

                // 5. Thêm item deposit (tính theo giá phòng và policy)
                // Kiểm tra xem trong hóa đơn gốc có deposit item với booking_detail_id của phòng này không
                $originalDepositItem = $originalInvoice->invoiceItems()
                    ->where('item_type', 'deposit')
                    ->where('booking_detail_id', $detail->id)
                    ->first();
                
                if ($originalDepositItem) {
                    // Nếu đã có deposit item với booking_detail_id, sử dụng giá trị đó
                    $roomDeposit = abs($originalDepositItem->total_line ?? 0);
                    InvoiceItem::create([
                        'invoice_id' => $newInvoice->id,
                        'booking_detail_id' => $detail->id,
                        'description' => "Tiền cọc đã thanh toán - Phòng {$roomName}",
                        'quantity' => 1,
                        'unit_price' => -$roomDeposit,
                        'total_line' => -$roomDeposit,
                        'item_type' => 'deposit',
                    ]);
                } elseif ($roomDeposit > 0) {
                    // Nếu chưa có, tính theo giá phòng và policy
                    $depositPercentage = $roomPrice < 1000000 ? '100%' : '50%';
                    InvoiceItem::create([
                        'invoice_id' => $newInvoice->id,
                        'booking_detail_id' => $detail->id,
                        'description' => "Tiền cọc đã thanh toán - Phòng {$roomName} ({$depositPercentage} giá phòng)",
                        'quantity' => 1,
                        'unit_price' => -$roomDeposit,
                        'total_line' => -$roomDeposit,
                        'item_type' => 'deposit',
                    ]);
                }

                // Tính lại total_amount dựa trên tất cả invoice items
                $calculatedTotal = $newInvoice->invoiceItems()->sum('total_line');
                $newInvoice->update(['total_amount' => max(0, $calculatedTotal)]);
                
                // Refresh invoice để có dữ liệu mới nhất
                $newInvoice->refresh();

                // Tạo record split_invoice để tracking
                $splitInvoice = SplitInvoice::create([
                    'original_invoice_id' => $originalInvoice->id,
                    'booking_detail_id' => $detail->id,
                    'new_invoice_id' => $newInvoice->id,
                    'room_price' => $roomPrice,
                    'service_price' => $servicePrice,
                    'damage_price' => $damagePrice,
                    'deposit_amount' => $roomDeposit,
                    'voucher_discount' => $voucherDiscount,
                    'total_amount' => $calculatedTotal,
                    'notes' => "Hóa đơn tách cho phòng {$roomName}",
                ]);

                // Load lại invoice với tất cả relationships để trả về đầy đủ
                $newInvoice->load('invoiceItems.damageImages');
                
                $splitInvoices[] = [
                    'split_invoice' => $splitInvoice,
                    'new_invoice' => $newInvoice,
                    'room' => $detail->room,
                ];
            }

            // Tách deposit items trong hóa đơn gốc thành nhiều dòng theo từng phòng
            // Tìm deposit items tổng (không có booking_detail_id) trong hóa đơn gốc
            $originalDepositItems = $originalInvoice->invoiceItems()
                ->where('item_type', 'deposit')
                ->whereNull('booking_detail_id')
                ->get();
            
            if ($originalDepositItems->count() > 0 && $depositAmount > 0) {
                // Xóa các deposit items tổng
                foreach ($originalDepositItems as $item) {
                    $item->delete();
                }
                
                // Tạo deposit items riêng cho từng phòng trong hóa đơn gốc
                foreach ($bookingDetails as $detail) {
                    $roomPrice = $roomPrices[$detail->id] ?? 0;
                    $roomName = $detail->room->name ?? '';
                    
                    // Tính tiền cọc theo từng phòng dựa trên giá phòng và policy
                    if ($roomPrice < 1000000) {
                        $roomDeposit = $roomPrice; // 100% giá phòng
                    } else {
                        $roomDeposit = $roomPrice * 0.5; // 50% giá phòng
                    }
                    $roomDeposit = round($roomDeposit, 2);
                    
                    if ($roomDeposit > 0) {
                        $depositPercentage = $roomPrice < 1000000 ? '100%' : '50%';
                        InvoiceItem::create([
                            'invoice_id' => $originalInvoice->id,
                            'booking_detail_id' => $detail->id,
                            'description' => "Tiền cọc đã thanh toán - Phòng {$roomName} ({$depositPercentage} giá phòng)",
                            'quantity' => 1,
                            'unit_price' => -$roomDeposit,
                            'total_line' => -$roomDeposit,
                            'item_type' => 'deposit',
                        ]);
                    }
                }
                
                // Tính lại total_amount cho hóa đơn gốc
                $originalInvoice->refresh();
                $originalTotal = $originalInvoice->invoiceItems()->sum('total_line');
                $originalInvoice->update(['total_amount' => max(0, $originalTotal)]);
            }
            
            // LƯU Ý: Hóa đơn gốc được GIỮ NGUYÊN, không cancelled
            // Hóa đơn gốc vẫn tồn tại với tất cả items (đã được tách deposit items thành nhiều dòng)

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được tách thành công theo phòng',
                'data' => [
                    'original_invoice_id' => $originalInvoice->id,
                    'split_invoices' => $splitInvoices,
                    'total_split' => count($splitInvoices),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error splitting invoice by rooms: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tách hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Merge multiple invoices into one
     *
     * @OA\Post(
     *     path="/api/invoices/merge",
     *     operationId="mergeInvoices",
     *     tags={"Invoices"},
     *     summary="Gộp nhiều hóa đơn",
     *     description="Gộp nhiều hóa đơn thành một",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invoice_ids", "target_invoice_id"},
     *             @OA\Property(property="invoice_ids", type="array", items={"type": "integer"}, example={1,2,3}),
     *             @OA\Property(property="target_invoice_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hóa đơn được gộp thành công"
     *     )
     * )
     */
    public function mergeInvoices(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_ids' => 'required|array|min:2',
            'invoice_ids.*' => 'integer|exists:invoices,id',
            'target_invoice_id' => 'required|integer|in:' . implode(',', $request->invoice_ids ?? [])
        ]);

        try {
            DB::beginTransaction();

            $invoiceIds = $request->invoice_ids;
            $targetInvoiceId = $request->target_invoice_id;

            // Get all invoices
            $invoices = Invoice::whereIn('id', $invoiceIds)->get();
            $targetInvoice = Invoice::findOrFail($targetInvoiceId);

            // Validate all invoices are from same booking order
            $bookingOrderIds = $invoices->pluck('booking_order_id')->unique();
            if ($bookingOrderIds->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể gộp hóa đơn từ cùng một đơn đặt phòng'
                ], 400);
            }

            // Move all items to target invoice
            $totalAmount = 0;
            foreach ($invoices as $invoice) {
                if ($invoice->id !== $targetInvoiceId) {
                    InvoiceItem::where('invoice_id', $invoice->id)
                        ->update(['invoice_id' => $targetInvoiceId]);
                    $totalAmount += $invoice->total_amount;
                }
            }

            // Update target invoice total
            $targetInvoice->update([
                'total_amount' => $targetInvoice->total_amount + $totalAmount
            ]);

            // Delete other invoices
            Invoice::whereIn('id', $invoiceIds)
                ->where('id', '!=', $targetInvoiceId)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được gộp thành công',
                'data' => $targetInvoice->fresh()->load('invoiceItems.damageImages')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gộp hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export invoices to CSV (áp dụng cùng bộ lọc như index)
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'status' => 'sometimes|string',
            'payment_status' => 'sometimes|string|in:paid,unpaid,overdue',
            'search' => 'sometimes|string|max:255',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        $query = Invoice::with(['bookingOrder.guest', 'payments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->paid();
            } elseif ($request->payment_status === 'unpaid') {
                $query->unpaid();
            } elseif ($request->payment_status === 'overdue') {
                $query->overdue();
            }
        }

        if ($request->has('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $fileName = 'invoices_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, [
                'ID',
                'Mã hóa đơn',
                'Mã đơn đặt phòng',
                'Khách hàng',
                'Email',
                'Số điện thoại',
                'Ngày xuất',
                'Hạn thanh toán',
                'Tổng tiền',
                'Giảm giá',
                'Hoàn tiền',
                'Trạng thái',
            ]);

            $query->orderBy('created_at', 'desc')->chunk(500, function ($invoices) use ($handle) {
                foreach ($invoices as $invoice) {
                    $booking = $invoice->bookingOrder;
                    $guest = optional($booking)->guest;

                    fputcsv($handle, [
                        $invoice->id,
                        $invoice->invoice_number ?? '',
                        optional($booking)->order_code ?? optional($booking)->id,
                        $booking->customer_name ?? optional($guest)->full_name ?? '',
                        $booking->customer_email ?? optional($guest)->email ?? '',
                        $booking->customer_phone ?? '',
                        optional($invoice->issue_date)->format('Y-m-d'),
                        optional($invoice->due_date)->format('Y-m-d'),
                        (float) $invoice->total_amount,
                        (float) $invoice->discount_amount,
                        (float) $invoice->refund_amount,
                        $invoice->status,
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
