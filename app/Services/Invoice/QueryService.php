<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QueryService
{
    public function index(array $q): array
    {
        $page = (int) ($q['page'] ?? 1);
        $perPage = (int) ($q['per_page'] ?? 15);
        
        $query = Invoice::query()
            ->with(['bookingOrder', 'invoiceItems']);

        // Filter by status
        if (!empty($q['status'])) {
            $query->where('status', $q['status']);
        }

        // Filter by payment_status
        if (!empty($q['payment_status'])) {
            if ($q['payment_status'] === 'paid') {
                $query->paid();
            } elseif ($q['payment_status'] === 'unpaid') {
                $query->unpaid();
            } elseif ($q['payment_status'] === 'overdue') {
                $query->overdue();
            }
        }

        // Search by invoice_number
        if (!empty($q['search'])) {
            $query->where('invoice_number', 'like', '%' . $q['search'] . '%');
        }

        // Filter by date range (created_at)
        if (!empty($q['date_from'])) {
            $query->whereDate('created_at', '>=', $q['date_from']);
        }
        if (!empty($q['date_to'])) {
            $query->whereDate('created_at', '<=', $q['date_to']);
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

