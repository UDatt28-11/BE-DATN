<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexPromotionRequest;
use App\Models\Promotion;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\BookingOrder;
use App\Services\Promotion\QueryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * @OA\Tag(
 *     name="Promotions",
 *     description="API Endpoints for Promotion Management"
 * )
 */
class PromotionController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 15;
    /**
     * Display a listing of promotions
     *
     * @OA\Get(
     *     path="/api/promotions",
     *     operationId="getPromotions",
     *     tags={"Promotions"},
     *     summary="Danh sách mã giảm giá",
     *     description="Lấy danh sách tất cả mã giảm giá với hỗ trợ lọc, tìm kiếm và phân trang",
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Lọc theo trạng thái (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Tìm kiếm theo mã hoặc mô tả",
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
     *         description="Danh sách mã giảm giá",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(IndexPromotionRequest $request, QueryService $service): JsonResponse
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
            Log::error('PromotionController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Create promotion
     *
     * @OA\Post(
     *     path="/api/promotions",
     *     operationId="createPromotion",
     *     tags={"Promotions"},
     *     summary="Tạo mã giảm giá",
     *     description="Tạo mã giảm giá mới. Lưu ý: room_ids bắt buộc khi applicable_to='specific_rooms', room_type_ids bắt buộc khi applicable_to='specific_room_types'",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Dữ liệu mã giảm giá",
     *         @OA\JsonContent(
     *             required={"property_id","code","discount_type","discount_value","start_date","end_date","applicable_to"},
     *             @OA\Property(property="property_id", type="integer", example=1, description="ID property"),
     *             @OA\Property(property="code", type="string", example="SUMMER2024", description="Mã giảm giá"),
     *             @OA\Property(property="description", type="string", example="Giảm giá mua hè 20%", description="Mô tả mã giảm"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage","fixed_amount"}, example="percentage", description="Loại giảm giá"),
     *             @OA\Property(property="discount_value", type="number", example=20, description="Giá trị giảm (%)"),
     *             @OA\Property(property="max_discount_amount", type="number", example=500, description="Giảm tối đa (VNĐ)"),
     *             @OA\Property(property="min_purchase_amount", type="number", example=1000, description="Mua tối thiểu (VNĐ)"),
     *             @OA\Property(property="max_usage_limit", type="integer", example=100, description="Lượt dùng tối đa"),
     *             @OA\Property(property="max_usage_per_user", type="integer", example=1, description="Lượt dùng per user"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-10-04", description="Ngày bắt đầu"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-01-02", description="Ngày kết thúc"),
     *             @OA\Property(property="is_active", type="integer", example=1, description="Kích hoạt (0 hoặc 1)"),
     *             @OA\Property(property="applicable_to", type="string", enum={"all","specific_rooms","specific_room_types"}, example="all", description="Áp dụng cho"),
     *             @OA\Property(property="room_ids", type="array", @OA\Items(type="integer", description="ID phòng"), description="IDs phòng (bắt buộc nếu applicable_to='specific_rooms', bỏ qua nếu applicable_to='all')"),
     *             @OA\Property(property="room_type_ids", type="array", @OA\Items(type="integer", description="ID loại phòng"), description="IDs loại phòng (bắt buộc nếu applicable_to='specific_room_types', bỏ qua nếu applicable_to='all')")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mã giảm giá đã được tạo thành công"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="property_id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SUMMER2024"),
     *                 @OA\Property(property="description", type="string", example="Giảm giá mua hè 20%"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", example=20),
     *                 @OA\Property(property="max_discount_amount", type="number", example=500),
     *                 @OA\Property(property="min_purchase_amount", type="number", example=1000),
     *                 @OA\Property(property="max_usage_limit", type="integer", example=100),
     *                 @OA\Property(property="max_usage_per_user", type="integer", example=1),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="start_date", type="string", format="date", example="2025-10-04"),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2026-01-02"),
     *                 @OA\Property(property="is_active", type="integer", example=1),
     *                 @OA\Property(property="applicable_to", type="string", example="all"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

        // Custom validation - handle room_ids and room_type_ids based on applicable_to
        $rules = [
            'property_id' => 'required|exists:properties,id',
            'code' => 'required|string|unique:promotions,code|max:50',
            'description' => 'nullable|string|max:500',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_usage_limit' => 'nullable|integer|min:1',
            'max_usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'nullable|in:0,1',
            'applicable_to' => 'required|in:all,specific_rooms,specific_room_types',
        ];

            $messages = [
                'property_id.required' => 'Vui lòng chọn property.',
                'property_id.exists' => 'Property không tồn tại.',
                'code.required' => 'Vui lòng nhập mã giảm giá.',
                'code.unique' => 'Mã giảm giá đã tồn tại.',
                'code.max' => 'Mã giảm giá không được vượt quá 50 ký tự.',
                'discount_type.required' => 'Vui lòng chọn loại giảm giá.',
                'discount_type.in' => 'Loại giảm giá không hợp lệ. Chỉ chấp nhận: percentage, fixed_amount.',
                'discount_value.required' => 'Vui lòng nhập giá trị giảm giá.',
                'discount_value.numeric' => 'Giá trị giảm giá phải là số.',
                'discount_value.min' => 'Giá trị giảm giá phải lớn hơn hoặc bằng 0.',
                'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
                'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
                'start_date.before_or_equal' => 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.',
                'end_date.required' => 'Vui lòng chọn ngày kết thúc.',
                'end_date.date' => 'Ngày kết thúc không hợp lệ.',
                'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
                'applicable_to.required' => 'Vui lòng chọn phạm vi áp dụng.',
                'applicable_to.in' => 'Phạm vi áp dụng không hợp lệ. Chỉ chấp nhận: all, specific_rooms, specific_room_types.',
                'room_ids.required' => 'Vui lòng chọn ít nhất một phòng.',
                'room_ids.array' => 'Danh sách phòng không hợp lệ.',
                'room_ids.min' => 'Vui lòng chọn ít nhất một phòng.',
                'room_ids.*.exists' => 'Một hoặc nhiều phòng không tồn tại.',
                'room_type_ids.required' => 'Vui lòng chọn ít nhất một loại phòng.',
                'room_type_ids.array' => 'Danh sách loại phòng không hợp lệ.',
                'room_type_ids.min' => 'Vui lòng chọn ít nhất một loại phòng.',
                'room_type_ids.*.exists' => 'Một hoặc nhiều loại phòng không tồn tại.',
            ];

        // Only validate room_ids/room_type_ids if applicable_to is not 'all'
        if ($request->get('applicable_to') === 'specific_rooms') {
            $rules['room_ids'] = 'required|array|min:1';
            $rules['room_ids.*'] = 'exists:rooms,id';
        } elseif ($request->get('applicable_to') === 'specific_room_types') {
            $rules['room_type_ids'] = 'required|array|min:1';
            $rules['room_type_ids.*'] = 'exists:room_types,id';
        }

            $validated = $request->validate($rules, $messages);

            $promotion = Promotion::create([
                'property_id' => $validated['property_id'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'discount_type' => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'max_discount_amount' => $validated['max_discount_amount'] ?? null,
                'min_purchase_amount' => $validated['min_purchase_amount'] ?? null,
                'max_usage_limit' => $validated['max_usage_limit'] ?? null,
                'max_usage_per_user' => $validated['max_usage_per_user'] ?? 1,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_active' => $validated['is_active'] ?? 1,
                'applicable_to' => $validated['applicable_to'],
            ]);

            if ($validated['applicable_to'] === 'specific_rooms') {
                $promotion->rooms()->attach($validated['room_ids']);
            }

            if ($validated['applicable_to'] === 'specific_room_types') {
                $promotion->roomTypes()->attach($validated['room_type_ids']);
            }

            // Load relationships for response
            $promotion->load([
                'property:id,name',
                'rooms:id,name',
                'roomTypes:id,name'
            ]);

            // Build response data conditionally
            $responseData = $promotion->toArray();
            if ($promotion->applicable_to !== 'all') {
                $responseData['rooms'] = $promotion->relationLoaded('rooms') ? $promotion->rooms->map(fn($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ])->toArray() : [];
                $responseData['room_types'] = $promotion->relationLoaded('roomTypes') ? $promotion->roomTypes->map(fn($roomType) => [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                ])->toArray() : [];
            } else {
                $responseData['rooms'] = [];
                $responseData['room_types'] = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Mã giảm giá đã được tạo thành công',
                'data' => $responseData
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PromotionController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Get promotion details
     *
     * @OA\Get(
     *     path="/api/promotions/{id}",
     *     operationId="getPromotion",
     *     tags={"Promotions"},
     *     summary="Chi tiết mã giảm giá",
     *     description="Lấy chi tiết một mã giảm giá",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID mã giảm giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết mã giảm giá",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="property_id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SUMMER2024"),
     *                 @OA\Property(property="description", type="string", example="Giảm giá mua hè 20%"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", example=20),
     *                 @OA\Property(property="max_discount_amount", type="number", example=500),
     *                 @OA\Property(property="min_purchase_amount", type="number", example=1000),
     *                 @OA\Property(property="max_usage_limit", type="integer", example=100),
     *                 @OA\Property(property="max_usage_per_user", type="integer", example=1),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="start_date", type="string", format="date", example="2025-10-04"),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2026-01-02"),
     *                 @OA\Property(property="is_active", type="integer", example=1),
     *                 @OA\Property(property="applicable_to", type="string", example="all"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $promotion = Promotion::with([
                'property:id,name',
                'rooms:id,name',
                'roomTypes:id,name'
            ])->findOrFail($id);

            // Build response data conditionally
            $responseData = $promotion->toArray();
            if ($promotion->applicable_to !== 'all') {
                $responseData['rooms'] = $promotion->relationLoaded('rooms') ? $promotion->rooms->map(fn($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ])->toArray() : [];
                $responseData['room_types'] = $promotion->relationLoaded('roomTypes') ? $promotion->roomTypes->map(fn($roomType) => [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                ])->toArray() : [];
            } else {
                $responseData['rooms'] = [];
                $responseData['room_types'] = [];
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy mã giảm giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('PromotionController@show failed', [
                'promotion_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Update promotion
     *
     * @OA\Put(
     *     path="/api/promotions/{id}",
     *     operationId="updatePromotion",
     *     tags={"Promotions"},
     *     summary="Cập nhật mã giảm giá",
     *     description="Cập nhật thông tin mã giảm giá. Lưu ý: room_ids bắt buộc khi applicable_to='specific_rooms', room_type_ids bắt buộc khi applicable_to='specific_room_types'",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID mã giảm giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="SUMMER2024", description="Mã giảm giá (optional)"),
     *             @OA\Property(property="description", type="string", example="Giảm giá mua hè 20%", description="Mô tả (optional)"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage","fixed_amount"}, description="Loại giảm (optional)"),
     *             @OA\Property(property="discount_value", type="number", example=20, description="Giá trị giảm (optional)"),
     *             @OA\Property(property="max_discount_amount", type="number", example=500, description="Giảm tối đa (optional)"),
     *             @OA\Property(property="min_purchase_amount", type="number", example=1000, description="Mua tối thiểu (optional)"),
     *             @OA\Property(property="max_usage_limit", type="integer", example=100, description="Lượt dùng tối đa (optional)"),
     *             @OA\Property(property="max_usage_per_user", type="integer", example=1, description="Lượt dùng per user (optional)"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-10-04", description="Ngày bắt đầu (optional)"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-01-02", description="Ngày kết thúc (optional)"),
     *             @OA\Property(property="is_active", type="integer", example=1, description="Kích hoạt (0 hoặc 1, optional)"),
     *             @OA\Property(property="applicable_to", type="string", enum={"all","specific_rooms","specific_room_types"}, description="Áp dụng cho (optional)"),
     *             @OA\Property(property="room_ids", type="array", @OA\Items(type="integer", description="ID phòng"), description="IDs phòng (bắt buộc nếu applicable_to='specific_rooms', bỏ qua nếu applicable_to='all')"),
     *             @OA\Property(property="room_type_ids", type="array", @OA\Items(type="integer", description="ID loại phòng"), description="IDs loại phòng (bắt buộc nếu applicable_to='specific_room_types', bỏ qua nếu applicable_to='all')")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cập nhật thành công"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="property_id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SUMMER2024"),
     *                 @OA\Property(property="description", type="string", example="Giảm giá mua hè 20%"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", example=20),
     *                 @OA\Property(property="max_discount_amount", type="number", example=500),
     *                 @OA\Property(property="min_purchase_amount", type="number", example=1000),
     *                 @OA\Property(property="max_usage_limit", type="integer", example=100),
     *                 @OA\Property(property="max_usage_per_user", type="integer", example=1),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="start_date", type="string", format="date", example="2025-10-04"),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2026-01-02"),
     *                 @OA\Property(property="is_active", type="integer", example=1),
     *                 @OA\Property(property="applicable_to", type="string", example="all"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $promotion = Promotion::findOrFail($id);

            // Custom validation - handle room_ids and room_type_ids based on applicable_to
            $rules = [
                'code' => 'nullable|string|unique:promotions,code,' . $id . '|max:50',
                'description' => 'nullable|string|max:500',
                'discount_type' => 'nullable|in:percentage,fixed_amount',
                'discount_value' => 'nullable|numeric|min:0',
                'max_discount_amount' => 'nullable|numeric|min:0',
                'min_purchase_amount' => 'nullable|numeric|min:0',
                'max_usage_limit' => 'nullable|integer|min:1',
                'max_usage_per_user' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date|before_or_equal:end_date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'nullable|in:0,1',
                'applicable_to' => 'nullable|in:all,specific_rooms,specific_room_types',
            ];

            $messages = [
                'code.unique' => 'Mã giảm giá đã tồn tại.',
                'code.max' => 'Mã giảm giá không được vượt quá 50 ký tự.',
                'discount_type.in' => 'Loại giảm giá không hợp lệ. Chỉ chấp nhận: percentage, fixed_amount.',
                'discount_value.numeric' => 'Giá trị giảm giá phải là số.',
                'discount_value.min' => 'Giá trị giảm giá phải lớn hơn hoặc bằng 0.',
                'start_date.before_or_equal' => 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.',
                'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
                'applicable_to.in' => 'Phạm vi áp dụng không hợp lệ. Chỉ chấp nhận: all, specific_rooms, specific_room_types.',
                'room_ids.required' => 'Vui lòng chọn ít nhất một phòng.',
                'room_ids.array' => 'Danh sách phòng không hợp lệ.',
                'room_ids.min' => 'Vui lòng chọn ít nhất một phòng.',
                'room_ids.*.exists' => 'Một hoặc nhiều phòng không tồn tại.',
                'room_type_ids.required' => 'Vui lòng chọn ít nhất một loại phòng.',
                'room_type_ids.array' => 'Danh sách loại phòng không hợp lệ.',
                'room_type_ids.min' => 'Vui lòng chọn ít nhất một loại phòng.',
                'room_type_ids.*.exists' => 'Một hoặc nhiều loại phòng không tồn tại.',
            ];

            // Only validate room_ids/room_type_ids if applicable_to is not 'all'
            if ($request->get('applicable_to') !== 'all' && !empty($request->get('applicable_to'))) {
                if ($request->get('applicable_to') === 'specific_rooms') {
                    $rules['room_ids'] = 'required|array|min:1';
                    $rules['room_ids.*'] = 'exists:rooms,id';
                } elseif ($request->get('applicable_to') === 'specific_room_types') {
                    $rules['room_type_ids'] = 'required|array|min:1';
                    $rules['room_type_ids.*'] = 'exists:room_types,id';
                }
            }

            $validated = $request->validate($rules, $messages);

            // Only update fields that were actually sent in the request
            $updateData = array_filter($validated, function ($value, $key) use ($request) {
                return $request->has($key) && !in_array($key, ['room_ids', 'room_type_ids']);
            }, ARRAY_FILTER_USE_BOTH);

            // Convert code to uppercase if provided
            if (isset($updateData['code'])) {
                $updateData['code'] = strtoupper($updateData['code']);
            }

            $promotion->update($updateData);

            // Handle room_ids and room_type_ids sync based on applicable_to
            if (!empty($validated['applicable_to'])) {
                if ($validated['applicable_to'] === 'specific_rooms') {
                    $promotion->rooms()->sync($validated['room_ids'] ?? []);
                } else {
                    // Clear rooms for 'all' or 'specific_room_types'
                    $promotion->rooms()->detach();
                }

                if ($validated['applicable_to'] === 'specific_room_types') {
                    $promotion->roomTypes()->sync($validated['room_type_ids'] ?? []);
                } else {
                    // Clear room types for 'all' or 'specific_rooms'
                    $promotion->roomTypes()->detach();
                }
            }

            // Refresh the model to get latest data
            $promotion->refresh();
            $promotion->load([
                'property:id,name',
                'rooms:id,name',
                'roomTypes:id,name'
            ]);

            // Only load relationships if they're relevant (not for 'all')
            $responseData = $promotion->toArray();
            if ($promotion->applicable_to !== 'all') {
                $responseData['rooms'] = $promotion->relationLoaded('rooms') ? $promotion->rooms->map(fn($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ])->toArray() : [];
                $responseData['room_types'] = $promotion->relationLoaded('roomTypes') ? $promotion->roomTypes->map(fn($roomType) => [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                ])->toArray() : [];
            } else {
                // For 'all', return empty arrays
                $responseData['rooms'] = [];
                $responseData['room_types'] = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thành công',
                'data' => $responseData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy mã giảm giá.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PromotionController@update failed', [
                'promotion_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Delete promotion
     *
     * @OA\Delete(
     *     path="/api/promotions/{id}",
     *     operationId="deletePromotion",
     *     tags={"Promotions"},
     *     summary="Xóa mã giảm giá",
     *     description="Xóa một mã giảm giá",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID mã giảm giá",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Xóa thành công")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $promotion = Promotion::findOrFail($id);
            $promotion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa mã giảm giá thành công.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy mã giảm giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('PromotionController@destroy failed', [
                'promotion_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Validate promotion code
     *
     * @OA\Post(
     *     path="/api/promotions/validate",
     *     operationId="validatePromotion",
     *     tags={"Promotions"},
     *     summary="Kiểm tra mã giảm giá",
     *     description="Kiểm tra mã giảm giá có hợp lệ không",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","total_amount"},
     *             @OA\Property(property="code", type="string", example="SUMMER2025"),
     *             @OA\Property(property="total_amount", type="number", example=5000000),
     *             @OA\Property(property="room_id", type="integer", example=1),
     *             @OA\Property(property="property_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Kết quả kiểm tra",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Mã không hợp lệ")
     * )
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'room_id' => 'nullable|exists:rooms,id',
            'property_id' => 'nullable|exists:properties,id',
        ]);

        $code = strtoupper($request->code);
        $promotion = Promotion::where('code', $code);

        if ($request->property_id) {
            $promotion->where('property_id', $request->property_id);
        }

        $promotion = $promotion->first();

        if (!$promotion) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy'], 404);
        }

        if (!$promotion->isValid()) {
            return response()->json(['success' => false, 'message' => 'Không còn hiệu lực'], 400);
        }

        if ($request->room_id && !$promotion->isApplicableToRoom($request->room_id)) {
            return response()->json(['success' => false, 'message' => 'Không áp dụng cho phòng này'], 400);
        }

        $discountAmount = $promotion->calculateDiscount($request->total_amount);

        return response()->json([
            'success' => true,
            'data' => [
                'promotion_id' => $promotion->id,
                'code' => $promotion->code,
                'description' => $promotion->description,
                'discount_type' => $promotion->discount_type,
                'discount_value' => $promotion->discount_value,
                'discount_amount' => $discountAmount,
                'final_amount' => $request->total_amount - $discountAmount,
            ]
        ]);
    }

    /**
     * Get promotion statistics
     *
     * @OA\Get(
     *     path="/api/promotions/statistics/overview",
     *     operationId="getPromotionStats",
     *     tags={"Promotions"},
     *     summary="Thống kê mã giảm giá",
     *     description="Lấy thống kê về mã giảm giá",
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property",
     *         required=false,
     *         @OA\Schema(type="integer")
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
        $query = Promotion::query();

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_promotions' => (clone $query)->count(),
                'active_promotions' => (clone $query)->active()->count(),
                'inactive_promotions' => (clone $query)->where('is_active', 0)->count(),
                'total_usage' => (clone $query)->sum('usage_count'),
            ]
        ]);
    }

    /**
     * Get active promotions
     *
     * @OA\Get(
     *     path="/api/promotions/active",
     *     operationId="getActivePromotions",
     *     tags={"Promotions"},
     *     summary="Danh sách mã giảm giá đang hoạt động",
     *     description="Lấy danh sách mã giảm giá đang hoạt động",
     *     @OA\Parameter(
     *         name="property_id",
     *         in="query",
     *         description="Lọc theo property",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (mặc định 1, 10 kết quả/trang)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách mã giảm giá đang hoạt động",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function activePromotions(Request $request): JsonResponse
    {
        $query = Promotion::active();

        if ($request->property_id) {
            $query->where('property_id', $request->property_id);
        }

        $promotions = $query->with(['property'])
            ->paginate($request->get('per_page', 10));

        // Map through promotions to conditionally add relationships
        $promotions->getCollection()->transform(function ($promotion) {
            $data = $promotion->toArray();
            if ($promotion->applicable_to !== 'all') {
                $data['rooms'] = $promotion->rooms()->get()->toArray();
                $data['room_types'] = $promotion->roomTypes()->get()->toArray();
            } else {
                $data['rooms'] = [];
                $data['room_types'] = [];
            }
            return $data;
        });

        return response()->json(['success' => true, 'data' => $promotions]);
    }

    /**
     * Bulk delete promotions
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'promotion_ids' => 'required|array|min:1',
                'promotion_ids.*' => 'required|integer|exists:promotions,id',
            ], [
                'promotion_ids.required' => 'Vui lòng chọn ít nhất một mã giảm giá.',
                'promotion_ids.*.exists' => 'Một trong các mã giảm giá không tồn tại.',
            ]);

            $count = Promotion::whereIn('id', $validatedData['promotion_ids'])->delete();

            Log::info('Promotions bulk deleted', [
                'promotion_ids' => $validatedData['promotion_ids'],
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$count} mã giảm giá thành công",
                'data' => ['deleted_count' => $count],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('PromotionController@bulkDelete failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Bulk update promotion status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'promotion_ids' => 'required|array|min:1',
                'promotion_ids.*' => 'required|integer|exists:promotions,id',
                'is_active' => 'required|boolean',
            ], [
                'promotion_ids.required' => 'Vui lòng chọn ít nhất một mã giảm giá.',
                'promotion_ids.*.exists' => 'Một trong các mã giảm giá không tồn tại.',
                'is_active.required' => 'Vui lòng chọn trạng thái.',
            ]);

            $count = Promotion::whereIn('id', $validatedData['promotion_ids'])
                ->update(['is_active' => $validatedData['is_active']]);

            Log::info('Promotions bulk status updated', [
                'promotion_ids' => $validatedData['promotion_ids'],
                'is_active' => $validatedData['is_active'],
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Đã cập nhật trạng thái {$count} mã giảm giá thành công",
                'data' => ['updated_count' => $count],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('PromotionController@bulkUpdateStatus failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái mã giảm giá.',
            ], 500);
        }
    }

    /**
     * Get promotion usage history - list of users who used this promotion
     *
     * @OA\Get(
     *     path="/api/admin/promotions/{id}/usage",
     *     operationId="getPromotionUsage",
     *     tags={"Promotions"},
     *     summary="Lịch sử sử dụng mã giảm giá",
     *     description="Lấy danh sách người dùng đã sử dụng mã giảm giá này",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID mã giảm giá",
     *         required=true,
     *         @OA\Schema(type="integer")
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
     *         description="Danh sách người dùng đã sử dụng mã",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Không tìm thấy mã giảm giá")
     * )
     */
    public function usage(int $id, Request $request): JsonResponse
    {
        try {
            $promotion = Promotion::findOrFail($id);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));

            // Get booking orders that used this promotion
            $bookingOrders = $promotion->bookingOrders()
                ->with([
                    'guest:id,name,email,phone',
                    'invoices:id,booking_order_id,invoice_number,total_amount,status'
                ])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Transform data to include user info and usage details
            $usageData = $bookingOrders->getCollection()->map(function ($bookingOrder) use ($promotion) {
                $pivot = $bookingOrder->pivot;
                return [
                    'id' => $bookingOrder->id,
                    'order_code' => $bookingOrder->order_code,
                    'user' => $bookingOrder->guest ? [
                        'id' => $bookingOrder->guest->id,
                        'name' => $bookingOrder->guest->name,
                        'email' => $bookingOrder->guest->email,
                        'phone' => $bookingOrder->guest->phone,
                    ] : null,
                    'customer_name' => $bookingOrder->customer_name,
                    'customer_email' => $bookingOrder->customer_email,
                    'customer_phone' => $bookingOrder->customer_phone,
                    'total_amount' => $bookingOrder->total_amount,
                    'discount_amount' => $pivot->applied_discount_amount ?? 0,
                    'used_at' => $pivot->created_at ?? $bookingOrder->created_at,
                    'booking_status' => $bookingOrder->status,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'promotion' => [
                        'id' => $promotion->id,
                        'code' => $promotion->code,
                        'description' => $promotion->description,
                        'usage_count' => $promotion->usage_count,
                        'max_usage_limit' => $promotion->max_usage_limit,
                    ],
                    'usage_history' => $usageData,
                    'pagination' => [
                        'current_page' => $bookingOrders->currentPage(),
                        'per_page' => $bookingOrders->perPage(),
                        'total' => $bookingOrders->total(),
                        'last_page' => $bookingOrders->lastPage(),
                    ],
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy mã giảm giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('PromotionController@usage failed', [
                'promotion_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy lịch sử sử dụng.',
            ], 500);
        }
    }
}
