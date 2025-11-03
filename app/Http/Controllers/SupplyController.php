<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\SupplyLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Supplies",
 *     description="API Endpoints for Supply Management"
 * )
 */
class SupplyController extends Controller
{
    /**
     * Display a listing of supplies
     * 
     * @OA\Get(
     *     path="/supplies",
     *     operationId="getSupplies",
     *     tags={"Supplies"},
     *     summary="Danh sách vật tư",
     *     description="Lấy danh sách tất cả vật tư với hỗ trợ lọc, tìm kiếm và phân trang",
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Lọc theo danh mục",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Lọc theo trạng thái (active, inactive, discontinued)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="stock_status",
     *         in="query",
     *         description="Lọc theo tình trạng kho (low_stock, out_of_stock, in_stock)",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *         description="Trang (15 kết quả/trang)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách vật tư",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supply::with('supplyLogs');

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by stock status
        if ($request->has('stock_status')) {
            switch ($request->stock_status) {
                case 'low_stock':
                    $query->lowStock();
                    break;
                case 'out_of_stock':
                    $query->outOfStock();
                    break;
                case 'in_stock':
                    $query->where('current_stock', '>', DB::raw('min_stock_level'))
                          ->where('current_stock', '>', 0);
                    break;
            }
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $supplies = $query->orderBy('name')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $supplies
        ]);
    }

    /**
     * Store a newly created supply
     * 
     * @OA\Post(
     *     path="/supplies",
     *     operationId="storeSupply",
     *     tags={"Supplies"},
     *     summary="Tạo vật tư mới",
     *     description="Tạo một vật tư mới với thông tin chi tiết",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "category", "unit", "current_stock", "min_stock_level", "unit_price"},
     *             @OA\Property(property="name", type="string", example="Nước rửa bát"),
     *             @OA\Property(property="description", type="string", example="Nước rửa bát chuyên dụng"),
     *             @OA\Property(property="category", type="string", example="Dụng cụ vệ sinh"),
     *             @OA\Property(property="unit", type="string", example="Chai"),
     *             @OA\Property(property="current_stock", type="integer", example=50),
     *             @OA\Property(property="min_stock_level", type="integer", example=10),
     *             @OA\Property(property="max_stock_level", type="integer", example=100),
     *             @OA\Property(property="unit_price", type="number", format="float", example=50000),
     *             @OA\Property(property="supplier", type="string", example="Công ty XYZ"),
     *             @OA\Property(property="supplier_contact", type="string", example="0123456789"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "discontinued"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Vật tư được tạo thành công"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Lỗi xác thực"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|max:100',
            'unit' => 'required|string|max:50',
            'current_stock' => 'required|integer|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'supplier_contact' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,discontinued'
        ]);

        try {
            DB::beginTransaction();

            $supply = Supply::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'unit' => $request->unit,
                'current_stock' => $request->current_stock,
                'min_stock_level' => $request->min_stock_level,
                'max_stock_level' => $request->max_stock_level ?? $request->current_stock,
                'unit_price' => $request->unit_price,
                'supplier' => $request->supplier,
                'supplier_contact' => $request->supplier_contact,
                'status' => $request->status ?? 'active'
            ]);

            // Create initial stock log
            if ($request->current_stock > 0) {
                SupplyLog::create([
                    'supply_id' => $supply->id,
                    'user_id' => Auth::check() ? Auth::id() : 1,
                    'change_quantity' => $request->current_stock,
                    'reason' => 'Initial stock entry'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vật tư đã được tạo thành công',
                'data' => $supply->load('supplyLogs')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified supply
     * 
     * @OA\Get(
     *     path="/supplies/{id}",
     *     operationId="getSupply",
     *     tags={"Supplies"},
     *     summary="Chi tiết vật tư",
     *     description="Lấy thông tin chi tiết của một vật tư",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết vật tư",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vật tư không tìm thấy"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $supply = Supply::with(['supplyLogs.performer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $supply
        ]);
    }

    /**
     * Update the specified supply
     * 
     * @OA\Put(
     *     path="/supplies/{id}",
     *     operationId="updateSupply",
     *     tags={"Supplies"},
     *     summary="Cập nhật vật tư",
     *     description="Cập nhật thông tin vật tư",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category", type="string"),
     *             @OA\Property(property="unit", type="string"),
     *             @OA\Property(property="min_stock_level", type="integer"),
     *             @OA\Property(property="max_stock_level", type="integer"),
     *             @OA\Property(property="unit_price", type="number"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "discontinued"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công"
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'sometimes|required|string|max:100',
            'unit' => 'sometimes|required|string|max:50',
            'min_stock_level' => 'sometimes|required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'supplier_contact' => 'nullable|string|max:255',
            'status' => 'sometimes|in:active,inactive,discontinued'
        ]);

        $supply = Supply::findOrFail($id);
        $supply->update($request->only([
            'name', 'description', 'category', 'unit', 'min_stock_level',
            'max_stock_level', 'unit_price', 'supplier', 'supplier_contact', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Vật tư đã được cập nhật',
            'data' => $supply
        ]);
    }

    /**
     * Remove the specified supply
     * 
     * @OA\Delete(
     *     path="/supplies/{id}",
     *     operationId="deleteSupply",
     *     tags={"Supplies"},
     *     summary="Xóa vật tư",
     *     description="Xóa một vật tư",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Không thể xóa vật tư này"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $supply = Supply::findOrFail($id);

        // Check if supply has stock
        if ($supply->current_stock > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa vật tư còn tồn kho'
            ], 400);
        }

        $supply->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vật tư đã được xóa'
        ]);
    }

    /**
     * Get low stock items
     * 
     * @OA\Get(
     *     path="/supplies/status/low-stock",
     *     operationId="getLowStockItems",
     *     tags={"Supplies"},
     *     summary="Danh sách vật tư cạn kiệt",
     *     description="Lấy danh sách vật tư có số lượng cạn kiệt",
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách vật tư cạn kiệt",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function getLowStockItems(): JsonResponse
    {
        $lowStockItems = Supply::active()->lowStock()->get();

        return response()->json([
            'success' => true,
            'data' => $lowStockItems
        ]);
    }

    /**
     * Get out of stock items
     * 
     * @OA\Get(
     *     path="/supplies/status/out-of-stock",
     *     operationId="getOutOfStockItems",
     *     tags={"Supplies"},
     *     summary="Danh sách vật tư hết hàng",
     *     description="Lấy danh sách vật tư đã hết hàng",
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách vật tư hết hàng",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function getOutOfStockItems(): JsonResponse
    {
        $outOfStockItems = Supply::active()->outOfStock()->get();

        return response()->json([
            'success' => true,
            'data' => $outOfStockItems
        ]);
    }

    /**
     * Adjust stock for a supply
     * 
     * @OA\Patch(
     *     path="/supplies/{id}/adjust-stock",
     *     operationId="adjustStock",
     *     tags={"Supplies"},
     *     summary="Điều chỉnh số lượng vật tư",
     *     description="Điều chỉnh (nhập, xuất, điều chỉnh) số lượng vật tư",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action_type", "quantity", "reason"},
     *             @OA\Property(property="action_type", type="string", enum={"in", "out", "adjustment"}),
     *             @OA\Property(property="quantity", type="integer", example=10),
     *             @OA\Property(property="reason", type="string", example="Kiểm kho định kỳ"),
     *             @OA\Property(property="notes", type="string", example="Ghi chú thêm"),
     *             @OA\Property(property="reference_type", type="string"),
     *             @OA\Property(property="reference_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Điều chỉnh thành công"
     *     )
     * )
     */
    public function adjustStock(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action_type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'reference_type' => 'nullable|string|max:100',
            'reference_id' => 'nullable|integer'
        ]);

        try {
            DB::beginTransaction();

            $supply = Supply::findOrFail($id);
            $previousStock = $supply->current_stock;
            $quantity = $request->quantity;

            // Calculate new stock based on action type
            switch ($request->action_type) {
                case 'in':
                    $newStock = $previousStock + $quantity;
                    break;
                case 'out':
                    if ($previousStock < $quantity) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không đủ tồn kho để xuất'
                        ], 400);
                    }
                    $newStock = $previousStock - $quantity;
                    break;
                case 'adjustment':
                    $newStock = $quantity;
                    break;
            }

            // Update supply stock
            $supply->update(['current_stock' => $newStock]);

            // Create supply log
            SupplyLog::create([
                'supply_id' => $supply->id,
                'user_id' => Auth::check() ? Auth::id() : 1,
                'change_quantity' => $request->action_type === 'out' ? -$quantity : $quantity,
                'reason' => $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tồn kho đã được điều chỉnh',
                'data' => $supply->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supply statistics
     * 
     * @OA\Get(
     *     path="/supplies/statistics/overview",
     *     operationId="getSupplyStatistics",
     *     tags={"Supplies"},
     *     summary="Thống kê vật tư",
     *     description="Lấy thống kê về vật tư (số lượng, giá trị)",
     *     @OA\Response(
     *         response=200,
     *         description="Thống kê vật tư",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_supplies", type="integer"),
     *                 @OA\Property(property="active_supplies", type="integer"),
     *                 @OA\Property(property="low_stock_count", type="integer"),
     *                 @OA\Property(property="out_of_stock_count", type="integer"),
     *                 @OA\Property(property="total_value", type="number"),
     *                 @OA\Property(property="categories", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function getStatistics(): JsonResponse
    {
        $stats = [
            'total_supplies' => Supply::count(),
            'active_supplies' => Supply::active()->count(),
            'low_stock_count' => Supply::active()->lowStock()->count(),
            'out_of_stock_count' => Supply::active()->outOfStock()->count(),
            'total_value' => Supply::active()->sum(DB::raw('current_stock * unit_price')),
            'categories' => Supply::select('category')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('category')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
