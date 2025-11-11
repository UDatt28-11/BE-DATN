<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('read_at');
            }
            $table->index('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'is_hidden')) {
                $table->dropIndex(['is_hidden']);
                $table->dropColumn('is_hidden');
            }
        });
    }
};

