<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->foreignId('guest_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('booking_orders', function (Blueprint $table) {
            $table->foreignId('guest_id')->nullable(false)->change();
        });
    }
};
