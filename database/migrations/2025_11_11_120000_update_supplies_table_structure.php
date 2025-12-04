<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            // Thêm các cột mới theo yêu cầu của SupplyController
            if (!Schema::hasColumn('supplies', 'description')) {
                $table->string('description', 1000)->nullable()->after('name');
            }
            if (!Schema::hasColumn('supplies', 'category')) {
                $table->string('category', 100)->after('description');
            }
            if (!Schema::hasColumn('supplies', 'unit')) {
                $table->string('unit', 50)->after('category');
            }
            if (!Schema::hasColumn('supplies', 'current_stock')) {
                $table->unsignedInteger('current_stock')->default(0)->after('unit');
            }
            if (!Schema::hasColumn('supplies', 'min_stock_level')) {
                $table->unsignedInteger('min_stock_level')->default(0)->after('current_stock');
            }
            if (!Schema::hasColumn('supplies', 'max_stock_level')) {
                $table->unsignedInteger('max_stock_level')->nullable()->after('min_stock_level');
            }
            if (!Schema::hasColumn('supplies', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->default(0)->after('max_stock_level');
            }
            if (!Schema::hasColumn('supplies', 'supplier')) {
                $table->string('supplier')->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('supplies', 'supplier_contact')) {
                $table->string('supplier_contact')->nullable()->after('supplier');
            }
            if (!Schema::hasColumn('supplies', 'status')) {
                $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active')->after('supplier_contact');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            if (Schema::hasColumn('supplies', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('supplies', 'supplier_contact')) {
                $table->dropColumn('supplier_contact');
            }
            if (Schema::hasColumn('supplies', 'supplier')) {
                $table->dropColumn('supplier');
            }
            if (Schema::hasColumn('supplies', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
            if (Schema::hasColumn('supplies', 'max_stock_level')) {
                $table->dropColumn('max_stock_level');
            }
            if (Schema::hasColumn('supplies', 'min_stock_level')) {
                $table->dropColumn('min_stock_level');
            }
            if (Schema::hasColumn('supplies', 'current_stock')) {
                $table->dropColumn('current_stock');
            }
            if (Schema::hasColumn('supplies', 'unit')) {
                $table->dropColumn('unit');
            }
            if (Schema::hasColumn('supplies', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('supplies', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};


