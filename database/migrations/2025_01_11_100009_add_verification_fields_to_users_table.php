<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'identity_verified')) {
                $table->boolean('identity_verified')->default(false)->after('status');
            }
            if (!Schema::hasColumn('users', 'identity_type')) {
                $table->enum('identity_type', ['cccd', 'passport', 'cmnd'])->nullable()->after('identity_verified');
            }
            if (!Schema::hasColumn('users', 'identity_number')) {
                $table->string('identity_number')->nullable()->after('identity_type');
            }
            if (!Schema::hasColumn('users', 'identity_image_url')) {
                $table->string('identity_image_url')->nullable()->after('identity_number');
            }
            if (!Schema::hasColumn('users', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('identity_image_url');
            }
            if (!Schema::hasColumn('users', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null')->after('verified_at');
            }
            
            $table->index('identity_verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'verified_by')) {
                $table->dropForeign(['verified_by']);
                $table->dropColumn('verified_by');
            }
            if (Schema::hasColumn('users', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
            if (Schema::hasColumn('users', 'identity_image_url')) {
                $table->dropColumn('identity_image_url');
            }
            if (Schema::hasColumn('users', 'identity_number')) {
                $table->dropColumn('identity_number');
            }
            if (Schema::hasColumn('users', 'identity_type')) {
                $table->dropColumn('identity_type');
            }
            if (Schema::hasColumn('users', 'identity_verified')) {
                $table->dropIndex(['identity_verified']);
                $table->dropColumn('identity_verified');
            }
        });
    }
};

