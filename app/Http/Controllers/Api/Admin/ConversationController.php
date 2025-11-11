<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Http\Resources\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of conversations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'sometimes|integer|exists:users,id',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'user_id.exists' => 'User không tồn tại.',
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $user = $request->user();

            $query = Conversation::query()
                ->whereHas('participants', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->with(['participants:id,full_name,email,avatar_url', 'messages' => function ($q) {
                    $q->visible()->latest()->limit(1);
                }]);

            // Filter by user_id (for admin)
            if ($request->has('user_id') && $user->isAdmin()) {
                $query->whereHas('participants', function ($q) use ($request) {
                    $q->where('user_id', $request->user_id);
                });
            }

            // Sort by latest message
            $query->latest('updated_at');

            // Paginate results
            $conversations = $query->paginate($perPage);

            // Add unread count for each conversation
            $conversations->getCollection()->transform(function ($conversation) use ($user) {
                $conversation->unread_count = $conversation->getUnreadCount($user->id);
                return $conversation;
            });

            return response()->json([
                'success' => true,
                'data' => ConversationResource::collection($conversations),
                'meta' => [
                    'pagination' => [
                        'current_page' => $conversations->currentPage(),
                        'per_page' => $conversations->perPage(),
                        'total' => $conversations->total(),
                        'last_page' => $conversations->lastPage(),
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
            Log::error('ConversationController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách cuộc hội thoại.',
            ], 500);
        }
    }

    /**
     * Store a newly created conversation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'participant_ids' => 'required|array|min:1',
                'participant_ids.*' => 'required|integer|exists:users,id',
            ], [
                'participant_ids.required' => 'Vui lòng chọn người tham gia.',
                'participant_ids.array' => 'Danh sách người tham gia không hợp lệ.',
                'participant_ids.min' => 'Phải có ít nhất 1 người tham gia.',
                'participant_ids.*.exists' => 'Một trong các người tham gia không tồn tại.',
            ]);

            $user = $request->user();
            $participantIds = array_unique(array_merge($validatedData['participant_ids'], [$user->id]));

            if (count($participantIds) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuộc hội thoại phải có ít nhất 2 người tham gia.',
                ], 422);
            }

            DB::beginTransaction();

            // Check if conversation already exists
            $existingConversation = Conversation::whereHas('participants', function ($q) use ($participantIds) {
                $q->whereIn('user_id', $participantIds);
            }, '=', count($participantIds))
                ->withCount('participants')
                ->having('participants_count', '=', count($participantIds))
                ->first();

            if ($existingConversation) {
                DB::rollBack();
                return response()->json([
                    'success' => true,
                    'message' => 'Cuộc hội thoại đã tồn tại',
                    'data' => new ConversationResource($existingConversation->load('participants:id,full_name,email,avatar_url')),
                ], 200);
            }

            // Create new conversation
            $conversation = Conversation::create([]);

            // Attach participants
            $conversation->participants()->attach($participantIds);

            DB::commit();

            Log::info('Conversation created', [
                'conversation_id' => $conversation->id,
                'participant_ids' => $participantIds,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo cuộc hội thoại thành công',
                'data' => new ConversationResource($conversation->load('participants:id,full_name,email,avatar_url')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ConversationController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo cuộc hội thoại.',
            ], 500);
        }
    }

    /**
     * Display the specified conversation
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is participant
            if (!$conversation->participants->contains('id', $user->id) && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem cuộc hội thoại này.',
                ], 403);
            }

            $conversation->load([
                'participants:id,full_name,email,avatar_url',
                'messages' => function ($q) {
                    $q->visible()->latest()->limit(1)->with('sender:id,full_name,email,avatar_url');
                }
            ]);

            $conversation->unread_count = $conversation->getUnreadCount($user->id);

            return response()->json([
                'success' => true,
                'data' => new ConversationResource($conversation),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cuộc hội thoại.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ConversationController@show failed', [
                'conversation_id' => $conversation->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin cuộc hội thoại.',
            ], 500);
        }
    }

    /**
     * Remove the specified conversation
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is participant or admin
            if (!$conversation->participants->contains('id', $user->id) && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa cuộc hội thoại này.',
                ], 403);
            }

            $conversationId = $conversation->id;

            // Delete messages first
            $conversation->messages()->delete();

            // Delete conversation
            $conversation->delete();

            Log::info('Conversation deleted', [
                'conversation_id' => $conversationId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa cuộc hội thoại thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ConversationController@destroy failed', [
                'conversation_id' => $conversation->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa cuộc hội thoại.',
            ], 500);
        }
    }
}

