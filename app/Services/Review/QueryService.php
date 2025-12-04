<?php

namespace App\Services\Review;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QueryService
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? self::DEFAULT_PER_PAGE);
        
        $query = Review::query()
            ->with([
                'user:id,full_name,email',
                'property:id,name',
                'room:id,name',
                'bookingDetail:id,booking_order_id,room_id'
            ]);

        // Filter by property_id
        if (!empty($q['property_id'])) {
            $query->where('property_id', $q['property_id']);
        }

        // Filter by room_id
        if (!empty($q['room_id'])) {
            $query->where('room_id', $q['room_id']);
        }

        // Filter by status
        if (!empty($q['status'])) {
            $query->where('status', $q['status']);
        }

        // Filter by rating
        if (!empty($q['rating'])) {
            $query->where('rating', $q['rating']);
        }

        // Filter verified purchases only
        if (isset($q['verified_only']) && filter_var($q['verified_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_verified_purchase', true);
        }

        // Filter by date range
        if (!empty($q['date_from']) && !empty($q['date_to'])) {
            $query->whereBetween('reviewed_at', [
                $q['date_from'],
                $q['date_to']
            ]);
        } elseif (!empty($q['date_from'])) {
            $query->where('reviewed_at', '>=', $q['date_from']);
        } elseif (!empty($q['date_to'])) {
            $query->where('reviewed_at', '<=', $q['date_to']);
        }

        // Search in comment and title
        if (!empty($q['search'])) {
            $search = '%' . trim($q['search']) . '%';
            $query->where(function (Builder $b) use ($search) {
                $b->where('title', 'like', $search)
                  ->orWhere('comment', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $q['sort_by'] ?? 'reviewed_at';
        $sortOrder = $q['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
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

