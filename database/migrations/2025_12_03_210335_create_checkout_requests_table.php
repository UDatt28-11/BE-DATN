<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_detail_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable(); // Ghi chú từ user (lý do checkout sớm, yêu cầu đặc biệt, etc.)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->index('booking_order_id');
            $table->index('booking_detail_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_requests');
    }
};
