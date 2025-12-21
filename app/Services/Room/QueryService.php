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
        
        // Load booking details nếu có check_in parameter
        $withRelations = ['property:id,name', 'roomType:id,name', 'roomType.images', 'amenities:id,name', 'verifier:id,full_name'];
        if (!empty($q['check_in']) || !empty($q['check_out'])) {
            // Load booking details với filter theo ngày
            $checkIn = $q['check_in'] ?? $q['check_out'] ?? null;
            $checkOut = $q['check_out'] ?? $q['check_in'] ?? null;
            
            $withRelations['bookingDetails'] = function($query) use ($checkIn, $checkOut) {
                $query->select('id', 'room_id', 'booking_order_id', 'check_in_date', 'check_out_date', 'status');
                
                // Filter booking details có overlap với ngày đã chọn
                if ($checkIn && $checkOut) {
                    $query->where(function($q) use ($checkIn, $checkOut) {
                        // Booking detail có overlap nếu: check_in_date <= checkOut AND check_out_date >= checkIn
                        $q->whereDate('check_in_date', '<=', $checkOut)
                          ->whereDate('check_out_date', '>=', $checkIn)
                          ->whereIn('status', ['confirmed', 'checked_in']); // Chỉ lấy booking đã confirm hoặc đang check-in
                    });
                } elseif ($checkIn) {
                    // Nếu chỉ có check_in, lấy booking có ngày đó nằm trong khoảng check_in_date và check_out_date
                    $query->whereDate('check_in_date', '<=', $checkIn)
                          ->whereDate('check_out_date', '>=', $checkIn)
                          ->whereIn('status', ['confirmed', 'checked_in']);
                }
            };
        }
        
        $query = Room::query()
            ->with($withRelations);

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

        // Serialize rooms to ensure relationships are properly formatted
        $roomsData = collect($paginator->items())->map(function($room) use ($q) {
            $roomArray = $room->toArray();
            
            // Ensure roomType is properly serialized with images
            if ($room->relationLoaded('roomType') && $room->roomType) {
                $roomTypeData = [
                    'id' => $room->roomType->id,
                    'name' => $room->roomType->name,
                ];
                
                // Load images from roomType
                if ($room->roomType->relationLoaded('images') && $room->roomType->images) {
                    $roomTypeData['images'] = $room->roomType->images->map(function($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => $image->image_url,
                            'is_primary' => (bool)($image->is_primary ?? false),
                        ];
                    })->values()->toArray();
                } else {
                    $roomTypeData['images'] = [];
                }
                
                $roomArray['roomType'] = $roomTypeData;
                // Map roomType images to room images for backward compatibility
                $roomArray['images'] = $roomTypeData['images'];
            } else {
                $roomArray['roomType'] = null;
                $roomArray['images'] = [];
            }
            
            // Ensure property is properly serialized
            if ($room->relationLoaded('property') && $room->property) {
                $roomArray['property'] = [
                    'id' => $room->property->id,
                    'name' => $room->property->name,
                ];
            } else {
                $roomArray['property'] = null;
            }
            
            // Thêm booking details nếu có
            if ($room->relationLoaded('bookingDetails') && $room->bookingDetails) {
                $roomArray['booking_details'] = $room->bookingDetails->map(function($detail) {
                    return [
                        'id' => $detail->id,
                        'booking_order_id' => $detail->booking_order_id,
                        'check_in_date' => $detail->check_in_date?->format('Y-m-d'),
                        'check_out_date' => $detail->check_out_date?->format('Y-m-d'),
                        'status' => $detail->status,
                    ];
                })->values()->toArray();
            } else {
                $roomArray['booking_details'] = [];
            }
            
            return $roomArray;
        })->toArray();

        return [
            'data' => $roomsData,
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

