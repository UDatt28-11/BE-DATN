<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplyLog;
use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Supply Logs",
 *     description="API Endpoints for Supply Log Management"
 * )
 */
class SupplyLogController extends Controller
{
    /**
     * Số lượng bản ghi mỗi trang mặc định
     */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * Display a listing of supply logs
     *
     * @OA\Get(
     *     path="/api/supply-logs",
     *     operationId="getSupplyLogs",
     *     tags={"Supply Logs"},
     *     summary="Danh sách lịch sử vật tư",
     *     description="Lấy danh sách tất cả lịch sử vật tư (nhập, xuất, điều chỉnh) với hỗ trợ lọc",
     *     @OA\Parameter(
     *         name="action_type",
     *         in="query",
     *         description="Lọc theo loại hành động (in, out, adjustment)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="supply_id",
     *         in="query",
     *         description="Lọc theo ID vật tư",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="performed_by",
     *         in="query",
     *         description="Lọc theo người thực hiện",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Lọc từ ngày (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Lọc đến ngày (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Trang (20 kết quả/trang)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách lịch sử vật tư",
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
                'action_type' => 'sometimes|string|in:in,out,adjustment',
                'supply_id' => 'sometimes|integer|exists:supplies,id',
                'user_id' => 'sometimes|integer|exists:users,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'supply_id.exists' => 'Vật tư không tồn tại.',
                'user_id.exists' => 'User không tồn tại.',
                'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = SupplyLog::query()->with([
                'supply:id,name,category,unit',
                'user:id,full_name,email'
            ]);

            // Filter by action type
            if ($request->has('action_type')) {
                $query->where('action_type', $request->action_type);
            }

            // Filter by supply
            if ($request->has('supply_id')) {
                $query->where('supply_id', $request->supply_id);
            }

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Date range filter
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Sort by latest
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
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
            Log::error('SupplyLogController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách lịch sử vật tư.',
            ], 500);
        }
    }

    /**
     * Display the specified supply log
     *
     * @OA\Get(
     *     path="/api/supply-logs/{id}",
     *     operationId="getSupplyLog",
     *     tags={"Supply Logs"},
     *     summary="Chi tiết lịch sử vật tư",
     *     description="Lấy thông tin chi tiết của một bản ghi lịch sử vật tư",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết lịch sử vật tư",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $log = SupplyLog::with([
                'supply:id,name,category,unit',
                'user:id,full_name,email'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bản ghi lịch sử vật tư.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('SupplyLogController@show failed', [
                'log_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin lịch sử vật tư.',
            ], 500);
        }
    }

    /**
     * Get supply logs for a specific supply
     *
     * @OA\Get(
     *     path="/api/supply-logs/supply/{supplyId}",
     *     operationId="getSupplyHistoryLogs",
     *     tags={"Supply Logs"},
     *     summary="Lịch sử vật tư theo ID",
     *     description="Lấy toàn bộ lịch sử nhập/xuất/điều chỉnh của một vật tư",
     *     @OA\Parameter(
     *         name="supplyId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lịch sử vật tư",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function getSupplyLogs(string $supplyId): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            $supply = Supply::findOrFail($supplyId);
            $logs = $supply->supplyLogs()
                ->with('user:id,full_name,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy vật tư.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('SupplyLogController@getSupplyLogs failed', [
                'supply_id' => $supplyId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy lịch sử vật tư.',
            ], 500);
        }
    }

    /**
     * Get recent supply activities
     *
     * @OA\Get(
     *     path="/api/supply-logs/activities/recent",
     *     operationId="getRecentActivities",
     *     tags={"Supply Logs"},
     *     summary="Hoạt động vật tư gần đây",
     *     description="Lấy danh sách hoạt động vật tư gần đây nhất",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Số bản ghi tối đa (mặc định 10)",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hoạt động gần đây",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function getRecentActivities(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            // Validate query parameters
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
            ], [
                'limit.max' => 'Số lượng bản ghi không được vượt quá 100.',
            ]);

            $limit = (int) ($request->get('limit', 10));

            $activities = SupplyLog::with([
                'supply:id,name,category,unit',
                'user:id,full_name,email'
            ])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('SupplyLogController@getRecentActivities failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy hoạt động gần đây.',
            ], 500);
        }
    }

    /**
     * Get supply movement summary
     *
     * @OA\Get(
     *     path="/api/supply-logs/summary/movement",
     *     operationId="getMovementSummary",
     *     tags={"Supply Logs"},
     *     summary="Tóm tắt vận động vật tư",
     *     description="Lấy tóm tắt vận động vật tư (nhập, xuất, điều chỉnh) theo khoảng thời gian",
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
     *         description="Tóm tắt vận động",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_inbound", type="integer"),
     *                 @OA\Property(property="total_outbound", type="integer"),
     *                 @OA\Property(property="total_adjustments", type="integer"),
     *                 @OA\Property(property="total_transfers", type="integer"),
     *                 @OA\Property(property="inbound_value", type="number"),
     *                 @OA\Property(property="outbound_value", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function getMovementSummary(Request $request): JsonResponse
    {
        try {
            // Authorization is handled by route middleware (role:admin)

            // Validate query parameters
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
            ], [
                'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            ]);

            $query = SupplyLog::query();

            // Date range filter
            if ($request->has('date_from')) {
                $query->whereDate('supply_logs.created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('supply_logs.created_at', '<=', $request->date_to);
            }

            $baseQuery = clone $query;

            $summary = [
                'total_inbound' => (int) $baseQuery->clone()->where('change_quantity', '>', 0)->sum('change_quantity'),
                'total_outbound' => (int) $baseQuery->clone()->where('change_quantity', '<', 0)->sum(DB::raw('ABS(change_quantity)')),
                'total_movements' => (int) $baseQuery->clone()->count(),
                'inbound_value' => (float) $baseQuery->clone()
                    ->where('change_quantity', '>', 0)
                    ->join('supplies', 'supply_logs.supply_id', '=', 'supplies.id')
                    ->sum(DB::raw('supply_logs.change_quantity * supplies.unit_price')),
                'outbound_value' => (float) $baseQuery->clone()
                    ->where('change_quantity', '<', 0)
                    ->join('supplies', 'supply_logs.supply_id', '=', 'supplies.id')
                    ->sum(DB::raw('ABS(supply_logs.change_quantity) * supplies.unit_price'))
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('SupplyLogController@getMovementSummary failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy tóm tắt vận động vật tư.',
            ], 500);
        }
    }
}
