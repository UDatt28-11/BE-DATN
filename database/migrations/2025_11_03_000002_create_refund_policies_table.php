<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên chính sách (VD: Hoàn 100%, Hoàn 50%)');
            $table->decimal('refund_percent', 5, 2)->comment('Phần trăm hoàn tiền');
            $table->integer('days_before_checkin')->comment('Hoàn nếu hủy trước N ngày');
            $table->decimal('penalty_percent', 5, 2)->default(0)->comment('Phí phạt %');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_policies');
    }
};
