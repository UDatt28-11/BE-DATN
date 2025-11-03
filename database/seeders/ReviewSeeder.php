<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\BookingDetail;
use App\Models\User;
use App\Models\Property;
use App\Models\Room;
use Carbon\Carbon;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $ratings = [5, 5, 4, 4, 3, 5, 5, 4, 5, 4];
        $titles = [
            'Tuyệt vời!',
            'Rất tốt',
            'Tốt lắm',
            'Bình thường',
            'Có thể cải thiện',
            'Xuất sắc',
            'Quán tuyệt vời',
            'Rất hài lòng',
            'Tất cả đều tuyệt vời',
            'Phòng sạch sẽ'
        ];
        $comments = [
            'Phòng rất sạch sẽ, nhân viên thân thiện, tất cả đều tuyệt vời!',
            'Quán tốt lắm, sẽ quay lại',
            'Phòng đẹp nhưng hơi ồn ào vào buổi tối',
            'Bình thường, không có gì nổi bật',
            'Phòng cũ, cần tu sửa',
            'Giường thoải mái, phòng tắm sạch sẽ',
            'Tuyệt vời! Sẽ giới thiệu cho bạn bè',
            'Nhân viên rất chu đáo, phục vụ tốt',
            'Giá hợp lý, chất lượng tốt',
            'Vị trí đẹp, view tuyệt vời'
        ];

        // Get sample data
        $bookingDetails = BookingDetail::take(10)->get();
        $users = User::take(10)->get();
        $properties = Property::take(2)->get();
        $rooms = Room::take(5)->get();

        // Create reviews
        $count = 0;
        foreach ($bookingDetails as $index => $bookingDetail) {
            if ($users->count() > $index && $properties->count() > 0 && $rooms->count() > 0) {
                Review::create([
                    'booking_details_id' => $bookingDetail->id,
                    'user_id' => $users[$index]->id,
                    'property_id' => $properties[$index % $properties->count()]->id,
                    'room_id' => $rooms[$index % $rooms->count()]->id,
                    'rating' => $ratings[$index],
                    'title' => $titles[$index],
                    'comment' => $comments[$index],
                    'photos' => null,
                    'is_verified_purchase' => true,
                    'status' => 'approved',
                    'reviewed_at' => Carbon::now()->subDays(rand(1, 30)),
                ]);
                $count++;
            }
        }

        echo "✅ Review seeder completed - Created {$count} reviews\n";
    }
}
