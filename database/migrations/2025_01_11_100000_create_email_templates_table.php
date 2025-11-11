<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên template (booking_confirmation, booking_cancellation, etc.)
            $table->string('subject'); // Tiêu đề email
            $table->text('body'); // Nội dung email (HTML)
            $table->string('language')->default('vi'); // Ngôn ngữ (vi, en, etc.)
            $table->json('variables')->nullable(); // Các biến có thể thay thế trong template
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('name');
            $table->index('language');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};

