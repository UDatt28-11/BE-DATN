<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('checked_in_guest_id');
            $table->string('image_url');
            $table->string('side')->nullable()->comment('front, back, or other - mặt trước, mặt sau, hoặc khác');
            $table->integer('order')->default(0)->comment('Thứ tự hiển thị');
            $table->timestamps();
            
            // Tạo foreign key sau khi đảm bảo bảng checked_in_guests đã tồn tại
            $table->foreign('checked_in_guest_id')
                  ->references('id')
                  ->on('checked_in_guests')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_images');
    }
};

