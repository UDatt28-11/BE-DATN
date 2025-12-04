# Hướng dẫn Setup PayOS - Chỉ cần `php artisan serve`

## 🎯 Mục tiêu

Làm cho PayOS hoạt động **CHỈ VỚI** `php artisan serve`, không cần setup ngrok thủ công.

---

## ⚠️ Về PayOS SDK

**PayOS SDK KHÔNG yêu cầu ngrok**, nhưng **PayOS API** có yêu cầu:
- ❌ **KHÔNG chấp nhận localhost** trong `returnUrl` và `cancelUrl`
- ✅ **CẦN public URL** để:
  - Redirect khách hàng về sau khi thanh toán
  - Gửi webhook callbacks về server

**→ Vì vậy cần tunnel (ngrok/cloudflared) để tạo public URL**

---

## 🚀 Giải pháp: Setup một lần, dùng mãi mãi

### **Cách 1: Setup Tunnel một lần (Khuyến nghị)** ⭐

#### Bước 1: Start Cloudflared (Chỉ cần làm 1 lần hoặc khi URL thay đổi)

**Mở Terminal 1:**
```powershell
cd BE1
.\cloudflared.exe tunnel --url http://localhost:8000
```

**Copy URL từ output** (ví dụ: `https://xxxxx.trycloudflare.com`)

#### Bước 2: Cập nhật .env (Chỉ cần làm 1 lần)

Thêm hoặc sửa trong `.env`:
```env
FRONTEND_URL=https://xxxxx.trycloudflare.com
APP_URL=https://xxxxx.trycloudflare.com
```

#### Bước 3: Chạy server bình thường

```bash
php artisan serve
```

**✅ Xong!** PayOS sẽ hoạt động. Chỉ cần giữ cloudflared chạy trong terminal khác.

---

### **Cách 2: Sử dụng Script tự động** 

#### Windows:
```powershell
# Script hướng dẫn và tự động cập nhật .env
.\start_server.ps1
```

**Script sẽ:**
1. ✅ Kiểm tra FRONTEND_URL hiện tại
2. ✅ Hướng dẫn setup tunnel nếu cần
3. ✅ Tự động cập nhật `.env` khi bạn nhập URL
4. ✅ Start Laravel server

---

### **Cách 2: Bypass Validation (Chỉ cho Development)**

Nếu bạn chỉ cần test code mà không cần PayOS thực sự hoạt động:

**Thêm vào `.env`:**
```env
PAYOS_ALLOW_LOCALHOST=true
```

**Lưu ý:**
- ⚠️ PayOS API **VẪN SẼ TỪ CHỐI** localhost
- ✅ Chỉ bypass validation trong code
- ❌ PayOS sẽ trả về lỗi khi tạo payment link
- 🎯 Chỉ dùng để test code logic, không test PayOS thực tế

---

### **Cách 3: Sử dụng Cloudflared thủ công (Nếu script không hoạt động)**

```powershell
# Terminal 1: Start cloudflared
.\cloudflared.exe tunnel --url http://localhost:8000

# Copy URL (ví dụ: https://xxxxx.trycloudflare.com)

# Terminal 2: Cập nhật .env
# Thêm hoặc sửa:
FRONTEND_URL=https://xxxxx.trycloudflare.com
APP_URL=https://xxxxx.trycloudflare.com

# Clear config
php artisan config:clear

# Start server
php artisan serve
```

---

## 📋 Checklist cho Team Members

Khi pull code về, mỗi thành viên chỉ cần:

### **Bước 1: Cài đặt dependencies**
```bash
composer install
npm install  # (nếu có frontend)
```

### **Bước 2: Setup .env**
```bash
cp .env.example .env
php artisan key:generate
```

### **Bước 3: Chạy migrations và seed**
```bash
php artisan migrate:fresh --seed
```

### **Bước 4: Start server với tunnel (Tự động)**
```powershell
# Windows
.\start_with_tunnel.ps1

# Hoặc
.\start_with_tunnel.bat
```

**Xong!** PayOS sẽ hoạt động ngay! 🎉

---

## 🔧 Troubleshooting

### **Lỗi: "returnUrl không được dùng localhost"**

**Nguyên nhân:**
- `.env` chưa có `FRONTEND_URL` hoặc vẫn là localhost
- Tunnel chưa được start

**Giải pháp:**
1. Chạy `.\start_with_tunnel.ps1` để tự động setup
2. Hoặc set `PAYOS_ALLOW_LOCALHOST=true` (chỉ để test code)

### **Lỗi: "Cloudflared không tìm thấy"**

**Giải pháp:**
1. Script sẽ tự động tải cloudflared
2. Hoặc tải thủ công: https://github.com/cloudflare/cloudflared/releases/latest
3. Lưu vào `BE1/cloudflared.exe`

### **Lỗi: "Tunnel URL không lấy được"**

**Giải pháp:**
1. Chạy cloudflared thủ công:
   ```powershell
   .\cloudflared.exe tunnel --url http://localhost:8000
   ```
2. Copy URL từ output
3. Cập nhật `.env` thủ công:
   ```env
   FRONTEND_URL=https://xxxxx.trycloudflare.com
   APP_URL=https://xxxxx.trycloudflare.com
   ```
4. Chạy: `php artisan config:clear`

---

## 📝 Environment Variables

### **Bắt buộc cho PayOS:**
```env
PAYOS_CLIENT_ID=your_client_id
PAYOS_API_KEY=your_api_key
PAYOS_CHECKSUM_KEY=your_checksum_key
FRONTEND_URL=https://xxxxx.trycloudflare.com  # Public URL (tự động bởi script)
APP_URL=https://xxxxx.trycloudflare.com       # Public URL (tự động bởi script)
```

### **Optional (Development only):**
```env
PAYOS_ALLOW_LOCALHOST=true  # Bypass validation (PayOS vẫn sẽ reject)
```

---

## ✅ Kết luận

**Với script tự động:**
- ✅ Chỉ cần chạy `.\start_with_tunnel.ps1`
- ✅ Tự động setup mọi thứ
- ✅ PayOS hoạt động ngay
- ✅ Team members không cần setup thủ công

**Không cần:**
- ❌ Setup ngrok thủ công
- ❌ Copy/paste URL
- ❌ Cập nhật .env thủ công
- ❌ Nhớ các bước phức tạp

**Chỉ cần:**
- ✅ Chạy 1 script
- ✅ Xong! 🎉

