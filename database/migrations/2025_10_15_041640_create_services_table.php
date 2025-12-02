<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('unit', 50)->default('per_night'); // Changed from enum to string to match frontend
            $table->timestamps();
            $table->softDeletes(); // Added for SoftDeletes trait
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
