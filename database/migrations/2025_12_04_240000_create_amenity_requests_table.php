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
        Schema::create('amenity_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_details_id')->constrained('booking_details')->onDelete('cascade');
            $table->foreignId('amenity_id')->constrained('amenities')->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price_at_request', 15, 2)->nullable(); // Giá tại thời điểm yêu cầu (nếu tiện ích có phí)
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('notes')->nullable(); // Ghi chú từ khách
            $table->text('admin_notes')->nullable(); // Ghi chú từ admin
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete(); // Admin/Staff xử lý
            $table->timestamps();

            // Index để query nhanh hơn
            $table->index(['status', 'created_at']);
            $table->index('booking_details_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_requests');
    }
};


