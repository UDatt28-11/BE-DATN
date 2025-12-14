<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseQueryService
{
    protected const DEFAULT_PER_PAGE = 15;

    /**
     * Apply common filters (status, search, dates, etc.)
     */
    protected function applyCommonFilters(Builder $query, array $filters, array $config = []): Builder
    {
        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by verification_status
        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        // Search
        if (!empty($filters['search']) && !empty($config['search_fields'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function (Builder $b) use ($search, $config) {
                foreach ($config['search_fields'] as $index => $field) {
                    if (str_contains($field, '.')) {
                        // Handle relationship fields
                        [$relation, $column] = explode('.', $field, 2);
                        if ($index === 0) {
                            $b->whereHas($relation, function (Builder $q) use ($column, $search) {
                                $q->where($column, 'like', $search);
                            });
                        } else {
                            $b->orWhereHas($relation, function (Builder $q) use ($column, $search) {
                                $q->where($column, 'like', $search);
                            });
                        }
                    } else {
                        if ($index === 0) {
                            $b->where($field, 'like', $search);
                        } else {
                            $b->orWhere($field, 'like', $search);
                        }
                    }
                }
            });
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Apply sorting
     */
    protected function applySorting(Builder $query, array $filters, string $defaultSort = 'created_at', string $defaultOrder = 'desc'): Builder
    {
        $sortBy = $filters['sort_by'] ?? $defaultSort;
        $sortOrder = $filters['sort_order'] ?? $defaultOrder;

        // Validate sort order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : $defaultOrder;

        $query->orderBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Paginate query
     */
    protected function paginateQuery(Builder $query, array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? static::DEFAULT_PER_PAGE);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Format paginated response
     */
    protected function formatPaginatedResponse(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }
}

