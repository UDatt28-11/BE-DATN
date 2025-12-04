<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\BookingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                $query->unused()
                    ->whereHas('voucher', function ($q) {
                        $q->active(); // Only show active vouchers that haven't expired
                    });
            } elseif ($status === 'used') {
                $query->used();
            }

            $perPage = (int) $request->get('per_page', 15);
            $userVouchers = $query->paginate($perPage);

            $data = $userVouchers->map(function (UserVoucher $userVoucher) {
                $voucher = $userVoucher->voucher;
                return [
                    'id' => $userVoucher->id,
                    'voucher_id' => $voucher->id,
                    'code' => $voucher->code,
                    'name' => $voucher->name ?? $voucher->code,
                    'description' => $voucher->description,
                    'discount_type' => $voucher->discount_type,
                    'discount_value' => (float) $voucher->discount_value,
                    'discount_text' => $voucher->discount_text,
                    'min_order_amount' => (float) ($voucher->min_order_amount ?? 0),
                    'max_discount_amount' => $voucher->max_discount_amount ? (float) $voucher->max_discount_amount : null,
                    'start_date' => $voucher->start_date?->toISOString(),
                    'end_date' => $voucher->end_date?->toISOString(),
                    'is_active' => $voucher->is_active,
                    'can_use' => $voucher->canBeUsed() && !$userVoucher->used_at,
                    'property' => $voucher->property ? [
                        'id' => $voucher->property->id,
                        'name' => $voucher->property->name,
                    ] : null,
                    'claimed_at' => $userVoucher->claimed_at?->toISOString(),
                    'used_at' => $userVoucher->used_at?->toISOString(),
                    'applied_discount_amount' => $userVoucher->applied_discount_amount ? (float) $userVoucher->applied_discount_amount : null,
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

    /**
     * Danh sách voucher công khai có thể claim (chưa claim)
     */
    public function available(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Lấy voucher công khai, đang active, còn hạn, chưa claim
            $vouchers = Voucher::query()
                ->public()
                ->available()
                ->with('property:id,name')
                ->whereDoesntHave('users', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orderByDesc('created_at')
                ->paginate((int) $request->get('per_page', 15));

            $data = $vouchers->map(function (Voucher $voucher) {
                return [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'name' => $voucher->name ?? $voucher->code,
                    'description' => $voucher->description,
                    'discount_type' => $voucher->discount_type,
                    'discount_value' => (float) $voucher->discount_value,
                    'discount_text' => $voucher->discount_text,
                    'min_order_amount' => (float) ($voucher->min_order_amount ?? 0),
                    'max_discount_amount' => $voucher->max_discount_amount ? (float) $voucher->max_discount_amount : null,
                    'start_date' => $voucher->start_date?->toISOString(),
                    'end_date' => $voucher->end_date?->toISOString(),
                    'property' => $voucher->property ? [
                        'id' => $voucher->property->id,
                        'name' => $voucher->property->name,
                    ] : null,
                    'remaining_uses' => $voucher->usage_limit 
                        ? max(0, $voucher->usage_limit - $voucher->usage_count) 
                        : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'pagination' => [
                        'current_page' => $vouchers->currentPage(),
                        'per_page' => $vouchers->perPage(),
                        'total' => $vouchers->total(),
                        'last_page' => $vouchers->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('User\\VoucherController@available failed', [
                'user_id' => $request->user()->id ?? null,
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
     * Claim voucher bằng mã
     */
    public function claim(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'code' => 'required|string|max:50',
            ], [
                'code.required' => 'Vui lòng nhập mã voucher.',
            ]);

            $voucher = Voucher::where('code', strtoupper(trim($validated['code'])))->first();

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher không tồn tại.',
                ], 404);
            }

            // Kiểm tra voucher có active không
            if (!$voucher->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher đã bị vô hiệu hóa.',
                ], 400);
            }

            // Kiểm tra thời hạn
            if ($voucher->start_date && $voucher->start_date->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher chưa có hiệu lực. Bắt đầu từ ' . $voucher->start_date->format('d/m/Y'),
                ], 400);
            }

            if ($voucher->end_date && $voucher->end_date->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher đã hết hạn.',
                ], 400);
            }

            // Kiểm tra giới hạn sử dụng
            if ($voucher->usage_limit !== null && $voucher->usage_count >= $voucher->usage_limit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher đã hết lượt sử dụng.',
                ], 400);
            }

            // Kiểm tra đã claim chưa
            $existingClaim = UserVoucher::where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingClaim) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã lưu mã voucher này rồi.',
                ], 400);
            }

            // Claim voucher
            $userVoucher = UserVoucher::create([
                'user_id' => $user->id,
                'voucher_id' => $voucher->id,
                'claimed_at' => now(),
            ]);

            Log::info('User claimed voucher', [
                'user_id' => $user->id,
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lưu mã giảm giá thành công!',
                'data' => [
                    'id' => $userVoucher->id,
                    'voucher_id' => $voucher->id,
                    'code' => $voucher->code,
                    'name' => $voucher->name ?? $voucher->code,
                    'discount_text' => $voucher->discount_text,
                    'claimed_at' => $userVoucher->claimed_at->toISOString(),
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('User\\VoucherController@claim failed', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lưu mã voucher.',
            ], 500);
        }
    }

    /**
     * Áp dụng voucher cho đơn đặt phòng
     */
    public function apply(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'voucher_id' => 'required|integer|exists:vouchers,id',
                'order_amount' => 'required|numeric|min:0',
                'booking_order_id' => 'sometimes|integer|exists:booking_orders,id',
            ], [
                'voucher_id.required' => 'Vui lòng chọn voucher.',
                'voucher_id.exists' => 'Voucher không tồn tại.',
                'order_amount.required' => 'Vui lòng nhập số tiền đơn hàng.',
            ]);

            $voucher = Voucher::find($validated['voucher_id']);

            // Kiểm tra user đã claim voucher này chưa
            $userVoucher = UserVoucher::where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->first();

            if (!$userVoucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa lưu mã voucher này hoặc đã sử dụng.',
                ], 400);
            }

            // Kiểm tra voucher có thể sử dụng không
            if (!$voucher->canBeUsedByUser($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã voucher không thể sử dụng. Có thể đã hết hạn hoặc bạn đã sử dụng hết số lần cho phép.',
                ], 400);
            }

            // Kiểm tra đơn tối thiểu
            if ($validated['order_amount'] < $voucher->min_order_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng tối thiểu ' . number_format($voucher->min_order_amount, 0, ',', '.') . 'đ để sử dụng voucher này.',
                ], 400);
            }

            // Tính số tiền giảm
            $discountAmount = $voucher->calculateDiscount($validated['order_amount']);

            // Nếu có booking_order_id, cập nhật ngay
            if (isset($validated['booking_order_id'])) {
                $booking = BookingOrder::where('id', $validated['booking_order_id'])
                    ->where('guest_id', $user->id)
                    ->first();

                if (!$booking) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy đơn đặt phòng.',
                    ], 404);
                }

                // Kiểm tra booking chưa hoàn tất
                if (in_array($booking->status, ['completed', 'cancelled', 'checked_out'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể áp dụng voucher cho đơn đã hoàn tất hoặc đã hủy.',
                    ], 400);
                }

                DB::transaction(function () use ($userVoucher, $voucher, $booking, $discountAmount) {
                    // Đánh dấu voucher đã sử dụng
                    $userVoucher->update([
                        'used_at' => now(),
                        'booking_order_id' => $booking->id,
                        'applied_discount_amount' => $discountAmount,
                    ]);

                    // Tăng usage_count của voucher
                    $voucher->increment('usage_count');

                    // Cập nhật total_amount của booking
                    $newTotal = max(0, $booking->total_amount - $discountAmount);
                    $booking->update([
                        'total_amount' => $newTotal,
                    ]);
                });

                Log::info('User applied voucher to booking', [
                    'user_id' => $user->id,
                    'voucher_id' => $voucher->id,
                    'booking_order_id' => $booking->id,
                    'discount_amount' => $discountAmount,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Áp dụng mã giảm giá thành công!',
                'data' => [
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->code,
                    'discount_type' => $voucher->discount_type,
                    'discount_value' => (float) $voucher->discount_value,
                    'discount_amount' => round($discountAmount, 0),
                    'original_amount' => (float) $validated['order_amount'],
                    'final_amount' => round($validated['order_amount'] - $discountAmount, 0),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('User\\VoucherController@apply failed', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi áp dụng voucher.',
            ], 500);
        }
    }

    /**
     * Xem chi tiết voucher trong kho
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $userVoucher = UserVoucher::with(['voucher.property:id,name'])
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$userVoucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy voucher.',
                ], 404);
            }

            $voucher = $userVoucher->voucher;

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $userVoucher->id,
                    'voucher_id' => $voucher->id,
                    'code' => $voucher->code,
                    'name' => $voucher->name ?? $voucher->code,
                    'description' => $voucher->description,
                    'discount_type' => $voucher->discount_type,
                    'discount_value' => (float) $voucher->discount_value,
                    'discount_text' => $voucher->discount_text,
                    'min_order_amount' => (float) ($voucher->min_order_amount ?? 0),
                    'max_discount_amount' => $voucher->max_discount_amount ? (float) $voucher->max_discount_amount : null,
                    'start_date' => $voucher->start_date?->toISOString(),
                    'end_date' => $voucher->end_date?->toISOString(),
                    'is_active' => $voucher->is_active,
                    'can_use' => $voucher->canBeUsed() && !$userVoucher->used_at,
                    'property' => $voucher->property ? [
                        'id' => $voucher->property->id,
                        'name' => $voucher->property->name,
                    ] : null,
                    'claimed_at' => $userVoucher->claimed_at?->toISOString(),
                    'used_at' => $userVoucher->used_at?->toISOString(),
                    'applied_discount_amount' => $userVoucher->applied_discount_amount ? (float) $userVoucher->applied_discount_amount : null,
                    'booking_order_id' => $userVoucher->booking_order_id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('User\\VoucherController@show failed', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra.',
            ], 500);
        }
    }

    /**
     * Đếm số voucher chưa sử dụng
     */
    public function counts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $unused = UserVoucher::where('user_id', $user->id)
                ->unused()
                ->whereHas('voucher', function ($q) {
                    $q->active();
                })
                ->count();

            $used = UserVoucher::where('user_id', $user->id)
                ->used()
                ->count();

            $expired = UserVoucher::where('user_id', $user->id)
                ->unused()
                ->whereHas('voucher', function ($q) {
                    $q->where(function ($query) {
                        $query->where('is_active', false)
                            ->orWhere('end_date', '<', now());
                    });
                })
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unused' => $unused,
                    'used' => $used,
                    'expired' => $expired,
                    'total' => $unused + $used + $expired,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('User\\VoucherController@counts failed', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra.',
            ], 500);
        }
    }
}
