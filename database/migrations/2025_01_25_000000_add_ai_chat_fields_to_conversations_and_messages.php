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
        // Thêm fields cho conversations table
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type')->default('user_to_user')->after('id'); // 'user_to_user' | 'user_to_ai'
            $table->string('session_id')->nullable()->unique()->after('type'); // Session ID cho guest users
            $table->json('context_data')->nullable()->after('session_id'); // Lưu context: booking_id, room_id, etc.
        });

        // Thêm fields cho messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_type')->default('user')->after('sender_id'); // 'user' | 'ai'
            $table->string('ai_provider')->nullable()->after('message_type'); // 'openai', 'claude', etc.
            $table->string('ai_model')->nullable()->after('ai_provider'); // 'gpt-4o-mini', 'claude-3-haiku', etc.
            $table->json('metadata')->nullable()->after('is_hidden'); // Lưu thêm metadata: tokens, cost, etc.
        });

        // Tạo index cho performance
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('type');
            $table->index('session_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index('message_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['message_type']);
            $table->dropColumn(['message_type', 'ai_provider', 'ai_model', 'metadata']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['session_id']);
            $table->dropColumn(['type', 'session_id', 'context_data']);
        });
    }
};

