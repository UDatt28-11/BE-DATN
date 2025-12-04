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
        // Thêm 'voucher_discount' vào enum item_type của bảng invoice_items
        DB::statement("ALTER TABLE invoice_items MODIFY COLUMN item_type ENUM('room_charge', 'service_charge', 'damage_fee', 'deposit', 'voucher_discount', 'penalty', 'other') DEFAULT 'room_charge'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Loại bỏ 'voucher_discount' và 'penalty' khỏi enum (quay lại phiên bản trước)
        DB::statement("ALTER TABLE invoice_items MODIFY COLUMN item_type ENUM('room_charge', 'service_charge', 'damage_fee', 'deposit', 'other') DEFAULT 'room_charge'");
    }
};

