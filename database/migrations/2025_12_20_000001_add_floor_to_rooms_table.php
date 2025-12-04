<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Thêm field floor vào rooms table để hỗ trợ filter "Vị trí tầng"
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Thêm field floor_number (số tầng cụ thể: 0 = tầng trệt, 1, 2, 3...)
            $table->integer('floor_number')->nullable()->after('description');
            
            // Thêm field floor_category để phân loại nhanh (ground_floor, upper_floor, attic)
            $table->enum('floor_category', ['ground_floor', 'upper_floor', 'attic'])->nullable()->after('floor_number');
            
            // Index để tối ưu query filter
            $table->index('floor_category');
            $table->index('floor_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['floor_category']);
            $table->dropIndex(['floor_number']);
            $table->dropColumn(['floor_category', 'floor_number']);
        });
    }
};

