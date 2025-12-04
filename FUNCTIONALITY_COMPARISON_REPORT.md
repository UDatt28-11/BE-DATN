# BÁO CÁO SO SÁNH CHỨC NĂNG YÊU CẦU VÀ BACKEND HIỆN TẠI

## 📋 TỔNG QUAN

Báo cáo này so sánh chi tiết các chức năng được yêu cầu trong bảng chức năng Admin với những gì đã được triển khai trong backend hiện tại.

---

## 1. QUẢN LÝ DANH MỤC (LOẠI HOMESTAY) - RoomTypeController

### ✅ ĐÃ CÓ

#### Trang danh sách:
- ✅ Hiển thị danh sách loại homestay với phân trang
- ✅ Tìm kiếm theo tên (`search` parameter)
- ✅ Filter theo property_id
- ✅ Pagination (mặc định 15, có thể tùy chỉnh qua `per_page`)
- ✅ Eager loading relationships (property)

#### Trang chi tiết:
- ✅ Hiển thị thông tin chi tiết loại homestay
- ✅ Load property relationship

#### Trang thêm mới:
- ✅ Thêm loại mới với tên, mô tả
- ✅ Upload hình ảnh (image_file)
- ✅ Validation đầy đủ

#### Trang sửa:
- ✅ Sửa thông tin loại
- ✅ Cập nhật hình ảnh
- ✅ Validation đầy đủ

### ❌ CHƯA CÓ / THIẾU

#### Trang danh sách:
- ❌ **Thay đổi số bản ghi trên một trang (15, 30, 45)** - Hiện tại chỉ có `per_page` tùy chỉnh, chưa có preset (15, 30, 45)
- ❌ **Sắp xếp tăng/giảm theo id, tên, trạng thái, ngày tạo, ngày cập nhật** - Chưa có sorting options
- ❌ **Thay đổi trạng thái (kích hoạt/khóa)** - Chưa có field `status` trong RoomType model
- ❌ **Chuyển vào lịch sử** - Chưa có soft deletes hoặc history table

#### Trang lịch sử:
- ❌ **Trang lịch sử** - Chưa có endpoint để xem lịch sử thay đổi

#### Trang chi tiết:
- ❌ **Hiển thị tiện ích liên quan** - Chưa có relationship với amenities (có thể thêm qua Room)

#### Trang sửa:
- ❌ **Cập nhật tiện ích** - Chưa có chức năng này (room types không có direct relationship với amenities)

---

## 2. QUẢN LÝ PHÒNG (LISTINGS) - RoomController

### ✅ ĐÃ CÓ

#### Trang danh sách:
- ✅ Hiển thị danh sách phòng với phân trang
- ✅ Tìm kiếm theo tên (`search` parameter)
- ✅ Filter theo property_id, room_type_id, status
- ✅ Pagination (mặc định 15, có thể tùy chỉnh)
- ✅ Eager loading relationships (property, roomType, amenities, images)

#### Trang thêm mới:
- ✅ Thêm phòng mới
- ✅ Gán tiện ích (amenities) cho phòng
- ✅ Validation đầy đủ

#### Trang sửa:
- ✅ Sửa chi tiết phòng
- ✅ Cập nhật tiện ích
- ✅ Validation đầy đủ

### ❌ CHƯA CÓ / THIẾU

#### Trang danh sách:
- ❌ **Thay đổi số bản ghi trên một trang (15, 30, 45)** - Chưa có preset
- ❌ **Tìm kiếm theo địa điểm** - Chưa có (có thể thêm qua property address)
- ❌ **Sắp xếp theo id, tên, giá, đánh giá, ngày tạo/cập nhật** - Chưa có sorting options
- ❌ **Thay đổi trạng thái (còn/hết phòng)** - Có status nhưng chưa có endpoint riêng để thay đổi nhanh

#### Trang thêm mới:
- ❌ **Xác minh giấy tờ** - Chưa có chức năng xác minh giấy tờ cho phòng/property

#### Trang sửa:
- ❌ **Xác minh** - Chưa có chức năng xác minh

---

## 3. QUẢN LÝ TIỆN ÍCH (AMENITIES) - AmenityController

### ✅ ĐÃ CÓ

#### Trang danh sách:
- ✅ Hiển thị tiện ích với phân trang
- ✅ Tìm kiếm theo tên (`search` parameter)
- ✅ Lọc theo loại (type: basic, advanced, safety)
- ✅ Filter theo property_id
- ✅ Pagination (mặc định 15, có thể tùy chỉnh)
- ✅ Eager loading relationships (property)

#### Trang thêm mới:
- ✅ Thêm tiện ích với biểu tượng (icon_file)
- ✅ Upload icon
- ✅ Validation đầy đủ

#### Trang sửa:
- ✅ Sửa thông tin tiện ích
- ✅ Cập nhật icon
- ✅ Validation đầy đủ

### ❌ CHƯA CÓ / THIẾU

#### Trang danh sách:
- ❌ **Thay đổi số bản ghi trên một trang (15, 30, 45)** - Chưa có preset
- ❌ **Sắp xếp theo id, tên, trạng thái, ngày tạo/cập nhật** - Chưa có sorting options
- ❌ **Mô tả** - Chưa có field description trong model (có thể thêm)

#### Trang giá trị tiện ích:
- ❌ **Quản lý biến thể (nếu có)** - Chưa có chức năng này

---

## 4. QUẢN LÝ LƯU TRÚ (TAGS/EXPERIENCES)

### ⚠️ KHÔNG RÕ RÀNG

Yêu cầu này khá mơ hồ và có vẻ như là tổng hợp của nhiều chức năng:

#### Đã có (thông qua các controllers khác):
- ✅ Quản lý thông tin homestay/phòng (PropertyController, RoomController)
- ✅ Cập nhật giá (RoomController có `price_per_night`, PriceRuleController)
- ✅ Quản lý tiện nghi (AmenityController, RoomController sync amenities)
- ✅ Theo dõi tình trạng phòng (RoomController có `status`)
- ✅ Quản lý lịch check-in/check-out (BookingOrderController, BookingDetail)
- ✅ Chính sách & dịch vụ kèm theo (ServiceController, RefundPolicy trong InvoiceController)

### ❌ CHƯA CÓ / THIẾU

- ❌ **Xử lý trùng lịch** - Chưa có logic kiểm tra conflict khi đặt phòng
- ❌ **Xem báo cáo công suất, doanh thu, phòng sự cố** - Chưa có báo cáo chi tiết
- ❌ **Phân quyền và giám sát nhân viên quản lý phòng** - Chưa có hệ thống phân quyền chi tiết cho staff

---

## 5. QUẢN LÝ ĐẶT PHÒNG (BOOKINGS) - BookingOrderController

### ✅ ĐÃ CÓ

#### Xem danh sách:
- ✅ Hiển thị danh sách đặt phòng với phân trang
- ✅ Eager loading relationships (guest, details.room, invoices, promotions)
- ✅ Pagination

#### Chi tiết:
- ✅ Hiển thị chi tiết đơn đặt phòng
- ✅ Load đầy đủ relationships

#### Cập nhật:
- ✅ Cập nhật đơn đặt phòng
- ✅ Cập nhật trạng thái (`updateStatus` method)

### ❌ CHƯA CÓ / THIẾU

#### Xem danh sách:
- ❌ **Tìm kiếm, lọc đặt phòng theo nhiều tiêu chí:**
  - ❌ Mã đặt phòng (order_code) - Chưa có filter
  - ❌ Tên khách hàng - Chưa có filter
  - ❌ Homestay - Chưa có filter theo property
  - ❌ Ngày đặt - Chưa có filter theo date range
  - ❌ Ngày check-in/out - Chưa có filter
  - ❌ Trạng thái - Chưa có filter
  - ❌ Nhân viên xử lý - Chưa có field này

#### Thay đổi trạng thái:
- ❌ **Các trạng thái cụ thể:**
  - ❌ Đang chờ xử lý
  - ❌ Đã xác nhận
  - ❌ Đã thanh toán
  - ❌ Đã hủy
  - ❌ Hoàn thành
  - ⚠️ Hiện tại có `updateStatus` nhưng chưa rõ các status values cụ thể

#### Quản lý chính sách hủy và hoàn tiền:
- ❌ **Cài đặt mức phạt, điều kiện** - Có RefundPolicy trong InvoiceController nhưng chưa tích hợp với BookingOrder

#### Báo cáo thống kê:
- ❌ **Số lượng đặt phòng theo ngày/tuần/tháng** - Chưa có
- ❌ **Tỷ lệ hủy đặt phòng** - Chưa có
- ❌ **Doanh thu dự kiến và thực tế** - Chưa có
- ❌ **Giám sát hoạt động của nhân viên** - Chưa có

#### Xuất dữ liệu:
- ❌ **Xuất dữ liệu đặt phòng ra Excel/PDF** - Chưa có

---

## 6. QUẢN LÝ NGƯỜI DÙNG (USERS) - UserController

### ✅ ĐÃ CÓ

#### Trang danh sách:
- ✅ Hiển thị người dùng với phân trang
- ✅ Tìm kiếm theo tên/email/số điện thoại (`search` parameter)
- ✅ Lọc theo vai trò (`role` parameter)
- ✅ Lọc theo trạng thái (`status` parameter)
- ✅ Sắp xếp theo cột (`sort_by`, `sort_order`)
- ✅ Pagination (mặc định 20, có thể tùy chỉnh)

#### Trang thêm mới:
- ✅ Thêm user mới
- ✅ Validation đầy đủ

#### Trang sửa:
- ✅ Sửa thông tin user
- ✅ Validation đầy đủ

### ❌ CHƯA CÓ / THIẾU

#### Trang danh sách:
- ❌ **Thay đổi số bản ghi trên một trang (15, 30, 45)** - Chưa có preset
- ❌ **Thay đổi trạng thái** - Chưa có endpoint riêng để thay đổi nhanh
- ❌ **Khóa một/nhiều** - Chưa có bulk operations

#### Trang danh sách khóa:
- ❌ **Trang danh sách khóa** - Chưa có endpoint riêng
- ❌ **Hiển thị tài khoản khóa, phân trang, tìm kiếm, sắp xếp** - Chưa có
- ❌ **Bỏ khóa một/nhiều** - Chưa có

#### Trang sửa:
- ❌ **Xác minh danh tính** - Chưa có chức năng này

---

## 7. QUẢN LÝ MÃ GIẢM GIÁ (PROMOTIONS) - PromotionController

### ✅ ĐÃ CÓ

#### Trang danh sách:
- ✅ Hiển thị mã giảm giá với phân trang
- ✅ Tìm kiếm theo mã hoặc mô tả (`search` parameter)
- ✅ Filter theo property_id, is_active
- ✅ Pagination (mặc định 15, có thể tùy chỉnh)
- ✅ Eager loading relationships (property, rooms, roomTypes)

#### Trang thêm mới:
- ✅ Tạo mã với điều kiện (giảm %, địa điểm)
- ✅ Validation đầy đủ

#### Trang sửa:
- ✅ Sửa mã, thời hạn
- ✅ Validation đầy đủ

#### Khác:
- ✅ Validate promotion (`validate` method)
- ✅ Statistics (`statistics` method)

### ❌ CHƯA CÓ / THIẾU

#### Trang danh sách:
- ❌ **Thay đổi số bản ghi trên một trang (15, 30, 45)** - Chưa có preset
- ❌ **Sắp xếp theo mã, tên, trạng thái, ngày tạo/cập nhật** - Chưa có sorting options
- ❌ **Thay đổi trạng thái** - Chưa có endpoint riêng để thay đổi nhanh
- ❌ **Xóa một/nhiều** - Chưa có bulk delete

---

## 8. QUẢN LÝ ĐÁNH GIÁ (REVIEWS) - ReviewController

### ✅ ĐÃ CÓ

#### Xem đánh giá:
- ✅ Hiển thị danh sách đánh giá với phân trang
- ✅ Filter theo property_id, room_id, status, rating
- ✅ Tìm kiếm theo tiêu đề hoặc nội dung
- ✅ Filter verified_only
- ✅ Pagination
- ✅ Eager loading relationships
- ✅ Statistics (`statistics` method)
- ✅ Approve/Reject (`approve`, `reject` methods)
- ✅ Mark helpful/not helpful (`markHelpful`, `markNotHelpful` methods)

### ❌ CHƯA CÓ / THIẾU

- ✅ Tất cả các chức năng cơ bản đã có
- ⚠️ Có thể cần thêm filter theo ngày tạo, sắp xếp chi tiết hơn

---

## 9. QUẢN LÝ BÌNH LUẬN (MESSAGES) - MessageController, ConversationController

### ✅ ĐÃ CÓ

#### Xem bình luận/trò chuyện:
- ✅ Hiển thị danh sách conversations
- ✅ Hiển thị danh sách messages trong conversation
- ✅ Tạo conversation mới
- ✅ Gửi message
- ✅ Mark as read
- ✅ Unread count

### ❌ CHƯA CÓ / THIẾU

- ❌ **Phản hồi** - Có thể gửi message nhưng chưa có chức năng phản hồi cụ thể
- ❌ **Ẩn bình luận không phù hợp** - Chưa có chức năng hide/delete message (có destroy nhưng chưa có hide)

---

## 10. THỐNG KÊ (ANALYTICS)

### ✅ ĐÃ CÓ (MỘT PHẦN)

- ✅ Statistics cho Reviews (`/api/reviews/statistics/overview`)
- ✅ Statistics cho Promotions (`/api/promotions/statistics/overview`)
- ✅ Statistics cho Supplies (`/api/supplies/statistics/overview`)
- ✅ Statistics cho Invoices (`/api/invoices/statistics/overview`)

### ❌ CHƯA CÓ / THIẾU

#### Dashboard:
- ❌ **Tổng quan doanh thu, đặt phòng (theo ngày/tuần)** - Chưa có dashboard tổng hợp

#### Chi tiết:
- ❌ **Doanh thu (theo địa điểm, homestay, khu vực)** - Chưa có
- ❌ **Khách (đặt nhiều, doanh thu cao, hủy nhiều)** - Chưa có
- ❌ **Đặt phòng (thời điểm hủy/đặt nhiều, homestay hủy nhiều/ít, doanh thu cao)** - Chưa có
- ❌ **Homestay (lịch trống, tỷ lệ hoàn tiền)** - Chưa có

---

## 11. QUẢN LÝ VẬT TƯ (SUPPLIES) - SupplyController

### ✅ ĐÃ CÓ

#### Vật tư trong phòng:
- ✅ Hiển thị danh sách vật tư với phân trang
- ✅ Filter theo category, status, stock_status
- ✅ Tìm kiếm theo tên
- ✅ CRUD operations
- ✅ Low stock items (`getLowStockItems`)
- ✅ Out of stock items (`getOutOfStockItems`)
- ✅ Statistics (`getStatistics`)
- ✅ Adjust stock (`adjustStock`)
- ✅ Supply logs (SupplyLogController)

### ❌ CHƯA CÓ / THIẾU

- ❌ **Vật tư mất, hỏng đền tiền** - Chưa có logic tính tiền đền bù
- ❌ **Tạo bảng dịch vụ chung (ăn sáng, giặt là, xe đưa đón…)** - Có ServiceController nhưng chưa rõ có đủ không
- ❌ **Dịch vụ có thể miễn phí hoặc tính phí tùy loại phòng** - Chưa có logic này

---

## 12. XÁC THỰC TÀI KHOẢN - AdminAuthController

### ✅ ĐÃ CÓ

#### Đăng nhập:
- ✅ Đăng nhập bằng Email
- ✅ Role = admin
- ✅ Token-based authentication (Sanctum)
- ✅ Validation

### ❌ CHƯA CÓ / THIẾU

#### Quên mật khẩu:
- ❌ **Gửi OTP qua email** - Chưa có
- ❌ **Xác thực OTP để đổi mật khẩu** - Chưa có
- ⚠️ Có ResetPasswordController cho User nhưng chưa có cho Admin

---

## 13. QUẢN LÝ MAIL ĐẶT PHÒNG

### ❌ CHƯA CÓ / THIẾU (HOÀN TOÀN)

- ❌ **Cấu hình mẫu email xác nhận (template)** - Chưa có
- ❌ **Quản lý ngôn ngữ email (đa ngôn ngữ)** - Chưa có
- ❌ **Xem nhật ký (log) toàn bộ email xác nhận đã gửi** - Chưa có
- ❌ **Thống kê số lượng email gửi thành công/thất bại** - Chưa có
- ❌ **Thiết lập địa chỉ email hệ thống (SMTP, API gửi mail)** - Chưa có
- ❌ **Phân quyền: cho phép nhân viên sử dụng mẫu email đã định sẵn** - Chưa có
- ❌ **Bật/tắt chế độ gửi mail tự động hoặc thủ công** - Chưa có

---

## 14. QUẢN LÝ HÓA ĐƠN (INVOICES) - InvoiceController

### ✅ ĐÃ CÓ

#### Cấu hình cách tính hóa đơn:
- ✅ Cấu hình cách tính (`getCalculationConfig`, `setCalculationConfig`)
- ✅ Refund policies (`getRefundPolicyConfig`, `createRefundPolicy`, `updateRefundPolicy`)

#### Hỗ trợ tách/gộp hóa đơn:
- ✅ Merge invoices (`mergeInvoices`)
- ✅ Split invoice (`splitInvoice`)

#### Chính sách hoàn tiền:
- ✅ Apply refund policy (`applyRefundPolicy`)
- ✅ RefundPolicy model và controller methods

#### Mã giảm giá:
- ✅ Apply discount (`applyDiscount`)
- ✅ Remove discount (`removeDiscount`)

#### Khác:
- ✅ Create from booking (`createFromBooking`)
- ✅ Mark as paid (`markAsPaid`)
- ✅ Update status (`updateStatus`)
- ✅ Statistics (`statistics`)

### ❌ CHƯA CÓ / THIẾU

- ✅ Tất cả các chức năng cơ bản đã có
- ⚠️ Có thể cần thêm export Excel/PDF

---

## 📊 TỔNG KẾT

### ✅ ĐÃ HOÀN THÀNH (Khoảng 60-70%)

1. **Quản lý danh mục (Loại homestay)** - 70%
2. **Quản lý phòng (Listings)** - 75%
3. **Quản lý tiện ích (Amenities)** - 80%
4. **Quản lý lưu trú (Tags/Experiences)** - 60%
5. **Quản lý đặt phòng (Bookings)** - 50%
6. **Quản lý người dùng (Users)** - 70%
7. **Quản lý mã giảm giá (Promotions)** - 80%
8. **Quản lý đánh giá (Reviews)** - 90%
9. **Quản lý bình luận (Messages)** - 85%
10. **Thống kê (Analytics)** - 30%
11. **Quản lý vật tư (Supplies)** - 80%
12. **Xác thực tài khoản** - 50%
13. **Quản lý mail đặt phòng** - 0%
14. **Quản lý hóa đơn (Invoices)** - 90%

### ❌ CẦN BỔ SUNG (Ưu tiên cao)

1. **Quản lý mail đặt phòng** - Chưa có gì (0%)
2. **Thống kê (Analytics)** - Dashboard tổng hợp, báo cáo chi tiết
3. **Quản lý đặt phòng (Bookings)** - Filtering, searching, báo cáo, export
4. **Xác thực tài khoản** - Quên mật khẩu với OTP
5. **Quản lý danh mục (Loại homestay)** - Sorting, status, history
6. **Quản lý phòng (Listings)** - Sorting, xác minh giấy tờ
7. **Quản lý người dùng (Users)** - Bulk operations, danh sách khóa

### ⚠️ CẦN CẢI THIỆN (Ưu tiên trung bình)

1. **Preset pagination (15, 30, 45)** - Tất cả controllers
2. **Sorting options** - Tất cả controllers
3. **Bulk operations** - Một số controllers
4. **Export Excel/PDF** - BookingOrder, Invoice
5. **Xác minh giấy tờ** - Room, User
6. **History/Soft deletes** - RoomType, các models khác

---

## 🎯 KHUYẾN NGHỊ

### Ưu tiên cao (Làm ngay):
1. **Quản lý mail đặt phòng** - Tạo EmailTemplateController, EmailLogController
2. **Dashboard Analytics** - Tạo AnalyticsController với các báo cáo tổng hợp
3. **Filtering & Searching cho Bookings** - Bổ sung vào BookingOrderController
4. **Quên mật khẩu với OTP** - Bổ sung vào AdminAuthController

### Ưu tiên trung bình (Làm sau):
1. **Sorting options** - Bổ sung vào tất cả controllers
2. **Preset pagination** - Bổ sung vào tất cả controllers
3. **Bulk operations** - Bổ sung vào UserController, PromotionController
4. **Export Excel/PDF** - Tạo ExportController

### Ưu tiên thấp (Tùy chọn):
1. **History/Soft deletes** - Bổ sung vào các models
2. **Xác minh giấy tờ** - Tạo VerificationController
3. **Biến thể tiện ích** - Nếu cần

---

**Ngày tạo**: 2025-01-11
**Trạng thái**: Đang phát triển (60-70% hoàn thành)

