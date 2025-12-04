<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('name')->nullable()->after('code');
            $table->text('description')->nullable()->after('name');
            $table->decimal('min_order_amount', 15, 2)->default(0)->after('discount_value');
            $table->decimal('max_discount_amount', 15, 2)->nullable()->after('min_order_amount');
            $table->unsignedInteger('usage_limit')->nullable()->after('max_discount_amount'); // Null = unlimited
            $table->unsignedInteger('usage_count')->default(0)->after('usage_limit');
            $table->unsignedInteger('max_usage_per_user')->default(1)->after('usage_count');
            $table->boolean('is_public')->default(true)->after('is_active'); // Can be claimed by anyone
        });

        // Add applied_discount_amount to user_vouchers
        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->decimal('applied_discount_amount', 15, 2)->nullable()->after('used_at');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'description', 
                'min_order_amount',
                'max_discount_amount',
                'usage_limit',
                'usage_count',
                'max_usage_per_user',
                'is_public',
            ]);
        });

        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->dropColumn('applied_discount_amount');
        });
    }
};

