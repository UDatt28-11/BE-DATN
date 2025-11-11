<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of services
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'property_id' => 'sometimes|integer|exists:properties,id',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = Service::query()->with('property:id,name');

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
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
}

