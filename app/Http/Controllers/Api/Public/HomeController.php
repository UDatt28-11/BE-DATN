<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use App\Models\Room;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

/**
 * Public Homepage Controller
 * Không cần đăng nhập để truy cập
 */
class HomeController extends Controller
{
    /**
     * Get public statistics for homepage
     */
    public function statistics(): JsonResponse
    {
        try {
            // Tổng số properties
            try {
                $totalProperties = Property::where('verification_status', 'verified')->count();
            } catch (\Exception $e) {
                $totalProperties = Property::count();
            }
            
            // Tổng số users (không tính admin)
            $totalUsers = User::where('role', '!=', 'admin')->count();
            
            // Tổng số rooms
            try {
                $totalRooms = Room::where('verification_status', 'verified')
                    ->where('status', 'available')
                    ->count();
            } catch (\Exception $e) {
                $totalRooms = Room::where('status', 'available')->count();
            }
            
            // Tổng số reviews với rating 5 sao
            try {
                $totalFiveStarReviews = Review::where('rating', 5)
                    ->where('status', 'approved')
                    ->count();
            } catch (\Exception $e) {
                $totalFiveStarReviews = Review::where('rating', 5)->count();
            }
            
            // Đếm số lượng room types
            try {
                $totalRoomTypes = RoomType::where('status', 'active')->count();
            } catch (\Exception $e) {
                $totalRoomTypes = RoomType::count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_properties' => (int) $totalProperties,
                    'total_users' => (int) $totalUsers,
                    'total_rooms' => (int) $totalRooms,
                    'total_five_star_reviews' => (int) $totalFiveStarReviews,
                    'total_room_types' => (int) $totalRoomTypes,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@statistics failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy dữ liệu thống kê.',
            ], 500);
        }
    }

    /**
     * Get popular room types for homepage
     */
    public function roomTypes(Request $request): JsonResponse
    {
        try {
            // Validate query parameters
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:20',
            ], [
                'limit.max' => 'Số lượng bản ghi không được vượt quá 20.',
            ]);

            $limit = (int) ($request->get('limit', 6));
            $query = RoomType::query();
            
            // Thêm điều kiện status nếu cột tồn tại
            if (Schema::hasColumn('room_types', 'status')) {
                $query->where('status', 'active');
            }
            
            // Load relationship nếu property_id tồn tại
            if (Schema::hasColumn('room_types', 'property_id')) {
                $query->with('property:id,name');
            }
            
            $query->orderBy('created_at', 'desc')
                ->limit($limit);

            $roomTypes = $query->get();

            // Đếm số lượng rooms cho mỗi room type
            $roomTypesWithCount = $roomTypes->map(function ($roomType) {
                $roomsQuery = Room::where('room_type_id', $roomType->id);
                
                // Chỉ lấy phòng available (bắt buộc)
                $roomsQuery->where('status', 'available');
                
                // Nếu có cột verification_status, ưu tiên lấy phòng đã verified
                // Nhưng nếu không có phòng verified nào, vẫn lấy phòng available
                if (Schema::hasColumn('rooms', 'verification_status')) {
                    $verifiedCount = (clone $roomsQuery)->where('verification_status', 'verified')->count();
                    // Nếu có phòng verified, dùng số đó. Nếu không, dùng tổng số phòng available
                    $roomsCount = $verifiedCount > 0 ? $verifiedCount : $roomsQuery->count();
                } else {
                    $roomsCount = $roomsQuery->count();
                }
                
                return [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'description' => $roomType->description,
                    'image_url' => $roomType->image_url,
                    'rooms_count' => $roomsCount,
                    'property' => $roomType->relationLoaded('property') && $roomType->property ? [
                        'id' => $roomType->property->id,
                        'name' => $roomType->property->name,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $roomTypesWithCount,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('HomeController@roomTypes failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách loại phòng.',
            ], 500);
        }
    }

    /**
     * Get public amenities list for filter
     */
    public function amenities(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
                'property_id' => 'sometimes|integer|exists:properties,id',
            ]);

            $limit = (int) ($request->get('limit', 100));
            $query = Amenity::query();
            
            // Filter by property_id if provided
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }
            
            // Chỉ lấy amenities active nếu có cột status
            if (Schema::hasColumn('amenities', 'status')) {
                $query->where('status', 'active');
            }
            
            // Load property relationship if property_id column exists
            if (Schema::hasColumn('amenities', 'property_id')) {
                $query->with('property:id,name');
            }
            
            $amenities = $query->orderBy('name', 'asc')
                ->limit($limit)
                ->get();

            // Map amenities with property info
            $amenitiesWithProperty = $amenities->map(function ($amenity) {
                return [
                    'id' => $amenity->id,
                    'name' => $amenity->name,
                    'icon_url' => $amenity->icon_url,
                    'type' => $amenity->type,
                    'category' => $amenity->category ?? 'facility', // Default to 'facility' if not set
                    'property' => $amenity->relationLoaded('property') && $amenity->property ? [
                        'id' => $amenity->property->id,
                        'name' => $amenity->property->name,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $amenitiesWithProperty,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('HomeController@amenities failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách tiện ích.',
            ], 500);
        }
    }

    /**
     * Get featured rooms for homepage
     * Lấy các phòng nổi bật (có thể sort theo rating, reviews, hoặc bookings)
     */
    public function featuredRooms(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:20',
            ], [
                'limit.max' => 'Số lượng bản ghi không được vượt quá 20.',
            ]);

            $limit = (int) ($request->get('limit', 4));
            $query = Room::query();
            
            // Chỉ lấy phòng available
            $query->where('status', 'available');
            
            // Nếu có cột verification_status, chỉ lấy phòng đã verified
            if (Schema::hasColumn('rooms', 'verification_status')) {
                $query->where('verification_status', 'verified');
            }
            
            // Load relationships
            $query->with([
                'property:id,name,address',
                'roomType:id,name',
                'amenities:id,name',
                'images',
                'reviews' => function ($q) {
                    if (Schema::hasColumn('reviews', 'status')) {
                        $q->where('status', 'approved');
                    }
                }
            ]);
            
            // Tính average rating và reviews count
            $rooms = $query->get()->map(function ($room) {
                $reviews = $room->reviews ?? collect([]);
                $avgRating = $reviews->avg('rating') ?? 0;
                $reviewsCount = $reviews->count();
                
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'description' => $room->description,
                    'price_per_night' => (float) $room->price_per_night,
                    'max_adults' => $room->max_adults,
                    'max_children' => $room->max_children,
                    'property' => $room->property ? [
                        'id' => $room->property->id,
                        'name' => $room->property->name,
                        'address' => $room->property->address,
                    ] : null,
                    'roomType' => $room->roomType ? [
                        'id' => $room->roomType->id,
                        'name' => $room->roomType->name,
                    ] : null,
                    'amenities' => $room->amenities->map(function ($amenity) {
                        return [
                            'id' => $amenity->id,
                            'name' => $amenity->name,
                        ];
                    }),
                    'images' => $room->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => $image->image_url,
                            'is_primary' => $image->is_primary ?? false,
                        ];
                    }),
                    'rating' => round($avgRating, 1),
                    'reviews_count' => $reviewsCount,
                ];
            });
            
            // Sort by rating và reviews count, sau đó limit
            $rooms = $rooms->sortByDesc(function ($room) {
                return ($room['rating'] * 100) + $room['reviews_count'];
            })->take($limit)->values();

            return response()->json([
                'success' => true,
                'data' => $rooms,
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@featuredRooms failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách phòng nổi bật.',
            ], 500);
        }
    }

    /**
     * Get unique/popular rooms for homepage
     * Lấy các phòng độc đáo hoặc được yêu thích (có nhiều reviews hoặc rating cao)
     */
    public function popularRooms(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:20',
            ], [
                'limit.max' => 'Số lượng bản ghi không được vượt quá 20.',
            ]);

            $limit = (int) ($request->get('limit', 4));
            $query = Room::query();
            
            // Chỉ lấy phòng available
            $query->where('status', 'available');
            
            // Nếu có cột verification_status, chỉ lấy phòng đã verified
            if (Schema::hasColumn('rooms', 'verification_status')) {
                $query->where('verification_status', 'verified');
            }
            
            // Load relationships
            $query->with([
                'property:id,name,address',
                'roomType:id,name',
                'images',
                'reviews' => function ($q) {
                    if (Schema::hasColumn('reviews', 'status')) {
                        $q->where('status', 'approved');
                    }
                }
            ]);
            
            // Tính average rating và reviews count
            $rooms = $query->get()->map(function ($room) {
                $reviews = $room->reviews ?? collect([]);
                $avgRating = $reviews->avg('rating') ?? 0;
                $reviewsCount = $reviews->count();
                
                // Lấy primary image hoặc image đầu tiên
                $primaryImage = $room->images->where('is_primary', true)->first() 
                    ?? $room->images->first();
                
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'price_per_night' => (float) $room->price_per_night,
                    'property' => $room->property ? [
                        'name' => $room->property->name,
                        'address' => $room->property->address,
                    ] : null,
                    'image_url' => $primaryImage ? $primaryImage->image_url : null,
                    'rating' => round($avgRating, 1),
                    'reviews_count' => $reviewsCount,
                    'badge' => $this->getBadgeByRating($avgRating),
                ];
            });
            
            // Sort by reviews count và rating, sau đó limit
            $rooms = $rooms->sortByDesc(function ($room) {
                return ($room['reviews_count'] * 10) + $room['rating'];
            })->take($limit)->values();

            return response()->json([
                'success' => true,
                'data' => $rooms,
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@popularRooms failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách phòng phổ biến.',
            ], 500);
        }
    }

    /**
     * Get featured properties for homepage
     * Lấy các homestay/properties nổi bật
     */
    public function properties(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:20',
            ], [
                'limit.max' => 'Số lượng bản ghi không được vượt quá 20.',
            ]);

            $limit = (int) ($request->get('limit', 6));
            $query = Property::query();
            
            // Ưu tiên lấy properties đã verified
            if (Schema::hasColumn('properties', 'verification_status')) {
                $verifiedCount = Property::where('verification_status', 'verified')->count();
                if ($verifiedCount > 0) {
                    $query->where('verification_status', 'verified');
                }
            }
            
            // Chỉ lấy properties active
            if (Schema::hasColumn('properties', 'status')) {
                $query->where('status', 'active');
            }
            
            // Load relationships
            $query->with([
                'owner:id,full_name',
                'images',
                'rooms' => function ($q) {
                    $q->where('status', 'available')
                      ->with([
                          'images',
                          'reviews' => function ($reviewQ) {
                              if (Schema::hasColumn('reviews', 'status')) {
                                  $reviewQ->where('status', 'approved');
                              }
                          }
                      ]);
                }
            ]);
            
            // Tính số phòng và rating trung bình cho mỗi property
            $properties = $query->get()->map(function ($property) {
                $rooms = $property->rooms ?? collect([]);
                $availableRooms = $rooms->where('status', 'available');
                
                // Lấy tất cả reviews từ các rooms của property
                $allReviews = collect([]);
                foreach ($availableRooms as $room) {
                    $roomReviews = $room->reviews ?? collect([]);
                    $allReviews = $allReviews->merge($roomReviews);
                }
                
                $avgRating = $allReviews->avg('rating') ?? 0;
                $reviewsCount = $allReviews->count();
                
                // Get primary image: ưu tiên property images, nếu không có thì lấy từ room
                $primaryImage = null;
                if ($property->images && $property->images->count() > 0) {
                    $primaryImage = $property->images->where('is_primary', true)->first() 
                        ?? $property->images->first();
                }
                
                // Fallback to room image if no property image
                if (!$primaryImage) {
                    $firstRoom = $availableRooms->first();
                    if ($firstRoom && $firstRoom->images) {
                        $primaryImage = $firstRoom->images->where('is_primary', true)->first() 
                            ?? $firstRoom->images->first();
                    }
                }
                
                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'address' => $property->address,
                    'description' => $property->description,
                    'image_url' => $primaryImage ? $primaryImage->image_url : null,
                    'rooms_count' => $availableRooms->count(),
                    'rating' => round($avgRating, 1),
                    'reviews_count' => $reviewsCount,
                    'badge' => $this->getBadgeByRating($avgRating),
                ];
            });
            
            // Sort by reviews count và rating, sau đó limit
            $properties = $properties->sortByDesc(function ($property) {
                return ($property['reviews_count'] * 10) + $property['rating'] + ($property['rooms_count'] * 5);
            })->take($limit)->values();

            return response()->json([
                'success' => true,
                'data' => $properties,
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@properties failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách homestay.',
            ], 500);
        }
    }

    /**
     * Search and filter properties (public API)
     * API tìm kiếm + lọc + sắp xếp properties
     */
    public function searchProperties(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'sometimes|string|max:255',
                'min_price' => 'sometimes|numeric|min:0',
                'max_price' => 'sometimes|numeric|min:0',
                'min_rating' => 'sometimes|numeric|min:0|max:5',
                'max_rating' => 'sometimes|numeric|min:0|max:5',
                'sort_by' => 'sometimes|string|in:name,rating,reviews_count,created_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
                'sort_order.in' => 'Thứ tự sắp xếp không hợp lệ.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', 15));
            $query = Property::query();
            
            // Chỉ lấy properties active
            if (Schema::hasColumn('properties', 'status')) {
                $query->where('status', 'active');
            }
            
            // Ưu tiên lấy properties đã verified
            if (Schema::hasColumn('properties', 'verification_status')) {
                $verifiedCount = Property::where('verification_status', 'verified')
                    ->where('status', 'active')
                    ->count();
                if ($verifiedCount > 0) {
                    $query->where('verification_status', 'verified');
                }
            }
            
            // Load relationships
            $query->with([
                'owner:id,full_name',
                'images',
                'rooms' => function ($q) {
                    $q->where('status', 'available')
                      ->with([
                          'images',
                          'reviews' => function ($reviewQ) {
                              if (Schema::hasColumn('reviews', 'status')) {
                                  $reviewQ->where('status', 'approved');
                              }
                          }
                      ]);
                }
            ]);

            // Search by name or address
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('address', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            // Get all properties first to calculate rating
            $properties = $query->get()->map(function ($property) {
                $rooms = $property->rooms ?? collect([]);
                $availableRooms = $rooms->where('status', 'available');
                
                // Calculate average rating from all room reviews
                $allReviews = collect([]);
                foreach ($availableRooms as $room) {
                    $roomReviews = $room->reviews ?? collect([]);
                    $allReviews = $allReviews->merge($roomReviews);
                }
                
                $avgRating = $allReviews->avg('rating') ?? 0;
                $reviewsCount = $allReviews->count();
                
                // Get min and max price from rooms
                $prices = $availableRooms->pluck('price_per_night')->filter();
                $minPrice = $prices->min() ?? 0;
                $maxPrice = $prices->max() ?? 0;
                
                // Get primary image: ưu tiên property images, nếu không có thì lấy từ room
                $primaryImage = null;
                if ($property->images && $property->images->count() > 0) {
                    $primaryImage = $property->images->where('is_primary', true)->first() 
                        ?? $property->images->first();
                }
                
                // Fallback to room image if no property image
                if (!$primaryImage) {
                    $firstRoom = $availableRooms->first();
                    if ($firstRoom && $firstRoom->images) {
                        $primaryImage = $firstRoom->images->where('is_primary', true)->first() 
                            ?? $firstRoom->images->first();
                    }
                }
                
                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'address' => $property->address,
                    'description' => $property->description,
                    'image_url' => $primaryImage ? $primaryImage->image_url : null,
                    'rooms_count' => $availableRooms->count(),
                    'rating' => round($avgRating, 1),
                    'reviews_count' => $reviewsCount,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'badge' => $this->getBadgeByRating($avgRating),
                ];
            });

            // Filter by price range
            if ($request->has('min_price')) {
                $properties = $properties->filter(function ($property) use ($request) {
                    return $property['max_price'] >= $request->min_price;
                });
            }
            if ($request->has('max_price')) {
                $properties = $properties->filter(function ($property) use ($request) {
                    return $property['min_price'] <= $request->max_price;
                });
            }

            // Filter by rating
            if ($request->has('min_rating')) {
                $properties = $properties->filter(function ($property) use ($request) {
                    return $property['rating'] >= $request->min_rating;
                });
            }
            if ($request->has('max_rating')) {
                $properties = $properties->filter(function ($property) use ($request) {
                    return $property['rating'] <= $request->max_rating;
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $properties = $properties->sortBy(function ($property) use ($sortBy, $sortOrder) {
                $value = match($sortBy) {
                    'name' => $property['name'],
                    'rating' => $property['rating'],
                    'reviews_count' => $property['reviews_count'],
                    default => $property['id'],
                };
                return $sortOrder === 'asc' ? $value : -$value;
            });

            // Paginate manually
            $total = $properties->count();
            $currentPage = (int) ($request->get('page', 1));
            $offset = ($currentPage - 1) * $perPage;
            $items = $properties->slice($offset, $perPage)->values();

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'pagination' => [
                        'current_page' => $currentPage,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => (int) ceil($total / $perPage),
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('HomeController@searchProperties failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tìm kiếm properties.',
            ], 500);
        }
    }

    /**
     * Get all homepage data in one request
     * API tổng hợp: lấy trending, bán chạy, nổi bật...
     */
    public function homepageData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'trending_limit' => 'sometimes|integer|min:1|max:20',
                'featured_limit' => 'sometimes|integer|min:1|max:20',
                'popular_limit' => 'sometimes|integer|min:1|max:20',
            ]);

            $trendingLimit = (int) ($request->get('trending_limit', 6));
            $featuredLimit = (int) ($request->get('featured_limit', 6));
            $popularLimit = (int) ($request->get('popular_limit', 6));

            // Get statistics
            $statisticsResponse = $this->statistics();
            $statisticsData = json_decode($statisticsResponse->getContent(), true);
            $statistics = $statisticsData['data'] ?? [];

            // Get trending rooms (using popular rooms logic)
            $trendingRequest = new Request(['limit' => $trendingLimit]);
            $trendingResponse = $this->popularRooms($trendingRequest);
            $trendingData = json_decode($trendingResponse->getContent(), true);
            $trending = $trendingData['data'] ?? [];

            // Get featured rooms
            $featuredRequest = new Request(['limit' => $featuredLimit]);
            $featuredResponse = $this->featuredRooms($featuredRequest);
            $featuredData = json_decode($featuredResponse->getContent(), true);
            $featured = $featuredData['data'] ?? [];

            // Get popular rooms
            $popularRequest = new Request(['limit' => $popularLimit]);
            $popularResponse = $this->popularRooms($popularRequest);
            $popularData = json_decode($popularResponse->getContent(), true);
            $popular = $popularData['data'] ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $statistics,
                    'trending' => $trending,
                    'featured' => $featured,
                    'popular' => $popular,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@homepageData failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy dữ liệu trang chủ.',
            ], 500);
        }
    }

    /**
     * Get property detail by ID (public API)
     * Lấy chi tiết homestay
     */
    public function propertyDetail(string $id): JsonResponse
    {
        try {
            $query = Property::query()
                ->where('id', $id);
            
            // Chỉ filter status nếu cột tồn tại
            if (Schema::hasColumn('properties', 'status')) {
                $query->where('status', 'active');
            }
            
            // Ưu tiên lấy property đã verified nếu có
            if (Schema::hasColumn('properties', 'verification_status')) {
                $verifiedProperty = (clone $query)->where('verification_status', 'verified')->first();
                if ($verifiedProperty) {
                    $property = $verifiedProperty;
                } else {
                    // Nếu không có verified, vẫn lấy property (không filter verification_status)
                    $property = Property::where('id', $id)
                        ->when(Schema::hasColumn('properties', 'status'), function ($q) {
                            $q->where('status', 'active');
                        })
                        ->first();
                }
            } else {
                $property = $query->first();
            }
            
            if (!$property) {
                Log::warning('PropertyDetail: Property not found', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy homestay.',
                ], 404);
            }
            
            // Load relationships - load từng phần để tránh lỗi
            try {
                $property->load('owner:id,full_name,email');
            } catch (\Exception $e) {
                Log::warning('Failed to load owner', ['property_id' => $id, 'error' => $e->getMessage()]);
            }
            
            try {
                $property->load('amenities:id,name,icon');
            } catch (\Exception $e) {
                Log::warning('Failed to load amenities', ['property_id' => $id, 'error' => $e->getMessage()]);
            }
            
            // Load property images
            try {
                $property->load('images');
            } catch (\Exception $e) {
                Log::warning('Failed to load property images', ['property_id' => $id, 'error' => $e->getMessage()]);
            }
            
            $rooms = collect([]);
            try {
                $roomsQuery = Room::where('property_id', $property->id);
                if (Schema::hasColumn('rooms', 'status')) {
                    $roomsQuery->where('status', 'available');
                }
                $rooms = $roomsQuery->with([
                    'roomType:id,name',
                    'images',
                    'amenities:id,name',
                ])->get();
                
                // Load reviews for each room separately
                foreach ($rooms as $room) {
                    try {
                        $reviewsQuery = Review::where('room_id', $room->id);
                        if (Schema::hasColumn('reviews', 'status')) {
                            $reviewsQuery->where('status', 'approved');
                        }
                        $room->setRelation('reviews', $reviewsQuery->get());
                    } catch (\Exception $e) {
                        Log::warning('Failed to load room reviews', ['room_id' => $room->id, 'error' => $e->getMessage()]);
                        $room->setRelation('reviews', collect([]));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to load rooms', ['property_id' => $id, 'error' => $e->getMessage()]);
            }
            $property->setRelation('rooms', $rooms);
            
            try {
                $reviewsQuery = Review::where('property_id', $property->id);
                if (Schema::hasColumn('reviews', 'status')) {
                    $reviewsQuery->where('status', 'approved');
                }
                $reviewsQuery->with('user:id,full_name');
                if (Schema::hasColumn('reviews', 'reviewed_at')) {
                    $reviewsQuery->latest('reviewed_at');
                } else {
                    $reviewsQuery->latest('created_at');
                }
                $property->setRelation('reviews', $reviewsQuery->limit(10)->get());
            } catch (\Exception $e) {
                Log::warning('Failed to load property reviews', ['property_id' => $id, 'error' => $e->getMessage()]);
                $property->setRelation('reviews', collect([]));
            }

            // Calculate rating from all room reviews
            $allReviews = collect([]);
            if ($property->rooms && $property->rooms->count() > 0) {
                foreach ($property->rooms as $room) {
                    if ($room && $room->reviews && $room->reviews->count() > 0) {
                        $allReviews = $allReviews->merge($room->reviews);
                    }
                }
            }
            
            // Also include property-level reviews
            if ($property->reviews && $property->reviews->count() > 0) {
                $allReviews = $allReviews->merge($property->reviews);
            }
            
            $avgRating = $allReviews->count() > 0 ? $allReviews->avg('rating') : 0;
            $reviewsCount = $allReviews->count();
            
            // Get images: ưu tiên property images, nếu không có hoặc ít thì bổ sung từ room images
            $images = collect([]);
            
            // Lấy property images trước
            if ($property->images && $property->images->count() > 0) {
                $images = $images->merge($property->images);
            }
            
            // Nếu property images ít hơn 5, bổ sung từ room images
            if ($images->count() < 5 && $property->rooms && $property->rooms->count() > 0) {
                foreach ($property->rooms as $room) {
                    if ($room && $room->images && $room->images->count() > 0) {
                        $roomImages = $room->images->take(5 - $images->count());
                        $images = $images->merge($roomImages);
                        if ($images->count() >= 5) {
                            break;
                        }
                    }
                }
            }
            
            // Ensure images have unique IDs
            $uniqueImages = $images->unique('id')->values();

            $propertyData = [
                'id' => $property->id,
                'name' => $property->name,
                'address' => $property->address,
                'description' => $property->description,
                'check_in_time' => $property->check_in_time,
                'check_out_time' => $property->check_out_time,
                'owner' => $property->owner,
                'amenities' => $property->amenities,
                'rooms' => $property->rooms,
                'images' => $uniqueImages,
                'rating' => round($avgRating, 1),
                'reviews_count' => $reviewsCount,
                'badge' => $this->getBadgeByRating($avgRating),
            ];

            return response()->json([
                'success' => true,
                'data' => $propertyData,
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@propertyDetail failed', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy chi tiết homestay: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get property reviews (public API)
     * Lấy danh sách đánh giá của homestay
     */
    public function propertyReviews(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:50',
                'rating' => 'sometimes|integer|min:1|max:5',
            ]);

            $perPage = (int) ($request->get('per_page', 10));
            
            // Get property to find all room IDs
            $property = Property::find($id);
            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy homestay.',
                ], 404);
            }
            
            // Get all room IDs for this property
            $roomIds = $property->rooms()->pluck('id')->toArray();
            
            // Query reviews: either property_id matches OR room_id is in the list
            $query = Review::query()
                ->where(function ($q) use ($id, $roomIds) {
                    $q->where('property_id', $id);
                    if (!empty($roomIds)) {
                        $q->orWhereIn('room_id', $roomIds);
                    }
                });

            if (Schema::hasColumn('reviews', 'status')) {
                $query->where('status', 'approved');
            }

            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            $reviews = $query->with([
                'user:id,full_name,avatar',
                'room:id,name',
                'property:id,name',
            ])
            ->latest('reviewed_at')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $reviews->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                        'last_page' => $reviews->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@propertyReviews failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đánh giá.',
            ], 500);
        }
    }

    /**
     * Get property comments (public API)
     * Lấy danh sách bình luận của homestay (reviews có comment)
     */
    public function propertyComments(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:50',
            ]);

            $perPage = (int) ($request->get('per_page', 10));
            
            // Get property to find all room IDs
            $property = Property::find($id);
            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy homestay.',
                ], 404);
            }
            
            // Get all room IDs for this property
            $roomIds = $property->rooms()->pluck('id')->toArray();
            
            // Query comments: either property_id matches OR room_id is in the list
            $query = Review::query()
                ->where(function ($q) use ($id, $roomIds) {
                    $q->where('property_id', $id);
                    if (!empty($roomIds)) {
                        $q->orWhereIn('room_id', $roomIds);
                    }
                })
                ->whereNotNull('comment')
                ->where('comment', '!=', '');

            if (Schema::hasColumn('reviews', 'status')) {
                $query->where('status', 'approved');
            }

            $comments = $query->with([
                'user:id,full_name,avatar',
                'room:id,name',
                'property:id,name',
            ])
            ->latest('reviewed_at')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $comments->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $comments->currentPage(),
                        'per_page' => $comments->perPage(),
                        'total' => $comments->total(),
                        'last_page' => $comments->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HomeController@propertyComments failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách bình luận.',
            ], 500);
        }
    }

    /**
     * Get badge text based on rating
     */
    private function getBadgeByRating(float $rating): string
    {
        if ($rating >= 4.8) {
            return 'Xuất sắc';
        } elseif ($rating >= 4.5) {
            return 'Tuyệt vời';
        } elseif ($rating >= 4.0) {
            return 'Tuyệt hảo';
        } else {
            return 'Tốt';
        }
    }
}

