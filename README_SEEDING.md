# Hướng dẫn Fresh và Seed Database

## 🎯 Mục đích

Tài liệu này hướng dẫn cách fresh và seed lại toàn bộ database để có dữ liệu sạch, hợp lý.

---

## 🚀 Cách sử dụng

### **Cách 1: Sử dụng Script (Khuyến nghị)**

#### Trên Windows:
```bash
fresh-seed.bat
```

#### Trên Linux/Mac:
```bash
chmod +x fresh-seed.sh
./fresh-seed.sh
```

### **Cách 2: Sử dụng Artisan Commands**

```bash
# Fresh migration (xóa tất cả tables và tạo lại)
php artisan migrate:fresh

# Seed database
php artisan db:seed
```

Hoặc kết hợp:
```bash
php artisan migrate:fresh --seed
```

---

## 📋 Thứ tự Seed

DatabaseSeeder sẽ chạy các seeder theo thứ tự sau:

1. **Roles & Users**
   - `RoleSeeder`: Tạo roles (admin, owner, staff, user)
   - `UserSeeder`: Tạo users (admin, owner, user, test users)
   - `StaffSeeder`: Tạo staff user

2. **Properties**
   - `PropertySeeder`: Tạo 1 property mẫu

3. **RoomTypes & Rooms**
   - `RoomTypeSeeder`: Tạo 4 loại phòng (Standard, Deluxe, Family, Studio)
   - `RoomTypeImageSeeder`: Tạo ảnh cho room types
   - `RoomSeeder`: Tạo rooms với floor information

4. **Amenities**
   - `AmenitySeeder`: Tạo đầy đủ amenities với filter categories
   - `RoomAmenitySeeder`: Gán amenities cho rooms

5. **Services, Promotions, Vouchers**
   - `ServiceSeeder`: Tạo services
   - `PromotionSeeder`: Tạo promotions
   - `VoucherSeeder`: Tạo vouchers

6. **Supplies**
   - `SupplySeeder`: Tạo supplies/inventory

7. **Bookings** (Optional)
   - `BookingSeeder`: Tạo sample bookings

8. **Invoices & Reviews** (Optional)
   - `InvoiceSeeder`: Tạo invoices
   - `ReviewSeeder`: Tạo reviews

---

## 🔑 Default Credentials

Sau khi seed, bạn có thể đăng nhập với:

- **Admin**: 
  - Email: `admin@staybook.com`
  - Password: `password`

- **Owner**: 
  - Email: `owner@staybook.com`
  - Password: `password`

- **User**: 
  - Email: `user@staybook.com`
  - Password: `password`

---

## 📊 Dữ liệu được tạo

### Properties
- 1 property: "Homestay Sài Gòn View"

### Room Types
- Phòng Standard (3 rooms)
- Phòng Deluxe (2 rooms)
- Phòng Family (2 rooms)
- Studio (2 rooms)

### Rooms
- Tổng cộng: 9 rooms
- Phân bổ tầng:
  - Tầng trệt (ground_floor): 30%
  - Tầng cao (upper_floor): 60%
  - Gác mái (attic): 10%

### Amenities
- Key Amenities: 4 (Bồn tắm, Ban công, Bếp riêng, Phòng tắm khép kín)
- View: 5 (View vườn, núi, bể bơi, thành phố, biển)
- Floor: 3 (Tầng trệt, Tầng cao, Gác mái)
- Other: 13 tiện ích khác

### Services
- Ăn sáng buffet
- Dịch vụ giặt là
- Xe đưa đón sân bay
- Thuê xe máy
- Dịch vụ massage
- Tour du lịch nội thành

### Supplies
- 10 loại supplies (khăn tắm, chăn ga gối, dầu gội, etc.)

---

## ⚠️ Lưu ý

1. **Backup trước khi fresh**: 
   - Script sẽ **XÓA TẤT CẢ** tables và dữ liệu
   - Đảm bảo đã backup nếu cần

2. **Môi trường**:
   - Chỉ chạy trên môi trường development
   - KHÔNG chạy trên production

3. **Dependencies**:
   - Đảm bảo đã chạy `composer install`
   - Đảm bảo database connection đúng trong `.env`

4. **Migrations**:
   - Tất cả migrations sẽ được chạy lại từ đầu
   - Đảm bảo không có migration conflicts

---

## 🔧 Troubleshooting

### Lỗi: "Class not found"
```bash
composer dump-autoload
php artisan db:seed
```

### Lỗi: "Foreign key constraint"
- Đảm bảo thứ tự seed đúng
- Kiểm tra các seeder có xóa dữ liệu cũ trước khi tạo mới

### Lỗi: "Table already exists"
```bash
php artisan migrate:fresh
```

---

## 📝 Customize Seeders

Nếu muốn tùy chỉnh dữ liệu, chỉnh sửa các file trong `database/seeders/`:

- `UserSeeder.php`: Thay đổi users
- `PropertySeeder.php`: Thay đổi properties
- `RoomSeeder.php`: Thay đổi số lượng/phân bổ rooms
- `AmenitySeeder.php`: Thay đổi amenities
- `BookingSeeder.php`: Thay đổi sample bookings

---

## ✅ Checklist sau khi seed

- [ ] Đăng nhập được với admin/owner/user
- [ ] Xem được properties
- [ ] Xem được room types và rooms
- [ ] Filter rooms theo amenities hoạt động
- [ ] Filter rooms theo floor hoạt động
- [ ] Check availability theo ngày hoạt động



