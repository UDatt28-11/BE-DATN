# Vai trò của Ngrok trong Dự án BookStay

## 📋 Tổng quan

Ngrok đóng vai trò **QUAN TRỌNG** trong dự án này, đặc biệt là cho việc tích hợp **PayOS payment gateway** và nhận **webhook callbacks**.

---

## 🎯 Vai trò chính của Ngrok

### 1. **PayOS Integration - Vai trò QUAN TRỌNG NHẤT** ⭐

#### Vấn đề:
- **PayOS KHÔNG CHẤP NHẬN localhost** trong `returnUrl` và `cancelUrl`
- PayOS cần **public URL** để:
  - Redirect khách hàng về sau khi thanh toán
  - Gửi webhook callbacks về server

#### Giải pháp với Ngrok:
```bash
# Expose backend server (port 8000)
ngrok http 8000
```

**Kết quả:**
- Tạo public URL: `https://xxxxx.ngrok-free.app`
- Backend có thể nhận webhook từ PayOS
- Frontend có thể redirect về sau khi thanh toán

#### Code liên quan:
```php
// BE1/app/Services/PayOSService.php (line 189-193)
if (strpos($returnUrl, 'localhost') !== false || strpos($returnUrl, '127.0.0.1') !== false) {
    throw new Exception('returnUrl không được dùng localhost. Vui lòng dùng public URL (ngrok/local tunnel) hoặc domain thật.');
}

if (strpos($cancelUrl, 'localhost') !== false || strpos($cancelUrl, '127.0.0.1') !== false) {
    throw new Exception('cancelUrl không được dùng localhost. Vui lòng dùng public URL (ngrok/local tunnel) hoặc domain thật.');
}
```

---

### 2. **Webhook Callbacks từ PayOS**

#### Webhook Endpoint:
```
POST https://your-ngrok-url.ngrok-free.app/api/payos/webhook
```

#### Vai trò:
- PayOS gửi kết quả thanh toán về server qua webhook
- Server xử lý và cập nhật trạng thái booking
- **KHÔNG THỂ dùng localhost** vì PayOS không thể gửi webhook về localhost

#### Code liên quan:
```php
// BE1/app/Http/Controllers/Api/PayOSController.php
public function webhook(Request $request): JsonResponse
{
    // Nhận webhook từ PayOS
    // Xác thực signature
    // Cập nhật trạng thái thanh toán
}
```

---

### 3. **Frontend Development (Thay thế Cloudflared)**

#### Vấn đề với Cloudflared:
- ❌ Không hỗ trợ WebSocket tốt
- ❌ Vite HMR (Hot Module Replacement) không hoạt động
- ⚠️ Cảnh báo WebSocket connection failed

#### Giải pháp với Ngrok:
```bash
# Expose frontend server (port 5173)
ngrok http 5173
```

**Lợi ích:**
- ✅ Hỗ trợ WebSocket tốt hơn Cloudflared
- ✅ Vite HMR hoạt động bình thường
- ✅ Hot reload hoạt động

#### Code liên quan:
```typescript
// FE1/src/api/axios.ts (line 17-21)
// Bypass ngrok warning page (ERR_NGROK_6024)
if (config.headers && config.baseURL?.includes("ngrok")) {
    config.headers["ngrok-skip-browser-warning"] = "true";
}
```

---

## 🔧 Cách sử dụng Ngrok

### **Bước 1: Cài đặt Ngrok**

Ngrok đã có sẵn trong project:
- File: `BE1/ngrok.exe`

Hoặc download từ: https://ngrok.com/download

### **Bước 2: Chạy Ngrok cho Backend**

```powershell
cd BE1
.\ngrok.exe http 8000
```

**Kết quả:**
```
Forwarding  https://xxxxx.ngrok-free.app -> http://localhost:8000
```

### **Bước 3: Cập nhật .env**

```env
FRONTEND_URL=https://xxxxx.ngrok-free.app
APP_URL=https://xxxxx.ngrok-free.app
```

### **Bước 4: Cập nhật PayOS Webhook URL**

Trong PayOS Dashboard:
```
Webhook URL: https://xxxxx.ngrok-free.app/api/payos/webhook
```

---

## ⚠️ Lưu ý quan trọng

### 1. **Ngrok Warning Page**

Ngrok sẽ hiển thị warning page nếu không có header:
```typescript
headers: {
    'ngrok-skip-browser-warning': 'true'
}
```

**Đã được xử lý trong code:**
- ✅ `FE1/src/api/axios.ts` - Tự động thêm header
- ✅ `FE1/src/service/axiosConfig.ts` - Tự động thêm header

### 2. **URL thay đổi mỗi lần chạy**

- Ngrok free plan: URL thay đổi mỗi lần restart
- Ngrok paid plan: Có thể dùng static domain

**Giải pháp:**
- Cập nhật `.env` mỗi lần chạy ngrok mới
- Chạy script `check_cloudflare_url.ps1` để tự động cập nhật

### 3. **Ngrok vs Cloudflared**

| Tính năng | Ngrok | Cloudflared |
|-----------|-------|-------------|
| WebSocket | ✅ Tốt | ❌ Kém |
| HMR Support | ✅ Có | ❌ Không |
| Free Plan | ✅ Có | ✅ Có |
| Static Domain | 💰 Paid | ✅ Free |
| PayOS Webhook | ✅ Hoạt động | ✅ Hoạt động |

**Khuyến nghị:**
- **Development**: Dùng Ngrok nếu cần HMR
- **Testing PayOS**: Dùng Cloudflared hoặc Ngrok đều được
- **Production**: Dùng domain thật

---

## 📝 Scripts hỗ trợ

### 1. **debug_payos_error.ps1**
- Kiểm tra FRONTEND_URL có phải localhost không
- Đề xuất dùng ngrok/cloudflared

### 2. **check_cloudflare_url.ps1**
- Kiểm tra và cập nhật FRONTEND_URL
- Hỗ trợ cả ngrok và cloudflared

---

## 🎯 Kết luận

### **Ngrok QUAN TRỌNG cho:**

1. ✅ **PayOS Integration** - Bắt buộc phải có public URL
2. ✅ **Webhook Callbacks** - PayOS cần gửi webhook về server
3. ✅ **Frontend Development** - Hỗ trợ HMR tốt hơn Cloudflared

### **Khi nào cần Ngrok:**

- 🔴 **Bắt buộc**: Khi test PayOS payment
- 🟡 **Nên dùng**: Khi cần HMR trong development
- 🟢 **Không cần**: Khi chỉ test local, không cần webhook

### **Alternatives:**

- **Cloudflared**: Miễn phí, nhưng không hỗ trợ WebSocket tốt
- **LocalTunnel**: Miễn phí, nhưng có thể chậm
- **Domain thật**: Tốt nhất cho production

---

## 📚 Tài liệu tham khảo

- PayOS Documentation: https://payos.vn/docs
- Ngrok Documentation: https://ngrok.com/docs
- POSTMAN_PAYOS_TEST.md: Hướng dẫn test PayOS với ngrok

