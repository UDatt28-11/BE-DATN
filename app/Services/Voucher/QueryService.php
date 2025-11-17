<?php

namespace App\Services\Voucher;

use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueryService
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? self::DEFAULT_PER_PAGE);
        
        $query = Voucher::query()
            ->with('property:id,name');

        // Filter by property_id
        if (!empty($q['property_id'])) {
            $query->where('property_id', $q['property_id']);
        }

        // Filter by is_active
        if (isset($q['is_active'])) {
            $query->where('is_active', filter_var($q['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by discount_type
        if (!empty($q['discount_type'])) {
            $query->where('discount_type', $q['discount_type']);
        }

        // Search by code or name
        if (!empty($q['search'])) {
            $query->where('code', 'like', '%' . trim($q['search']) . '%');
        }

        // Sort by latest
        $query->latest();

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

