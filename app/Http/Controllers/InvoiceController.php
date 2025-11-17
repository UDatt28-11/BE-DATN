<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\BookingOrder;
use App\Models\InvoiceItem;
use App\Models\InvoiceConfig;
use App\Models\RefundPolicy;
use App\Models\InvoiceDiscount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *     title="BE-DATN-kien API Documentation",
 *     version="1.0.0",
 *     description="Hệ thống quản lý hóa đơn và vật tư cho homestay booking",
 *     contact={
 *         "name": "BE-DATN-kien Team",
 *         "email": "support@homestay.local"
 *     }
 * )
 * 
 * @OA\Server(
 *     url="http://127.0.0.1:8000/api",
 *     description="Development Server"
 * )
 * 
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
     *     path="/invoices",
     *     operationId="getInvoices",
     *     tags={"Invoices"},
     *     summary="Danh sách hóa đơn",
     *     description="Lấy danh sách tất cả hóa đơn với hỗ trợ lọc, tìm kiếm và phân trang",
     *     security={{"bearerAuth":{}}},
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
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['bookingOrder', 'invoiceItems']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->paid();
            } elseif ($request->payment_status === 'unpaid') {
                $query->unpaid();
            } elseif ($request->payment_status === 'overdue') {
                $query->overdue();
            }
        }

        // Search by invoice number
        if ($request->has('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    /**
     * Create invoice from booking order
     * 
     * @OA\Post(
     *     path="/invoices/create-from-booking",
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

            // Generate invoice number
            $invoiceNumber = 'INV-' . strtoupper(Str::random(8));

            // Create invoice với đầy đủ thông tin
            $invoice = Invoice::create([
                'booking_order_id' => $bookingOrder->id,
                'property_id' => $bookingOrder->property_id ?? null,
                'invoice_number' => $invoiceNumber,
                'issue_date' => now()->toDateString(),
                'due_date' => $request->due_date ?? now()->addDays(7)->toDateString(),
                'customer_name' => $bookingOrder->customer_name,
                'customer_email' => $bookingOrder->customer_email,
                'customer_phone' => $bookingOrder->customer_phone,
                'customer_address' => null, // Có thể lấy từ booking nếu có
                'subtotal' => 0,
                'tax_rate' => 10, // Default 10%
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,  // Will be updated after calculating items
                'paid_amount' => 0,
                'balance' => 0,
                'status' => 'pending', // Giữ lại để tương thích
                'payment_status' => 'pending',
                'invoice_status' => 'draft',
                'payment_method' => $bookingOrder->payment_method ?? null,
                'notes' => $request->notes ?? $bookingOrder->notes ?? null,
                'terms_conditions' => null,
            ]);

            // Create invoice items from booking details
            $subtotal = 0;
            $taxRate = 10; // Default tax rate
            foreach ($bookingOrder->bookingDetails as $detail) {
                // Calculate nights from check-in and check-out
                $nights = $detail->check_out_date->diffInDays($detail->check_in_date);
                if ($nights <= 0) $nights = 1; // At least 1 night
                
                $unitPrice = $detail->room->price_per_night ?? 0;
                $amount = $unitPrice * $nights; // Tổng trước thuế
                $taxAmount = ($amount * $taxRate) / 100;
                $total = $amount + $taxAmount; // Tổng sau thuế
                
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'booking_detail_id' => $detail->id,
                    'room_id' => $detail->room_id,
                    'description' => "Phòng {$detail->room->name} - {$nights} đêm",
                    'quantity' => $nights,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'amount' => $amount,
                    'total_line' => $total, // Giữ lại để tương thích
                    'total' => $total,
                    'item_type' => 'room_charge'
                ]);
                $subtotal += $amount;
            }

            // Create invoice items from booking services
            foreach ($bookingOrder->bookingServices as $bookingService) {
                $unitPrice = $bookingService->service->unit_price ?? 0;
                $quantity = $bookingService->quantity ?? 1;
                $amount = $unitPrice * $quantity;
                $taxAmount = ($amount * $taxRate) / 100;
                $total = $amount + $taxAmount;
                
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $bookingService->service_id ?? null,
                    'description' => ($bookingService->service->name ?? 'Dịch vụ') . ' - ' . ($bookingService->service->description ?? ''),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'amount' => $amount,
                    'total_line' => $total,
                    'total' => $total,
                    'item_type' => 'service_charge'
                ]);
                $subtotal += $amount;
            }

            // Calculate totals
            $taxAmount = ($subtotal * $taxRate) / 100;
            $totalAmount = $subtotal + $taxAmount;
            $balance = $totalAmount; // Chưa thanh toán nên balance = total_amount

            // Update invoice totals
            $invoice->update([
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'balance' => $balance,
                'invoice_status' => 'sent', // Tự động chuyển sang sent sau khi tạo
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được tạo thành công',
                'data' => $invoice->load(['bookingOrder', 'invoiceItems'])
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
     *     path="/invoices/{id}",
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
    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::with(['bookingOrder.guest', 'invoiceItems'])
                         ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice
        ]);
    }

    /**
     * Mark invoice as paid
     * 
     * @OA\Patch(
     *     path="/invoices/{id}/mark-paid",
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
            'payment_method' => 'nullable|in:cash,bank_transfer,credit_card,e_wallet,other',
            'payment_notes' => 'nullable|string|max:500',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Hóa đơn đã được thanh toán'
            ], 400);
        }

        $paidAmount = $request->paid_amount ?? $invoice->total_amount;
        $balance = $invoice->total_amount - $paidAmount;
        $paymentStatus = $balance > 0 ? 'partially_paid' : 'paid';

        $invoice->update([
            'status' => 'paid', // Giữ lại để tương thích
            'payment_status' => $paymentStatus,
            'invoice_status' => 'paid',
            'paid_amount' => $paidAmount,
            'balance' => $balance,
            'payment_method' => $request->payment_method ?? $invoice->payment_method,
            'payment_date' => now()->toDateString(),
            'payment_notes' => $request->payment_notes ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hóa đơn đã được đánh dấu là đã thanh toán',
            'data' => $invoice->fresh()
        ]);
    }

    /**
     * Update invoice status
     * 
     * @OA\Patch(
     *     path="/invoices/{id}/status",
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
            'status' => 'nullable|in:pending,paid,overdue,cancelled',
            'payment_status' => 'nullable|in:pending,partially_paid,paid,overdue,cancelled',
            'invoice_status' => 'nullable|in:draft,sent,viewed,paid,cancelled',
        ]);

        $invoice = Invoice::findOrFail($id);
        
        $updateData = [];
        
        // Cập nhật status (giữ lại để tương thích)
        if ($request->has('status')) {
            $updateData['status'] = $request->status;
            // Đồng bộ payment_status nếu không được chỉ định
            if (!$request->has('payment_status')) {
                $updateData['payment_status'] = $request->status === 'paid' ? 'paid' : 
                    ($request->status === 'cancelled' ? 'cancelled' : 'pending');
            }
        }
        
        // Cập nhật payment_status
        if ($request->has('payment_status')) {
            $updateData['payment_status'] = $request->payment_status;
            // Đồng bộ status nếu không được chỉ định
            if (!$request->has('status')) {
                $updateData['status'] = $request->payment_status === 'paid' ? 'paid' : 
                    ($request->payment_status === 'cancelled' ? 'cancelled' : 'pending');
            }
        }
        
        // Cập nhật invoice_status
        if ($request->has('invoice_status')) {
            $updateData['invoice_status'] = $request->invoice_status;
        }
        
        // Nếu chuyển sang cancelled, cập nhật balance
        if (($request->status === 'cancelled' || $request->payment_status === 'cancelled') && 
            !isset($updateData['balance'])) {
            $updateData['balance'] = 0;
        }
        
        $invoice->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Trạng thái hóa đơn đã được cập nhật',
            'data' => $invoice->fresh()
        ]);
    }

    /**
     * Get invoice statistics
     * 
     * @OA\Get(
     *     path="/invoices/statistics/overview",
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
        $stats = [
            'total_invoices' => Invoice::count(),
            'paid_invoices' => Invoice::paid()->count(),
            'unpaid_invoices' => Invoice::unpaid()->count(),
            'overdue_invoices' => Invoice::overdue()->count(),
            'total_revenue' => Invoice::paid()->sum('total_amount'),
            'pending_revenue' => Invoice::unpaid()->sum('total_amount'),
            'overdue_revenue' => Invoice::overdue()->sum('total_amount')
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
     *     path="/invoices",
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
        $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'booking_order_id' => 'nullable|exists:booking_orders,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'payment_method' => 'nullable|in:cash,bank_transfer,credit_card,e_wallet,other',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:room_charge,service_charge,penalty,other',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Generate invoice number
            $invoiceNumber = 'INV-' . strtoupper(Str::random(8));

            // Calculate totals from items
            $subtotal = 0;
            $taxRate = $request->tax_rate ?? 10;
            foreach ($request->items as $itemData) {
                $itemTaxRate = $itemData['tax_rate'] ?? $taxRate;
                $amount = $itemData['quantity'] * $itemData['unit_price'];
                $subtotal += $amount;
            }
            $taxAmount = ($subtotal * $taxRate) / 100;
            $totalAmount = $subtotal + $taxAmount;
            $balance = $totalAmount;

            // Create invoice
            $invoice = Invoice::create([
                'property_id' => $request->property_id ?? null,
                'booking_order_id' => $request->booking_order_id ?? null,
                'invoice_number' => $invoiceNumber,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email ?? null,
                'customer_phone' => $request->customer_phone ?? null,
                'customer_address' => $request->customer_address ?? null,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance' => $balance,
                'status' => 'pending', // Giữ lại để tương thích
                'payment_status' => 'pending',
                'invoice_status' => 'draft',
                'payment_method' => $request->payment_method ?? null,
                'notes' => $request->notes ?? null,
                'terms_conditions' => $request->terms_conditions ?? null,
                'calculation_method' => 'manual',
            ]);

            // Create invoice items
            foreach ($request->items as $itemData) {
                $itemTaxRate = $itemData['tax_rate'] ?? $taxRate;
                $amount = $itemData['quantity'] * $itemData['unit_price'];
                $itemTaxAmount = ($amount * $itemTaxRate) / 100;
                $total = $amount + $itemTaxAmount;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => $itemTaxRate,
                    'tax_amount' => $itemTaxAmount,
                    'amount' => $amount,
                    'total_line' => $total,
                    'total' => $total,
                    'item_type' => $itemData['item_type'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hóa đơn đã được tạo thành công',
                'data' => $invoice->load(['bookingOrder', 'invoiceItems'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo hóa đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing invoice
     * 
     * @OA\Put(
     *     path="/invoices/{id}",
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
                'data' => $invoice->load(['bookingOrder', 'invoiceItems'])
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
     *     path="/invoices/{id}",
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
     *     path="/invoices/config/calculation",
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
     *     path="/invoices/config/calculation",
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
     *     path="/invoices/config/refund-policies",
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
     *     path="/invoices/config/refund-policies",
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
     *     path="/invoices/config/refund-policies/{policyId}",
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
     *     path="/invoices/{id}/discounts",
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
     *     path="/invoices/{id}/discounts/{discountId}",
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
     *     path="/invoices/{id}/apply-refund-policy",
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
     *     path="/invoices/{id}/split",
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

            $originalInvoice = Invoice::with('invoiceItems')->findOrFail($id);

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
            $newInvoice = Invoice::create([
                'booking_order_id' => $originalInvoice->booking_order_id,
                'issue_date' => $originalInvoice->issue_date,
                'due_date' => $originalInvoice->due_date,
                'total_amount' => $newInvoiceAmount,
                'status' => 'pending',
                'calculation_method' => $originalInvoice->calculation_method,
            ]);

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
     * Merge multiple invoices into one
     * 
     * @OA\Post(
     *     path="/invoices/merge",
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
                'data' => $targetInvoice->fresh()->load('invoiceItems')
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
     * Customer: Display a listing of their own invoices
     */
    public function customerIndex(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = Invoice::with(['bookingOrder', 'invoiceItems'])
                ->whereHas('bookingOrder', function ($q) use ($user) {
                    $q->where('guest_id', $user->id);
                });

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment status
            if ($request->has('payment_status')) {
                if ($request->payment_status === 'paid') {
                    $query->paid();
                } elseif ($request->payment_status === 'unpaid') {
                    $query->unpaid();
                } elseif ($request->payment_status === 'overdue') {
                    $query->overdue();
                }
            }

            // Search by invoice number
            if ($request->has('search')) {
                $query->where('invoice_number', 'like', '%' . $request->search . '%');
            }

            // Date range filter
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $invoices = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Customer: Display the specified invoice (only their own)
     */
    public function customerShow(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $invoice = Invoice::with(['bookingOrder', 'invoiceItems', 'discounts', 'refundPolicy'])
                ->whereHas('bookingOrder', function ($q) use ($user) {
                    $q->where('guest_id', $user->id);
                })
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải thông tin hóa đơn: ' . $e->getMessage()
            ], 404);
        }
    }
}