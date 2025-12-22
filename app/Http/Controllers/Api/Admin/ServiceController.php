<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of services
     * Public endpoint - không cần authorization
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Không check authorization cho public endpoint
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'room_type_id' => 'sometimes|integer|exists:room_types,id',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'room_type_id.exists' => 'Room type không tồn tại.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Service::query()->with(['property' => function ($query) {
                $query->select('id', 'name');
            }]);

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }
            
            // Filter by room_type_id - chỉ lấy services thuộc room type này
            // Nếu có room_type_id, chỉ lấy services được gán cho room type đó qua bảng room_type_services
            if ($request->has('room_type_id') && $request->room_type_id) {
                // Sử dụng whereHas với relationship roomTypes
                $query->whereHas('roomTypes', function ($q) use ($request) {
                    $q->where('room_types.id', $request->room_type_id);
                });
                
                // Debug: Kiểm tra xem có services nào được gán cho room_type_id này không
                $servicesCount = \DB::table('room_type_services')
                    ->where('room_type_id', $request->room_type_id)
                    ->count();
                
                Log::info('ServiceController@index: Checking room_type_services', [
                    'room_type_id' => $request->room_type_id,
                    'services_in_pivot_table' => $servicesCount,
                ]);
            }
            
            // Debug log để kiểm tra query
            if ($request->has('room_type_id')) {
                $totalBeforeFilter = Service::query();
                if ($request->has('property_id')) {
                    $totalBeforeFilter->where('property_id', $request->property_id);
                }
                
                Log::info('ServiceController@index: Filtering by room_type_id', [
                    'room_type_id' => $request->room_type_id,
                    'property_id' => $request->property_id,
                    'total_services_before_room_type_filter' => $totalBeforeFilter->count(),
                    'total_services_after_room_type_filter' => (clone $query)->count(),
                ]);
            }

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sort by latest
            $query->latest();

            // Paginate results
            $services = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ServiceResource::collection($services),
                'meta' => [
                    'pagination' => [
                        'current_page' => $services->currentPage(),
                        'per_page' => $services->perPage(),
                        'total' => $services->total(),
                        'last_page' => $services->lastPage(),
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
            Log::error('ServiceController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách dịch vụ.',
            ], 500);
        }
    }

    /**
     * Store a newly created service
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'required|integer|exists:properties,id',
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'unit' => 'required|string|max:50',
                'status' => 'sometimes|in:active,disabled',
            ], [
                'property_id.required' => 'Vui lòng chọn property.',
                'property_id.exists' => 'Property không tồn tại.',
                'name.required' => 'Vui lòng nhập tên dịch vụ.',
                'price.required' => 'Vui lòng nhập giá.',
                'price.numeric' => 'Giá phải là số.',
                'price.min' => 'Giá phải lớn hơn hoặc bằng 0.',
                'unit.required' => 'Vui lòng nhập đơn vị.',
            ]);

            $service = Service::create($validatedData);

            Log::info('Service created', [
                'service_id' => $service->id,
                'name' => $service->name,
                'property_id' => $service->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo dịch vụ thành công',
                'data' => new ServiceResource($service->load('property:id,name')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ServiceController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo dịch vụ.',
            ], 500);
        }
    }

    /**
     * Display the specified service
     */
    public function show(Service $service): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new ServiceResource($service->load('property:id,name')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy dịch vụ.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ServiceController@show failed', [
                'service_id' => $service->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin dịch vụ.',
            ], 500);
        }
    }

    /**
     * Update the specified service
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'name' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'unit' => 'sometimes|string|max:50',
                'status' => 'sometimes|in:active,disabled',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'price.numeric' => 'Giá phải là số.',
                'price.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            ]);

            $service->update($validatedData);

            Log::info('Service updated', [
                'service_id' => $service->id,
                'name' => $service->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật dịch vụ thành công',
                'data' => new ServiceResource($service->load('property:id,name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ServiceController@update failed', [
                'service_id' => $service->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật dịch vụ.',
            ], 500);
        }
    }

    /**
     * Remove the specified service
     */
    public function destroy(Service $service): JsonResponse
    {
        try {
            $serviceId = $service->id;
            $serviceName = $service->name;

            $service->delete();

            Log::info('Service deleted', [
                'service_id' => $serviceId,
                'name' => $serviceName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa dịch vụ thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ServiceController@destroy failed', [
                'service_id' => $service->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa dịch vụ.',
            ], 500);
        }
    }

    /**
     * Update service status
     */
    public function updateStatus(Request $request, Service $service): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'status' => 'required|in:active,disabled',
            ], [
                'status.required' => 'Vui lòng chọn trạng thái.',
                'status.in' => 'Trạng thái không hợp lệ.',
            ]);

            $service->update(['status' => $validatedData['status']]);

            Log::info('Service status updated', [
                'service_id' => $service->id,
                'name' => $service->name,
                'status' => $service->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái dịch vụ thành công',
                'data' => new ServiceResource($service->load('property:id,name')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ServiceController@updateStatus failed', [
                'service_id' => $service->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật trạng thái dịch vụ.',
            ], 500);
        }
    }
}

