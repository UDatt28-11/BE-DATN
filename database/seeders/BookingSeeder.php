<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BookingOrder;
use App\Models\BookingDetail;
use App\Models\User;
use App\Models\Room;
use Carbon\Carbon;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $guest = User::where('role', 'user')->first();
        $staff = User::where('role', 'staff')->first();
        $room = Room::first();

        if (!$guest || !$room) {
            $this->command->warn('⚠️  Guest or Room not found. Skipping booking creation.');
            return;
        }

        // Xóa các booking cũ nếu có (xóa theo thứ tự để tránh foreign key constraint)
        BookingDetail::query()->delete();
        BookingOrder::query()->delete();
        
        // Tạo booking orders với các trạng thái khác nhau để test
        $statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'completed'];
        
        foreach ($statuses as $index => $status) {
            $checkInDate = Carbon::now()->addDays($index);
            $checkOutDate = Carbon::now()->addDays($index + 2);
            
            $booking = BookingOrder::create([
                'guest_id' => $guest->id,
                'staff_id' => $staff?->id,
                'order_code' => 'BK' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'total_amount' => $room->price_per_night * 2,
                'status' => $status,
                'customer_name' => $guest->full_name,
                'customer_phone' => $guest->phone_number,
                'customer_email' => $guest->email,
                'payment_method' => 'cash',
                'notes' => "Booking test với status: {$status}",
            ]);

            BookingDetail::create([
                'booking_order_id' => $booking->id,
                'room_id' => $room->id,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'num_adults' => 2,
                'num_children' => 0,
                'sub_total' => $room->price_per_night * 2,
                'status' => 'active', // BookingDetail status: active, cancelled, checked_in, checked_out
            ]);
        }

        $this->command->info('✅ Created 5 booking orders with different statuses for testing');
    }
}

