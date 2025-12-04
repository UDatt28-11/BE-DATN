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
        $rooms = Room::where('status', 'available')->get();

        if (!$guest || $rooms->isEmpty()) {
            $this->command->warn('⚠️  Guest or Rooms not found. Skipping booking creation.');
            return;
        }

        // Xóa các booking cũ nếu có (xóa theo thứ tự để tránh foreign key constraint)
        BookingDetail::query()->delete();
        BookingOrder::query()->delete();
        
        // Tạo booking orders với các trạng thái khác nhau để test
        $statuses = [
            ['status' => 'pending', 'days_from_now' => 5],
            ['status' => 'confirmed', 'days_from_now' => 3],
            ['status' => 'checked_in', 'days_from_now' => -1],
            ['status' => 'checked_out', 'days_from_now' => -3],
            ['status' => 'completed', 'days_from_now' => -7],
        ];
        
        foreach ($statuses as $index => $statusData) {
            $room = $rooms->random();
            $checkInDate = Carbon::now()->addDays($statusData['days_from_now']);
            $checkOutDate = $checkInDate->copy()->addDays(2);
            $nights = $checkOutDate->diffInDays($checkInDate);
            if ($nights <= 0) $nights = 1;
            
            $totalAmount = $room->price_per_night * $nights;
            
            $booking = BookingOrder::create([
                'guest_id' => $guest->id,
                'staff_id' => $staff?->id,
                'order_code' => 'BK' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'total_amount' => $totalAmount,
                'status' => $statusData['status'],
                'customer_name' => $guest->full_name,
                'customer_phone' => $guest->phone_number,
                'customer_email' => $guest->email,
                'payment_method' => ['cash', 'bank_transfer', 'credit_card'][rand(0, 2)],
                'notes' => "Đặt phòng {$room->name} - {$nights} đêm",
            ]);

            BookingDetail::create([
                'booking_order_id' => $booking->id,
                'room_id' => $room->id,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'num_adults' => rand(1, $room->max_adults),
                'num_children' => rand(0, min(2, $room->max_children)),
                'sub_total' => $totalAmount,
                'status' => $statusData['status'] === 'checked_in' ? 'checked_in' : 
                          ($statusData['status'] === 'checked_out' || $statusData['status'] === 'completed' ? 'checked_out' : 'active'),
            ]);
        }

        $this->command->info('✅ Created 5 booking orders with different statuses for testing');
    }
}

