<?php

/**
 * @OA\OpenApi(
 *     openapi="3.0.0",
 *     info=@OA\Info(
 *         title="API Documentation",
 *         version="1.0.0",
 *         description="Hệ thống quản lý hóa đơn và vật tư cho homestay booking - Invoice & Supply Management System",
 *         contact=@OA\Contact(
 *             name="BE-DATN-kien Team",
 *             email="support@homestay.local"
 *         ),
 *         license=@OA\License(
 *             name="MIT"
 *         )
 *     ),
 *     servers={
 *         @OA\Server(
 *             url="http://127.0.0.1:8000/api",
 *             description="Development Server"
 *         ),
 *         @OA\Server(
 *             url="http://localhost:8000/api",
 *             description="Local Development"
 *         )
 *     },
 *     components=@OA\Components(
 *         securitySchemes={
 *             @OA\SecurityScheme(
 *                 type="http",
 *                 scheme="bearer",
 *                 securityScheme="bearer"
 *             ),
 *             @OA\SecurityScheme(
 *                 type="apiKey",
 *                 in="header",
 *                 name="X-API-Key",
 *                 securityScheme="api_key"
 *             )
 *         },
 *         schemas={
 *             @OA\Schema(
 *                 schema="BookingOrder",
 *                 type="object",
 *                 title="Booking Order",
 *                 description="Đơn đặt phòng",
 *                 properties={
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="guest_id", type="integer", example=1),
 *                     @OA\Property(property="order_code", type="string", example="BK202511010001"),
 *                     @OA\Property(property="customer_name", type="string", example="Nguyễn Văn An"),
 *                     @OA\Property(property="customer_phone", type="string", example="0901234567"),
 *                     @OA\Property(property="customer_email", type="string", example="nva@example.com"),
 *                     @OA\Property(property="total_amount", type="number", format="float", example=3000000),
 *                     @OA\Property(property="payment_method", type="string", enum={"credit_card", "bank_transfer", "cash", "e_wallet"}, example="cash"),
 *                     @OA\Property(property="notes", type="string", example="Yêu cầu phòng view biển"),
 *                     @OA\Property(property="status", type="string", enum={"pending", "confirmed", "cancelled", "completed"}, example="confirmed"),
 *                     @OA\Property(property="created_at", type="string", format="date-time"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time")
 *                 }
 *             ),
 *             @OA\Schema(
 *                 schema="Invoice",
 *                 type="object",
 *                 title="Invoice",
 *                 description="Hóa đơn",
 *                 properties={
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="booking_order_id", type="integer", example=1),
 *                     @OA\Property(property="invoice_number", type="string", example="INV-001"),
 *                     @OA\Property(property="issue_date", type="string", format="date", example="2025-11-03"),
 *                     @OA\Property(property="due_date", type="string", format="date", example="2025-11-10"),
 *                     @OA\Property(property="total_amount", type="number", format="float", example=5000000),
 *                     @OA\Property(property="discount_amount", type="number", format="float", example=0),
 *                     @OA\Property(property="refund_amount", type="number", format="float", example=0),
 *                     @OA\Property(property="status", type="string", enum={"pending", "paid", "overdue", "cancelled"}, example="pending"),
 *                     @OA\Property(property="created_at", type="string", format="date-time")
 *                 }
 *             ),
 *             @OA\Schema(
 *                 schema="InvoiceConfig",
 *                 type="object",
 *                 title="Invoice Configuration",
 *                 description="Cấu hình tính toán hóa đơn",
 *                 properties={
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="calculation_method", type="string", enum={"automatic", "manual"}, example="automatic"),
 *                     @OA\Property(property="auto_calculate", type="boolean", example=true),
 *                     @OA\Property(property="tax_rate", type="number", format="float", example=10),
 *                     @OA\Property(property="service_charge_rate", type="number", format="float", example=5),
 *                     @OA\Property(property="late_fee_percent", type="number", format="float", example=2),
 *                     @OA\Property(property="late_fee_per_day", type="number", format="float", example=50000)
 *                 }
 *             ),
 *             @OA\Schema(
 *                 schema="RefundPolicy",
 *                 type="object",
 *                 title="Refund Policy",
 *                 description="Chính sách hoàn tiền",
 *                 properties={
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="name", type="string", example="Hoàn 100%"),
 *                     @OA\Property(property="refund_percent", type="number", format="float", example=100),
 *                     @OA\Property(property="days_before_checkin", type="integer", example=7),
 *                     @OA\Property(property="penalty_percent", type="number", format="float", example=0),
 *                     @OA\Property(property="is_active", type="boolean", example=true)
 *                 }
 *             ),
 *             @OA\Schema(
 *                 schema="InvoiceDiscount",
 *                 type="object",
 *                 title="Invoice Discount",
 *                 description="Giảm giá hóa đơn",
 *                 properties={
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="invoice_id", type="integer", example=1),
 *                     @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed_amount"}, example="percentage"),
 *                     @OA\Property(property="discount_value", type="number", format="float", example=10),
 *                     @OA\Property(property="discount_amount", type="number", format="float", example=500000),
 *                     @OA\Property(property="reason", type="string", example="Khuyến mãi đặc biệt"),
 *                     @OA\Property(property="approved_at", type="string", format="date-time")
 *                 }
 *             ),
 *             @OA\Schema(
 *                 schema="Error",
 *                 type="object",
 *                 title="Error",
 *                 properties={
 *                     @OA\Property(property="success", type="boolean", example=false),
 *                     @OA\Property(property="message", type="string", example="Lỗi xảy ra")
 *                 }
 *             )
 *         }
 *     ),
 *     security={
 *         {}
 *     }
 * )
 */
