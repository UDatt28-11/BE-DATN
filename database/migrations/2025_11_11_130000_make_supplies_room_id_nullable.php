<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Làm room_id nullable trong bảng supplies để cho phép tạo vật tư không gắn với phòng cụ thể
     */
    public function up(): void
    {
        if (Schema::hasColumn('supplies', 'room_id')) {
            Schema::table('supplies', function (Blueprint $table) {
                // Drop foreign key constraint
                $table->dropForeign(['room_id']);
            });
            
            // Modify column thành nullable (sử dụng raw SQL để tránh vấn đề với Doctrine DBAL)
            DB::statement('ALTER TABLE `supplies` MODIFY `room_id` BIGINT UNSIGNED NULL');
            
            // Recreate foreign key constraint (NULL values được phép)
            Schema::table('supplies', function (Blueprint $table) {
                $table->foreign('room_id')
                    ->references('id')
                    ->on('rooms')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Chú ý: Khi rollback, các record có room_id = NULL sẽ bị lỗi
        // Cần xử lý dữ liệu trước khi rollback
        if (Schema::hasColumn('supplies', 'room_id')) {
            // Set room_id cho các record NULL (lấy room_id đầu tiên từ bảng rooms)
            $firstRoomId = DB::table('rooms')->value('id');
            if ($firstRoomId) {
                DB::table('supplies')
                    ->whereNull('room_id')
                    ->update(['room_id' => $firstRoomId]);
            }
            
            Schema::table('supplies', function (Blueprint $table) {
                // Drop foreign key constraint
                $table->dropForeign(['room_id']);
            });
            
            // Đổi lại thành NOT NULL
            DB::statement('ALTER TABLE `supplies` MODIFY `room_id` BIGINT UNSIGNED NOT NULL');
            
            // Recreate foreign key constraint
            Schema::table('supplies', function (Blueprint $table) {
                $table->foreign('room_id')
                    ->references('id')
                    ->on('rooms')
                    ->onDelete('cascade');
            });
        }
    }
};

