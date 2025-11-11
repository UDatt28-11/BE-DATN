<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // SMTP_HOST, SMTP_PORT, etc.
            $table->text('value')->nullable(); // Giá trị cấu hình
            $table->string('description')->nullable(); // Mô tả
            $table->boolean('is_encrypted')->default(false); // Có mã hóa không (cho password)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_configs');
    }
};

