<?php

namespace App\Services\BookingOrder;

/*
 FILE GUARD – ADMIN BOOKING MODULE ONLY
 - KHÔNG chạm schema/migrations; dùng đúng tên cột từ migrations.
 - Guests FK: 'booking_details_id'.
 - Chỉ index/show/updateStatus; bắt buộc middleware/policy admin.
*/

use App\Http\Resources\Admin\BookingOrderResource;
use App\Models\BookingOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class QueryService
{
    public function index(array $q): array
    {
        // Chuẩn hóa dữ liệu đầu vào
        if (isset($q['status']) && is_string($q['status'])) {
            $q['status'] = array_values(array_filter(explode(',', $q['status'])));
        }

        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? 20);
        $include = array_filter(explode(',', (string)($q['include'] ?? '')));

        $query = BookingOrder::query()
            ->with(['guest'])
            ->withCount('details')
            ->select([
                'booking_orders.id',
                'booking_orders.guest_id',
                'booking_orders.order_code',
                'booking_orders.total_amount',
                'booking_orders.status',
                'booking_orders.created_at',
            ]);

        // Tìm kiếm theo từ khóa
        if (!empty($q['keyword'])) {
            $keyword = '%' . trim($q['keyword']) . '%';
            $query->where(function (Builder $b) use ($keyword) {
                $b->where('booking_orders.order_code', 'like', $keyword)
                  ->orWhereHas('guest', function (Builder $g) use ($keyword) {
                      $g->where('full_name', 'like', $keyword)
                        ->orWhere('phone_number', 'like', $keyword);
                  });
            });
        }

        // Lọc theo trạng thái
        if (!empty($q['status']) && is_array($q['status'])) {
            $query->whereIn('booking_orders.status', $q['status']);
        }

        // Join để lấy ngày checkin/checkout min/max
        $query->leftJoin('booking_details as bd_min', function ($join) {
            $join->on('bd_min.booking_order_id', '=', 'booking_orders.id');
        });
        $query->addSelect([
            DB::raw('MIN(bd_min.check_in_date) as details_min_check_in_date'),
            DB::raw('MAX(bd_min.check_out_date) as details_max_check_out_date'),
        ]);
        $query->groupBy(
            'booking_orders.id',
            'booking_orders.guest_id',
            'booking_orders.order_code',
            'booking_orders.total_amount',
            'booking_orders.status',
            'booking_orders.created_at'
        );

        // Lọc theo ngày và tên phòng
        $dateField = $q['date_field'] ?? null;
        $dateFrom = $q['date_from'] ?? null;
        $dateTo = $q['date_to'] ?? null;
        $roomName = $q['room_name'] ?? null;

        if ($dateField || $roomName) {
            $query->whereHas('details', function (Builder $d) use ($dateField, $dateFrom, $dateTo, $roomName) {
                if ($dateField && ($dateFrom || $dateTo)) {
                    if ($dateFrom) {
                        $d->where($dateField, '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $d->where($dateField, '<=', $dateTo);
                    }
                }
                if ($roomName) {
                    $d->whereHas('room', function (Builder $r) use ($roomName) {
                        $r->where('name', 'like', '%' . $roomName . '%');
                    });
                }
            });
        }

        // Lọc theo tổng tiền
        if (!empty($q['min_total'])) {
            $query->where('booking_orders.total_amount', '>=', $q['min_total']);
        }
        if (!empty($q['max_total'])) {
            $query->where('booking_orders.total_amount', '<=', $q['max_total']);
        }

        // Bao gồm các quan hệ
        $relations = [];
        if (in_array('details', $include, true)) {
            $relations[] = 'details';
            if (in_array('details.room', $include, true)) {
                $relations[] = 'details.room';
            }
            if (in_array('details.guests', $include, true)) {
                $relations[] = 'details.guests';
            }
        }
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Sắp xếp
        $sort = $q['sort'] ?? 'created_at';
        $direction = str_starts_with((string)$sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim((string)$sort, '-');
        $map = [
            'created_at'   => 'booking_orders.created_at',
            'checkin_date' => 'details_min_check_in_date',
            'total_amount' => 'booking_orders.total_amount',
        ];
        $orderBy = $map[$sortField] ?? 'booking_orders.created_at';
        $query->orderBy($orderBy, $direction);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Chuyển dữ liệu thành mảng để tránh lỗi JSON trống
        $items = collect($paginator->items())
            ->map(fn ($o) => (new BookingOrderResource($o))->toArray(request()))
            ->values()
            ->all();

        return [
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'page'      => $paginator->currentPage(),
                    'per_page'  => $paginator->perPage(),
                    'total'     => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }
}
