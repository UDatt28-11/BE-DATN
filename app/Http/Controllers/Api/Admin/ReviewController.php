<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\BookingDetail;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with username and password to get the authentication token",
 *     name="Token based based security",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="bearerAuth",
 * )
 * @OA\Tag(
 *     name="Reviews",
 *     description="API Endpoints for Review Management"
 * )
 */
class ReviewController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of reviews
     *
     * @OA\Get(
     *     path="/api/reviews",
     *     operationId="getReviews",
     *     tags={"Reviews"},
     *     summary="Danh sách đánh giá",
     *     description="Lấy danh sách tất cả đánh giá với hỗ trợ lọc, tìm kiếm và phân trang",
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="room_id",
     *         in="query",
     *         description="Lọc theo phòng",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái (pending, approved, rejected)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Lọc theo rating (1-5)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="verified_only",
     *         in="query",
     *         description="Chỉ lấy đánh giá từ khách đã booking",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo tiêu đề hoặc nội dung",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (mặc định 1, 15 kết quả/trang)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách đánh giá",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            // Validate query parameters
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'room_id' => 'sometimes|integer|exists:rooms,id',
                'status' => 'sometimes|string|in:pending,approved,rejected',
                'rating' => 'sometimes|integer|min:1|max:5',
                'verified_only' => 'sometimes|boolean',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:reviewed_at,rating,created_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'room_id.exists' => 'Room không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: pending, approved, rejected.',
                'rating.min' => 'Rating phải từ 1 đến 5.',
                'rating.max' => 'Rating phải từ 1 đến 5.',
                'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Review::query()->with([
                'user:id,full_name,email',
                'property:id,name',
                'room:id,name',
                'bookingDetail:id,booking_order_id,room_id'
            ]);

        // Filter by property
        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        // Filter by room
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter verified purchases only
        if ($request->boolean('verified_only', false)) {
            $query->where('is_verified_purchase', true);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('reviewed_at', [
                $request->date_from,
                $request->date_to
            ]);
        }

        // Search in comment and title
            if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhere('comment', 'like', '%' . $search . '%');
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'reviewed_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $reviews = $query->paginate($perPage);

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ReviewController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đánh giá.',
            ], 500);
        }
    }

    /**
     * Create review
     *
     * @OA\Post(
     *     path="/api/reviews",
     *     operationId="createReview",
     *     tags={"Reviews"},
     *     summary="Tạo đánh giá",
     *     description="Tạo đánh giá mới cho phòng hoặc property (Yêu cầu đăng nhập)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Dữ liệu đánh giá",
     *         @OA\JsonContent(
     *             required={"booking_details_id","rating","title"},
     *             @OA\Property(property="booking_details_id", type="integer", example=1, description="ID chi tiết đặt phòng"),
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5, description="Điểm đánh giá"),
     *             @OA\Property(property="title", type="string", example="Tuyệt vời", description="Tiêu đề đánh giá"),
     *             @OA\Property(property="comment", type="string", example="Phòng rất sạch, view đẹp", description="Nội dung đánh giá"),
     *             @OA\Property(property="photos", type="array", @OA\Items(type="string"), example={"photo1.jpg","photo2.jpg"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đánh giá đã được tạo"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized - Cần Bearer token"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_details_id' => 'required|exists:booking_details,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:100',
            'comment' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'string',
        ]);

        try {
            // Check if user is authenticated
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập để đánh giá'
                ], 401);
            }

            // Check if user already reviewed this booking
            $bookingDetail = BookingDetail::findOrFail($validated['booking_details_id']);

            if (Review::hasUserReviewedBooking($userId, $bookingDetail->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã đánh giá lần này rồi'
                ], 400);
            }

            // Get room from booking detail
            $room = $bookingDetail->room;
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phòng'
                ], 404);
            }

            // Get property from room
            $property = $room->property;
            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy property của phòng'
                ], 404);
            }

            $review = Review::create([
                'booking_details_id' => $bookingDetail->id,
                'user_id' => $userId,
                'property_id' => $property->id,
                'room_id' => $room->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'],
                'comment' => $validated['comment'] ?? null,
                'photos' => $validated['photos'] ?? null,
                'is_verified_purchase' => true,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá của bạn đã được gửi và đang chờ duyệt',
                'data' => $review
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ReviewController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đánh giá.',
            ], 500);
        }
    }

    /**
     * Get review details
     *
     * @OA\Get(
     *     path="/api/reviews/{id}",
     *     operationId="getReview",
     *     tags={"Reviews"},
     *     summary="Chi tiết đánh giá",
     *     description="Lấy chi tiết một đánh giá",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết đánh giá",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $review = Review::with(['user', 'property', 'room', 'bookingDetail'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $review
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đánh giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ReviewController@show failed', [
                'review_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin đánh giá.',
            ], 500);
        }
    }

    /**
     * Update review
     *
     * @OA\Put(
     *     path="/api/reviews/{id}",
     *     operationId="updateReview",
     *     tags={"Reviews"},
     *     summary="Cập nhật đánh giá",
     *     description="Cập nhật thông tin đánh giá",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4),
     *             @OA\Property(property="title", type="string", example="Rất tốt"),
     *             @OA\Property(property="comment", type="string", example="Phòng thoải mái"),
     *             @OA\Property(property="photos", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật thành công"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);

            // Check authorization
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập để cập nhật đánh giá'
                ], 401);
            }

            if ($review->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền chỉnh sửa đánh giá này'
                ], 403);
            }

            $validated = $request->validate([
                'rating' => 'nullable|integer|min:1|max:5',
                'title' => 'nullable|string|max:100',
                'comment' => 'nullable|string|max:2000',
                'photos' => 'nullable|array|max:10',
                'photos.*' => 'string',
            ]);

            $review->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá đã được cập nhật',
                'data' => $review
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đánh giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ReviewController@update failed', [
                'review_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật đánh giá.',
            ], 500);
        }
    }

    /**
     * Delete review
     *
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     operationId="deleteReview",
     *     tags={"Reviews"},
     *     summary="Xóa đánh giá",
     *     description="Xóa một đánh giá",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đánh giá đã được xóa")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);

            // Check authorization
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập để xóa đánh giá'
                ], 401);
            }

            if ($review->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa đánh giá này'
                ], 403);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá đã được xóa'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đánh giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ReviewController@destroy failed', [
                'review_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa đánh giá.',
            ], 500);
        }
    }

    /**
     * Approve review
     *
     * @OA\Post(
     *     path="/api/reviews/{id}/approve",
     *     operationId="approveReview",
     *     tags={"Reviews"},
     *     summary="Phê duyệt đánh giá",
     *     description="Phê duyệt một đánh giá chưa duyệt",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="admin_notes", type="string", example="Đánh giá hợp lệ", description="Ghi chú của admin")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Phê duyệt thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đánh giá đã được phê duyệt"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);

            $validated = $request->validate([
                'admin_notes' => 'nullable|string|max:500',
            ]);

            $review->approve($validated['admin_notes'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá đã được phê duyệt',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi phê duyệt đánh giá: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject review
     *
     * @OA\Post(
     *     path="/api/reviews/{id}/reject",
     *     operationId="rejectReview",
     *     tags={"Reviews"},
     *     summary="Từ chối đánh giá",
     *     description="Từ chối một đánh giá",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="rejection_reason", type="string", example="Vi phạm chính sách", description="Lý do từ chối")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Từ chối thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Đánh giá đã bị từ chối"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);

            $validated = $request->validate([
                'admin_notes' => 'required|string|max:500',
            ]);

            $review->reject($validated['admin_notes']);

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá đã bị từ chối',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi từ chối đánh giá: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark review as helpful
     *
     * @OA\Post(
     *     path="/api/reviews/{id}/mark-helpful",
     *     operationId="markReviewHelpful",
     *     tags={"Reviews"},
     *     summary="Đánh dấu hữu ích",
     *     description="Đánh dấu đánh giá này là hữu ích",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cảm ơn bạn đã đánh giá đánh giá này"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function markHelpful(int $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);
            $review->markAsHelpful();

            return response()->json([
                'success' => true,
                'message' => 'Cảm ơn bạn đã đánh giá đánh giá này',
                'data' => ['is_helpful_count' => $review->is_helpful_count]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark review as not helpful
     *
     * @OA\Post(
     *     path="/api/reviews/{id}/mark-not-helpful",
     *     operationId="markReviewNotHelpful",
     *     tags={"Reviews"},
     *     summary="Đánh dấu không hữu ích",
     *     description="Đánh dấu đánh giá này là không hữu ích",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID đánh giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cảm ơn bạn đã đánh giá đánh giá này"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function markNotHelpful(int $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);
            $review->markAsNotHelpful();

            return response()->json([
                'success' => true,
                'message' => 'Cảm ơn bạn về ý kiến phản hồi',
                'data' => ['is_not_helpful_count' => $review->is_not_helpful_count]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews by property
     *
     * @OA\Get(
     *     path="/api/reviews/property/{propertyId}",
     *     operationId="getPropertyReviews",
     *     tags={"Reviews"},
     *     summary="Đánh giá theo property",
     *     description="Lấy tất cả đánh giá của một property",
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         description="ID property",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Lọc theo rating (1-5)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách đánh giá",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getPropertyReviews(Request $request, $propertyId): JsonResponse
    {
        try {
            $property = Property::findOrFail($propertyId);

            $query = Review::byProperty($propertyId)->approved();

            // Filter by rating
            if ($request->has('rating')) {
                $query->byRating($request->rating);
            }

            // Filter verified purchases
            if ($request->boolean('verified_only', false)) {
                $query->verifiedPurchase();
            }

            $reviews = $query->with(['user', 'room'])
                ->orderBy('reviewed_at', 'desc')
                ->paginate($request->get('per_page', 10));

            $averageRating = Review::getPropertyAverageRating(
                $propertyId,
                !$request->boolean('verified_only', false)
            );
            $ratingDistribution = Review::getPropertyRatingDistribution(
                $propertyId,
                !$request->boolean('verified_only', false)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'average_rating' => $averageRating,
                    'rating_distribution' => $ratingDistribution,
                    'total_reviews' => Review::byProperty($propertyId)->approved()->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews by room
     *
     * @OA\Get(
     *     path="/api/reviews/room/{roomId}",
     *     operationId="getRoomReviews",
     *     tags={"Reviews"},
     *     summary="Đánh giá theo phòng",
     *     description="Lấy tất cả đánh giá của một phòng",
     *     @OA\Parameter(
     *         name="roomId",
     *         in="path",
     *         description="ID phòng",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Lọc theo rating (1-5)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách đánh giá",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getRoomReviews(Request $request, $roomId): JsonResponse
    {
        try {
            $room = Room::findOrFail($roomId);

            $query = Review::where('room_id', $roomId)->approved();

            // Filter by rating
            if ($request->has('rating')) {
                $query->byRating($request->rating);
            }

            $reviews = $query->with(['user', 'property'])
                ->orderBy('reviewed_at', 'desc')
                ->paginate($request->get('per_page', 10));

            $averageRating = round($reviews->avg('rating') ?? 0, 2);

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'average_rating' => $averageRating,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get review statistics
     *
     * @OA\Get(
     *     path="/api/reviews/statistics/overview",
     *     operationId="getReviewStats",
     *     tags={"Reviews"},
     *     summary="Thống kê đánh giá",
     *     description="Lấy thống kê về đánh giá",
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Từ ngày (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Đến ngày (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thống kê",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $propertyId = $request->property_id;

        $query = Review::query();
        if ($propertyId) {
            $query->byProperty($propertyId);
        }

        $totalReviews = (clone $query)->count();
        $approvedReviews = (clone $query)->approved()->count();
        $pendingReviews = (clone $query)->pending()->count();
        $averageRating = Review::getPropertyAverageRating($propertyId ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $totalReviews,
                'approved_reviews' => $approvedReviews,
                'pending_reviews' => $pendingReviews,
                'average_rating' => $averageRating,
            ]
        ]);
    }
}
