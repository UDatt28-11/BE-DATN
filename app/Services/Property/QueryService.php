<?php

namespace App\Services\Property;

use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QueryService
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? self::DEFAULT_PER_PAGE);
        
        $query = Property::query()
            ->with(['owner:id,full_name', 'verifier:id,full_name', 'images']);

        // Filter by owner_id
        if (!empty($q['owner_id'])) {
            $query->where('owner_id', $q['owner_id']);
        }

        // Filter by status
        if (!empty($q['status'])) {
            $query->where('status', $q['status']);
        }

        // Filter by verification_status
        if (!empty($q['verification_status'])) {
            $query->where('verification_status', $q['verification_status']);
        }

        // Search by name or address
        if (!empty($q['search'])) {
            $search = '%' . trim($q['search']) . '%';
            $query->where(function (Builder $b) use ($search) {
                $b->where('name', 'like', $search)
                  ->orWhere('address', 'like', $search);
            });
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

