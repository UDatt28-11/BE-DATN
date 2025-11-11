<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceRule;
use App\Http\Resources\PriceRuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PriceRuleController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of price rules
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'room_id' => 'sometimes|integer|exists:rooms,id',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'room_id.exists' => 'Room không tồn tại.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = PriceRule::query()->with('room:id,name,property_id');

            // Filter by room_id
            if ($request->has('room_id')) {
                $query->where('room_id', $request->room_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('end_date', '<=', $request->end_date);
            }

            // Sort by start_date
            $query->orderBy('start_date', 'asc');

            // Paginate results
            $priceRules = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => PriceRuleResource::collection($priceRules),
                'meta' => [
                    'pagination' => [
                        'current_page' => $priceRules->currentPage(),
                        'per_page' => $priceRules->perPage(),
                        'total' => $priceRules->total(),
                        'last_page' => $priceRules->lastPage(),
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
            Log::error('PriceRuleController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách quy tắc giá.',
            ], 500);
        }
    }

    /**
     * Store a newly created price rule
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'room_id' => 'required|integer|exists:rooms,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'price_override' => 'required|numeric|min:0',
            ], [
                'room_id.required' => 'Vui lòng chọn phòng.',
                'room_id.exists' => 'Phòng không tồn tại.',
                'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
                'end_date.required' => 'Vui lòng chọn ngày kết thúc.',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
                'price_override.required' => 'Vui lòng nhập giá.',
                'price_override.numeric' => 'Giá phải là số.',
                'price_override.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            ]);

            $priceRule = PriceRule::create($validatedData);

            Log::info('PriceRule created', [
                'price_rule_id' => $priceRule->id,
                'room_id' => $priceRule->room_id,
                'price_override' => $priceRule->price_override,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo quy tắc giá thành công',
                'data' => new PriceRuleResource($priceRule->load('room:id,name,property_id')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PriceRuleController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo quy tắc giá.',
            ], 500);
        }
    }

    /**
     * Display the specified price rule
     */
    public function show(PriceRule $priceRule): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new PriceRuleResource($priceRule->load('room:id,name,property_id')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy quy tắc giá.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('PriceRuleController@show failed', [
                'price_rule_id' => $priceRule->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin quy tắc giá.',
            ], 500);
        }
    }

    /**
     * Update the specified price rule
     */
    public function update(Request $request, PriceRule $priceRule): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'room_id' => 'sometimes|integer|exists:rooms,id',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'price_override' => 'sometimes|numeric|min:0',
            ], [
                'room_id.exists' => 'Phòng không tồn tại.',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
                'price_override.numeric' => 'Giá phải là số.',
                'price_override.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            ]);

            $priceRule->update($validatedData);

            Log::info('PriceRule updated', [
                'price_rule_id' => $priceRule->id,
                'price_override' => $priceRule->price_override,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật quy tắc giá thành công',
                'data' => new PriceRuleResource($priceRule->load('room:id,name,property_id')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PriceRuleController@update failed', [
                'price_rule_id' => $priceRule->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật quy tắc giá.',
            ], 500);
        }
    }

    /**
     * Remove the specified price rule
     */
    public function destroy(PriceRule $priceRule): JsonResponse
    {
        try {
            $priceRuleId = $priceRule->id;

            $priceRule->delete();

            Log::info('PriceRule deleted', [
                'price_rule_id' => $priceRuleId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa quy tắc giá thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PriceRuleController@destroy failed', [
                'price_rule_id' => $priceRule->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa quy tắc giá.',
            ], 500);
        }
    }
}

