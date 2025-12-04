<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QueryService
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? self::DEFAULT_PER_PAGE);
        
        $query = Promotion::query()
            ->with([
                'property:id,name',
                'rooms:id,name',
                'roomTypes:id,name'
            ]);

        // Filter by property_id
        if (!empty($q['property_id'])) {
            $query->where('property_id', $q['property_id']);
        }

        // Filter by is_active
        if (isset($q['is_active'])) {
            $query->where('is_active', filter_var($q['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        // Search by code or description
        if (!empty($q['search'])) {
            $search = '%' . trim($q['search']) . '%';
            $query->where(function (Builder $b) use ($search) {
                $b->where('code', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $q['sort_by'] ?? 'created_at';
        $sortOrder = $q['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform data to include relationships conditionally
        $items = $paginator->getCollection()->map(function ($promotion) {
            $data = $promotion->toArray();
            if ($promotion->applicable_to !== 'all') {
                $data['rooms'] = $promotion->relationLoaded('rooms') ? $promotion->rooms->map(fn($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ]) : [];
                $data['room_types'] = $promotion->relationLoaded('roomTypes') ? $promotion->roomTypes->map(fn($roomType) => [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                ]) : [];
            } else {
                $data['rooms'] = [];
                $data['room_types'] = [];
            }
            return $data;
        })->all();

        return [
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
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

