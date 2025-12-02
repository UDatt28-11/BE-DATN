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
        Schema::table('booking_orders', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['guest_id']);
            // Make guest_id nullable
            $table->foreignId('guest_id')->nullable()->change();
            // Re-add foreign key constraint
            $table->foreign('guest_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['guest_id']);
            // Make guest_id not nullable
            $table->foreignId('guest_id')->nullable(false)->change();
            // Re-add foreign key constraint
            $table->foreign('guest_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
