<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;
use PayOS\PayOS;

class PayOSService
{
    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private string $baseUrl;
    private PayOS $sdk;

    public function __construct()
    {
        $this->clientId = config('services.payos.client_id', '');
        $this->apiKey = config('services.payos.api_key', '');
        $this->checksumKey = config('services.payos.checksum_key', '');
        // Production API: https://api-merchant.payos.vn
        $this->baseUrl = config('services.payos.base_url', 'https://api-merchant.payos.vn');

        // Khởi tạo SDK PayOS giống SportZone
        $this->sdk = new PayOS($this->clientId, $this->apiKey, $this->checksumKey);
    }

    /**
     * Tạo signature cho PayOS request
     */
    private function createSignature(array $data): string
    {
        // Sort data by key alphabetically and create signature string
        $signData = "amount={$data['amount']}&cancelUrl={$data['cancelUrl']}&description={$data['description']}&orderCode={$data['orderCode']}&returnUrl={$data['returnUrl']}";
        return hash_hmac('sha256', $signData, $this->checksumKey);
    }

    /**
     * Tạo signature từ response data để verify
     */
    private function createSignatureFromResponse(array $data): string
    {
        // Lấy các field cần thiết từ response theo thứ tự alphabet
        $fields = ['accountNumber', 'amount', 'bin', 'checkoutUrl', 'currency', 'description', 
                   'orderCode', 'paymentLinkId', 'qrCode', 'status'];
        
        $signParts = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $signParts[] = "{$field}={$data[$field]}";
            }
        }
        
        $signData = implode('&', $signParts);
        return hash_hmac('sha256', $signData, $this->checksumKey);
    }

    /**
     * Custom implementation of createPaymentLink using Laravel HTTP client
     * This handles SSL certificate issues better on Windows
     */
    private function createPaymentLinkCustom(array $requestData): array
    {
        $url = $this->baseUrl . '/v2/payment-requests';
        
        // Create signature
        $signature = $this->createSignature($requestData);
        $requestData['signature'] = $signature;
        
        Log::info('PayOS Custom: Making request', [
            'url' => $url,
            'orderCode' => $requestData['orderCode'],
        ]);
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-client-id' => $this->clientId,
                'x-api-key' => $this->apiKey,
            ])
            ->withOptions([
                'verify' => false, // Disable SSL verification for development (Windows issue)
            ])
            ->timeout(30)
            ->post($url, $requestData);
            
            Log::info('PayOS Custom: Response received', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            if (!$response->successful()) {
                throw new Exception("PayOS API returned HTTP {$response->status()}: {$response->body()}");
            }
            
            $json = $response->json();
            
            if (!$json) {
                throw new Exception('PayOS API returned invalid JSON');
            }
            
            if (($json['code'] ?? '') !== '00') {
                throw new Exception($json['desc'] ?? 'PayOS API error: ' . ($json['code'] ?? 'unknown'));
            }
            
            return $json['data'] ?? [];
            
        } catch (Exception $e) {
            Log::error('PayOS Custom: Request failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Tạo payment link từ PayOS
     * 
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createPaymentLink(array $data): array
    {
        try {
            // Validate credentials
            if (empty($this->clientId) || empty($this->apiKey)) {
                throw new Exception('PayOS credentials chưa được cấu hình. Vui lòng kiểm tra PAYOS_CLIENT_ID và PAYOS_API_KEY trong file .env');
            }

            $orderCode = $data['orderCode'] ?? time();
            $amount = $data['amount'];
            $description = $data['description'] ?? 'Thanh toán đặt phòng';
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            $returnUrl = $data['returnUrl'] ?? $frontendUrl . '/payment/success';
            $cancelUrl = $data['cancelUrl'] ?? $frontendUrl . '/payment/cancel';
            
            // Validate orderCode: phải là số nguyên dương, tối đa 19 chữ số
            if (!is_numeric($orderCode) || $orderCode <= 0 || $orderCode > 9999999999999999999) {
                throw new Exception('orderCode phải là số nguyên dương, tối đa 19 chữ số');
            }
            
            // Validate amount: tối thiểu 1000 VNĐ
            if ($amount < 1000) {
                throw new Exception('Số tiền thanh toán phải tối thiểu 1,000 VNĐ');
            }
            
            // Validate URLs
            if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('returnUrl không hợp lệ');
            }
            if (!filter_var($cancelUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('cancelUrl không hợp lệ');
            }
            
            // Cảnh báo nếu dùng localhost (PayOS có thể không chấp nhận)
            if (str_contains($returnUrl, 'localhost') || str_contains($cancelUrl, 'localhost')) {
                Log::warning('PayOS: Using localhost in returnUrl/cancelUrl - PayOS may reject this', [
                    'returnUrl' => $returnUrl,
                    'cancelUrl' => $cancelUrl,
                    'suggestion' => 'Use ngrok or a public domain instead of localhost',
                ]);
            }

            // PayOS yêu cầu amount >= 1000 VNĐ
            if ($amount < 1000) {
                throw new Exception('Số tiền thanh toán phải tối thiểu 1,000 VNĐ');
            }

            // Validate và format items
            $items = $data['items'] ?? [
                [
                    'name' => $description,
                    'quantity' => 1,
                    'price' => (int) $amount,
                ]
            ];
            
            // Đảm bảo items là array và có format đúng
            if (!is_array($items) || empty($items)) {
                throw new Exception('items phải là array không rỗng');
            }
            
            // Validate và format từng item - đảm bảo đúng kiểu dữ liệu
            $formattedItems = [];
            foreach ($items as $index => $item) {
                if (!isset($item['name']) || !isset($item['quantity']) || !isset($item['price'])) {
                    throw new Exception("Item tại index {$index} thiếu field bắt buộc (name, quantity, price)");
                }
                
                // Sanitize item name - loại bỏ ký tự đặc biệt có thể gây lỗi
                $itemName = (string) $item['name'];
                $itemName = mb_substr($itemName, 0, 255); // Giới hạn 255 ký tự
                // Loại bỏ tất cả ký tự đặc biệt, chỉ giữ chữ, số, khoảng trắng, dấu chấm, phẩy, gạch ngang
                $itemName = preg_replace('/[^\p{L}\p{N}\s.,\-]/u', '', $itemName);
                $itemName = trim($itemName);
                
                // Format item theo đúng yêu cầu PayOS: name (string), quantity (int), price (int)
                $formattedItems[] = [
                    'name' => $itemName,
                    'quantity' => (int) $item['quantity'],
                    'price' => (int) $item['price'],
                ];
            }
            
            // Đảm bảo tổng amount khớp với tổng items (price * quantity)
            // PayOS yêu cầu: sum(items[i].price * items[i].quantity) === amount
            $itemsTotal = 0;
            foreach ($formattedItems as $item) {
                $itemsTotal += ($item['price'] * $item['quantity']);
            }
            
            if ($itemsTotal !== (int) $amount) {
                Log::warning('PayOS items total mismatch', [
                    'items_total' => $itemsTotal,
                    'amount' => $amount,
                    'difference' => (int) $amount - $itemsTotal,
                ]);
                // Điều chỉnh item đầu tiên để tổng khớp
                if (!empty($formattedItems)) {
                    $diff = (int) $amount - $itemsTotal;
                    $formattedItems[0]['price'] += (int) ($diff / $formattedItems[0]['quantity']);
                }
            }
            
            // Sanitize description - PayOS yêu cầu mô tả khá ngắn, SDK trả về lỗi nếu quá dài
            // Cắt description tối đa 25 ký tự, sau đó loại bỏ ký tự đặc biệt
            $sanitizedDescription = mb_substr($description, 0, 25);
            // Loại bỏ tất cả ký tự đặc biệt, chỉ giữ chữ, số, khoảng trắng, dấu chấm, phẩy, gạch ngang
            $sanitizedDescription = preg_replace('/[^\p{L}\p{N}\s.,\-]/u', '', $sanitizedDescription);
            $sanitizedDescription = trim($sanitizedDescription);
            
            // Payload gửi lên PayOS - giữ đơn giản, gần giống SportZone
            $requestData = [
                'orderCode' => (int) $orderCode,
                'amount' => (int) $amount,
                'description' => $sanitizedDescription,
                'items' => $formattedItems,
                'returnUrl' => (string) $returnUrl,
                'cancelUrl' => (string) $cancelUrl,
            ];

            // Thêm buyer info nếu có - chỉ thêm nếu không rỗng
            // Sanitize buyer info để tránh ký tự đặc biệt
            if (!empty($data['buyerName'])) {
                $buyerName = (string) $data['buyerName'];
                // Giới hạn độ dài và loại bỏ ký tự đặc biệt nguy hiểm
                $buyerName = mb_substr($buyerName, 0, 100);
                // Chỉ giữ chữ, số, khoảng trắng
                $buyerName = preg_replace('/[^\p{L}\p{N}\s]/u', '', $buyerName);
                $buyerName = trim($buyerName);
                if (!empty($buyerName)) {
                    $requestData['buyerName'] = $buyerName;
                }
            }
            if (!empty($data['buyerEmail'])) {
                $buyerEmail = filter_var($data['buyerEmail'], FILTER_SANITIZE_EMAIL);
                if (filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
                    $requestData['buyerEmail'] = $buyerEmail;
                }
            }
            if (!empty($data['buyerPhone'])) {
                // Chỉ giữ lại số và một số ký tự đặc biệt
                $buyerPhone = preg_replace('/[^0-9+\-() ]/', '', (string) $data['buyerPhone']);
                $buyerPhone = mb_substr($buyerPhone, 0, 20);
                $requestData['buyerPhone'] = $buyerPhone;
            }

            // Validate request data cơ bản
            if (empty($returnUrl) || empty($cancelUrl)) {
                throw new Exception('returnUrl và cancelUrl không được để trống');
            }
            
            // PayOS không chấp nhận localhost trong returnUrl và cancelUrl
            // Cho phép bypass validation trong development mode nếu có PAYOS_ALLOW_LOCALHOST=true
            // Lưu ý: PayOS API vẫn sẽ reject localhost, chỉ bypass validation trong code
            $allowLocalhost = filter_var(env('PAYOS_ALLOW_LOCALHOST', false), FILTER_VALIDATE_BOOLEAN);
            $isReturnUrlLocalhost = str_contains($returnUrl, 'localhost') || str_contains($returnUrl, '127.0.0.1');
            $isCancelUrlLocalhost = str_contains($cancelUrl, 'localhost') || str_contains($cancelUrl, '127.0.0.1');
            
            if ($isReturnUrlLocalhost && !$allowLocalhost) {
                throw new Exception('returnUrl không được dùng localhost. Vui lòng dùng public URL (ngrok/cloudflared tunnel) hoặc domain thật. Hoặc set PAYOS_ALLOW_LOCALHOST=true trong .env để bypass validation (chỉ dùng cho development, PayOS API vẫn sẽ reject).');
            }
            
            if ($isCancelUrlLocalhost && !$allowLocalhost) {
                throw new Exception('cancelUrl không được dùng localhost. Vui lòng dùng public URL (ngrok/cloudflared tunnel) hoặc domain thật. Hoặc set PAYOS_ALLOW_LOCALHOST=true trong .env để bypass validation (chỉ dùng cho development, PayOS API vẫn sẽ reject).');
            }
            
            // Cảnh báo nếu dùng localhost với bypass
            if (($isReturnUrlLocalhost || $isCancelUrlLocalhost) && $allowLocalhost) {
                Log::warning('PayOS: Using localhost with PAYOS_ALLOW_LOCALHOST=true - PayOS API will still reject this', [
                    'returnUrl' => $returnUrl,
                    'cancelUrl' => $cancelUrl,
                    'note' => 'This bypasses code validation only. PayOS API will still reject localhost URLs.',
                ]);
            }
            
            // Validate URL format
            if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('returnUrl không hợp lệ');
            }
            
            if (!filter_var($cancelUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('cancelUrl không hợp lệ');
            }

            if (empty($requestData['items']) || !is_array($requestData['items'])) {
                throw new Exception('items không được để trống và phải là array');
            }

            Log::info('PayOS creating payment link', [
                'order_code' => $orderCode,
                'amount' => $amount,
                'base_url' => $this->baseUrl,
                'has_client_id' => !empty($this->clientId),
                'has_api_key' => !empty($this->apiKey),
                'request_data' => $requestData,
            ]);

            // Sử dụng custom implementation thay vì SDK để xử lý SSL issue trên Windows
            // SDK PayOS sử dụng curl trực tiếp và không xử lý SSL certificate tốt
            $response = $this->createPaymentLinkCustom($requestData);

            Log::info('PayOS createPaymentLink response', [
                'response' => $response,
                'response_type' => gettype($response),
            ]);

            // Response từ custom implementation đã là data array
            $data = $response;

            // Nếu response vẫn có code/desc, kiểm tra thêm
            $responseCode = $data['code'] ?? $response['code'] ?? null;
            $responseDesc = $data['desc'] ?? $response['desc'] ?? '';
            if ($responseCode !== null && $responseCode !== '00') {
                Log::error('PayOS SDK returned error code', [
                    'order_code' => $orderCode,
                    'code' => $responseCode,
                    'desc' => $responseDesc,
                    'full_response' => $response,
                ]);
                throw new Exception($responseDesc ?: "PayOS API error: Code {$responseCode}");
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error('PayOSService@createPaymentLink (SDK) exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Xác thực webhook từ PayOS
     * 
     * @param array $webhookData
     * @return bool
     */
    public function verifyWebhook(array $webhookData): bool
    {
        try {
            // Kiểm tra checksum key
            if (empty($this->checksumKey)) {
                Log::error('PayOS checksum key is not set');
                return false;
            }

            $data = $webhookData['data'] ?? [];
            $signature = $webhookData['signature'] ?? '';

            if (empty($signature)) {
                Log::warning('PayOS webhook: Missing signature', [
                    'webhook_keys' => array_keys($webhookData),
                ]);
                return false;
            }

            if (empty($data)) {
                Log::warning('PayOS webhook: Missing data', [
                    'webhook_keys' => array_keys($webhookData),
                ]);
                return false;
            }

            // Tạo checksum từ data
            // PayOS yêu cầu JSON encode với JSON_UNESCAPED_UNICODE và không có spaces
            $dataString = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $calculatedSignature = hash_hmac('sha256', $dataString, $this->checksumKey);

            $isValid = hash_equals($signature, $calculatedSignature);

            if (!$isValid) {
                Log::warning('PayOS signature mismatch', [
                    'received_signature' => substr($signature, 0, 20) . '...',
                    'calculated_signature' => substr($calculatedSignature, 0, 20) . '...',
                    'data_string_length' => strlen($dataString),
                    'data_string_preview' => substr($dataString, 0, 200),
                ]);
            }

            return $isValid;
        } catch (Exception $e) {
            Log::error('PayOSService@verifyWebhook exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Lấy thông tin payment từ PayOS
     * 
     * @param int $orderCode
     * @return array
     * @throws Exception
     */
    public function getPaymentInfo(int $orderCode): array
    {
        try {
            // Dùng SDK PayOS giống SportZone
            $result = $this->sdk->getPaymentLinkInfomation($orderCode);

            return [
                'success' => true,
                'data' => $result['data'] ?? $result,
            ];
        } catch (Exception $e) {
            Log::error('PayOSService@getPaymentInfo exception', [
                'order_code' => $orderCode,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Hủy payment link
     * 
     * @param int $orderCode
     * @return array
     * @throws Exception
     */
    public function cancelPaymentLink(int $orderCode): array
    {
        try {
            // Dùng SDK để hủy payment link
            $result = $this->sdk->cancelPaymentLink($orderCode, []);

            return [
                'success' => true,
                'data' => $result['data'] ?? $result,
            ];
        } catch (Exception $e) {
            Log::error('PayOSService@cancelPaymentLink exception', [
                'order_code' => $orderCode,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

