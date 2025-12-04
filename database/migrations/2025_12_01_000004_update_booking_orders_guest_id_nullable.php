<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cho phép guest_id có thể null (cho trường hợp admin tạo booking mà khách chưa đăng ký)
     */
    public function up(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            // Drop foreign key constraint trước
            $table->dropForeign(['guest_id']);
            
            // Sửa column thành nullable
            $table->foreignId('guest_id')->nullable()->change();
            
            // Thêm lại foreign key constraint với nullable
            $table->foreign('guest_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['guest_id']);
            
            // Sửa column thành not null (cần xóa các record có guest_id null trước)
            $table->foreignId('guest_id')->nullable(false)->change();
            
            // Thêm lại foreign key constraint
            $table->foreign('guest_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};







