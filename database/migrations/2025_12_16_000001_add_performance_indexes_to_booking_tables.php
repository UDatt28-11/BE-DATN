<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for date range queries
     */
    public function up(): void
    {
        // Add indexes to booking_details table (main booking dates table)
        if (Schema::hasTable('booking_details')) {
            Schema::table('booking_details', function (Blueprint $table) {
                // Composite index for date range queries
                $table->index(['check_in_date', 'check_out_date'], 'booking_details_date_range_idx');
                
                // Single index on room_id for joining
                $table->index('room_id', 'booking_details_room_id_idx');
                
                // Composite index for room + dates (optimal for availability queries)
                $table->index(['room_id', 'check_in_date', 'check_out_date'], 'booking_details_room_dates_idx');
            });
        }

        // Add indexes to booking_orders table (for status filtering)
        if (Schema::hasTable('booking_orders')) {
            Schema::table('booking_orders', function (Blueprint $table) {
                // Index on status for filtering confirmed/cancelled bookings
                $table->index('status', 'booking_orders_status_idx');
            });
            
            // Index on booking_order_id in booking_details (if not exists)
            if (Schema::hasColumn('booking_details', 'booking_order_id')) {
                Schema::table('booking_details', function (Blueprint $detailTable) {
                    $detailTable->index('booking_order_id', 'booking_details_order_id_idx');
                });
            }
        }

        // Add indexes to rooms table for availability queries
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                // Composite index for room_type_id + status
                $table->index(['room_type_id', 'status'], 'rooms_type_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('booking_details')) {
            Schema::table('booking_details', function (Blueprint $table) {
                $table->dropIndex(['check_in_date', 'check_out_date']);
                $table->dropIndex(['room_id']);
                $table->dropIndex(['room_id', 'check_in_date', 'check_out_date']);
                $table->dropIndex(['booking_order_id']);
            });
        }

        if (Schema::hasTable('booking_orders')) {
            Schema::table('booking_orders', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }

        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropIndex(['room_type_id', 'status']);
            });
        }
    }
};
