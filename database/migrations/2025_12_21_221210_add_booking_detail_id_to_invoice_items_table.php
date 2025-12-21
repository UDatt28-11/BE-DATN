<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'booking_detail_id')) {
                $table->foreignId('booking_detail_id')->nullable()->after('invoice_id')->constrained('booking_details')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'booking_detail_id')) {
                $table->dropForeign(['booking_detail_id']);
                $table->dropColumn('booking_detail_id');
            }
        });
    }
};
