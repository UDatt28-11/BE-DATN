<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * These indexes will significantly improve query performance
     */
    public function up(): void
    {
        // Room Types indexes
        if (Schema::hasTable('room_types')) {
            Schema::table('room_types', function (Blueprint $table) {
                // Index for filtering and sorting
                if (!$this->indexExists('room_types', 'room_types_status_index')) {
                    $table->index('status', 'room_types_status_index');
                }
                if (Schema::hasColumn('room_types', 'verification_status') && !$this->indexExists('room_types', 'room_types_verification_status_index')) {
                    $table->index('verification_status', 'room_types_verification_status_index');
                }
                if (Schema::hasColumn('room_types', 'base_price') && !$this->indexExists('room_types', 'room_types_base_price_index')) {
                    $table->index('base_price', 'room_types_base_price_index');
                }
                // Composite index for common queries
                if (Schema::hasColumn('room_types', 'base_price') && !$this->indexExists('room_types', 'room_types_status_price_index')) {
                    $table->index(['status', 'base_price'], 'room_types_status_price_index');
                }
            });
        }

        // Rooms indexes
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (!$this->indexExists('rooms', 'rooms_status_index')) {
                    $table->index('status', 'rooms_status_index');
                }
                if (!$this->indexExists('rooms', 'rooms_property_id_index')) {
                    $table->index('property_id', 'rooms_property_id_index');
                }
                if (!$this->indexExists('rooms', 'rooms_room_type_id_index')) {
                    $table->index('room_type_id', 'rooms_room_type_id_index');
                }
                // Composite index
                if (!$this->indexExists('rooms', 'rooms_status_property_index')) {
                    $table->index(['status', 'property_id'], 'rooms_status_property_index');
                }
            });
        }

        // Booking Orders indexes
        if (Schema::hasTable('booking_orders')) {
            Schema::table('booking_orders', function (Blueprint $table) {
                if (!$this->indexExists('booking_orders', 'booking_orders_status_index')) {
                    $table->index('status', 'booking_orders_status_index');
                }
                if (!$this->indexExists('booking_orders', 'booking_orders_payment_status_index')) {
                    $table->index('payment_status', 'booking_orders_payment_status_index');
                }
            });
        }

        // Booking Details indexes
        if (Schema::hasTable('booking_details')) {
            Schema::table('booking_details', function (Blueprint $table) {
                if (!$this->indexExists('booking_details', 'booking_details_check_in_date_index')) {
                    $table->index('check_in_date', 'booking_details_check_in_date_index');
                }
                if (!$this->indexExists('booking_details', 'booking_details_check_out_date_index')) {
                    $table->index('check_out_date', 'booking_details_check_out_date_index');
                }
                if (!$this->indexExists('booking_details', 'booking_details_status_index')) {
                    $table->index('status', 'booking_details_status_index');
                }
                // Composite index for date range queries
                if (!$this->indexExists('booking_details', 'booking_details_dates_index')) {
                    $table->index(['check_in_date', 'check_out_date', 'status'], 'booking_details_dates_index');
                }
            });
        }

        // Reviews indexes
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (Schema::hasColumn('reviews', 'status') && !$this->indexExists('reviews', 'reviews_status_index')) {
                    $table->index('status', 'reviews_status_index');
                }
                if (!$this->indexExists('reviews', 'reviews_rating_index')) {
                    $table->index('rating', 'reviews_rating_index');
                }
                if (Schema::hasColumn('reviews', 'room_type_id') && !$this->indexExists('reviews', 'reviews_room_type_id_index')) {
                    $table->index('room_type_id', 'reviews_room_type_id_index');
                }
            });
        }

        // Properties indexes
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table) {
                if (Schema::hasColumn('properties', 'status') && !$this->indexExists('properties', 'properties_status_index')) {
                    $table->index('status', 'properties_status_index');
                }
                if (Schema::hasColumn('properties', 'verification_status') && !$this->indexExists('properties', 'properties_verification_status_index')) {
                    $table->index('verification_status', 'properties_verification_status_index');
                }
            });
        }

        // Amenities pivot table indexes
        if (Schema::hasTable('room_amenities')) {
            Schema::table('room_amenities', function (Blueprint $table) {
                if (!$this->indexExists('room_amenities', 'room_amenities_room_id_index')) {
                    $table->index('room_id', 'room_amenities_room_id_index');
                }
                if (!$this->indexExists('room_amenities', 'room_amenities_amenity_id_index')) {
                    $table->index('amenity_id', 'room_amenities_amenity_id_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Room Types
        $this->dropIndexSafely('room_types', [
            'room_types_status_index',
            'room_types_verification_status_index',
            'room_types_base_price_index',
            'room_types_status_price_index',
        ]);

        // Rooms
        $this->dropIndexSafely('rooms', [
            'rooms_status_index',
            'rooms_property_id_index',
            'rooms_room_type_id_index',
            'rooms_status_property_index',
        ]);

        // Booking Orders
        $this->dropIndexSafely('booking_orders', [
            'booking_orders_status_index',
            'booking_orders_payment_status_index',
        ]);

        // Booking Details
        $this->dropIndexSafely('booking_details', [
            'booking_details_check_in_date_index',
            'booking_details_check_out_date_index',
            'booking_details_status_index',
            'booking_details_dates_index',
        ]);

        // Reviews
        $this->dropIndexSafely('reviews', [
            'reviews_status_index',
            'reviews_rating_index',
            'reviews_room_type_id_index',
        ]);

        // Properties
        $this->dropIndexSafely('properties', [
            'properties_status_index',
            'properties_verification_status_index',
        ]);

        // Amenities pivot
        $this->dropIndexSafely('room_amenities', [
            'room_amenities_room_id_index',
            'room_amenities_amenity_id_index',
        ]);
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = collect(\DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->toArray();
        
        return in_array($indexName, $indexes);
    }

    /**
     * Safely drop indexes from a table
     */
    private function dropIndexSafely(string $table, array $indexes): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
            foreach ($indexes as $indexName) {
                if ($this->indexExists($table, $indexName)) {
                    $blueprint->dropIndex($indexName);
                }
            }
        });
    }
};

