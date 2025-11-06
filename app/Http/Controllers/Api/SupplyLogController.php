<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplyLog;
use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Supply Logs",
 *     description="API Endpoints for Supply Log Management"
 * )
 */
class SupplyLogController extends Controller
{
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
        $query = SupplyLog::with(['supply', 'user']);

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

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
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
        $log = SupplyLog::with(['supply', 'user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
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
        $supply = Supply::findOrFail($supplyId);
        $logs = $supply->supplyLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
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
        $limit = $request->get('limit', 10);

        $activities = SupplyLog::with(['supply', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
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
        $query = SupplyLog::query();

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('supply_logs.created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('supply_logs.created_at', '<=', $request->date_to);
        }

        $summary = [
            'total_inbound' => $query->clone()->where('change_quantity', '>', 0)->sum('change_quantity'),
            'total_outbound' => $query->clone()->where('change_quantity', '<', 0)->sum(DB::raw('ABS(change_quantity)')),
            'total_movements' => $query->clone()->count(),
            'inbound_value' => $query->clone()
                ->where('change_quantity', '>', 0)
                ->join('supplies', 'supply_logs.supply_id', '=', 'supplies.id')
                ->sum(DB::raw('supply_logs.change_quantity * supplies.unit_price')),
            'outbound_value' => $query->clone()
                ->where('change_quantity', '<', 0)
                ->join('supplies', 'supply_logs.supply_id', '=', 'supplies.id')
                ->sum(DB::raw('ABS(supply_logs.change_quantity) * supplies.unit_price'))
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
