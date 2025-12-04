<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Http\Resources\EmailLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EmailLogController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of email logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'template_id' => 'sometimes|integer|exists:email_templates,id',
                'recipient_email' => 'sometimes|string|email',
                'status' => 'sometimes|string|in:pending,sent,failed',
                'related_type' => 'sometimes|string',
                'related_id' => 'sometimes|integer',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'sort_by' => 'sometimes|string|in:id,recipient_email,status,sent_at,created_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = EmailLog::query()->with('template:id,name');

            // Filter by template_id
            if ($request->has('template_id')) {
                $query->where('template_id', $request->template_id);
            }

            // Filter by recipient_email
            if ($request->has('recipient_email')) {
                $query->where('recipient_email', $request->recipient_email);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by related
            if ($request->has('related_type') && $request->has('related_id')) {
                $query->where('related_type', $request->related_type)
                    ->where('related_id', $request->related_id);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $emailLogs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => EmailLogResource::collection($emailLogs),
                'meta' => [
                    'pagination' => [
                        'current_page' => $emailLogs->currentPage(),
                        'per_page' => $emailLogs->perPage(),
                        'total' => $emailLogs->total(),
                        'last_page' => $emailLogs->lastPage(),
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
            Log::error('EmailLogController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách log email.',
            ], 500);
        }
    }

    /**
     * Display the specified email log
     */
    public function show(EmailLog $emailLog): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new EmailLogResource($emailLog->load('template:id,name')),
            ]);
        } catch (\Exception $e) {
            Log::error('EmailLogController@show failed', [
                'email_log_id' => $emailLog->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin log email.',
            ], 500);
        }
    }

    /**
     * Get email statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
            ]);

            $query = EmailLog::query();

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $total = $query->count();
            $sent = (clone $query)->where('status', 'sent')->count();
            $failed = (clone $query)->where('status', 'failed')->count();
            $pending = (clone $query)->where('status', 'pending')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'sent' => $sent,
                    'failed' => $failed,
                    'pending' => $pending,
                    'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('EmailLogController@statistics failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thống kê email.',
            ], 500);
        }
    }
}

