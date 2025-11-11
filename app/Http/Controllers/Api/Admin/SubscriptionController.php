<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubscriptionController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'status' => 'sometimes|string|in:active,cancelled,expired',
                'plan_name' => 'sometimes|string|max:255',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, cancelled, expired.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Subscription::query()->with('property:id,name');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by plan_name
            if ($request->has('plan_name')) {
                $query->where('plan_name', $request->plan_name);
            }

            // Search by plan_name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('plan_name', 'like', '%' . $request->search . '%');
            }

            // Sort by latest
            $query->latest();

            // Paginate results
            $subscriptions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => SubscriptionResource::collection($subscriptions),
                'meta' => [
                    'pagination' => [
                        'current_page' => $subscriptions->currentPage(),
                        'per_page' => $subscriptions->perPage(),
                        'total' => $subscriptions->total(),
                        'last_page' => $subscriptions->lastPage(),
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
            Log::error('SubscriptionController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách đăng ký.',
            ], 500);
        }
    }

    /**
     * Store a newly created subscription
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'required|integer|exists:properties,id',
                'plan_name' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'status' => 'sometimes|string|in:active,cancelled,expired',
            ], [
                'property_id.required' => 'Vui lòng chọn property.',
                'property_id.exists' => 'Property không tồn tại.',
                'plan_name.required' => 'Vui lòng nhập tên gói.',
                'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
                'end_date.required' => 'Vui lòng chọn ngày kết thúc.',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, cancelled, expired.',
            ]);

            if (!isset($validatedData['status'])) {
                $validatedData['status'] = 'active';
            }

            $subscription = Subscription::create($validatedData);

            Log::info('Subscription created', [
                'subscription_id' => $subscription->id,
                'property_id' => $subscription->property_id,
                'plan_name' => $subscription->plan_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo đăng ký thành công',
                'data' => new SubscriptionResource($subscription->load('property:id,name')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('SubscriptionController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đăng ký.',
            ], 500);
        }
    }

    /**
     * Display the specified subscription
     */
    public function show(Subscription $subscription): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new SubscriptionResource($subscription->load('property:id,name')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đăng ký.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('SubscriptionController@show failed', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin đăng ký.',
            ], 500);
        }
    }

    /**
     * Update the specified subscription
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'plan_name' => 'sometimes|string|max:255',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'status' => 'sometimes|string|in:active,cancelled,expired',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
                'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, cancelled, expired.',
            ]);

            $subscription->update($validatedData);

            Log::info('Subscription updated', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đăng ký thành công',
                'data' => new SubscriptionResource($subscription->load('property:id,name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('SubscriptionController@update failed', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật đăng ký.',
            ], 500);
        }
    }

    /**
     * Remove the specified subscription
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        try {
            $subscriptionId = $subscription->id;

            $subscription->delete();

            Log::info('Subscription deleted', [
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa đăng ký thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('SubscriptionController@destroy failed', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa đăng ký.',
            ], 500);
        }
    }
}

