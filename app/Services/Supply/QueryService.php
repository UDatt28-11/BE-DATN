<?php

namespace App\Services\Supply;

use App\Models\Supply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QueryService
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? self::DEFAULT_PER_PAGE);
        
        $query = Supply::query()
            ->with('room:id,name');

        // Filter by room_id
        if (!empty($q['room_id'])) {
            $query->where('room_id', $q['room_id']);
        }

        // Filter by category
        if (!empty($q['category'])) {
            $query->where('category', $q['category']);
        }

        // Filter by status
        if (!empty($q['status'])) {
            $query->where('status', $q['status']);
        }

        // Filter by stock_status
        if (!empty($q['stock_status'])) {
            switch ($q['stock_status']) {
                case 'low_stock':
                    $query->lowStock();
                    break;
                case 'out_of_stock':
                    $query->outOfStock();
                    break;
                case 'in_stock':
                    $query->whereColumn('current_stock', '>', 'min_stock_level')
                        ->where('current_stock', '>', 0);
                    break;
            }
        }

        // Search by name
        if (!empty($q['search'])) {
            $query->where('name', 'like', '%' . trim($q['search']) . '%');
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

