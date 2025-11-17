<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\UserVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoucherController extends Controller
{
    /**
     * Danh sách voucher của user hiện tại (kho mã giảm giá)
     *
     * Query params:
     * - status: all|unused|used (mặc định: unused)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'status' => 'sometimes|string|in:all,unused,used',
            ]);

            $status = $request->get('status', 'unused');

            $query = UserVoucher::query()
                ->where('user_id', $user->id)
                ->with(['voucher.property:id,name'])
                ->orderByDesc('claimed_at');

            if ($status === 'unused') {
                $query->unused();
            } elseif ($status === 'used') {
                $query->used();
            }

            $perPage = (int) $request->get('per_page', 15);
            $userVouchers = $query->paginate($perPage);

            $data = $userVouchers->map(function (UserVoucher $userVoucher) {
                return [
                    'id' => $userVoucher->id,
                    'voucher' => new VoucherResource($userVoucher->voucher),
                    'claimed_at' => $userVoucher->claimed_at,
                    'used_at' => $userVoucher->used_at,
                    'booking_order_id' => $userVoucher->booking_order_id,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'pagination' => [
                        'current_page' => $userVouchers->currentPage(),
                        'per_page' => $userVouchers->perPage(),
                        'total' => $userVouchers->total(),
                        'last_page' => $userVouchers->lastPage(),
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
            Log::error('User\\VoucherController@index failed', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách voucher của bạn.',
            ], 500);
        }
    }
}


