<?php

namespace App\Services\BookingOrder;


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
                'booking_orders.customer_name',
                'booking_orders.customer_phone',
                'booking_orders.customer_email',
                'booking_orders.payment_method',
                'booking_orders.notes',
                'booking_orders.total_amount',
                'booking_orders.status',
                'booking_orders.created_at',
            ]);

        // Tìm kiếm theo từ khóa (keyword hoặc search)
        $searchTerm = $q['keyword'] ?? $q['search'] ?? null;
        if (!empty($searchTerm)) {
            $keyword = '%' . trim($searchTerm) . '%';
            $query->where(function (Builder $b) use ($keyword) {
                $b->where('booking_orders.order_code', 'like', $keyword)
                  ->orWhere('booking_orders.customer_name', 'like', $keyword)
                  ->orWhere('booking_orders.customer_email', 'like', $keyword)
                  ->orWhereHas('guest', function (Builder $g) use ($keyword) {
                      $g->where('full_name', 'like', $keyword)
                        ->orWhere('phone_number', 'like', $keyword)
                        ->orWhere('email', 'like', $keyword);
                  });
            });
        }

        // Filter by order_code
        if (!empty($q['order_code'])) {
            $query->where('booking_orders.order_code', 'like', '%' . $q['order_code'] . '%');
        }

        // Filter by customer_name
        if (!empty($q['customer_name'])) {
            $query->where('booking_orders.customer_name', 'like', '%' . $q['customer_name'] . '%');
        }

        // Filter by customer_email
        if (!empty($q['customer_email'])) {
            $query->where('booking_orders.customer_email', $q['customer_email']);
        }

        // Filter by staff_id
        if (!empty($q['staff_id'])) {
            $query->where('booking_orders.staff_id', $q['staff_id']);
        }

        // Filter by property_id (via details.room.property_id)
        if (!empty($q['property_id'])) {
            $query->whereHas('details.room', function (Builder $r) use ($q) {
                $r->where('property_id', $q['property_id']);
            });
        }

        // Filter by date range (created_at)
        if (!empty($q['date_from'])) {
            $query->whereDate('booking_orders.created_at', '>=', $q['date_from']);
        }
        if (!empty($q['date_to'])) {
            $query->whereDate('booking_orders.created_at', '<=', $q['date_to']);
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
            'booking_orders.customer_name',
            'booking_orders.customer_phone',
            'booking_orders.customer_email',
            'booking_orders.payment_method',
            'booking_orders.notes',
            'booking_orders.total_amount',
            'booking_orders.status',
            'booking_orders.created_at'
        );

        // Filter by check-in date range
        if (!empty($q['check_in_from']) || !empty($q['check_in_to'])) {
            $query->whereHas('details', function (Builder $d) use ($q) {
                if (!empty($q['check_in_from'])) {
                    $d->whereDate('check_in_date', '>=', $q['check_in_from']);
                }
                if (!empty($q['check_in_to'])) {
                    $d->whereDate('check_in_date', '<=', $q['check_in_to']);
                }
            });
        }

        // Filter by check-out date range
        if (!empty($q['check_out_from']) || !empty($q['check_out_to'])) {
            $query->whereHas('details', function (Builder $d) use ($q) {
                if (!empty($q['check_out_from'])) {
                    $d->whereDate('check_out_date', '>=', $q['check_out_from']);
                }
                if (!empty($q['check_out_to'])) {
                    $d->whereDate('check_out_date', '<=', $q['check_out_to']);
                }
            });
        }

        // Lọc theo ngày và tên phòng (date_field, room_name - backward compatibility)
        $dateField = $q['date_field'] ?? null;
        $roomName = $q['room_name'] ?? null;

        if ($dateField || $roomName) {
            $query->whereHas('details', function (Builder $d) use ($dateField, $q, $roomName) {
                if ($dateField && (!empty($q['date_from']) || !empty($q['date_to']))) {
                    if (!empty($q['date_from'])) {
                        $d->where($dateField, '>=', $q['date_from']);
                    }
                    if (!empty($q['date_to'])) {
                        $d->where($dateField, '<=', $q['date_to']);
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
        $sortBy = $q['sort_by'] ?? $q['sort'] ?? 'created_at';
        $sortOrder = $q['sort_order'] ?? 'desc';
        
        // Nếu sort có dạng "-field" thì parse
        if (str_starts_with((string)$sortBy, '-')) {
            $sortOrder = 'desc';
            $sortBy = ltrim((string)$sortBy, '-');
        }
        
        $map = [
            'id' => 'booking_orders.id',
            'order_code' => 'booking_orders.order_code',
            'created_at' => 'booking_orders.created_at',
            'updated_at' => 'booking_orders.updated_at',
            'checkin_date' => 'details_min_check_in_date',
            'checkout_date' => 'details_max_check_out_date',
            'total_amount' => 'booking_orders.total_amount',
            'status' => 'booking_orders.status',
        ];
        $orderBy = $map[$sortBy] ?? 'booking_orders.created_at';
        $query->orderBy($orderBy, $sortOrder);

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
