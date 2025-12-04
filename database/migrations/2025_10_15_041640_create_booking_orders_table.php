<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('order_code')->unique();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->nullable()->comment('Số tiền đặt cọc');
            $table->decimal('paid_amount', 12, 2)->default(0)->comment('Tổng số tiền đã thanh toán');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->comment('Trạng thái thanh toán');
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'partially_checked_in', 'checked_out', 'partially_checked_out', 'cancelled', 'completed'])->default('pending');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_orders');
    }
};
