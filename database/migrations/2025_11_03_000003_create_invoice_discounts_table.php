<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->comment('Loại giảm giá');
            $table->decimal('discount_value', 10, 2)->comment('Giá trị giảm (% hoặc tiền)');
            $table->decimal('discount_amount', 12, 2)->comment('Số tiền giảm thực tế');
            $table->string('reason')->nullable()->comment('Lý do giảm giá');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_discounts');
    }
};
