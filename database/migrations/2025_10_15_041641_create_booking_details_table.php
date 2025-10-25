<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedTinyInteger('num_adults');
            $table->unsignedTinyInteger('num_children')->default(0);
            $table->decimal('sub_total', 10, 2);
            $table->enum('status', ['active', 'cancelled', 'checked_in', 'checked_out'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_details');
    }
};
