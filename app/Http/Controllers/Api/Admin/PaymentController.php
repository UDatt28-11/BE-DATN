<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of payments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'invoice_id' => 'sometimes|integer|exists:invoices,id',
                'status' => 'sometimes|string|in:success,pending,failed',
                'payment_method' => 'sometimes|string|max:50',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'invoice_id.exists' => 'Invoice không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: success, pending, failed.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Payment::query()->with('invoice:id,total_amount,status');

            // Filter by invoice_id
            if ($request->has('invoice_id')) {
                $query->where('invoice_id', $request->invoice_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment_method
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Search by transaction_id
            if ($request->has('search') && !empty($request->search)) {
                $query->where('transaction_id', 'like', '%' . $request->search . '%');
            }

            // Sort by latest
            $query->latest('paid_at')->latest('created_at');

            // Paginate results
            $payments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => PaymentResource::collection($payments),
                'meta' => [
                    'pagination' => [
                        'current_page' => $payments->currentPage(),
                        'per_page' => $payments->perPage(),
                        'total' => $payments->total(),
                        'last_page' => $payments->lastPage(),
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
            Log::error('PaymentController@index failed', [
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
     * Store a newly created payment
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|string|max:50',
                'transaction_id' => 'nullable|string|max:255|unique:payments,transaction_id',
                'status' => 'required|string|in:success,pending,failed',
                'paid_at' => 'nullable|date',
            ], [
                'invoice_id.required' => 'Vui lòng chọn invoice.',
                'invoice_id.exists' => 'Invoice không tồn tại.',
                'amount.required' => 'Vui lòng nhập số tiền.',
                'amount.numeric' => 'Số tiền phải là số.',
                'amount.min' => 'Số tiền phải lớn hơn hoặc bằng 0.',
                'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
                'transaction_id.unique' => 'Mã giao dịch đã tồn tại.',
                'status.required' => 'Vui lòng chọn trạng thái.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: success, pending, failed.',
            ]);

            if (!isset($validatedData['paid_at'])) {
                $validatedData['paid_at'] = now();
            }

            $payment = Payment::create($validatedData);

            Log::info('Payment created', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
                'status' => $payment->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo thanh toán thành công',
                'data' => new PaymentResource($payment->load('invoice:id,total_amount,status')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PaymentController@store failed', [
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
     * Display the specified payment
     */
    public function show(Payment $payment): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new PaymentResource($payment->load('invoice:id,total_amount,status')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thanh toán.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('PaymentController@show failed', [
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin thanh toán.',
            ], 500);
        }
    }

    /**
     * Update the specified payment
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'invoice_id' => 'sometimes|integer|exists:invoices,id',
                'amount' => 'sometimes|numeric|min:0',
                'payment_method' => 'sometimes|string|max:50',
                'transaction_id' => 'sometimes|string|max:255|unique:payments,transaction_id,' . $payment->id,
                'status' => 'sometimes|string|in:success,pending,failed',
                'paid_at' => 'nullable|date',
            ], [
                'invoice_id.exists' => 'Invoice không tồn tại.',
                'amount.numeric' => 'Số tiền phải là số.',
                'amount.min' => 'Số tiền phải lớn hơn hoặc bằng 0.',
                'transaction_id.unique' => 'Mã giao dịch đã tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: success, pending, failed.',
            ]);

            $payment->update($validatedData);

            Log::info('Payment updated', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thanh toán thành công',
                'data' => new PaymentResource($payment->load('invoice:id,total_amount,status')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PaymentController@update failed', [
                'payment_id' => $payment->id,
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
     * Remove the specified payment
     */
    public function destroy(Payment $payment): JsonResponse
    {
        try {
            $paymentId = $payment->id;

            $payment->delete();

            Log::info('Payment deleted', [
                'payment_id' => $paymentId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa thanh toán thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('PaymentController@destroy failed', [
                'payment_id' => $payment->id,
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

