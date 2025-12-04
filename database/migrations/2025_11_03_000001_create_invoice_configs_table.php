<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('calculation_method', ['automatic', 'manual'])->default('automatic');
            $table->boolean('auto_calculate')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Tỷ lệ thuế %');
            $table->decimal('service_charge_rate', 5, 2)->default(0)->comment('Phí dịch vụ %');
            $table->decimal('late_fee_percent', 5, 2)->nullable()->comment('Phí trễ hạn %');
            $table->decimal('late_fee_per_day', 10, 2)->nullable()->comment('Phí trễ hạn/ngày');
            $table->json('settings')->nullable()->comment('Cấu hình khác');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_configs');
    }
};
