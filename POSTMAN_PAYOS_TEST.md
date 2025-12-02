# 🧪 Test PayOS với Postman - Hướng Dẫn Đầy Đủ

## 📋 Tổng Quan

Có 3 endpoint chính để test:

1. **Create Payment Link** - Tạo link thanh toán (cần auth)
2. **Webhook** - Nhận callback từ PayOS (public, không cần auth)
3. **Check Payment Status** - Kiểm tra trạng thái thanh toán (cần auth)

---

## 🔑 Bước 1: Lấy Authentication Token

### Endpoint: Login

**Method:** `POST`  
**URL:** `http://localhost:8000/api/login`  
**Headers:**
```
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "email": "your-email@example.com",
  "password": "your-password",
  "role": "user"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "User Name",
      "email": "user@example.com",
      "role": "user"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
  }
}
```

**Lưu lại `token` để dùng cho các request sau!**

---

## 💳 Bước 2: Test Create Payment Link

### Endpoint: Create Payment Link

**Method:** `POST`  
**URL:** `http://localhost:8000/api/user/payos/create-payment-link`  
**Headers:**
```
Content-Type: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**Body (JSON):**
```json
{
  "booking_id": 1,
  "amount": 100000,
  "description": "Thanh toán đặt cọc đơn #BK001"
}
```

**Giải thích:**
- `booking_id`: ID của booking trong database (phải tồn tại)
- `amount`: Số tiền thanh toán (tối thiểu 1000 VND)
- `description`: Mô tả thanh toán (tùy chọn)

**Response thành công:**
```json
{
  "success": true,
  "data": {
    "checkoutUrl": "https://pay.payos.vn/web/...",
    "paymentLinkId": "1234567890",
    "orderCode": 1234567890123456
  }
}
```

**Response lỗi:**
```json
{
  "success": false,
  "message": "Lỗi mô tả",
  "error_type": "payos_api_error"
}
```

**Các lỗi thường gặp:**
- `401 Unauthorized` → Token không hợp lệ hoặc hết hạn
- `403 Forbidden` → User không có quyền thanh toán booking này
- `404 Not Found` → Booking không tồn tại
- `400 Bad Request` → Dữ liệu không hợp lệ (amount < 1000, booking_id không tồn tại)

---

## 🔔 Bước 3: Test Webhook

### Endpoint: Webhook (Public - Không cần auth)

**Method:** `POST`  
**URL:** `http://localhost:8000/api/payos/webhook`  
**Hoặc với ngrok:** `https://your-ngrok-url.ngrok-free.app/api/payos/webhook`

**Headers:**
```
Content-Type: application/json
```

**Body (JSON) - Format từ PayOS:**
```json
{
  "code": "00",
  "desc": "Thành công",
  "data": {
    "orderCode": 1234567890123456,
    "amount": 100000,
    "description": "Thanh toán đặt cọc đơn #BK001",
    "accountNumber": "1234567890",
    "reference": "REF123456",
    "transactionDateTime": "2024-01-16T10:30:00Z",
    "currency": "VND",
    "paymentLinkId": "1234567890",
    "code": "00",
    "desc": "Thành công",
    "counterAccountBankId": null,
    "counterAccountBankName": null,
    "counterAccountName": null,
    "counterAccountNumber": null,
    "virtualAccountName": null,
    "virtualAccountNumber": null
  },
  "signature": "abc123def456..."
}
```

**Response thành công:**
```json
{
  "success": true,
  "message": "Webhook processed successfully"
}
```

**Response lỗi:**
```json
{
  "success": false,
  "message": "Invalid signature"
}
```

**Lưu ý:**
- Webhook không cần authentication
- PayOS sẽ tự động gửi webhook sau khi thanh toán
- Signature phải được verify (dùng `PAYOS_CHECKSUM_KEY`)
- Nếu signature không hợp lệ, sẽ trả về `200` (nhưng log warning)

---

## 📊 Bước 4: Test Check Payment Status

### Endpoint: Check Payment Status

**Method:** `GET`  
**URL:** `http://localhost:8000/api/user/payos/check-status/{orderCode}`

**Ví dụ:**
```
http://localhost:8000/api/user/payos/check-status/1234567890123456
```

**Headers:**
```
Authorization: Bearer {YOUR_TOKEN}
```

**Response thành công:**
```json
{
  "success": true,
  "data": {
    "orderCode": 1234567890123456,
    "amount": 100000,
    "description": "Thanh toán đặt cọc đơn #BK001",
    "status": "PAID",
    "accountNumber": "1234567890",
    "reference": "REF123456",
    "transactionDateTime": "2024-01-16T10:30:00Z"
  }
}
```

**Response lỗi:**
```json
{
  "success": false,
  "message": "Không thể kiểm tra trạng thái thanh toán"
}
```

---

## 📝 Postman Collection Example

### 1. Setup Environment Variables

Trong Postman, tạo Environment với các variables:

```
base_url: http://localhost:8000
token: (sẽ được set sau khi login)
order_code: (sẽ được set sau khi tạo payment link)
```

### 2. Request 1: Login

```
POST {{base_url}}/api/login

Body:
{
  "email": "user@example.com",
  "password": "password",
  "role": "user"
}

Tests (Postman):
pm.environment.set("token", pm.response.json().data.token);
```

### 3. Request 2: Create Payment Link

```
POST {{base_url}}/api/user/payos/create-payment-link

Headers:
Authorization: Bearer {{token}}

Body:
{
  "booking_id": 1,
  "amount": 100000,
  "description": "Test payment"
}

Tests (Postman):
if (pm.response.json().success) {
    pm.environment.set("order_code", pm.response.json().data.orderCode);
    pm.environment.set("checkout_url", pm.response.json().data.checkoutUrl);
}
```

### 4. Request 3: Check Payment Status

```
GET {{base_url}}/api/user/payos/check-status/{{order_code}}

Headers:
Authorization: Bearer {{token}}
```

### 5. Request 4: Test Webhook

```
POST {{base_url}}/api/payos/webhook

Body:
{
  "code": "00",
  "desc": "Thành công",
  "data": {
    "orderCode": {{order_code}},
    "amount": 100000,
    "description": "Test payment",
    "paymentLinkId": "1234567890"
  },
  "signature": "test_signature"
}
```

**Lưu ý:** Signature trong test này sẽ fail (vì không phải signature thật từ PayOS), nhưng bạn có thể xem webhook endpoint có hoạt động không.

---

## 🧪 Test Scenarios

### Scenario 1: Tạo Payment Link Thành Công

1. Login để lấy token
2. Tạo payment link với booking_id hợp lệ
3. Copy `checkoutUrl` và mở trong browser
4. Thanh toán trên PayOS (dùng thẻ test)
5. Kiểm tra webhook có được gọi không (xem logs)

### Scenario 2: Test Webhook Signature

1. Tạo payment link thành công
2. Lấy `orderCode` từ response
3. Tạo webhook payload với signature giả
4. Gửi POST request đến webhook endpoint
5. Kiểm tra response (sẽ trả về `200` nhưng log warning về invalid signature)

### Scenario 3: Test Check Payment Status

1. Tạo payment link thành công
2. Lấy `orderCode` từ response
3. Gọi check payment status endpoint
4. Kiểm tra response (sẽ trả về status hiện tại từ PayOS)

---

## 🔍 Debug Tips

### 1. Kiểm tra Token

Nếu gặp lỗi `401 Unauthorized`:
- Kiểm tra token có đúng format không: `Bearer {token}`
- Token có thể đã hết hạn → Login lại
- Kiểm tra user có đúng role không (user hoặc admin)

### 2. Kiểm tra Booking ID

Nếu gặp lỗi `404 Not Found`:
- Kiểm tra booking_id có tồn tại trong database không
- Kiểm tra user có quyền thanh toán booking này không (user chỉ có thể thanh toán booking của chính mình, admin có thể thanh toán bất kỳ)

### 3. Kiểm tra Webhook

Nếu webhook không hoạt động:
- Kiểm tra `PAYOS_CHECKSUM_KEY` trong `.env`
- Kiểm tra logs: `storage/logs/laravel.log`
- Test webhook bằng Postman với payload mẫu

### 4. Kiểm tra PayOS API

Nếu gặp lỗi từ PayOS:
- Kiểm tra `PAYOS_CLIENT_ID`, `PAYOS_API_KEY` trong `.env`
- Kiểm tra `PAYOS_BASE_URL` (nên là `https://api-merchant.payos.vn`)
- Kiểm tra kết nối internet
- Xem logs chi tiết trong `storage/logs/laravel.log`

---

## 📋 Checklist Test

- [ ] Login thành công và lấy được token
- [ ] Tạo payment link thành công
- [ ] Có thể mở checkoutUrl trong browser
- [ ] Webhook endpoint có thể nhận POST request
- [ ] Check payment status trả về đúng thông tin
- [ ] Logs không có lỗi nghiêm trọng

---

## 🆘 Troubleshooting

### Lỗi: "Could not resolve host"

**Nguyên nhân:** `PAYOS_BASE_URL` sai hoặc không kết nối được internet

**Giải pháp:**
- Kiểm tra `PAYOS_BASE_URL=https://api-merchant.payos.vn`
- Kiểm tra kết nối internet
- Test ping: `ping api-merchant.payos.vn`

### Lỗi: "Invalid signature" (Webhook)

**Nguyên nhân:** Checksum key không đúng hoặc signature không khớp

**Giải pháp:**
- Kiểm tra `PAYOS_CHECKSUM_KEY` trong `.env`
- Chạy `php artisan config:clear`
- Test với webhook thật từ PayOS (không phải test payload)

### Lỗi: "Thông tin truyền lên không đúng" (400)

**Nguyên nhân:** Request body không đúng format PayOS yêu cầu

**Giải pháp:**
- Kiểm tra `amount` >= 1000
- Kiểm tra `orderCode` là số nguyên dương, tối đa 19 chữ số
- Kiểm tra `items` array không rỗng
- Xem logs chi tiết trong `storage/logs/laravel.log`

---

## 📚 Tài Liệu Tham Khảo

- PayOS API Documentation: https://payos.vn/docs/
- Laravel Logs: `BE1/storage/logs/laravel.log`
- PayOS Service: `BE1/app/Services/PayOSService.php`
- PayOS Controller: `BE1/app/Http/Controllers/Api/PayOSController.php`
