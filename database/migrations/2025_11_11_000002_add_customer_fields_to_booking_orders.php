<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            // Thêm các trường mới nếu chưa có
            if (!Schema::hasColumn('booking_orders', 'customer_name')) {
                $table->string('customer_name')->after('order_code');
            }
            if (!Schema::hasColumn('booking_orders', 'customer_phone')) {
                $table->string('customer_phone', 20)->after('customer_name');
            }
            if (!Schema::hasColumn('booking_orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('booking_orders', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('booking_orders', 'notes')) {
                $table->text('notes')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_email', 'payment_method', 'notes']);
        });
    }
};
