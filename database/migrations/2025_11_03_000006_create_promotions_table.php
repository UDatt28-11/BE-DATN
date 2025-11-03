<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create promotions table
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('properties')->onDelete('cascade');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // For percentage discounts
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->integer('max_usage_limit')->nullable();
            $table->integer('max_usage_per_user')->default(1);
            $table->integer('usage_count')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->enum('applicable_to', ['all', 'specific_rooms', 'specific_room_types'])->default('all');
            $table->json('additional_settings')->nullable();
            $table->timestamps();
            
            $table->index('property_id');
            $table->index('code');
            $table->index('is_active');
            $table->index('start_date');
            $table->index('end_date');
        });

        // Create pivot table for promotion and rooms
        Schema::create('promotion_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['promotion_id', 'room_id']);
        });

        // Create pivot table for promotion and room types
        Schema::create('promotion_room_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignId('room_type_id')->constrained('room_types')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['promotion_id', 'room_type_id']);
        });

        // Create pivot table for promotion usage tracking
        Schema::create('promotion_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignId('booking_order_id')->constrained('booking_orders')->onDelete('cascade');
            $table->decimal('applied_discount_amount', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['promotion_id', 'booking_order_id']);
            $table->index('promotion_id');
            $table->index('booking_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usage');
        Schema::dropIfExists('promotion_room_type');
        Schema::dropIfExists('promotion_room');
        Schema::dropIfExists('promotions');
    }
};
