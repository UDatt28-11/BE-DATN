<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Thêm các field còn thiếu vào bảng invoice_items
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Thêm tax_rate và tax_amount
            if (!Schema::hasColumn('invoice_items', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(10)->after('unit_price')->comment('Thuế suất %');
            }
            if (!Schema::hasColumn('invoice_items', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            }

            // Thêm amount (tổng trước thuế) nếu chưa có
            if (!Schema::hasColumn('invoice_items', 'amount')) {
                $table->decimal('amount', 10, 2)->default(0)->after('tax_amount')->comment('Tổng trước thuế (quantity * unit_price)');
            }

            // Thêm total (tổng sau thuế) nếu chưa có
            if (!Schema::hasColumn('invoice_items', 'total')) {
                $table->decimal('total', 10, 2)->default(0)->after('amount')->comment('Tổng sau thuế (amount + tax_amount)');
            }

            // Cập nhật item_type enum để thêm 'penalty'
            // Laravel không hỗ trợ thay đổi enum trực tiếp, cần tạo migration riêng nếu cần

            // Thêm các foreign key liên quan
            if (!Schema::hasColumn('invoice_items', 'booking_detail_id')) {
                $table->foreignId('booking_detail_id')->nullable()->after('invoice_id')
                    ->constrained('booking_details')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoice_items', 'room_id')) {
                $table->foreignId('room_id')->nullable()->after('booking_detail_id')
                    ->constrained('rooms')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoice_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('room_id')
                    ->constrained('services')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $columns = [
                'tax_rate',
                'tax_amount',
                'amount',
                'total',
                'booking_detail_id',
                'room_id',
                'service_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};





