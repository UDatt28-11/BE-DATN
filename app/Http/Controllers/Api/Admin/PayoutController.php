<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Http\Resources\PayoutResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PayoutController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of payouts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'status' => 'sometimes|string|in:pending,completed,failed',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: pending, completed, failed.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Payout::query()->with('property:id,name,owner_id');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Sort by payout_date and latest
            $query->orderBy('payout_date', 'desc')->latest();

            // Paginate results
            $payouts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => PayoutResource::collection($payouts),
                'meta' => [
                    'pagination' => [
                        'current_page' => $payouts->currentPage(),
                        'per_page' => $payouts->perPage(),
                        'total' => $payouts->total(),
                        'last_page' => $payouts->lastPage(),
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
            Log::error('PayoutController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách thanh toán.',
            ], 500);
        }
    }

    /**
     * Store a newly created payout
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'required|integer|exists:properties,id',
                'amount' => 'required|numeric|min:0',
                'payout_date' => 'required|date',
                'status' => 'sometimes|string|in:pending,completed,failed',
            ], [
                'property_id.required' => 'Vui lòng chọn property.',
                'property_id.exists' => 'Property không tồn tại.',
                'amount.required' => 'Vui lòng nhập số tiền.',
                'amount.numeric' => 'Số tiền phải là số.',
                'amount.min' => 'Số tiền phải lớn hơn hoặc bằng 0.',
                'payout_date.required' => 'Vui lòng chọn ngày thanh toán.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: pending, completed, failed.',
            ]);

            if (!isset($validatedData['status'])) {
                $validatedData['status'] = 'pending';
            }

            $payout = Payout::create($validatedData);

            Log::info('Payout created', [
                'payout_id' => $payout->id,
                'property_id' => $payout->property_id,
                'amount' => $payout->amount,
                'status' => $payout->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo thanh toán thành công',
                'data' => new PayoutResource($payout->load('property:id,name,owner_id')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayoutController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo thanh toán.',
            ], 500);
        }
    }

    /**
     * Display the specified payout
     */
    public function show(Payout $payout): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new PayoutResource($payout->load('property:id,name,owner_id')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thanh toán.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('PayoutController@show failed', [
                'payout_id' => $payout->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin thanh toán.',
            ], 500);
        }
    }

    /**
     * Update the specified payout
     */
    public function update(Request $request, Payout $payout): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'amount' => 'sometimes|numeric|min:0',
                'payout_date' => 'sometimes|date',
                'status' => 'sometimes|string|in:pending,completed,failed',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'amount.numeric' => 'Số tiền phải là số.',
                'amount.min' => 'Số tiền phải lớn hơn hoặc bằng 0.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: pending, completed, failed.',
            ]);

            $payout->update($validatedData);

            Log::info('Payout updated', [
                'payout_id' => $payout->id,
                'status' => $payout->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thanh toán thành công',
                'data' => new PayoutResource($payout->load('property:id,name,owner_id')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayoutController@update failed', [
                'payout_id' => $payout->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật thanh toán.',
            ], 500);
        }
    }

    /**
     * Remove the specified payout
     */
    public function destroy(Payout $payout): JsonResponse
    {
        try {
            $payoutId = $payout->id;

            $payout->delete();

            Log::info('Payout deleted', [
                'payout_id' => $payoutId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa thanh toán thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PayoutController@destroy failed', [
                'payout_id' => $payout->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa thanh toán.',
            ], 500);
        }
    }
}

