<?php

namespace App\Services\Room;

use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QueryService
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? self::DEFAULT_PER_PAGE);
        
        $query = Room::query()
            ->with(['property:id,name', 'roomType:id,name', 'amenities:id,name', 'images', 'verifier:id,full_name']);

        // Filter by property_id
        if (!empty($q['property_id'])) {
            $query->where('property_id', $q['property_id']);
        }

        // Filter by room_type_id
        if (!empty($q['room_type_id'])) {
            $query->where('room_type_id', $q['room_type_id']);
        }

        // Filter by status
        if (!empty($q['status'])) {
            $query->where('status', $q['status']);
        }

        // Filter by verification_status
        if (!empty($q['verification_status'])) {
            $query->where('verification_status', $q['verification_status']);
        }

        // Search by name or property address
        if (!empty($q['search'])) {
            $search = '%' . trim($q['search']) . '%';
            $query->where(function (Builder $b) use ($search) {
                $b->where('name', 'like', $search)
                  ->orWhereHas('property', function (Builder $p) use ($search) {
                      $p->where('address', 'like', $search);
                  });
            });
        }

        // Sorting
        $sortBy = $q['sort_by'] ?? 'created_at';
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

