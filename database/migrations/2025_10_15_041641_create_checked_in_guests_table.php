<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checked_in_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_details_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->enum('identity_type', ['cccd', 'passport'])->nullable();
            $table->string('identity_number')->nullable();
            $table->string('identity_image_url')->nullable();
            $table->timestamp('check_in_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checked_in_guests');
    }
};
