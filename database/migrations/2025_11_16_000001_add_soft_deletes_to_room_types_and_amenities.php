<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            if (!Schema::hasColumn('room_types', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('amenities', function (Blueprint $table) {
            if (!Schema::hasColumn('amenities', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            if (Schema::hasColumn('room_types', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('amenities', function (Blueprint $table) {
            if (Schema::hasColumn('amenities', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};


