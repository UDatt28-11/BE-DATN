<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Thêm field filter_category vào amenities table để phân loại amenities cho filter
     * - key_amenity: Tiện nghi đặc biệt (Bồn tắm, Ban công, Bếp riêng, Khép kín)
     * - view: Hướng nhìn (View vườn, View núi, View bể bơi)
     * - floor: Vị trí tầng (Tầng trệt, Tầng cao, Gác mái)
     * - null: Tiện ích khác (không thuộc nhóm filter đặc biệt)
     */
    public function up(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->enum('filter_category', ['key_amenity', 'view', 'floor'])->nullable()->after('category');
            
            // Index để tối ưu query filter
            $table->index('filter_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->dropIndex(['filter_category']);
            $table->dropColumn('filter_category');
        });
    }
};

