<?php

namespace Database\Seeders;

use App\Models\BookingOrder;
use App\Models\BookingDetail;
use App\Models\CheckedInGuest;
use App\Models\User;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class BookingOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', 'user')->take(5)->get();
        $rooms = Room::all();

        if ($users->isEmpty() || $rooms->isEmpty()) {
            $this->command->warn('Không có đủ dữ liệu users hoặc rooms để tạo booking orders');
            return;
        }

        // Tạo 15 booking orders với các trạng thái khác nhau
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $paymentMethods = ['cash', 'credit_card', 'bank_transfer', 'e_wallet'];

        for ($i = 1; $i <= 15; $i++) {
            $user = $users->random();
            $status = $statuses[array_rand($statuses)];
            
            // Tạo ngày checkin và checkout
            $daysAgo = rand(1, 30);
            $checkInDate = Carbon::now()->subDays($daysAgo);
            $checkOutDate = (clone $checkInDate)->addDays(rand(1, 5));
            
            // Tính tổng tiền dựa trên số phòng và số ngày
            $numRooms = rand(1, 3);
            $totalAmount = 0;
            
            $bookingOrder = BookingOrder::create([
                'guest_id' => $user->id,
                'order_code' => 'BK' . now()->format('Ymd') . str_pad($i, 6, '0', STR_PAD_LEFT),
                'customer_name' => $user->full_name ?? 'Khách hàng ' . $i,
                'customer_phone' => $user->phone_number ?? '0' . rand(100000000, 999999999),
                'customer_email' => $user->email,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'notes' => $i % 3 == 0 ? 'Yêu cầu phòng view đẹp, tầng cao' : ($i % 2 == 0 ? 'Check-in sớm nếu có thể' : null),
                'total_amount' => 0, // Sẽ cập nhật sau
                'status' => $status,
                'created_at' => Carbon::now()->subDays($daysAgo + 1),
                'updated_at' => Carbon::now()->subDays($daysAgo + 1),
            ]);

            // Tạo booking details (phòng đã đặt)
            $selectedRooms = $rooms->random($numRooms);
            
            foreach ($selectedRooms as $room) {
                $numAdults = rand(1, 3);
                $numChildren = rand(0, 2);
                $nights = $checkInDate->diffInDays($checkOutDate);
                $subTotal = rand(800000, 3000000); // Giá phòng random từ 800k-3tr/đêm
                $totalAmount += $subTotal;

                $bookingDetail = BookingDetail::create([
                    'booking_order_id' => $bookingOrder->id,
                    'room_id' => $room->id,
                    'check_in_date' => $checkInDate->format('Y-m-d'),
                    'check_out_date' => $checkOutDate->format('Y-m-d'),
                    'num_adults' => $numAdults,
                    'num_children' => $numChildren,
                    'sub_total' => $subTotal,
                    'status' => $status === 'completed' ? 'checked_out' : ($status === 'confirmed' ? 'active' : ($status === 'cancelled' ? 'cancelled' : 'active')),
                ]);

                // Thêm checked-in guests cho các booking đã completed hoặc confirmed
                if (in_array($status, ['completed', 'confirmed'])) {
                    // Thêm khách chính
                    CheckedInGuest::create([
                        'booking_details_id' => $bookingDetail->id,
                        'full_name' => $user->full_name ?? 'Khách hàng ' . $i,
                        'date_of_birth' => Carbon::now()->subYears(rand(20, 60))->format('Y-m-d'),
                        'identity_type' => rand(0, 1) ? 'cccd' : 'passport',
                        'identity_number' => rand(100000000, 999999999),
                        'identity_image_url' => null,
                        'check_in_time' => $checkInDate->copy()->setTime(14, 0, 0),
                    ]);

                    // Thêm người đi cùng nếu có nhiều người lớn
                    if ($numAdults > 1) {
                        for ($j = 1; $j < $numAdults; $j++) {
                            CheckedInGuest::create([
                                'booking_details_id' => $bookingDetail->id,
                                'full_name' => 'Khách đi cùng ' . $j,
                                'date_of_birth' => Carbon::now()->subYears(rand(20, 60))->format('Y-m-d'),
                                'identity_type' => rand(0, 1) ? 'cccd' : 'passport',
                                'identity_number' => rand(100000000, 999999999),
                                'identity_image_url' => null,
                                'check_in_time' => $checkInDate->copy()->setTime(14, 0, 0),
                            ]);
                        }
                    }
                }
            }

            // Cập nhật tổng tiền cho booking order
            $bookingOrder->update(['total_amount' => $totalAmount]);
        }

        $this->command->info('✅ Đã tạo 15 booking orders với booking details và checked-in guests');
    }
}
