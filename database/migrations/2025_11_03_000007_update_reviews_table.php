<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update reviews table with additional fields
        Schema::table('reviews', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('reviews', 'property_id')) {
                $table->foreignId('property_id')->nullable()->constrained('properties')->onDelete('cascade');
            }
            if (!Schema::hasColumn('reviews', 'room_id')) {
                $table->foreignId('room_id')->nullable()->constrained('rooms')->onDelete('cascade');
            }
            if (!Schema::hasColumn('reviews', 'title')) {
                $table->string('title')->nullable();
            }
            if (!Schema::hasColumn('reviews', 'photos')) {
                $table->json('photos')->nullable();
            }
            if (!Schema::hasColumn('reviews', 'is_verified_purchase')) {
                $table->boolean('is_verified_purchase')->default(false);
            }
            if (!Schema::hasColumn('reviews', 'is_helpful_count')) {
                $table->integer('is_helpful_count')->default(0);
            }
            if (!Schema::hasColumn('reviews', 'is_not_helpful_count')) {
                $table->integer('is_not_helpful_count')->default(0);
            }
            if (!Schema::hasColumn('reviews', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            }
            if (!Schema::hasColumn('reviews', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
            if (!Schema::hasColumn('reviews', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable();
            }

            $table->index('property_id');
            $table->index('room_id');
            $table->index('status');
            $table->index('is_verified_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeignIdFor('Property::class', 'property_id');
            $table->dropForeignIdFor('Room::class', 'room_id');
            $table->dropColumn([
                'property_id',
                'room_id',
                'title',
                'photos',
                'is_verified_purchase',
                'is_helpful_count',
                'is_not_helpful_count',
                'status',
                'admin_notes',
                'reviewed_at'
            ]);
        });
    }
};
