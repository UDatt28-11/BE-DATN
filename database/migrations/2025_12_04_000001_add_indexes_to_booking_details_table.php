<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Thêm indexes cho booking_details để tối ưu performance khi query availability
     */
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            // Composite index cho query availability (room_id + check_in_date + check_out_date)
            // Giúp tối ưu query: whereDoesntHave('bookingDetails', ...) trong RoomController
            $table->index(['room_id', 'check_in_date', 'check_out_date'], 'idx_room_check_dates');
            
            // Index cho status để filter nhanh các booking active/cancelled
            $table->index('status', 'idx_booking_detail_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropIndex('idx_room_check_dates');
            $table->dropIndex('idx_booking_detail_status');
        });
    }
};



