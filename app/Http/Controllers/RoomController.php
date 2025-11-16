<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    /**
     * Display a listing of rooms
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Room::with(['property', 'roomType']);

            // Filter by property_id
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by room_type_id
            if ($request->has('room_type_id')) {
                $query->where('room_type_id', $request->room_type_id);
            }

            // Search by name
            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Filter by price range
            if ($request->has('min_price')) {
                $query->where('price_per_night', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price_per_night', '<=', $request->max_price);
            }

            // Only show available rooms by default (unless specified)
            if (!$request->has('status') && !$request->has('include_all')) {
                $query->where('status', 'available');
            }

            // Sort
            $sort = $request->get('sort', 'name');
            $direction = $request->get('direction', 'asc');
            if ($sort === 'price') {
                $query->orderBy('price_per_night', $direction);
            } elseif ($sort === 'name') {
                $query->orderBy('name', $direction);
            } else {
                $query->orderBy('name', 'asc');
            }

            // Pagination
            $perPage = $request->get('per_page', 100);
            $rooms = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $rooms->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $rooms->currentPage(),
                        'per_page' => $rooms->perPage(),
                        'total' => $rooms->total(),
                        'last_page' => $rooms->lastPage(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách phòng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified room
     */
    public function show(string $id): JsonResponse
    {
        try {
            $room = Room::with(['property', 'roomType'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $room
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải thông tin phòng: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Store a newly created room
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_adults' => 'required|integer|min:1',
            'max_children' => 'required|integer|min:0',
            'price_per_night' => 'required|numeric|min:0',
            'status' => 'nullable|in:available,maintenance,occupied',
        ]);

        try {
            $room = Room::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Phòng đã được tạo thành công',
                'data' => $room->load(['property', 'roomType'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo phòng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified room
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'room_type_id' => 'sometimes|exists:room_types,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'max_adults' => 'sometimes|integer|min:1',
            'max_children' => 'sometimes|integer|min:0',
            'price_per_night' => 'sometimes|numeric|min:0',
            'status' => 'nullable|in:available,maintenance,occupied',
        ]);

        try {
            $room = Room::findOrFail($id);
            $room->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Phòng đã được cập nhật',
                'data' => $room->load(['property', 'roomType'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật phòng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified room
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $room = Room::findOrFail($id);
            $room->delete();

            return response()->json([
                'success' => true,
                'message' => 'Phòng đã được xóa'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa phòng: ' . $e->getMessage()
            ], 500);
        }
    }
}





