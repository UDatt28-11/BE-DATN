<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Di chuyển dữ liệu từ room_images sang room_type_images
        // Lấy room_type_id từ room thông qua room_id
        DB::statement("
            INSERT INTO room_type_images (room_type_id, image_url, is_primary, created_at, updated_at)
            SELECT 
                r.room_type_id,
                ri.image_url,
                ri.is_primary,
                ri.created_at,
                ri.updated_at
            FROM room_images ri
            INNER JOIN rooms r ON ri.room_id = r.id
            WHERE NOT EXISTS (
                SELECT 1 FROM room_type_images rti 
                WHERE rti.room_type_id = r.room_type_id 
                AND rti.image_url = ri.image_url
            )
        ");
    }

    public function down(): void
    {
        // Không thể rollback chính xác vì đã mất thông tin room_id
        // Chỉ xóa dữ liệu trong room_type_images
        DB::table('room_type_images')->truncate();
    }
};

