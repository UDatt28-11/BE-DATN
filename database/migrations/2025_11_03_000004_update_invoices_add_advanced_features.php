<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount')->comment('Tổng tiền giảm giá');
            $table->decimal('refund_amount', 12, 2)->default(0)->after('discount_amount')->comment('Số tiền hoàn lại');
            $table->foreignId('refund_policy_id')->nullable()->after('refund_amount')->constrained('refund_policies')->onDelete('set null');
            $table->timestamp('refund_date')->nullable()->after('refund_policy_id')->comment('Ngày hoàn tiền');
            $table->enum('calculation_method', ['automatic', 'manual'])->default('automatic')->after('refund_date')->comment('Cách tính hóa đơn');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'refund_amount', 'refund_policy_id', 'refund_date', 'calculation_method']);
        });
    }
};
