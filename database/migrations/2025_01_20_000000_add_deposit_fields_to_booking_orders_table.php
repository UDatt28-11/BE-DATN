<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable()->after('total_amount')->comment('Số tiền đặt cọc');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('deposit_amount')->comment('Tổng số tiền đã thanh toán');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('paid_amount')->comment('Trạng thái thanh toán');
        });
    }

    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'paid_amount', 'payment_status']);
        });
    }
};

