<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kiểm tra xem bảng đã tồn tại chưa
        if (!Schema::hasTable('booking_orders')) {
            // Bảng chưa tồn tại, migration tạo bảng sẽ xử lý
            return;
        }

        // Kiểm tra xem các cột đã tồn tại chưa (có thể đã được tích hợp vào migration tạo bảng)
        if (Schema::hasColumn('booking_orders', 'deposit_amount') && 
            Schema::hasColumn('booking_orders', 'paid_amount') && 
            Schema::hasColumn('booking_orders', 'payment_status')) {
            // Các cột đã tồn tại, không cần làm gì
            return;
        }

        Schema::table('booking_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 12, 2)->nullable()->after('total_amount')->comment('Số tiền đặt cọc');
            }
            if (!Schema::hasColumn('booking_orders', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('deposit_amount')->comment('Tổng số tiền đã thanh toán');
            }
            if (!Schema::hasColumn('booking_orders', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('paid_amount')->comment('Trạng thái thanh toán');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('booking_orders')) {
            return;
        }

        Schema::table('booking_orders', function (Blueprint $table) {
            if (Schema::hasColumn('booking_orders', 'deposit_amount') ||
                Schema::hasColumn('booking_orders', 'paid_amount') ||
                Schema::hasColumn('booking_orders', 'payment_status')) {
                $table->dropColumn(['deposit_amount', 'paid_amount', 'payment_status']);
            }
        });
    }
};


