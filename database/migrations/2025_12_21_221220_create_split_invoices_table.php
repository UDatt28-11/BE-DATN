<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('split_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_invoice_id')->constrained('invoices')->onDelete('cascade')->comment('Hóa đơn gốc');
            $table->foreignId('booking_detail_id')->constrained('booking_details')->onDelete('cascade')->comment('Phòng được tách ra');
            $table->foreignId('new_invoice_id')->constrained('invoices')->onDelete('cascade')->comment('Hóa đơn mới sau khi tách');
            $table->decimal('room_price', 12, 2)->default(0)->comment('Giá phòng');
            $table->decimal('service_price', 12, 2)->default(0)->comment('Tổng giá dịch vụ');
            $table->decimal('damage_price', 12, 2)->default(0)->comment('Tổng giá thiệt hại');
            $table->decimal('deposit_amount', 12, 2)->default(0)->comment('Tiền cọc của phòng này');
            $table->decimal('voucher_discount', 12, 2)->default(0)->comment('Giảm giá voucher được phân bổ');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('Tổng tiền hóa đơn tách');
            $table->text('notes')->nullable()->comment('Ghi chú');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('split_invoices');
    }
};
