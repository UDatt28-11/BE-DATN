<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayOSService;
use App\Models\BookingOrder;
use App\Models\Payment;
use App\Models\Invoice;
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
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            $paymentData = [
                'orderCode' => $orderCode,
                'amount' => (int) $validated['amount'],
                'description' => $validated['description'] ?? "Thanh toán đặt cọc đơn #{$booking->order_code}",
                'returnUrl' => $frontendUrl . '/payment/success?booking_id=' . $booking->id,
                'cancelUrl' => $frontendUrl . '/payment/cancel?booking_id=' . $booking->id,
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
                    $invoice = Invoice::create([
                        'booking_order_id' => $booking->id,
                        'issue_date' => now(),
                        'due_date' => now()->addDays(7),
                        'total_amount' => $booking->total_amount,
                        'status' => 'pending',
                    ]);
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
                // Cập nhật paid_amount và payment_status của booking
                $newPaidAmount = ($booking->paid_amount ?? 0) + $payment->amount;
                $paymentStatus = 'partial';
                
                if ($newPaidAmount >= $booking->total_amount) {
                    $paymentStatus = 'paid';
                    $newPaidAmount = $booking->total_amount;
                }

                $booking->update([
                    'paid_amount' => $newPaidAmount,
                    'payment_status' => $paymentStatus,
                    'status' => 'confirmed',
                ]);

                // Cập nhật invoice status
                if ($paymentStatus === 'paid') {
                    $invoice->update(['status' => 'paid']);
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

