<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            // Số lần đã đổi ngày (1 lần miễn phí)
            $table->unsignedInteger('date_change_count')->default(0)->after('notes');
            // Số tiền hoàn lại khi hủy
            $table->decimal('refund_amount', 15, 2)->nullable()->after('date_change_count');
            // Lý do hủy
            $table->text('cancellation_reason')->nullable()->after('refund_amount');
            // Thời gian hủy
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->dropColumn(['date_change_count', 'refund_amount', 'cancellation_reason', 'cancelled_at']);
        });
    }
};

