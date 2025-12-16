<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexRoomRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Review;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Services\Room\QueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Rooms",
 *     description="API Endpoints for Room Management"
 * )
 */
class RoomController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Cache schema checks to avoid repeated queries
     */
    private function hasColumn(string $table, string $column): bool
    {
        return Cache::remember("schema_{$table}_{$column}", 3600, function () use ($table, $column) {
            return Schema::hasColumn($table, $column);
        });
    }

    /**
     * Display a listing of rooms
     *
     * @OA\Get(
     *     path="/api/admin/rooms",
     *     operationId="getRooms",
     *     tags={"Rooms"},
     *     summary="Danh sách phòng",
     *     description="Lấy danh sách tất cả phòng với hỗ trợ lọc và phân trang",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="room_type_id",
     *         in="query",
     *         description="Lọc theo room type ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái (available, maintenance, occupied)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"available", "maintenance", "occupied"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo tên",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (mặc định 1)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Số lượng bản ghi mỗi trang (mặc định 15)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(IndexRoomRequest $request, QueryService $service): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            // Use QueryService để xử lý logic query phức tạp
            // Use raw query params to avoid dropping filters when validation is lenient
            $result = $service->index($request->query());

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            Log::error('RoomController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách phòng.',
            ], 500);
        }
    }

    /**
     * Public method: Display a listing of rooms (không cần đăng nhập)
     * Chỉ trả về phòng đã verified và available
     */
    public function indexPublic(Request $request): JsonResponse
    {
        try {
            // Validate query parameters
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'room_type_id' => 'sometimes|integer|exists:room_types,id',
                'search' => 'sometimes|string|max:255',
                'min_price' => 'sometimes|numeric|min:0',
                'max_price' => 'sometimes|numeric|min:0',
                'min_rating' => 'sometimes|numeric|min:0|max:5',
                'max_rating' => 'sometimes|numeric|min:0|max:5',
                'max_adults' => 'sometimes|integer|min:1|max:50',
                'max_children' => 'sometimes|integer|min:0|max:50',
                'amenities' => 'sometimes|array',
                'amenities.*' => 'sometimes|integer|exists:amenities,id',
                'check_in' => 'sometimes|date',
                'check_out' => 'sometimes|date|after:check_in',
                'sort_by' => 'sometimes|string|in:id,name,price_per_night,rating,reviews_count,created_at,updated_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'room_type_id.exists' => 'Room type không tồn tại.',
                'amenities.array' => 'Amenities phải là mảng.',
                'amenities.*.exists' => 'Một hoặc nhiều amenities không tồn tại.',
                'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
                'sort_order.in' => 'Thứ tự sắp xếp không hợp lệ. Chỉ chấp nhận: asc, desc.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Room::query();
            
            // Chỉ lấy phòng available (bắt buộc)
            $query->where('status', 'available');
            
            // Cache verification status check
            $hasVerification = $this->hasColumn('rooms', 'verification_status');
            $hasReviewStatus = $this->hasColumn('reviews', 'status');
            
            // Nếu có cột verification_status, ưu tiên lấy phòng đã verified
            if ($hasVerification) {
                // Cache verified count for 1 minute
                $verifiedCount = Cache::remember('rooms_verified_count', 60, function () use ($query) {
                    return (clone $query)->where('verification_status', 'verified')->count();
                });
                if ($verifiedCount > 0) {
                    $query->where('verification_status', 'verified');
                }
            }
            
            // Optimized eager loading with select
            $query->with([
                'property:id,name,address', 
                'roomType:id,name', 
                'roomType.images:id,room_type_id,image_url,is_primary',
                'amenities:id,name,filter_category',
            ]);
            
            // Use withAvg instead of loading all reviews (much faster)
            $query->withAvg(['reviews as rating' => function ($q) use ($hasReviewStatus) {
                if ($hasReviewStatus) {
                    $q->where('status', 'approved');
                }
            }], 'rating');
            
            $query->withCount(['reviews as reviews_count' => function ($q) use ($hasReviewStatus) {
                if ($hasReviewStatus) {
                    $q->where('status', 'approved');
                }
            }]);

            // Filter by date range: loại trừ các phòng đã có booking trùng khoảng ngày
            if ($request->filled('check_in') && $request->filled('check_out')) {
                $checkIn = $request->get('check_in');
                $checkOut = $request->get('check_out');

                $query->whereDoesntHave('bookingDetails', function ($detailQuery) use ($checkIn, $checkOut) {
                    // Chỉ xét các booking chưa bị hủy
                    $detailQuery->whereHas('bookingOrder', function ($bo) {
                        $bo->whereNotIn('status', ['cancelled']);
                    });

                    // Khoảng ngày trùng nhau nếu:
                    // existing_check_in < requested_check_out AND existing_check_out > requested_check_in
                    $detailQuery->whereDate('check_in_date', '<', $checkOut)
                        ->whereDate('check_out_date', '>', $checkIn);
                });
            }

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by room_type_id
            if ($request->has('room_type_id')) {
                $query->where('room_type_id', $request->room_type_id);
            }

            // Filter by price range
            if ($request->has('min_price')) {
                $query->where('price_per_night', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price_per_night', '<=', $request->max_price);
            }

            // Filter by max_adults (room must accommodate at least this many adults)
            if ($request->has('max_adults')) {
                $query->where('max_adults', '>=', $request->max_adults);
            }

            // Filter by max_children (room must accommodate at least this many children)
            if ($request->has('max_children')) {
                $query->where('max_children', '>=', $request->max_children);
            }

            // Filter by amenities (room must have ALL selected amenities)
            if ($request->has('amenities') && is_array($request->amenities) && count($request->amenities) > 0) {
                $amenityIds = array_filter(array_map('intval', $request->amenities));
                if (count($amenityIds) > 0) {
                    // Room must have ALL selected amenities (not just some)
                    foreach ($amenityIds as $amenityId) {
                        $query->whereHas('amenities', function ($q) use ($amenityId) {
                            $q->where('amenities.id', $amenityId);
                        });
                    }
                }
            }

            // Search by name or property address
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhereHas('property', function ($q) use ($search) {
                            $q->where('address', 'like', '%' . $search . '%')
                              ->orWhere('name', 'like', '%' . $search . '%');
                        });
                });
            }

            // Sorting (using database query)
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Map sort fields - use rooms table prefix for actual columns
            $sortColumn = match($sortBy) {
                'rating' => 'rating',
                'reviews_count' => 'reviews_count',
                'price_per_night' => 'rooms.price_per_night',
                'name' => 'rooms.name',
                'created_at' => 'rooms.created_at',
                'updated_at' => 'rooms.updated_at',
                default => 'rooms.created_at',
            };
            
            $query->orderBy($sortColumn, $sortOrder);

            // Paginate directly from database (much faster!)
            $currentPage = (int) ($request->get('page', 1));
            $rooms = $query->paginate($perPage, ['*'], 'page', $currentPage);
            
            // Process rooms for response and filter by rating in memory (small dataset after pagination)
            $minRating = $request->get('min_rating');
            $maxRating = $request->get('max_rating');
            
            $items = collect($rooms->items())->map(function ($room) {
                // Round rating
                $room->rating = round($room->rating ?? 0, 1);
                return $room;
            })->filter(function ($room) use ($minRating, $maxRating) {
                if ($minRating !== null && $room->rating < $minRating) return false;
                if ($maxRating !== null && $room->rating > $maxRating) return false;
                return true;
            })->values();

            // Convert rooms to array để đảm bảo images được serialize đúng
            $roomsArray = $items->map(function($room) {
                // Convert room to array
                $roomArray = $room->toArray();
                
                // Load images from roomType instead of room
                $images = [];
                if ($room->relationLoaded('roomType') && $room->roomType) {
                    if ($room->roomType->relationLoaded('images') && $room->roomType->images) {
                        $images = $room->roomType->images->map(function($image) {
                            return [
                                'id' => $image->id,
                                'image_url' => $image->image_url,
                                'is_primary' => (bool)($image->is_primary ?? false),
                            ];
                        })->values()->toArray();
                    }
                }
                $roomArray['images'] = $images;
                
                // Serialize amenities với filter_category
                if ($room->relationLoaded('amenities') && $room->amenities) {
                    $roomArray['amenities'] = $room->amenities->map(function($amenity) {
                        return [
                            'id' => $amenity->id,
                            'name' => $amenity->name,
                            'filter_category' => $amenity->filter_category ?? null, // NEW: filter_category
                        ];
                    })->values()->toArray();
                } else {
                    $roomArray['amenities'] = [];
                }
                
                // Đảm bảo floor_number và floor_category được trả về (NEW)
                $roomArray['floor_number'] = $room->floor_number ?? null;
                $roomArray['floor_category'] = $room->floor_category ?? null;
                
                return $roomArray;
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $roomsArray,
                'meta' => [
                    'pagination' => [
                        'current_page' => $rooms->currentPage(),
                        'per_page' => $rooms->perPage(),
                        'total' => $rooms->total(),
                        'last_page' => $rooms->lastPage(),
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
            Log::error('RoomController@indexPublic failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách phòng.',
            ], 500);
        }
    }

    /**
     * Public method: Display the specified room (không cần đăng nhập)
     * Chỉ trả về phòng available, ưu tiên phòng đã verified
     */
    public function showPublic(string $id): JsonResponse
    {
        try {
            // Log request ID để debug (ID không phải sensitive data nên không cần filter)
            Log::info('RoomController@showPublic - Request ID', ['requested_id' => $id, 'type' => gettype($id)]);
            
            // Tìm phòng theo ID - QUAN TRỌNG: Phải tìm chính xác theo ID trước, không filter ngay
            $room = Room::where('id', $id)->first();
            
            if (!$room) {
                Log::warning('RoomDetail: Room not found', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phòng hoặc phòng không khả dụng.',
                ], 404);
            }
            
            // Log room found
            Log::info('RoomController@showPublic - Room found', [
                'id' => $room->id,
                'name' => $room->name,
                'status' => $room->status ?? 'N/A',
                'verification_status' => $room->verification_status ?? 'N/A',
            ]);
            
            // Kiểm tra status và verification_status sau khi tìm thấy
            // CHÚ Ý: Nếu room có trong danh sách (indexPublic), thì nó phải available
            // Nhưng nếu user truy cập trực tiếp URL, vẫn cho phép xem (chỉ cảnh báo)
            if (Schema::hasColumn('rooms', 'status')) {
                if ($room->status !== 'available') {
                    Log::warning('RoomDetail: Room status is not available', [
                        'id' => $room->id,
                        'status' => $room->status,
                    ]);
                    // Vẫn trả về room nhưng có thể thêm flag để frontend hiển thị cảnh báo
                    // Không reject để tránh 404 khi user truy cập trực tiếp URL
                }
            }
            
            // Nếu có verification_status, ưu tiên phòng đã verified
            if (Schema::hasColumn('rooms', 'verification_status')) {
                if ($room->verification_status !== 'verified') {
                    Log::info('RoomDetail: Room is not verified, but returning anyway', [
                        'id' => $room->id,
                        'verification_status' => $room->verification_status,
                    ]);
                    // Vẫn trả về phòng dù chưa verified (theo yêu cầu trước đó)
                }
            }
            
            // Load relationships - load images from roomType
            $room->load([
                'property:id,name,address',
                'roomType:id,name',
                'roomType.images',
                'amenities:id,name',
            ]);

            // Serialize room với images từ roomType
            $roomArray = $room->toArray();
            $images = [];
            if ($room->relationLoaded('roomType') && $room->roomType) {
                if ($room->roomType->relationLoaded('images') && $room->roomType->images) {
                    $images = $room->roomType->images->map(function($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => $image->image_url,
                            'is_primary' => (bool)($image->is_primary ?? false),
                        ];
                    })->values()->toArray();
                }
            }
            $roomArray['images'] = $images;

            return response()->json([
                'success' => true,
                'data' => $roomArray,
            ]);
        } catch (\Exception $e) {
            Log::error('RoomController@showPublic failed', [
                'room_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin phòng.',
            ], 500);
        }
    }

    /**
     * Get room reviews (public API)
     * Lấy danh sách đánh giá của phòng
     */
    public function roomReviews(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:50',
                'rating' => 'sometimes|integer|min:1|max:5',
            ]);

            $perPage = (int) ($request->get('per_page', 10));
            $query = Review::query()
                ->where('room_id', $id);

            if (Schema::hasColumn('reviews', 'status')) {
                $query->where('status', 'approved');
            }

            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            $reviews = $query->with([
                'user:id,full_name,avatar',
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
            Log::error('RoomController@roomReviews failed', [
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
     * Get room comments (public API)
     * Lấy danh sách bình luận của phòng (reviews có comment)
     */
    public function roomComments(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:50',
            ]);

            $perPage = (int) ($request->get('per_page', 10));
            $query = Review::query()
                ->where('room_id', $id)
                ->whereNotNull('comment')
                ->where('comment', '!=', '');

            if (Schema::hasColumn('reviews', 'status')) {
                $query->where('status', 'approved');
            }

            $comments = $query->with([
                'user:id,full_name,avatar',
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
            Log::error('RoomController@roomComments failed', [
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
     * Store a newly created room
     *
     * @OA\Post(
     *     path="/api/admin/rooms",
     *     operationId="storeRoom",
     *     tags={"Rooms"},
     *     summary="Tạo phòng mới",
     *     description="Tạo phòng mới với thông tin và amenities",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"property_id", "room_type_id", "name", "max_adults", "max_children", "price_per_night", "status"},
     *             @OA\Property(property="property_id", type="integer", example=1),
     *             @OA\Property(property="room_type_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Phòng 101"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="max_adults", type="integer", example=2),
     *             @OA\Property(property="max_children", type="integer", example=1),
     *             @OA\Property(property="price_per_night", type="number", example=500000),
     *             @OA\Property(property="status", type="string", enum={"available", "maintenance", "occupied"}, example="available"),
     *             @OA\Property(property="amenities", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo phòng thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tạo phòng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            $validatedData = $request->validated();

            // Tách mảng 'amenities' ra khỏi dữ liệu chính
            $amenityIds = $validatedData['amenities'] ?? [];
            unset($validatedData['amenities']);

            // Đồng bộ giá & sức chứa từ RoomType để đảm bảo luôn khớp
            $roomType = RoomType::findOrFail($validatedData['room_type_id']);
            $validatedData['max_adults'] = $roomType->max_adults ?? 2;
            $validatedData['max_children'] = $roomType->max_children ?? 0;
            $validatedData['price_per_night'] = $roomType->base_price ?? 0;

            // Tạo phòng
            $room = Room::create($validatedData);

            // Đồng bộ các tiện ích vào bảng 'room_amenities'
        if (!empty($amenityIds)) {
            $room->amenities()->sync($amenityIds);
        }

            Log::info('Room created', [
                'room_id' => $room->id,
                'name' => $room->name,
                'property_id' => $room->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'roomType.images', 'amenities:id,name']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified room
     *
     * @OA\Get(
     *     path="/api/admin/rooms/{id}",
     *     operationId="getRoom",
     *     tags={"Rooms"},
     *     summary="Chi tiết phòng",
     *     description="Lấy thông tin chi tiết của một phòng. Có thể sử dụng 'include' parameter để chỉ load relationships cần thiết.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relationships (property, roomType, amenities, images, verifier). Ví dụ: 'property,roomType,images'",
     *         required=false,
     *         @OA\Schema(type="string", example="property,roomType,images")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết phòng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Room not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Request $request, Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            
            // Parse include parameter
            $includes = $request->get('include', '');
            $with = [];
            
            if ($includes) {
                // Parse include string (e.g., "property,roomType,images")
                $includeArray = array_map('trim', explode(',', $includes));
                
                foreach ($includeArray as $include) {
                    if ($include === 'property') {
                        $with[] = 'property:id,name';
                    } elseif ($include === 'roomType') {
                        $with[] = 'roomType:id,name';
                        $with[] = 'roomType.images'; // Load images from roomType
                    } elseif ($include === 'amenities') {
                        $with[] = 'amenities:id,name';
                    } elseif ($include === 'images') {
                        $with[] = 'roomType.images'; // Load images from roomType instead
                    } elseif ($include === 'verifier') {
                        $with[] = 'verifier:id,full_name';
                    }
                }
            } else {
                // Default: Load tất cả relationships (backward compatibility)
                $with = [
                    'property:id,name',
                    'roomType:id,name',
                    'roomType.images', // Load images from roomType
                    'amenities:id,name',
                    'verifier:id,full_name'
                ];
            }
            
            // Loại bỏ duplicates
            $with = array_unique($with);
            
            // Load relationships
            if (!empty($with)) {
                $room->load($with);
            }
            
            return response()->json([
                'success' => true,
                'data' => $room,
            ]);
        } catch (\Exception $e) {
            Log::error('RoomController@show failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin phòng.',
            ], 500);
        }
    }

    /**
     * Update the specified room
     *
     * @OA\Put(
     *     path="/api/admin/rooms/{id}",
     *     operationId="updateRoom",
     *     tags={"Rooms"},
     *     summary="Cập nhật phòng",
     *     description="Cập nhật thông tin phòng",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="property_id", type="integer"),
     *             @OA\Property(property="room_type_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="max_adults", type="integer"),
     *             @OA\Property(property="max_children", type="integer"),
     *             @OA\Property(property="price_per_night", type="number"),
     *             @OA\Property(property="status", type="string", enum={"available", "maintenance", "occupied"}),
     *             @OA\Property(property="amenities", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật phòng thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Room not found")
     * )
     */
    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)
            $validatedData = $request->validated();

            $amenityIds = $validatedData['amenities'] ?? [];
            unset($validatedData['amenities']);

            // Nếu room_type_id được cập nhật (hoặc có base_price mới), đồng bộ lại giá & sức chứa
            $roomTypeId = $validatedData['room_type_id'] ?? $room->room_type_id;
            if ($roomTypeId) {
                $roomType = RoomType::findOrFail($roomTypeId);
                $validatedData['max_adults'] = $roomType->max_adults ?? $room->max_adults;
                $validatedData['max_children'] = $roomType->max_children ?? $room->max_children;
                $validatedData['price_per_night'] = $roomType->base_price ?? $room->price_per_night;
            }

            // Cập nhật phòng
            $room->update($validatedData);
        
        // Refresh room để đảm bảo có đầy đủ thông tin
        $room->refresh();

            // Đồng bộ lại tiện ích (sync = tự động thêm/xóa)
        if (!empty($amenityIds)) {
            $room->amenities()->sync($amenityIds);
        } else {
            // Nếu amenities rỗng, xóa tất cả
            $room->amenities()->detach();
        }

            Log::info('Room updated', [
                'room_id' => $room->id,
                'name' => $room->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'roomType.images', 'amenities:id,name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@update failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified room
     *
     * @OA\Delete(
     *     path="/api/admin/rooms/{id}",
     *     operationId="deleteRoom",
     *     tags={"Rooms"},
     *     summary="Xóa phòng",
     *     description="Xóa phòng và các liên kết liên quan",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Xóa phòng thành công")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Room not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(Room $room): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $roomId = $room->id;
            $roomName = $room->name;

            // Xóa tất cả ảnh của phòng trên S3 trước khi xóa phòng
            $images = $room->images;
            foreach ($images as $image) {
                try {
                    // Xóa file trên S3
                    $this->deleteImageFromS3($image->image_url);
                } catch (\Exception $e) {
                    // Log lỗi nhưng không dừng quá trình xóa phòng
                    Log::warning('RoomController@destroy - Failed to delete image from S3', [
                        'room_id' => $roomId,
                        'image_id' => $image->id,
                        'image_url' => $image->image_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Xóa phòng (tự động xóa các liên kết trong 'room_amenities' và 'room_images' do cascade)
            $room->delete();

            Log::info('Room deleted', [
                'room_id' => $roomId,
                'name' => $roomName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa phòng thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('RoomController@destroy failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa phòng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa file ảnh từ S3
     *
     * @param string|null $urlOrPath Stored S3 path or URL
     * @return void
     */
    private function deleteImageFromS3($urlOrPath)
    {
        if (!$urlOrPath) {
            return;
        }

        try {
            // If full URL is stored, extract path part after bucket domain
            $path = $urlOrPath;

            // If looks like a URL, parse it
            if (filter_var($urlOrPath, FILTER_VALIDATE_URL)) {
                $parsedUrl = parse_url($urlOrPath);
                $path = $parsedUrl['path'] ?? '';
                $path = ltrim($path, '/');
            }

            if ($path && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
                Log::info('RoomController@deleteImageFromS3 - Image deleted from S3', [
                    'path' => $path,
                    'original_url' => $urlOrPath,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('RoomController@deleteImageFromS3 failed (S3)', [
                'input' => $urlOrPath,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw exception, just log the error to avoid breaking the flow
        }
    }

    /**
     * Update room status
     */
    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:available,maintenance,occupied',
            ], [
                'status.required' => 'Vui lòng chọn trạng thái.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: available, maintenance, occupied.',
            ]);

            $room->update(['status' => $validatedData['status']]);

            Log::info('Room status updated', [
                'room_id' => $room->id,
                'status' => $room->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@updateStatus failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái.',
            ], 500);
        }
    }

    /**
     * Verify room
     */
    public function verify(Request $request, Room $room): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'sometimes|string|max:1000',
            ]);

            $admin = $request->user();

            $room->update([
                'verification_status' => 'verified',
                'verification_notes' => $validatedData['notes'] ?? null,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('Room verified', [
                'room_id' => $room->id,
                'verified_by' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xác minh phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'verifier:id,full_name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@verify failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác minh phòng.',
            ], 500);
        }
    }

    /**
     * Reject room verification
     */
    public function reject(Request $request, Room $room): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'notes' => 'required|string|max:1000',
            ], [
                'notes.required' => 'Vui lòng nhập lý do từ chối.',
            ]);

            $admin = $request->user();

            $room->update([
                'verification_status' => 'rejected',
                'verification_notes' => $validatedData['notes'],
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Log::info('Room verification rejected', [
                'room_id' => $room->id,
                'verified_by' => $admin->id,
                'notes' => $validatedData['notes'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Từ chối xác minh phòng thành công',
                'data' => $room->load(['property:id,name', 'roomType:id,name', 'verifier:id,full_name']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RoomController@reject failed', [
                'room_id' => $room->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi từ chối xác minh phòng.',
            ], 500);
        }
    }
}
