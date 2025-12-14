<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Services\BaseQueryService;
use Illuminate\Database\Eloquent\Builder;

class QueryService extends BaseQueryService
{
    public function index(array $q): array
    {
        $query = Property::query()
            ->with(['owner:id,full_name', 'verifier:id,full_name', 'images']);

        // Filter by owner_id
        if (!empty($q['owner_id'])) {
            $query->where('owner_id', $q['owner_id']);
        }

        // Apply common filters (status, verification_status, search)
        $this->applyCommonFilters($query, $q, [
            'search_fields' => ['name', 'address'],
        ]);

        // Sort by latest
        $query->latest();

        // Paginate and format response
        $paginator = $this->paginateQuery($query, $q);
        return $this->formatPaginatedResponse($paginator);
    }
}

