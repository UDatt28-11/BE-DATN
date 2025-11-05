<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Invoice Items",
 *     description="API Endpoints for Invoice Item Management"
 * )
 */
class InvoiceItemController extends Controller
{
    /**
     * Display invoice items for a specific invoice
     * 
     * @OA\Get(
     *     path="/api/invoices/{invoiceId}/items",
     *     operationId="getInvoiceItems",
     *     tags={"Invoice Items"},
     *     summary="Danh sách hạng mục hóa đơn",
     *     description="Lấy danh sách tất cả hạng mục trong một hóa đơn",
     *     @OA\Parameter(
     *         name="invoiceId",
     *         in="path",
     *         required=true,
     *         description="ID của hóa đơn",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách hạng mục",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function index(Request $request, string $invoiceId): JsonResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $items = $invoice->invoiceItems()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    /**
     * Add penalty/additional item to invoice
     * 
     * @OA\Post(
     *     path="/api/invoices/{invoiceId}/items/penalty",
     *     operationId="addPenaltyItem",
     *     tags={"Invoice Items"},
     *     summary="Thêm phí phát sinh vào hóa đơn",
     *     description="Thêm phí phát sinh, phí hư hại vào hóa đơn",
     *     @OA\Parameter(
     *         name="invoiceId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"description", "quantity", "unit_price"},
     *             @OA\Property(property="description", type="string", example="Phí hư hại thiết bị"),
     *             @OA\Property(property="quantity", type="integer", example=1),
     *             @OA\Property(property="unit_price", type="number", format="float", example=500000),
     *             @OA\Property(property="item_type", type="string", enum={"damage_fee", "other"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Phí được thêm thành công"
     *     )
     * )
     */
    public function addPenaltyItem(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'item_type' => 'nullable|in:damage_fee,other'
        ]);

        try {
            DB::beginTransaction();

            $invoice = Invoice::findOrFail($invoiceId);

            // Check if invoice is not paid
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể thêm mục vào hóa đơn đã thanh toán'
                ], 400);
            }

            $totalPrice = $request->quantity * $request->unit_price;

            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $request->description,
                'quantity' => $request->quantity,
                'unit_price' => $request->unit_price,
                'total_line' => $totalPrice,
                'item_type' => $request->item_type ?? 'damage_fee'
            ]);

            // Update invoice totals
            $newTotalAmount = $invoice->total_amount + $totalPrice;

            $invoice->update([
                'total_amount' => $newTotalAmount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Phí phát sinh đã được thêm vào hóa đơn',
                'data' => $item
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
     * Add regular item to invoice
     * 
     * @OA\Post(
     *     path="/api/invoices/{invoiceId}/items/regular",
     *     operationId="addRegularItem",
     *     tags={"Invoice Items"},
     *     summary="Thêm hạng mục vào hóa đơn",
     *     description="Thêm hạng mục (phòng, dịch vụ, khác) vào hóa đơn",
     *     @OA\Parameter(
     *         name="invoiceId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"item_name", "quantity", "unit_price", "item_type"},
     *             @OA\Property(property="item_name", type="string", example="Phòng Deluxe"),
     *             @OA\Property(property="description", type="string", example="2 đêm"),
     *             @OA\Property(property="quantity", type="integer", example=2),
     *             @OA\Property(property="unit_price", type="number", format="float", example=1500000),
     *             @OA\Property(property="item_type", type="string", enum={"room_charge", "service_charge", "damage_fee", "other"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Hạng mục được thêm thành công"
     *     )
     * )
     */
    public function addRegularItem(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'item_type' => 'required|in:room_charge,service_charge,damage_fee,other'
        ]);

        try {
            DB::beginTransaction();

            $invoice = Invoice::findOrFail($invoiceId);

            // Check if invoice is not paid
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể thêm mục vào hóa đơn đã thanh toán'
                ], 400);
            }

            $totalPrice = $request->quantity * $request->unit_price;

            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $request->description,
                'quantity' => $request->quantity,
                'unit_price' => $request->unit_price,
                'total_line' => $totalPrice,
                'item_type' => $request->item_type
            ]);

            // Update invoice totals
            $newTotalAmount = $invoice->total_amount + $totalPrice;

            $invoice->update([
                'total_amount' => $newTotalAmount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mục đã được thêm vào hóa đơn',
                'data' => $item
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
     * Create invoice item (generic)
     *
     * Staff endpoint: POST /invoice-items
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'description' => 'required|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'item_type' => 'nullable|in:room_charge,service_charge,damage_fee,other'
        ]);

        DB::beginTransaction();
        try {
            $invoice = Invoice::findOrFail($request->invoice_id);

            if ($invoice->status === 'paid') {
                return response()->json(['success' => false, 'message' => 'Không thể thêm mục vào hóa đơn đã thanh toán'], 400);
            }

            $total = $request->quantity * $request->unit_price;

            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $request->description,
                'quantity' => $request->quantity,
                'unit_price' => $request->unit_price,
                'total_line' => $total,
                'item_type' => $request->item_type ?? 'other'
            ]);

            $invoice->increment('total_amount', $total);

            DB::commit();

            return response()->json(['success' => true, 'data' => $item, 'message' => 'Item created'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Có lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Bulk create invoice items
     *
     * Admin endpoint: POST /invoice-items/bulk/create
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.invoice_id' => 'required|exists:invoices,id',
            'items.*.description' => 'required|string|max:1000',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.item_type' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $created = [];
            foreach ($request->items as $it) {
                $invoice = Invoice::findOrFail($it['invoice_id']);

                // skip items for paid invoices
                if ($invoice->status === 'paid') {
                    continue;
                }

                $total = $it['quantity'] * $it['unit_price'];

                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $it['description'],
                    'quantity' => $it['quantity'],
                    'unit_price' => $it['unit_price'],
                    'total_line' => $total,
                    'item_type' => $it['item_type'] ?? 'other'
                ]);

                $invoice->increment('total_amount', $total);
                $created[] = $item;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $created,
                'message' => 'Bulk items created'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi khi tạo hàng loạt mục: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete invoice items
     *
     * Admin endpoint: DELETE /invoice-items/bulk/delete
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:invoice_items,id'
        ]);

        DB::beginTransaction();
        try {
            $items = InvoiceItem::whereIn('id', $request->ids)->get();
            foreach ($items as $item) {
                $invoice = $item->invoice;

                // do not delete items from paid invoices
                if ($invoice->status === 'paid') {
                    continue;
                }

                $price = $item->total_line ?? ($item->quantity * $item->unit_price);
                $invoice->decrement('total_amount', $price);
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Items deleted'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi khi xóa hàng loạt mục: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update invoice item
     * 
     * @OA\Put(
     *     path="/api/invoice-items/{id}",
     *     operationId="updateInvoiceItem",
     *     tags={"Invoice Items"},
     *     summary="Cập nhật hạng mục hóa đơn",
     *     description="Cập nhật thông tin hạng mục hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="item_name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="quantity", type="integer"),
     *             @OA\Property(property="unit_price", type="number"),
     *             @OA\Property(property="item_type", type="string", enum={"room_charge", "service_charge", "damage_fee", "other"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $item = InvoiceItem::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $item
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hạng mục không tìm thấy'
            ], 404);
        }
    }

    /**
     * Update invoice item
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'description' => 'nullable|string|max:1000',
            'quantity' => 'sometimes|required|integer|min:1',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'item_type' => 'sometimes|in:room_charge,service_charge,damage_fee,other'
        ]);

        try {
            DB::beginTransaction();

            $item = InvoiceItem::findOrFail($id);
            $invoice = $item->invoice;

            // Check if invoice is not paid
            if ($invoice->paid_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chỉnh sửa mục trong hóa đơn đã thanh toán'
                ], 400);
            }

            $oldTotalPrice = $item->total_line;

            $item->update($request->only(['description', 'quantity', 'unit_price', 'item_type']));

            // Recalculate total price
            $newTotalPrice = $item->quantity * $item->unit_price;
            $item->update(['total_line' => $newTotalPrice]);

            // Update invoice totals
            $priceDifference = $newTotalPrice - $oldTotalPrice;
            $newTotalAmount = $invoice->total_amount + $priceDifference;

            $invoice->update([
                'total_amount' => $newTotalAmount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mục hóa đơn đã được cập nhật',
                'data' => $item
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
     * Remove invoice item
     * 
     * @OA\Delete(
     *     path="/api/invoice-items/{id}",
     *     operationId="deleteInvoiceItem",
     *     tags={"Invoice Items"},
     *     summary="Xóa hạng mục hóa đơn",
     *     description="Xóa một hạng mục khỏi hóa đơn",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa thành công"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $item = InvoiceItem::findOrFail($id);
            $invoice = $item->invoice;

            // Check if invoice is not paid
            if ($invoice->paid_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa mục trong hóa đơn đã thanh toán'
                ], 400);
            }

            $itemPrice = $item->total_price;

            // Update invoice totals
            $newTotalAmount = $invoice->total_amount - $itemPrice;
            $newFinalAmount = $invoice->final_amount - $itemPrice;

            $invoice->update([
                'total_amount' => $newTotalAmount,
                'final_amount' => $newFinalAmount
            ]);

            $item->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mục hóa đơn đã được xóa'
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
     * Get penalty items for an invoice
     * 
     * @OA\Get(
     *     path="/api/invoices/{invoiceId}/items/penalties",
     *     operationId="getPenaltyItems",
     *     tags={"Invoice Items"},
     *     summary="Danh sách phí phát sinh trong hóa đơn",
     *     description="Lấy danh sách tất cả phí phát sinh trong một hóa đơn",
     *     @OA\Parameter(
     *         name="invoiceId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách phí phát sinh",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function getPenaltyItems(string $invoiceId): JsonResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $penaltyItems = $invoice->invoiceItems()->penalties()->get();

        return response()->json([
            'success' => true,
            'data' => $penaltyItems
        ]);
    }

    /**
     * Get regular items for an invoice
     * 
     * @OA\Get(
     *     path="/api/invoices/{invoiceId}/items/regular",
     *     operationId="getRegularItems",
     *     tags={"Invoice Items"},
     *     summary="Danh sách hạng mục thường trong hóa đơn",
     *     description="Lấy danh sách tất cả hạng mục thường (phòng, dịch vụ) trong một hóa đơn",
     *     @OA\Parameter(
     *         name="invoiceId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách hạng mục thường",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", items={"type": "object"})
     *         )
     *     )
     * )
     */
    public function getRegularItems(string $invoiceId): JsonResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $regularItems = $invoice->invoiceItems()->regularItems()->get();

        return response()->json([
            'success' => true,
            'data' => $regularItems
        ]);
    }
}
