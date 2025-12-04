<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->onDelete('set null');
            $table->string('recipient_email'); // Email người nhận
            $table->string('subject'); // Tiêu đề email đã gửi
            $table->text('body'); // Nội dung email đã gửi
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable(); // Lỗi nếu có
            $table->timestamp('sent_at')->nullable(); // Thời gian gửi
            $table->string('related_type')->nullable(); // Loại đối tượng liên quan (BookingOrder, Invoice, etc.)
            $table->unsignedBigInteger('related_id')->nullable(); // ID đối tượng liên quan
            $table->timestamps();
            
            $table->index('recipient_email');
            $table->index('status');
            $table->index('sent_at');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

