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
        Schema::table('messages', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['sender_id']);
            
            // Make sender_id nullable (for AI messages)
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            
            // Re-add foreign key constraint with nullable
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['sender_id']);
            
            // Make sender_id not nullable again
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            
            // Re-add foreign key constraint
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
