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
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('notes')->constrained('vouchers')->nullOnDelete();
            $table->decimal('discount_amount', 15, 2)->default(0)->after('voucher_id');
            $table->decimal('original_total_amount', 15, 2)->nullable()->after('discount_amount'); // Tổng tiền gốc trước khi giảm giá
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_id', 'discount_amount', 'original_total_amount']);
        });
    }
};

