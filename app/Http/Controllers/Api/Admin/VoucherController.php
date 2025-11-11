<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Http\Resources\VoucherResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class VoucherController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of vouchers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'is_active' => 'sometimes|boolean',
                'discount_type' => 'sometimes|string|max:50',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Voucher::query()->with('property:id,name');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by is_active
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by discount_type
            if ($request->has('discount_type')) {
                $query->where('discount_type', $request->discount_type);
            }

            // Search by code or name
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('code', 'like', '%' . $request->search . '%');
                });
            }

            // Sort by latest
            $query->latest();

            // Paginate results
            $vouchers = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => VoucherResource::collection($vouchers),
                'meta' => [
                    'pagination' => [
                        'current_page' => $vouchers->currentPage(),
                        'per_page' => $vouchers->perPage(),
                        'total' => $vouchers->total(),
                        'last_page' => $vouchers->lastPage(),
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
            Log::error('VoucherController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách voucher.',
            ], 500);
        }
    }

    /**
     * Store a newly created voucher
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'required|integer|exists:properties,id',
                'code' => 'required|string|max:50|unique:vouchers,code',
                'discount_type' => 'required|string|max:50',
                'discount_value' => 'required|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'is_active' => 'sometimes|boolean',
            ], [
                'property_id.required' => 'Vui lòng chọn property.',
                'property_id.exists' => 'Property không tồn tại.',
                'code.required' => 'Vui lòng nhập mã voucher.',
                'code.unique' => 'Mã voucher đã tồn tại.',
                'discount_type.required' => 'Vui lòng chọn loại giảm giá.',
                'discount_value.required' => 'Vui lòng nhập giá trị giảm giá.',
                'discount_value.numeric' => 'Giá trị giảm giá phải là số.',
                'discount_value.min' => 'Giá trị giảm giá phải lớn hơn hoặc bằng 0.',
                'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
                'end_date.required' => 'Vui lòng chọn ngày kết thúc.',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            ]);

            if (!isset($validatedData['is_active'])) {
                $validatedData['is_active'] = true;
            }

            $voucher = Voucher::create($validatedData);

            Log::info('Voucher created', [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
                'property_id' => $voucher->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo voucher thành công',
                'data' => new VoucherResource($voucher->load('property:id,name')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('VoucherController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo voucher.',
            ], 500);
        }
    }

    /**
     * Display the specified voucher
     */
    public function show(Voucher $voucher): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new VoucherResource($voucher->load('property:id,name')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy voucher.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('VoucherController@show failed', [
                'voucher_id' => $voucher->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin voucher.',
            ], 500);
        }
    }

    /**
     * Update the specified voucher
     */
    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'code' => 'sometimes|string|max:50|unique:vouchers,code,' . $voucher->id,
                'discount_type' => 'sometimes|string|max:50',
                'discount_value' => 'sometimes|numeric|min:0',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'is_active' => 'sometimes|boolean',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'code.unique' => 'Mã voucher đã tồn tại.',
                'discount_value.numeric' => 'Giá trị giảm giá phải là số.',
                'discount_value.min' => 'Giá trị giảm giá phải lớn hơn hoặc bằng 0.',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            ]);

            $voucher->update($validatedData);

            Log::info('Voucher updated', [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật voucher thành công',
                'data' => new VoucherResource($voucher->load('property:id,name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('VoucherController@update failed', [
                'voucher_id' => $voucher->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật voucher.',
            ], 500);
        }
    }

    /**
     * Remove the specified voucher
     */
    public function destroy(Voucher $voucher): JsonResponse
    {
        try {
            $voucherId = $voucher->id;
            $voucherCode = $voucher->code;

            $voucher->delete();

            Log::info('Voucher deleted', [
                'voucher_id' => $voucherId,
                'code' => $voucherCode,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa voucher thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('VoucherController@destroy failed', [
                'voucher_id' => $voucher->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa voucher.',
            ], 500);
        }
    }

    /**
     * Validate voucher code
     */
    public function validateVoucher(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string|max:50',
                'property_id' => 'sometimes|integer|exists:properties,id',
            ], [
                'code.required' => 'Vui lòng nhập mã voucher.',
                'property_id.exists' => 'Property không tồn tại.',
            ]);

            $voucher = Voucher::where('code', $validatedData['code'])
                ->where('is_active', true)
                ->when(isset($validatedData['property_id']), function ($query) use ($validatedData) {
                    return $query->where('property_id', $validatedData['property_id']);
                })
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher không hợp lệ hoặc đã hết hạn.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mã voucher hợp lệ',
                'data' => new VoucherResource($voucher->load('property:id,name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('VoucherController@validateVoucher failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi kiểm tra voucher.',
            ], 500);
        }
    }
}

