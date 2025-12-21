<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayOSService;
use App\Models\BookingOrder;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PayOSController extends Controller
{
    protected PayOSService $payOSService;

    public function __construct(PayOSService $payOSService)
    {
        $this->payOSService = $payOSService;
    }

    /**
     * Tạo payment link PayOS cho booking
     */
    public function createPaymentLink(Request $request): JsonResponse
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
                'amount' => 'required|numeric|min:1000',
                'description' => 'nullable|string|max:255',
            ]);

            $booking = BookingOrder::findOrFail($validated['booking_id']);

            // Kiểm tra quyền: User chỉ có thể thanh toán booking của chính mình, Admin có thể thanh toán bất kỳ booking nào
            if ($user->role !== 'admin' && $booking->guest_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thanh toán đơn đặt phòng này.',
                ], 403);
            }

            // Tạo order code từ booking id và timestamp (đảm bảo unique và là số nguyên)
            // PayOS yêu cầu orderCode là số nguyên dương, tối đa 19 chữ số
            // Format: booking_id (tối đa 6 chữ số) + timestamp (10 chữ số cuối) = tối đa 16 chữ số
            $bookingIdPart = str_pad($booking->id, 6, '0', STR_PAD_LEFT);
            $timestampPart = substr(time(), -10); // Lấy 10 chữ số cuối của timestamp
            $orderCode = (int) ($bookingIdPart . $timestampPart);
            
            // Đảm bảo orderCode không vượt quá 19 chữ số
            if ($orderCode > 9999999999999999999) {
                $orderCode = (int) substr((string) $orderCode, 0, 19);
            }

            // Tạo payment link
            // Dùng backend URL làm returnUrl/cancelUrl (sẽ redirect về localhost)
            $backendUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));
            $paymentData = [
                'orderCode' => $orderCode,
                'amount' => (int) $validated['amount'],
                'description' => $validated['description'] ?? "Thanh toán đặt cọc đơn #{$booking->order_code}",
                'returnUrl' => $backendUrl . '/api/payment/redirect?booking_id=' . $booking->id . '&type=success',
                'cancelUrl' => $backendUrl . '/api/payment/redirect?booking_id=' . $booking->id . '&type=cancel',
                'items' => [
                    [
                        'name' => "Đặt cọc đơn {$booking->order_code}",
                        'quantity' => 1,
                        'price' => (int) $validated['amount'],
                    ]
                ],
                'buyerName' => $booking->customer_name ?? $user->full_name,
                'buyerEmail' => $booking->customer_email ?? $user->email,
                'buyerPhone' => $booking->customer_phone ?? $user->phone_number,
            ];

            $result = $this->payOSService->createPaymentLink($paymentData);

            Log::info('PayOS createPaymentLink result', [
                'success' => $result['success'] ?? false,
                'data_keys' => isset($result['data']) ? array_keys($result['data']) : [],
                'has_checkoutUrl' => isset($result['data']['checkoutUrl']),
                'has_paymentLinkId' => isset($result['data']['paymentLinkId']),
            ]);

            if ($result['success']) {
                // Lấy checkoutUrl hoặc paymentLinkId từ response
                $checkoutUrl = $result['data']['checkoutUrl'] ?? null;
                $paymentLinkId = $result['data']['paymentLinkId'] ?? null;
                
                // Nếu không có checkoutUrl, tạo từ paymentLinkId
                if (!$checkoutUrl && $paymentLinkId) {
                    $checkoutUrl = "https://pay.payos.vn/web/{$paymentLinkId}";
                }
                
                // Nếu vẫn không có, log để debug
                if (!$checkoutUrl) {
                    Log::error('PayOS: No checkoutUrl or paymentLinkId in response', [
                        'result_data' => $result['data'] ?? null,
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'PayOS không trả về link thanh toán. Vui lòng thử lại.',
                        'error' => 'Missing checkoutUrl and paymentLinkId',
                    ], 500);
                }

                // Lưu thông tin payment vào database
                $invoice = $booking->invoices()->first();
                
                if (!$invoice) {
                    // Tạo invoice mới
                    $invoice = Invoice::create([
                        'booking_order_id' => $booking->id,
                        'issue_date' => now(),
                        'due_date' => now()->addDays(7),
                        'total_amount' => 0, // Sẽ tính lại sau khi tạo items
                        'status' => 'pending',
                    ]);

                    // Tạo invoice items từ booking details (tiền phòng)
                    $totalAmount = 0;
                    $booking->load('details.room'); // Eager load để tránh N+1 query
                    
                    foreach ($booking->details as $detail) {
                        if ($detail->room) {
                            // Calculate nights
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

                    // Trừ tiền cọc đã thanh toán (nếu có) - trường hợp đã thanh toán trước đó
                    $paidAmount = $booking->paid_amount ?? 0;
                    if ($paidAmount > 0) {
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'description' => 'Tiền cọc đã thanh toán',
                            'quantity' => 1,
                            'unit_price' => -$paidAmount, // Giá trị âm để trừ
                            'total_line' => -$paidAmount, // Giá trị âm để trừ
                            'item_type' => 'deposit',
                        ]);
                        $totalAmount -= $paidAmount;
                    }

                    // Đảm bảo total_amount không âm
                    $finalAmount = max(0, $totalAmount);

                    // Update invoice total_amount
                    $invoice->update(['total_amount' => $finalAmount]);
                }

                // Tạo payment record với status pending
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $validated['amount'],
                    'payment_method' => 'payos',
                    'transaction_id' => $paymentLinkId ?? (string) $orderCode,
                    'status' => 'pending',
                    'paid_at' => now(),
                ]);

                Log::info('PayOS payment link created for booking', [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'order_code' => $orderCode,
                    'payment_link_id' => $paymentLinkId,
                    'checkout_url' => $checkoutUrl,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tạo link thanh toán thành công',
                    'data' => [
                        'payment_link' => $checkoutUrl,
                        'payment_link_id' => $paymentLinkId,
                        'order_code' => $orderCode,
                        'booking_id' => $booking->id,
                    ],
                ]);
            }

            Log::error('PayOS createPaymentLink returned unsuccessful', [
                'result' => $result,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo link thanh toán',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Exception $e) {
            Log::error('PayOSController@createPaymentLink failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = $e->getMessage();
            
            // Kiểm tra nếu là lỗi kết nối/DNS
            if (str_contains($errorMessage, 'Could not resolve host') || 
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'kết nối')) {
                $errorMessage = 'Không thể kết nối đến PayOS. Vui lòng kiểm tra kết nối mạng hoặc thử lại sau.';
            }
            
            // Kiểm tra nếu lỗi từ PayOS API (code trong response)
            if (str_contains($errorMessage, 'PayOS API error') || 
                str_contains($errorMessage, 'Thông tin truyền lên không đúng') ||
                str_contains($errorMessage, 'Code:')) {
                
                // Log chi tiết để debug
                Log::error('PayOS API error details', [
                    'error_message' => $errorMessage,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'error_type' => 'payos_api_error',
                    'debug_info' => config('app.debug') ? [
                        'exception' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : null,
                ], 400);
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_type' => 'connection_error',
            ], 500);
        }
    }

    /**
     * Tạo payment link PayOS cho invoice (thanh toán sau checkout)
     */
    public function createInvoicePaymentLink(Request $request): JsonResponse
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
                'amount' => 'required|numeric|min:1000',
                'description' => 'nullable|string|max:255',
            ]);

            $invoice = Invoice::with('bookingOrder')->findOrFail($validated['invoice_id']);

            // Kiểm tra quyền: User chỉ có thể thanh toán invoice của chính mình, Admin có thể thanh toán bất kỳ invoice nào
            if ($user->role !== 'admin' && $invoice->bookingOrder && $invoice->bookingOrder->guest_id !== $user->id) {
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

            // Tạo order code từ invoice id và timestamp (đảm bảo unique và là số nguyên)
            // PayOS yêu cầu orderCode là số nguyên dương, tối đa 19 chữ số
            // Format: invoice_id (tối đa 6 chữ số) + timestamp (10 chữ số cuối) = tối đa 16 chữ số
            $invoiceIdPart = str_pad($invoice->id, 6, '0', STR_PAD_LEFT);
            $timestampPart = substr(time(), -10); // Lấy 10 chữ số cuối của timestamp
            $orderCode = (int) ($invoiceIdPart . $timestampPart);
            
            // Đảm bảo orderCode không vượt quá 19 chữ số
            if ($orderCode > 9999999999999999999) {
                $orderCode = (int) substr((string) $orderCode, 0, 19);
            }

            // Tạo description nếu chưa có
            $invoiceNumber = $invoice->invoice_number ? $invoice->invoice_number : $invoice->id;
            $description = $validated['description'] ?? "Thanh toán hóa đơn #{$invoiceNumber}";

            // Tạo payment link
            // Dùng backend URL làm returnUrl/cancelUrl (sẽ redirect về frontend)
            $backendUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));
            
            $paymentData = [
                'orderCode' => $orderCode,
                'amount' => (int) $validated['amount'],
                'description' => $description,
                'returnUrl' => $backendUrl . '/api/payment/redirect?invoice_id=' . $invoice->id . '&type=success',
                'cancelUrl' => $backendUrl . '/api/payment/redirect?invoice_id=' . $invoice->id . '&type=cancel',
                'items' => [
                    [
                        'name' => "Thanh toán hóa đơn #{$invoiceNumber}",
                        'quantity' => 1,
                        'price' => (int) $validated['amount'],
                    ]
                ],
                'buyerName' => $invoice->customer_name ?? ($invoice->bookingOrder ? $invoice->bookingOrder->customer_name : null) ?? $user->full_name,
                'buyerEmail' => $invoice->customer_email ?? ($invoice->bookingOrder ? $invoice->bookingOrder->customer_email : null) ?? $user->email,
                'buyerPhone' => $invoice->customer_phone ?? ($invoice->bookingOrder ? $invoice->bookingOrder->customer_phone : null) ?? $user->phone_number,
            ];

            Log::info('PayOS createInvoicePaymentLink: Before calling PayOSService', [
                'invoice_id' => $invoice->id,
                'order_code' => $orderCode,
                'amount' => $validated['amount'],
                'backend_url' => $backendUrl,
                'return_url' => $paymentData['returnUrl'],
                'cancel_url' => $paymentData['cancelUrl'],
                'has_localhost' => str_contains($backendUrl, 'localhost') || str_contains($backendUrl, '127.0.0.1'),
            ]);

            // Gọi PayOS service để tạo payment link
            $result = $this->payOSService->createPaymentLink($paymentData);

            Log::info('PayOS createInvoicePaymentLink result', [
                'success' => $result['success'] ?? false,
                'data_keys' => isset($result['data']) ? array_keys($result['data']) : [],
                'has_checkoutUrl' => isset($result['data']['checkoutUrl']),
                'has_paymentLinkId' => isset($result['data']['paymentLinkId']),
                'invoice_id' => $invoice->id,
            ]);

            if ($result['success']) {
                // Lấy checkoutUrl hoặc paymentLinkId từ response
                $checkoutUrl = $result['data']['checkoutUrl'] ?? null;
                $paymentLinkId = $result['data']['paymentLinkId'] ?? null;
                
                // Nếu không có checkoutUrl, tạo từ paymentLinkId
                if (!$checkoutUrl && $paymentLinkId) {
                    $checkoutUrl = "https://pay.payos.vn/web/{$paymentLinkId}";
                }
                
                if (!$checkoutUrl) {
                    Log::error('PayOS: No checkoutUrl or paymentLinkId in response for invoice', [
                        'result_data' => $result['data'] ?? null,
                        'invoice_id' => $invoice->id,
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'PayOS không trả về link thanh toán. Vui lòng thử lại.',
                        'error' => 'Missing checkoutUrl and paymentLinkId',
                    ], 500);
                }

                // Lưu thông tin payment vào database
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $validated['amount'],
                    'payment_method' => 'payos',
                    'status' => 'pending',
                    'transaction_id' => $paymentLinkId ?? (string) $orderCode,
                    'paid_at' => now(), // Tạm thời set now(), sẽ được cập nhật lại khi webhook xác nhận
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tạo link thanh toán thành công',
                    'data' => [
                        'checkoutUrl' => $checkoutUrl,
                        'checkout_url' => $checkoutUrl, // Support cả hai format
                        'payment_link_id' => $paymentLinkId,
                        'order_code' => $orderCode,
                        'invoice_id' => $invoice->id,
                    ],
                ]);
            }

            Log::error('PayOS createInvoicePaymentLink returned unsuccessful', [
                'result' => $result,
                'invoice_id' => $invoice->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo link thanh toán',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Exception $e) {
            Log::error('PayOSController@createInvoicePaymentLink failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = $e->getMessage();
            
            if (str_contains($errorMessage, 'Could not resolve host') || 
                str_contains($errorMessage, 'Connection')) {
                $errorMessage = 'Không thể kết nối đến PayOS. Vui lòng kiểm tra kết nối mạng hoặc thử lại sau.';
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], 500);
        }
    }

    /**
     * Webhook nhận kết quả thanh toán từ PayOS
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $webhookData = $request->all();

            Log::info('PayOS webhook received', [
                'headers' => $request->headers->all(),
                'data' => $webhookData,
                'raw_body' => $request->getContent(),
            ]);

            // Xác thực webhook
            $verificationResult = $this->payOSService->verifyWebhook($webhookData);
            
            if (!$verificationResult) {
                Log::warning('PayOS webhook verification failed', [
                    'data' => $webhookData,
                    'has_signature' => isset($webhookData['signature']),
                    'has_data' => isset($webhookData['data']),
                    'checksum_key_set' => !empty(config('services.payos.checksum_key')),
                ]);

                // Trả về 200 để PayOS không retry (theo best practice)
                // Nhưng log để admin biết có vấn đề
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 200); // Đổi từ 400 sang 200 để PayOS không retry
            }

            // Theo tài liệu PayOS: webhook có format {code, desc, success, data, signature}
            $data = $webhookData['data'] ?? [];
            $code = $webhookData['code'] ?? $data['code'] ?? null;
            $desc = $webhookData['desc'] ?? $data['desc'] ?? null;
            $orderCode = $data['orderCode'] ?? null;
            $amount = $data['amount'] ?? null;
            $paymentLinkId = $data['paymentLinkId'] ?? null;

            if (!$orderCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing order code',
                ], 400);
            }

            // Kiểm tra code: "00" = thành công, các mã khác = lỗi/hủy
            $isSuccess = ($code === '00' || $desc === 'Thành công' || $desc === 'success');

            // Tìm payment theo transaction_id (orderCode hoặc paymentLinkId)
            $payment = Payment::where('transaction_id', (string) $orderCode)
                ->orWhere('transaction_id', $paymentLinkId)
                ->orWhere('transaction_id', 'like', "%{$orderCode}%")
                ->first();

            if (!$payment) {
                Log::warning('PayOS webhook: Payment not found', [
                    'order_code' => $orderCode,
                    'payment_link_id' => $paymentLinkId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            DB::beginTransaction();

            // Cập nhật payment status dựa trên code và desc
            $paymentStatus = 'pending';
            if ($isSuccess) {
                $paymentStatus = 'success';
            } elseif ($code && $code !== '00') {
                $paymentStatus = 'failed';
            }

            $payment->update([
                'status' => $paymentStatus,
                'paid_at' => $isSuccess ? now() : $payment->paid_at,
            ]);

            // Cập nhật booking và invoice
            $invoice = $payment->invoice;
            $booking = $invoice->bookingOrder ?? null;

            if ($booking && $isSuccess) {
                // Kiểm tra xem đây là thanh toán cọc hay thanh toán invoice sau checkout
                $isCheckoutPayment = in_array($booking->status, ['checked_out', 'partially_checked_out', 'completed']);
                
                Log::info('PayOS webhook: Processing payment', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'booking_id' => $booking->id,
                    'booking_status' => $booking->status,
                    'is_checkout_payment' => $isCheckoutPayment,
                    'payment_amount' => $payment->amount,
                ]);

                if ($isCheckoutPayment) {
                    // THANH TOÁN INVOICE SAU CHECKOUT
                    // Tính tổng số tiền đã thanh toán cho invoice này (bao gồm payment hiện tại đã được cập nhật status)
                    $totalPaidForInvoice = $invoice->payments()
                        ->whereIn('status', ['success', 'paid'])
                        ->sum('amount');
                    
                    // Cập nhật invoice status nếu đã thanh toán đầy đủ
                    if ($totalPaidForInvoice >= $invoice->total_amount) {
                        $invoice->update(['status' => 'paid']);
                        
                        // Cập nhật booking status thành completed
                        if ($booking->status !== 'completed') {
                            $booking->update(['status' => 'completed']);
                        }
                        
                        // Cập nhật booking payment_status thành paid
                        $booking->update(['payment_status' => 'paid']);
                        
                        Log::info('PayOS webhook: Invoice payment completed', [
                            'invoice_id' => $invoice->id,
                            'booking_id' => $booking->id,
                            'total_paid' => $totalPaidForInvoice,
                            'invoice_total' => $invoice->total_amount,
                        ]);
                    } else {
                        Log::info('PayOS webhook: Invoice payment partial', [
                            'invoice_id' => $invoice->id,
                            'total_paid' => $totalPaidForInvoice,
                            'invoice_total' => $invoice->total_amount,
                        ]);
                    }
                } else {
                    // THANH TOÁN CỌC (DEPOSIT)
                    // Cập nhật paid_amount và payment_status của booking
                    $newPaidAmount = ($booking->paid_amount ?? 0) + $payment->amount;
                    $bookingPaymentStatus = 'partial';
                    
                    if ($newPaidAmount >= $booking->total_amount) {
                        $bookingPaymentStatus = 'paid';
                        $newPaidAmount = $booking->total_amount;
                    }

                    $booking->update([
                        'paid_amount' => $newPaidAmount,
                        'payment_status' => $bookingPaymentStatus,
                        'status' => 'confirmed',
                    ]);

                    // Đảm bảo invoice có invoice items cho tiền phòng (nếu chưa có)
                    $hasRoomChargeItems = InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('item_type', 'room_charge')
                        ->exists();

                    if (!$hasRoomChargeItems) {
                        // Tạo invoice items cho tiền phòng từ booking details
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
                            }
                        }
                    }

                    // Kiểm tra xem đã có InvoiceItem cho deposit chưa
                    $existingDepositItem = InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('item_type', 'deposit')
                        ->first();

                    Log::info('PayOS webhook: Checking deposit item', [
                        'invoice_id' => $invoice->id,
                        'has_existing_deposit' => $existingDepositItem ? true : false,
                        'payment_amount' => $payment->amount,
                        'new_paid_amount' => $newPaidAmount,
                    ]);

                    if (!$existingDepositItem) {
                        // Tạo InvoiceItem cho deposit (giá trị âm để trừ vào tổng tiền)
                        try {
                            $depositItem = InvoiceItem::create([
                                'invoice_id' => $invoice->id,
                                'description' => 'Tiền cọc đã thanh toán (PayOS)',
                                'quantity' => 1,
                                'unit_price' => -$payment->amount, // Giá trị âm để trừ
                                'total_line' => -$payment->amount, // Giá trị âm để trừ
                                'item_type' => 'deposit',
                            ]);
                            
                            Log::info('PayOS webhook: Deposit item created successfully', [
                                'deposit_item_id' => $depositItem->id,
                                'invoice_id' => $invoice->id,
                                'amount' => -$payment->amount,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('PayOS webhook: Failed to create deposit item', [
                                'invoice_id' => $invoice->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            throw $e; // Re-throw để rollback transaction
                        }
                    } else {
                        // Nếu đã có deposit item, cập nhật nó với tổng số tiền đã trả
                        $existingDepositItem->update([
                            'description' => 'Tiền cọc đã thanh toán (PayOS)',
                            'unit_price' => -$newPaidAmount,
                            'total_line' => -$newPaidAmount,
                        ]);
                        
                        Log::info('PayOS webhook: Deposit item updated', [
                            'deposit_item_id' => $existingDepositItem->id,
                            'invoice_id' => $invoice->id,
                            'new_amount' => -$newPaidAmount,
                        ]);
                    }

                    // Tính lại invoice total_amount (chỉ tính các item chưa thanh toán)
                    $invoice->recalculateTotalAmount();
                    
                    Log::info('PayOS webhook: Recalculating invoice total', [
                        'invoice_id' => $invoice->id,
                        'new_total_amount' => $invoice->total_amount,
                    ]);

                    // Cập nhật invoice status
                    if ($bookingPaymentStatus === 'paid') {
                        $invoice->update(['status' => 'paid']);
                    }

                    Log::info('PayOS webhook: Invoice updated with deposit', [
                        'invoice_id' => $invoice->id,
                        'payment_amount' => $payment->amount,
                        'new_paid_amount' => $newPaidAmount,
                        'new_total_amount' => $newTotalAmount,
                        'invoice_items_count' => $allItems->count(),
                    ]);
                }
            }

            DB::commit();

            Log::info('PayOS webhook processed', [
                'payment_id' => $payment->id,
                'order_code' => $orderCode,
                'code' => $code,
                'desc' => $desc,
                'is_success' => $isSuccess,
                'payment_status' => $paymentStatus,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('PayOSController@webhook failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook',
            ], 500);
        }
    }

    /**
     * Kiểm tra trạng thái thanh toán
     */
    public function checkPaymentStatus(Request $request, int $orderCode): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $paymentInfo = $this->payOSService->getPaymentInfo($orderCode);

            return response()->json([
                'success' => true,
                'data' => $paymentInfo['data'],
            ]);
        } catch (\Exception $e) {
            Log::error('PayOSController@checkPaymentStatus failed', [
                'order_code' => $orderCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể kiểm tra trạng thái thanh toán',
            ], 500);
        }
    }
}

