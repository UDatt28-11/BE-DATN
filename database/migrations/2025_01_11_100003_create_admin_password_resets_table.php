<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('otp', 6); // OTP 6 chữ số
            $table->timestamp('expires_at'); // Thời gian hết hạn
            $table->boolean('is_used')->default(false); // Đã sử dụng chưa
            $table->timestamp('used_at')->nullable(); // Thời gian sử dụng
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_resets');
    }
};

