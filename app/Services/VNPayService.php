<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class VNPayService
{
    private string $tmnCode;
    private string $hashSecret;
    private string $vnpUrl;
    private string $apiUrl;
    private string $version;
    private string $command;
    private string $currCode;
    private string $locale;

    public function __construct()
    {
        $this->tmnCode = config('services.vnpay.tmn_code', '');
        $this->hashSecret = config('services.vnpay.hash_secret', '');
        $this->vnpUrl = config('services.vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->apiUrl = config('services.vnpay.api_url', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction');
        $this->version = config('services.vnpay.version', '2.1.0');
        $this->command = config('services.vnpay.command', 'pay');
        $this->currCode = config('services.vnpay.curr_code', 'VND');
        $this->locale = config('services.vnpay.locale', 'vn');
    }

    /**
     * Kiểm tra cấu hình VNPAY
     */
    public function validateConfig(): bool
    {
        if (empty($this->tmnCode) || empty($this->hashSecret)) {
            throw new Exception('VNPAY credentials chưa được cấu hình. Vui lòng kiểm tra VNP_TMN_CODE và VNP_HASH_SECRET trong file .env');
        }
        return true;
    }

    /**
     * Tạo URL thanh toán VNPAY
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createPaymentUrl(array $data): array
    {
        try {
            $this->validateConfig();

            // Validate required fields
            if (!isset($data['amount']) || $data['amount'] < 10000) {
                throw new Exception('Số tiền thanh toán phải tối thiểu 10,000 VNĐ');
            }

            if (!isset($data['order_code'])) {
                throw new Exception('Mã đơn hàng (order_code) là bắt buộc');
            }

            // Lấy IP address
            $ipAddress = request()->ip() ?? '127.0.0.1';
            if ($ipAddress === '::1') {
                $ipAddress = '127.0.0.1';
            }

            // Tạo thời gian - VNPAY yêu cầu múi giờ GMT+7 (Asia/Ho_Chi_Minh)
            $timezone = new \DateTimeZone('Asia/Ho_Chi_Minh');
            $now = new \DateTime('now', $timezone);
            $createDate = $now->format('YmdHis');
            
            // Thời gian hết hạn: 30 phút từ lúc tạo
            $expireTime = clone $now;
            $expireTime->modify('+30 minutes');
            $expireDate = $expireTime->format('YmdHis');

            // Build URL callback
            $backendUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));
            $returnUrl = $data['return_url'] ?? $backendUrl . '/api/vnpay/return';

            // Các tham số VNPAY
            $vnpParams = [
                'vnp_Version' => $this->version,
                'vnp_Command' => $this->command,
                'vnp_TmnCode' => $this->tmnCode,
                'vnp_Amount' => (int) ($data['amount'] * 100), // VNPAY yêu cầu nhân 100
                'vnp_CurrCode' => $this->currCode,
                'vnp_TxnRef' => $data['order_code'],
                'vnp_OrderInfo' => $data['description'] ?? 'Thanh toan don hang ' . $data['order_code'],
                'vnp_OrderType' => $data['order_type'] ?? 'other',
                'vnp_Locale' => $this->locale,
                'vnp_ReturnUrl' => $returnUrl,
                'vnp_IpAddr' => $ipAddress,
                'vnp_CreateDate' => $createDate,
                'vnp_ExpireDate' => $expireDate,
            ];

            // Thêm bank code nếu có (cho phép chọn ngân hàng cụ thể)
            if (!empty($data['bank_code'])) {
                $vnpParams['vnp_BankCode'] = $data['bank_code'];
            }

            // Sắp xếp theo key và tạo query string
            ksort($vnpParams);
            $query = '';
            $hashData = '';
            $i = 0;

            foreach ($vnpParams as $key => $value) {
                if ($i == 1) {
                    $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
                } else {
                    $hashData .= urlencode($key) . '=' . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . '=' . urlencode($value) . '&';
            }

            // Tạo secure hash
            $vnpSecureHash = hash_hmac('sha512', $hashData, $this->hashSecret);
            $paymentUrl = $this->vnpUrl . '?' . $query . 'vnp_SecureHash=' . $vnpSecureHash;

            Log::info('VNPay: Created payment URL', [
                'order_code' => $data['order_code'],
                'amount' => $data['amount'],
                'vnp_amount' => $vnpParams['vnp_Amount'],
                'tmn_code' => $this->tmnCode,
                'return_url' => $returnUrl,
                'create_date' => $createDate,
                'expire_date' => $expireDate,
                'hash_data' => $hashData,
            ]);

            return [
                'success' => true,
                'data' => [
                    'payment_url' => $paymentUrl,
                    'order_code' => $data['order_code'],
                    'amount' => $data['amount'],
                    'expire_date' => $expireDate,
                ],
            ];
        } catch (Exception $e) {
            Log::error('VNPay: Create payment URL failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Xác thực response từ VNPAY
     *
     * @param array $vnpParams Tất cả params từ query string (trừ vnp_SecureHash và vnp_SecureHashType)
     * @param string $receivedHash vnp_SecureHash từ VNPAY
     * @return bool
     */
    public function verifyReturnUrl(array $vnpParams, string $receivedHash): bool
    {
        try {
            // Loại bỏ hash khỏi params
            unset($vnpParams['vnp_SecureHash']);
            unset($vnpParams['vnp_SecureHashType']);

            // Sắp xếp theo key
            ksort($vnpParams);

            // Tạo hash data
            $hashData = '';
            $i = 0;
            foreach ($vnpParams as $key => $value) {
                if ($i == 1) {
                    $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
                } else {
                    $hashData .= urlencode($key) . '=' . urlencode($value);
                    $i = 1;
                }
            }

            // Tạo secure hash để so sánh
            $computedHash = hash_hmac('sha512', $hashData, $this->hashSecret);

            return hash_equals($computedHash, $receivedHash);
        } catch (Exception $e) {
            Log::error('VNPay: Verify return URL failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Kiểm tra trạng thái giao dịch
     *
     * @param string $responseCode vnp_ResponseCode từ VNPAY
     * @return array
     */
    public function getTransactionStatus(string $responseCode): array
    {
        $messages = [
            '00' => 'Giao dịch thành công',
            '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường)',
            '09' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng',
            '10' => 'Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần',
            '11' => 'Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch',
            '12' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa',
            '13' => 'Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Xin quý khách vui lòng thực hiện lại giao dịch',
            '24' => 'Giao dịch không thành công do: Khách hàng hủy giao dịch',
            '51' => 'Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch',
            '65' => 'Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày',
            '75' => 'Ngân hàng thanh toán đang bảo trì',
            '79' => 'Giao dịch không thành công do: KH nhập sai mật khẩu thanh toán quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch',
            '99' => 'Các lỗi khác (lỗi còn lại, không có trong danh sách mã lỗi đã liệt kê)',
        ];

        $isSuccess = $responseCode === '00';
        $message = $messages[$responseCode] ?? 'Lỗi không xác định';

        return [
            'success' => $isSuccess,
            'code' => $responseCode,
            'message' => $message,
        ];
    }

    /**
     * Truy vấn trạng thái giao dịch từ VNPAY API
     *
     * @param string $txnRef Mã giao dịch
     * @param string $transDate Ngày giao dịch (yyyyMMddHHmmss)
     * @return array
     */
    public function queryTransaction(string $txnRef, string $transDate): array
    {
        try {
            $this->validateConfig();

            $ipAddress = request()->ip() ?? '127.0.0.1';
            if ($ipAddress === '::1') {
                $ipAddress = '127.0.0.1';
            }

            $vnpParams = [
                'vnp_Version' => $this->version,
                'vnp_Command' => 'querydr',
                'vnp_TmnCode' => $this->tmnCode,
                'vnp_TxnRef' => $txnRef,
                'vnp_OrderInfo' => 'Query transaction ' . $txnRef,
                'vnp_TransactionDate' => $transDate,
                'vnp_CreateDate' => date('YmdHis'),
                'vnp_IpAddr' => $ipAddress,
            ];

            ksort($vnpParams);
            $hashData = '';
            $i = 0;

            foreach ($vnpParams as $key => $value) {
                if ($i == 1) {
                    $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
                } else {
                    $hashData .= urlencode($key) . '=' . urlencode($value);
                    $i = 1;
                }
            }

            $vnpSecureHash = hash_hmac('sha512', $hashData, $this->hashSecret);
            $vnpParams['vnp_SecureHash'] = $vnpSecureHash;

            $response = \Illuminate\Support\Facades\Http::withOptions([
                'verify' => false,
            ])->post($this->apiUrl, $vnpParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Query transaction failed',
                'response' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('VNPay: Query transaction failed', [
                'error' => $e->getMessage(),
                'txn_ref' => $txnRef,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Yêu cầu hoàn tiền
     *
     * @param array $data
     * @return array
     */
    public function refund(array $data): array
    {
        try {
            $this->validateConfig();

            $ipAddress = request()->ip() ?? '127.0.0.1';
            if ($ipAddress === '::1') {
                $ipAddress = '127.0.0.1';
            }

            $vnpParams = [
                'vnp_Version' => $this->version,
                'vnp_Command' => 'refund',
                'vnp_TmnCode' => $this->tmnCode,
                'vnp_TxnRef' => $data['txn_ref'],
                'vnp_Amount' => (int) ($data['amount'] * 100),
                'vnp_OrderInfo' => $data['description'] ?? 'Hoan tien don hang ' . $data['txn_ref'],
                'vnp_TransactionType' => $data['type'] ?? '02', // 02: Hoàn toàn bộ, 03: Hoàn một phần
                'vnp_TransactionDate' => $data['trans_date'],
                'vnp_CreateDate' => date('YmdHis'),
                'vnp_CreateBy' => $data['created_by'] ?? 'admin',
                'vnp_IpAddr' => $ipAddress,
            ];

            ksort($vnpParams);
            $hashData = '';
            $i = 0;

            foreach ($vnpParams as $key => $value) {
                if ($i == 1) {
                    $hashData .= '&' . urlencode($key) . '=' . urlencode($value);
                } else {
                    $hashData .= urlencode($key) . '=' . urlencode($value);
                    $i = 1;
                }
            }

            $vnpSecureHash = hash_hmac('sha512', $hashData, $this->hashSecret);
            $vnpParams['vnp_SecureHash'] = $vnpSecureHash;

            $response = \Illuminate\Support\Facades\Http::withOptions([
                'verify' => false,
            ])->post($this->apiUrl, $vnpParams);

            if ($response->successful()) {
                $result = $response->json();
                $isSuccess = ($result['vnp_ResponseCode'] ?? '') === '00';

                return [
                    'success' => $isSuccess,
                    'data' => $result,
                    'message' => $isSuccess ? 'Yêu cầu hoàn tiền thành công' : ($result['vnp_Message'] ?? 'Yêu cầu hoàn tiền thất bại'),
                ];
            }

            return [
                'success' => false,
                'error' => 'Refund request failed',
                'response' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('VNPay: Refund failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lấy danh sách ngân hàng hỗ trợ
     */
    public function getSupportedBanks(): array
    {
        return [
            'VNPAYQR' => 'VNPAYQR - Thanh toán quét mã QR',
            'VNBANK' => 'VNBANK - Thẻ ATM - Tài khoản ngân hàng nội địa',
            'INTCARD' => 'INTCARD - Thẻ thanh toán quốc tế',
            // Ngân hàng cụ thể
            'NCB' => 'NCB - Ngân hàng NCB',
            'SACOMBANK' => 'SACOMBANK - Ngân hàng SACOMBANK',
            'EXIMBANK' => 'EXIMBANK - Ngân hàng EXIMBANK',
            'MSBANK' => 'MSBANK - Ngân hàng MSBANK',
            'NAMABANK' => 'NAMABANK - Ngân hàng NAMABANK',
            'VNMART' => 'VNMART - Ví điện tử VnMart',
            'VIETINBANK' => 'VIETINBANK - Ngân hàng Vietinbank',
            'VIETCOMBANK' => 'VIETCOMBANK - Ngân hàng VCB',
            'HDBANK' => 'HDBANK - Ngân hàng HDBank',
            'DONGABANK' => 'DONGABANK - Ngân hàng Đông Á',
            'TPBANK' => 'TPBANK - Ngân hàng TPBank',
            'OJB' => 'OJB - Ngân hàng OceanBank',
            'BIDV' => 'BIDV - Ngân hàng BIDV',
            'TECHCOMBANK' => 'TECHCOMBANK - Ngân hàng Techcombank',
            'VPBANK' => 'VPBANK - Ngân hàng VPBank',
            'AGRIBANK' => 'AGRIBANK - Ngân hàng Agribank',
            'MBBANK' => 'MBBANK - Ngân hàng MBBank',
            'ACB' => 'ACB - Ngân hàng ACB',
            'OCB' => 'OCB - Ngân hàng OCB',
            'SHB' => 'SHB - Ngân hàng SHB',
            'IVB' => 'IVB - Ngân hàng IVB',
        ];
    }
}
