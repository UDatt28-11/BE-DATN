<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            // Thêm các trường mới cho workflow
            $table->integer('actual_quantity')->nullable()->after('quantity');
            $table->decimal('actual_price', 10, 2)->nullable()->after('price_at_booking');
            $table->timestamp('started_at')->nullable()->after('notes');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null')->after('completed_at');
            
            // Sửa quantity và price_at_booking thành nullable (không cần khi tạo request)
            $table->integer('quantity')->nullable()->change();
            $table->decimal('price_at_booking', 10, 2)->nullable()->change();
        });
        
        // Sửa enum status để thêm 'in_use' và 'completed'
        // Laravel không hỗ trợ sửa enum trực tiếp, cần drop và tạo lại
        // Kiểm tra xem cột status có tồn tại không
        if (Schema::hasColumn('booking_services', 'status')) {
            DB::statement("ALTER TABLE booking_services MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'in_use', 'completed') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn(['actual_quantity', 'actual_price', 'started_at', 'completed_at', 'staff_id']);
            
            // Khôi phục quantity và price_at_booking thành required
            $table->integer('quantity')->nullable(false)->change();
            $table->decimal('price_at_booking', 10, 2)->nullable(false)->change();
        });
        
        // Khôi phục enum status
        DB::statement("ALTER TABLE booking_services MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    }
};
