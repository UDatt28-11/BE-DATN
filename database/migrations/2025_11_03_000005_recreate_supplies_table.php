<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();
        
        // Drop old supplies table if exists
        Schema::dropIfExists('supplies');
        
        // Create new supplies table matching the Supply model
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('unit');
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('min_stock_level')->default(0);
            $table->unsignedInteger('max_stock_level')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->string('supplier')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->timestamps();
        });
        
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
