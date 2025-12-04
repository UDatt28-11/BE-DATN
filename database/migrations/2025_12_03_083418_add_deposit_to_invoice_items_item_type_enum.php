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
        // Thêm 'deposit' vào enum item_type của bảng invoice_items
        // MySQL/MariaDB yêu cầu MODIFY COLUMN để thay đổi enum
        DB::statement("ALTER TABLE invoice_items MODIFY COLUMN item_type ENUM('room_charge', 'service_charge', 'damage_fee', 'deposit', 'other') DEFAULT 'room_charge'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Xóa 'deposit' khỏi enum (chỉ nếu không có deposit items nào)
        // Lưu ý: Nếu đã có deposit items, migration này sẽ fail
        // Trong trường hợp đó, cần xóa tất cả deposit items trước
        DB::statement("ALTER TABLE invoice_items MODIFY COLUMN item_type ENUM('room_charge', 'service_charge', 'damage_fee', 'other') DEFAULT 'room_charge'");
    }
};
