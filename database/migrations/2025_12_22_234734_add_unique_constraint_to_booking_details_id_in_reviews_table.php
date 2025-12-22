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
        // Xóa các review trùng lặp trước khi thêm unique constraint
        // Giữ lại review đầu tiên cho mỗi booking_detail_id
        DB::statement('
            DELETE r1 FROM reviews r1
            INNER JOIN reviews r2 
            WHERE r1.id > r2.id 
            AND r1.booking_details_id = r2.booking_details_id
        ');

        // Thêm unique constraint cho booking_details_id
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique('booking_details_id', 'reviews_booking_details_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_booking_details_id_unique');
        });
    }
};
