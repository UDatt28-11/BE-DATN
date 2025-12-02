<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL: ALTER TABLE để thêm các giá trị mới vào enum
        // Lưu ý: MySQL không hỗ trợ MODIFY enum trực tiếp, cần dùng raw query
        DB::statement("ALTER TABLE booking_orders MODIFY COLUMN status ENUM('pending', 'confirmed', 'checked_in', 'partially_checked_in', 'checked_out', 'partially_checked_out', 'cancelled', 'completed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback về enum ban đầu (không có partially_checked_in và partially_checked_out)
        // Lưu ý: Cần đảm bảo không có dữ liệu nào đang dùng các giá trị này
        DB::statement("ALTER TABLE booking_orders MODIFY COLUMN status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'completed') DEFAULT 'pending'");
    }
};
