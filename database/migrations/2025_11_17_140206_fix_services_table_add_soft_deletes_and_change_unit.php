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
        Schema::table('services', function (Blueprint $table) {
            // Thêm softDeletes nếu chưa có
            if (!Schema::hasColumn('services', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Thay đổi cột unit từ enum sang string
        // Lưu ý: MySQL không hỗ trợ thay đổi trực tiếp enum, cần drop và tạo lại
        if (Schema::hasColumn('services', 'unit')) {
            // Kiểm tra xem có phải enum không
            $columnType = DB::select("SHOW COLUMNS FROM services WHERE Field = 'unit'");
            if (!empty($columnType) && str_contains($columnType[0]->Type, 'enum')) {
                // Thay đổi từ enum sang string
                DB::statement("ALTER TABLE services MODIFY COLUMN unit VARCHAR(50) DEFAULT 'per_night'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Xóa softDeletes nếu có
            if (Schema::hasColumn('services', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        // Khôi phục lại enum (nếu cần)
        // Lưu ý: Không thể khôi phục chính xác giá trị enum cũ, nên chỉ đổi về enum mặc định
        if (Schema::hasColumn('services', 'unit')) {
            DB::statement("ALTER TABLE services MODIFY COLUMN unit ENUM('per_person', 'per_day', 'per_item', 'per_booking') DEFAULT 'per_item'");
        }
    }
};
