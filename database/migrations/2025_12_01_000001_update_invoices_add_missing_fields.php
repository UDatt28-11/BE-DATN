<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Thêm các field còn thiếu vào bảng invoices để phù hợp với frontend
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Thêm property_id nếu chưa có
            if (!Schema::hasColumn('invoices', 'property_id')) {
                $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->onDelete('cascade');
            }

            // Thêm invoice_number nếu chưa có
            if (!Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number')->unique()->nullable()->after('property_id');
            }

            // Thêm customer info nếu chưa có (có thể lấy từ booking_order nhưng lưu lại để dễ truy vấn)
            if (!Schema::hasColumn('invoices', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('booking_order_id');
            }
            if (!Schema::hasColumn('invoices', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('invoices', 'customer_phone')) {
                $table->string('customer_phone', 20)->nullable()->after('customer_email');
            }
            if (!Schema::hasColumn('invoices', 'customer_address')) {
                $table->text('customer_address')->nullable()->after('customer_phone');
            }

            // Thêm các field tính toán
            if (!Schema::hasColumn('invoices', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('customer_address');
            }
            if (!Schema::hasColumn('invoices', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(10)->after('subtotal')->comment('Thuế suất %');
            }
            if (!Schema::hasColumn('invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'balance')) {
                $table->decimal('balance', 12, 2)->default(0)->after('paid_amount')->comment('Số tiền còn lại');
            }

            // Thêm payment_status và invoice_status
            if (!Schema::hasColumn('invoices', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'partially_paid', 'paid', 'overdue', 'cancelled'])
                    ->default('pending')
                    ->after('balance');
            }
            if (!Schema::hasColumn('invoices', 'invoice_status')) {
                $table->enum('invoice_status', ['draft', 'sent', 'viewed', 'paid', 'cancelled'])
                    ->default('draft')
                    ->after('payment_status');
            }

            // Cập nhật status enum nếu cần (giữ lại để tương thích)
            // status sẽ được map với invoice_status hoặc payment_status

            // Thêm payment info
            if (!Schema::hasColumn('invoices', 'payment_method')) {
                $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card', 'e_wallet', 'other'])
                    ->nullable()
                    ->after('invoice_status');
            }
            if (!Schema::hasColumn('invoices', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('invoices', 'payment_notes')) {
                $table->text('payment_notes')->nullable()->after('payment_date');
            }

            // Thêm notes và terms
            if (!Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable()->after('payment_notes');
            }
            if (!Schema::hasColumn('invoices', 'terms_conditions')) {
                $table->text('terms_conditions')->nullable()->after('notes');
            }

            // Đảm bảo booking_order_id có thể nullable (cho hóa đơn không từ booking)
            if (Schema::hasColumn('invoices', 'booking_order_id')) {
                // Chỉ thay đổi nếu cần
                // $table->foreignId('booking_order_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $columns = [
                'property_id',
                'invoice_number',
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_address',
                'subtotal',
                'tax_rate',
                'tax_amount',
                'paid_amount',
                'balance',
                'payment_status',
                'invoice_status',
                'payment_method',
                'payment_date',
                'payment_notes',
                'notes',
                'terms_conditions',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};







