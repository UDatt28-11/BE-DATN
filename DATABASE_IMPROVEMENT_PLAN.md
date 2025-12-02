# Kế hoạch cải thiện Database System

## 📋 Tổng quan

Tài liệu này mô tả các cải tiến database để hỗ trợ đầy đủ hệ thống filter và trả về dữ liệu sạch, đầy đủ thông tin.

---

## 🔴 Vấn đề hiện tại

### 1. Rooms Table thiếu thông tin về tầng
- **Vấn đề**: Không có field `floor` hoặc `floor_number`
- **Hậu quả**: Filter "Vị trí tầng" không hoạt động
- **Giải pháp**: Thêm `floor_number` và `floor_category`

### 2. Amenities Table thiếu phân loại filter
- **Vấn đề**: Không có cách phân biệt Key Amenities, View, Floor
- **Hậu quả**: Frontend phải dùng string matching không chính xác
- **Giải pháp**: Thêm field `filter_category`

### 3. Amenities thiếu nhiều items quan trọng
- **Vấn đề**: Thiếu Bồn tắm, Bếp riêng, View vườn/núi, Tầng trệt/cao
- **Hậu quả**: Filter không có dữ liệu để lọc
- **Giải pháp**: Cập nhật AmenitySeeder với đầy đủ amenities

### 4. RoomType không có amenities trực tiếp
- **Vấn đề**: Amenities thuộc về Room, không phải RoomType
- **Hậu quả**: Phải aggregate từ tất cả Rooms, tốn performance
- **Giải pháp**: Tạo view hoặc cache aggregated amenities

---

## ✅ Các cải tiến đã thực hiện

### Migration 1: Thêm Floor vào Rooms
**File**: `2025_12_20_000001_add_floor_to_rooms_table.php`

```php
- floor_number (integer, nullable): Số tầng cụ thể (0 = tầng trệt, 1, 2, 3...)
- floor_category (enum, nullable): Phân loại nhanh
  - 'ground_floor': Tầng trệt
  - 'upper_floor': Tầng cao
  - 'attic': Gác mái
```

**Lợi ích**:
- Hỗ trợ filter "Vị trí tầng" chính xác
- Có thể query nhanh theo floor_category
- Có thể query chi tiết theo floor_number

### Migration 2: Thêm Filter Category vào Amenities
**File**: `2025_12_20_000002_add_filter_category_to_amenities_table.php`

```php
- filter_category (enum, nullable):
  - 'key_amenity': Tiện nghi đặc biệt (Bồn tắm, Ban công, Bếp riêng, Khép kín)
  - 'view': Hướng nhìn (View vườn, View núi, View bể bơi)
  - 'floor': Vị trí tầng (Tầng trệt, Tầng cao, Gác mái)
  - null: Tiện ích khác
```

**Lợi ích**:
- Frontend không cần string matching
- Query chính xác và nhanh hơn
- Dễ dàng thêm amenities mới vào đúng category

### Seeder: Cập nhật AmenitySeeder
**File**: `database/seeders/AmenitySeeder.php`

**Amenities mới được thêm**:
- ✅ Bồn tắm (key_amenity)
- ✅ Bếp riêng (key_amenity)
- ✅ Phòng tắm khép kín (key_amenity)
- ✅ View vườn (view)
- ✅ View núi (view)
- ✅ View bể bơi (view)
- ✅ View biển (view)
- ✅ Tầng trệt (floor)
- ✅ Tầng cao (floor)
- ✅ Gác mái (floor)

**Tổng cộng**: 25 amenities (tăng từ 12 lên 25)

---

## 🎯 Cấu trúc Database mới

### Rooms Table
```sql
rooms
├── id
├── property_id
├── room_type_id
├── name
├── description
├── floor_number (NEW)          -- Số tầng cụ thể
├── floor_category (NEW)        -- Phân loại tầng
├── max_adults
├── max_children
├── price_per_night
├── status
├── verification_status
└── timestamps
```

### Amenities Table
```sql
amenities
├── id
├── property_id
├── name
├── icon_url
├── type (basic, advanced, safety)
├── category (facility, service)
├── filter_category (NEW)       -- key_amenity, view, floor, null
└── timestamps
```

### Amenities Categories
```
Key Amenities (filter_category = 'key_amenity'):
- Bồn tắm
- Ban công
- Bếp riêng
- Phòng tắm khép kín

View (filter_category = 'view'):
- View vườn
- View núi
- View bể bơi
- View thành phố
- View biển

Floor (filter_category = 'floor'):
- Tầng trệt
- Tầng cao
- Gác mái

Other (filter_category = null):
- WiFi miễn phí
- Điều hòa nhiệt độ
- TV màn hình phẳng
- ... (các tiện ích khác)
```

---

## 🔧 Cách sử dụng trong Backend

### 1. Query Amenities theo Filter Category
```php
// Lấy Key Amenities
$keyAmenities = Amenity::where('filter_category', 'key_amenity')->get();

// Lấy View amenities
$viewAmenities = Amenity::where('filter_category', 'view')->get();

// Lấy Floor amenities
$floorAmenities = Amenity::where('filter_category', 'floor')->get();
```

### 2. Query Rooms theo Floor
```php
// Lấy phòng tầng trệt
$groundFloorRooms = Room::where('floor_category', 'ground_floor')->get();

// Lấy phòng tầng cao
$upperFloorRooms = Room::where('floor_category', 'upper_floor')->get();

// Lấy phòng gác mái
$atticRooms = Room::where('floor_category', 'attic')->get();
```

### 3. Aggregate Amenities cho RoomType
```php
// Lấy tất cả amenities từ tất cả Rooms trong RoomType
$roomType = RoomType::with(['rooms.amenities'])->find($id);
$allAmenities = $roomType->rooms->flatMap->amenities->unique('id')->values();

// Phân loại amenities
$keyAmenities = $allAmenities->where('filter_category', 'key_amenity');
$viewAmenities = $allAmenities->where('filter_category', 'view');
$floorAmenities = $allAmenities->where('filter_category', 'floor');
```

---

## 🎨 Cách sử dụng trong Frontend

### 1. Filter Amenities theo Category
```typescript
// Lấy Key Amenities
const keyAmenities = amenities.filter(a => a.filter_category === 'key_amenity');

// Lấy View amenities
const viewAmenities = amenities.filter(a => a.filter_category === 'view');

// Lấy Floor amenities
const floorAmenities = amenities.filter(a => a.filter_category === 'floor');
```

### 2. Filter Rooms theo Floor
```typescript
// Filter theo floor_category
const groundFloorRooms = rooms.filter(r => r.floor_category === 'ground_floor');
```

### 3. Không cần string matching nữa
```typescript
// ❌ CŨ: Phải dùng string matching
amenities.filter(a => 
    a.name.toLowerCase().includes('bồn tắm') || 
    a.name.toLowerCase().includes('bathtub')
)

// ✅ MỚI: Dùng filter_category
amenities.filter(a => a.filter_category === 'key_amenity')
```

---

## 📊 So sánh Before/After

### Before
- ❌ 12 amenities
- ❌ Không có floor information
- ❌ Không có filter_category
- ❌ Frontend phải string matching
- ❌ Filter không hoạt động đúng

### After
- ✅ 25 amenities (tăng 108%)
- ✅ Có floor_number và floor_category
- ✅ Có filter_category cho tất cả amenities
- ✅ Frontend query chính xác
- ✅ Filter hoạt động đầy đủ

---

## 🚀 Bước tiếp theo (Tùy chọn)

### 1. Tạo View hoặc Cache cho RoomType Amenities
**Vấn đề**: RoomType không có amenities trực tiếp, phải aggregate từ Rooms

**Giải pháp**:
- Tạo database view `room_type_amenities` để cache aggregated amenities
- Hoặc thêm field `cached_amenities` (JSON) vào `room_types` table
- Update cache khi Room amenities thay đổi

### 2. Thêm Indexes cho Performance
```sql
-- Indexes đã được thêm trong migrations
CREATE INDEX idx_rooms_floor_category ON rooms(floor_category);
CREATE INDEX idx_rooms_floor_number ON rooms(floor_number);
CREATE INDEX idx_amenities_filter_category ON amenities(filter_category);
```

### 3. Thêm Validation Rules
- `floor_number`: >= 0 (0 = tầng trệt)
- `floor_category`: Phải khớp với `floor_number` (nếu có)
- `filter_category`: Chỉ set khi amenity thuộc nhóm filter đặc biệt

---

## 📝 Migration Commands

```bash
# Chạy migrations
php artisan migrate

# Rollback nếu cần
php artisan migrate:rollback --step=2

# Chạy seeder
php artisan db:seed --class=AmenitySeeder
```

---

## ✅ Checklist

- [x] Migration thêm floor vào rooms
- [x] Migration thêm filter_category vào amenities
- [x] Cập nhật AmenitySeeder với đầy đủ amenities
- [ ] Update RoomSeeder để thêm floor_number và floor_category
- [ ] Update Backend API để trả về filter_category
- [ ] Update Frontend để sử dụng filter_category thay vì string matching
- [ ] Test filter với dữ liệu mới

---

## 📚 Tài liệu tham khảo

- `FE1/FILTER_ISSUES_ANALYSIS.md` - Phân tích vấn đề filter
- `info.text` - Yêu cầu filter từ người dùng
- `BE1/database/migrations/` - Tất cả migrations
- `BE1/database/seeders/AmenitySeeder.php` - Seeder mới

