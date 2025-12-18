<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VNPayService;
use App\Models\BookingOrder;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VNPayController extends Controller
{
    protected VNPayService $vnpayService;

    public function __construct(VNPayService $vnpayService)
    {
        $this->vnpayService = $vnpayService;
    }

    /**
     * Tạo URL thanh toán VNPAY cho booking
     *
     * @OA\Post(
     *     path="/api/vnpay/create-payment",
     *     summary="Tạo URL thanh toán VNPAY",
     *     tags={"VNPay"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_id", "amount"},
     *             @OA\Property(property="booking_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", example=500000),
     *             @OA\Property(property="description", type="string", example="Thanh toán đặt phòng"),
     *             @OA\Property(property="bank_code", type="string", example="NCB", description="Mã ngân hàng (không bắt buộc)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment_url", type="string"),
     *                 @OA\Property(property="order_code", type="string"),
     *                 @OA\Property(property="amount", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function createPayment(Request $request): JsonResponse
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
                'booking_id' => 'required|exists:booking_orders,id',
                'amount' => 'required|numeric|min:10000', // VNPAY minimum 10,000 VND
                'description' => 'nullable|string|max:255',
                'bank_code' => 'nullable|string|max:20',
            ], [
                'amount.min' => 'VNPAY yêu cầu số tiền thanh toán tối thiểu là 10.000 VNĐ. Vui lòng sử dụng PayOS cho các đơn có giá trị thấp hơn.',
            ]);

            $booking = BookingOrder::findOrFail($validated['booking_id']);

            // Kiểm tra quyền
            if ($user->role !== 'admin' && $booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thanh toán đơn đặt phòng này.',
                ], 403);
            }

            // Tạo order code unique: booking_id + timestamp
            $orderCode = $booking->id . '_' . time();

            // Build callback URL
            $backendUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));

            $paymentData = [
                'order_code' => $orderCode,
                'amount' => (int) $validated['amount'],
                'description' => $validated['description'] ?? "Thanh toan dat phong #{$booking->order_code}",
                'return_url' => $backendUrl . '/api/vnpay/return?booking_id=' . $booking->id,
                'bank_code' => $validated['bank_code'] ?? null,
            ];

            $result = $this->vnpayService->createPaymentUrl($paymentData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tạo URL thanh toán',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

            // Tạo hoặc lấy invoice
            $invoice = $booking->invoices()->first();

            if (!$invoice) {
                $invoice = $this->createInvoiceForBooking($booking);
            }

            // Tạo payment record với status pending
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $validated['amount'],
                'payment_method' => 'vnpay',
                'transaction_id' => $orderCode,
                'status' => 'pending',
                'paid_at' => now(),
            ]);

            Log::info('VNPay: Payment URL created', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'order_code' => $orderCode,
                'amount' => $validated['amount'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo URL thanh toán VNPAY thành công',
                'data' => [
                    'payment_url' => $result['data']['payment_url'],
                    'order_code' => $orderCode,
                    'amount' => $validated['amount'],
                    'booking_id' => $booking->id,
                    'expire_date' => $result['data']['expire_date'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('VNPayController@createPayment failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tạo URL thanh toán VNPAY cho invoice (thanh toán sau checkout)
     */
    public function createInvoicePayment(Request $request): JsonResponse
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
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:10000',
                'description' => 'nullable|string|max:255',
                'bank_code' => 'nullable|string|max:20',
            ], [
                'amount.min' => 'VNPAY yêu cầu số tiền thanh toán tối thiểu là 10.000 VNĐ. Vui lòng sử dụng PayOS cho các đơn có giá trị thấp hơn.',
            ]);

            $invoice = Invoice::with('bookingOrder')->findOrFail($validated['invoice_id']);

            // Kiểm tra quyền
            if ($user->role !== 'admin' && $invoice->bookingOrder && $invoice->bookingOrder->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thanh toán hóa đơn này.',
                ], 403);
            }

            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hóa đơn này đã được thanh toán.',
                ], 400);
            }

            // Tạo order code unique
            $orderCode = 'INV_' . $invoice->id . '_' . time();

            $backendUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));

            $paymentData = [
                'order_code' => $orderCode,
                'amount' => (int) $validated['amount'],
                'description' => $validated['description'] ?? "Thanh toan hoa don #{$invoice->id}",
                'return_url' => $backendUrl . '/api/vnpay/return?invoice_id=' . $invoice->id,
                'bank_code' => $validated['bank_code'] ?? null,
            ];

            $result = $this->vnpayService->createPaymentUrl($paymentData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tạo URL thanh toán',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

            // Tạo payment record
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $validated['amount'],
                'payment_method' => 'vnpay',
                'transaction_id' => $orderCode,
                'status' => 'pending',
                'paid_at' => now(),
            ]);

            Log::info('VNPay: Invoice payment URL created', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'order_code' => $orderCode,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo URL thanh toán VNPAY thành công',
                'data' => [
                    'payment_url' => $result['data']['payment_url'],
                    'order_code' => $orderCode,
                    'amount' => $validated['amount'],
                    'invoice_id' => $invoice->id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('VNPayController@createInvoicePayment failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xử lý callback từ VNPAY (Return URL)
     */
    public function handleReturn(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $vnpParams = $request->all();
            $vnpSecureHash = $vnpParams['vnp_SecureHash'] ?? '';

            Log::info('VNPay Return: Received callback', [
                'params' => $vnpParams,
            ]);

            // Verify signature
            $isValid = $this->vnpayService->verifyReturnUrl($vnpParams, $vnpSecureHash);

            if (!$isValid) {
                Log::error('VNPay Return: Invalid signature', [
                    'params' => $vnpParams,
                ]);
                return $this->redirectToFrontend('error', null, 'Chữ ký không hợp lệ');
            }

            $responseCode = $vnpParams['vnp_ResponseCode'] ?? '99';
            $txnRef = $vnpParams['vnp_TxnRef'] ?? '';
            $amount = ($vnpParams['vnp_Amount'] ?? 0) / 100; // Convert back from VNPay format
            $transactionNo = $vnpParams['vnp_TransactionNo'] ?? '';
            $bankCode = $vnpParams['vnp_BankCode'] ?? '';
            $payDate = $vnpParams['vnp_PayDate'] ?? '';

            // Kiểm tra trạng thái giao dịch
            $status = $this->vnpayService->getTransactionStatus($responseCode);

            // Tìm payment record
            $payment = Payment::where('transaction_id', $txnRef)
                ->where('payment_method', 'vnpay')
                ->first();

            if (!$payment) {
                Log::error('VNPay Return: Payment not found', [
                    'txn_ref' => $txnRef,
                ]);
                return $this->redirectToFrontend('error', null, 'Không tìm thấy thông tin thanh toán');
            }

            $invoice = $payment->invoice;
            $booking = $invoice->bookingOrder ?? null;
            $bookingId = $request->get('booking_id');
            $invoiceId = $request->get('invoice_id');

            if ($status['success']) {
                // Giao dịch thành công
                DB::beginTransaction();
                try {
                    // Cập nhật payment
                    $payment->update([
                        'status' => 'success',
                        'transaction_id' => $transactionNo ?: $txnRef,
                        'paid_at' => now(),
                    ]);

                    // Tính tổng đã thanh toán
                    $totalPaidAmount = $invoice->payments()
                        ->whereIn('status', ['success', 'paid'])
                        ->sum('amount');

                    // Cập nhật invoice
                    if ($totalPaidAmount >= $invoice->total_amount) {
                        $invoice->update(['status' => 'paid']);
                    }

                    // Cập nhật booking nếu có
                    if ($booking) {
                        $newPaidAmount = min($totalPaidAmount, $booking->total_amount);
                        $bookingPaymentStatus = $newPaidAmount >= $booking->total_amount ? 'paid' : 'partial';

                        $booking->update([
                            'paid_amount' => $newPaidAmount,
                            'payment_status' => $bookingPaymentStatus,
                        ]);

                        // Cập nhật status nếu cần
                        if ($booking->status === 'pending' && $bookingPaymentStatus === 'partial') {
                            $booking->update(['status' => 'confirmed']);
                        }

                        if (in_array($booking->status, ['checked_out', 'partially_checked_out']) && $bookingPaymentStatus === 'paid') {
                            $booking->update(['status' => 'completed']);
                        }
                    }

                    DB::commit();

                    Log::info('VNPay Return: Payment successful', [
                        'payment_id' => $payment->id,
                        'transaction_no' => $transactionNo,
                        'amount' => $amount,
                        'bank_code' => $bankCode,
                    ]);

                    return $this->redirectToFrontend('success', $bookingId ?? $invoiceId, null, [
                        'amount' => $amount,
                        'transaction_no' => $transactionNo,
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('VNPay Return: DB update failed', [
                        'error' => $e->getMessage(),
                    ]);
                    return $this->redirectToFrontend('error', $bookingId ?? $invoiceId, 'Lỗi cập nhật dữ liệu');
                }
            } else {
                // Giao dịch thất bại
                $payment->update([
                    'status' => 'failed',
                ]);

                // Nếu là thanh toán booking và chưa có payment nào thành công, hủy booking
                if ($booking && $booking->payment_status === 'unpaid' && $booking->status === 'pending') {
                    $booking->update(['status' => 'cancelled']);
                    Log::info('VNPay Return: Auto-cancelled booking', [
                        'booking_id' => $booking->id,
                    ]);
                }

                Log::info('VNPay Return: Payment failed', [
                    'payment_id' => $payment->id,
                    'response_code' => $responseCode,
                    'message' => $status['message'],
                ]);

                return $this->redirectToFrontend('cancel', $bookingId ?? $invoiceId, $status['message']);
            }
        } catch (\Exception $e) {
            Log::error('VNPay Return: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectToFrontend('error', null, 'Đã xảy ra lỗi');
        }
    }

    /**
     * IPN URL - VNPAY gọi để thông báo kết quả giao dịch
     */
    public function handleIPN(Request $request): JsonResponse
    {
        try {
            $vnpParams = $request->all();
            $vnpSecureHash = $vnpParams['vnp_SecureHash'] ?? '';

            Log::info('VNPay IPN: Received', [
                'params' => $vnpParams,
            ]);

            // Verify signature
            $isValid = $this->vnpayService->verifyReturnUrl($vnpParams, $vnpSecureHash);

            if (!$isValid) {
                Log::error('VNPay IPN: Invalid signature');
                return response()->json([
                    'RspCode' => '97',
                    'Message' => 'Invalid signature',
                ]);
            }

            $txnRef = $vnpParams['vnp_TxnRef'] ?? '';
            $amount = ($vnpParams['vnp_Amount'] ?? 0) / 100;
            $responseCode = $vnpParams['vnp_ResponseCode'] ?? '99';
            $transactionNo = $vnpParams['vnp_TransactionNo'] ?? '';

            // Tìm payment
            $payment = Payment::where('transaction_id', $txnRef)
                ->where('payment_method', 'vnpay')
                ->first();

            if (!$payment) {
                Log::error('VNPay IPN: Order not found', ['txn_ref' => $txnRef]);
                return response()->json([
                    'RspCode' => '01',
                    'Message' => 'Order not found',
                ]);
            }

            // Kiểm tra số tiền
            if ((int) $payment->amount != (int) $amount) {
                Log::error('VNPay IPN: Invalid amount', [
                    'expected' => $payment->amount,
                    'received' => $amount,
                ]);
                return response()->json([
                    'RspCode' => '04',
                    'Message' => 'Invalid amount',
                ]);
            }

            // Kiểm tra đã xử lý chưa
            if (in_array($payment->status, ['success', 'paid'])) {
                Log::info('VNPay IPN: Already processed', ['payment_id' => $payment->id]);
                return response()->json([
                    'RspCode' => '02',
                    'Message' => 'Order already confirmed',
                ]);
            }

            // Xử lý kết quả
            if ($responseCode === '00') {
                DB::beginTransaction();
                try {
                    $payment->update([
                        'status' => 'success',
                        'transaction_id' => $transactionNo ?: $txnRef,
                        'paid_at' => now(),
                    ]);

                    $invoice = $payment->invoice;
                    $booking = $invoice->bookingOrder ?? null;

                    // Tính tổng đã thanh toán
                    $totalPaidAmount = $invoice->payments()
                        ->whereIn('status', ['success', 'paid'])
                        ->sum('amount');

                    if ($totalPaidAmount >= $invoice->total_amount) {
                        $invoice->update(['status' => 'paid']);
                    }

                    if ($booking) {
                        $newPaidAmount = min($totalPaidAmount, $booking->total_amount);
                        $bookingPaymentStatus = $newPaidAmount >= $booking->total_amount ? 'paid' : 'partial';

                        $booking->update([
                            'paid_amount' => $newPaidAmount,
                            'payment_status' => $bookingPaymentStatus,
                        ]);

                        if ($booking->status === 'pending' && $bookingPaymentStatus === 'partial') {
                            $booking->update(['status' => 'confirmed']);
                        }
                    }

                    DB::commit();

                    Log::info('VNPay IPN: Payment confirmed', [
                        'payment_id' => $payment->id,
                        'transaction_no' => $transactionNo,
                    ]);

                    return response()->json([
                        'RspCode' => '00',
                        'Message' => 'Confirm Success',
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('VNPay IPN: DB error', ['error' => $e->getMessage()]);
                    return response()->json([
                        'RspCode' => '99',
                        'Message' => 'Unknown error',
                    ]);
                }
            } else {
                $payment->update(['status' => 'failed']);

                return response()->json([
                    'RspCode' => '00',
                    'Message' => 'Confirm Success',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('VNPay IPN: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'RspCode' => '99',
                'Message' => 'Unknown error',
            ]);
        }
    }

    /**
     * Truy vấn trạng thái giao dịch
     */
    public function queryTransaction(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'txn_ref' => 'required|string',
                'trans_date' => 'required|string', // Format: yyyyMMddHHmmss
            ]);

            $result = $this->vnpayService->queryTransaction(
                $validated['txn_ref'],
                $validated['trans_date']
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy danh sách ngân hàng hỗ trợ
     */
    public function getBanks(): JsonResponse
    {
        $banks = $this->vnpayService->getSupportedBanks();

        return response()->json([
            'success' => true,
            'data' => $banks,
        ]);
    }

    /**
     * Helper: Redirect về frontend
     */
    private function redirectToFrontend(string $type, ?int $id = null, ?string $error = null, array $extra = []): \Illuminate\Http\RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));

        $queryParams = array_filter(array_merge([
            'type' => $type,
            'id' => $id,
            'error' => $error,
            'payment_method' => 'vnpay',
        ], $extra));

        $url = $frontendUrl . '/payment/' . $type . '?' . http_build_query($queryParams);

        return redirect($url);
    }

    /**
     * Helper: Tạo invoice cho booking
     */
    private function createInvoiceForBooking(BookingOrder $booking): Invoice
    {
        $invoice = Invoice::create([
            'booking_order_id' => $booking->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'total_amount' => 0,
            'status' => 'pending',
        ]);

        $totalAmount = 0;
        $booking->load('details.room');

        foreach ($booking->details as $detail) {
            if ($detail->room) {
                $checkIn = \Carbon\Carbon::parse($detail->check_in_date);
                $checkOut = \Carbon\Carbon::parse($detail->check_out_date);
                $nights = max(1, $checkOut->diffInDays($checkIn));

                $roomPrice = ($detail->room->price_per_night ?? 0) * $nights;

                InvoiceItem::create([
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

        $invoice->update(['total_amount' => max(0, $totalAmount)]);

        return $invoice;
    }
}
