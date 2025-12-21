<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use App\Models\Conversation;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    private ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Get or create AI conversation
     * 
     * @OA\Get(
     *     path="/api/chat/conversation",
     *     operationId="getAIConversation",
     *     tags={"AI Chat"},
     *     summary="Lấy hoặc tạo conversation với AI",
     *     description="Lấy conversation hiện có hoặc tạo mới cho user/guest",
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         description="Session ID cho guest users",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversation data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getConversation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sessionId = $request->get('session_id');
            $type = $request->get('type', 'ai'); // 'admin' or 'ai', default to 'ai'

            // Validate type parameter
            if (!in_array($type, ['admin', 'ai'])) {
                $type = 'ai';
            }

            // Generate session_id for guest if not provided
            if (!$user && !$sessionId) {
                $sessionId = Str::uuid()->toString();
            }

            $conversation = $this->chatService->getOrCreateAIConversation($user, $sessionId, $type);
            
            // Only load participants if user is logged in (guest conversations don't have participants)
            if ($user) {
                $conversation->load(['participants' => function ($q) {
                    $q->select('id', 'full_name', 'email', 'avatar_url');
                }]);
            }

            // Build response data safely
            $responseData = [
                'id' => $conversation->id,
                'created_at' => $conversation->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $conversation->updated_at?->format('Y-m-d H:i:s'),
            ];

            // Add optional fields if they exist
            if (Schema::hasColumn('conversations', 'type')) {
                $responseData['type'] = $conversation->type ?? 'user_to_ai';
            }
            if (Schema::hasColumn('conversations', 'session_id')) {
                $responseData['session_id'] = $conversation->session_id;
            }
            if (Schema::hasColumn('conversations', 'context_data')) {
                $responseData['context_data'] = $conversation->context_data;
            }

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'session_id' => $conversation->session_id ?? $sessionId, // Return session_id for guest
            ]);
        } catch (\Exception $e) {
            Log::error('ChatController@getConversation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy conversation: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Send message to AI
     * 
     * @OA\Post(
     *     path="/api/chat/send-message",
     *     operationId="sendAIMessage",
     *     tags={"AI Chat"},
     *     summary="Gửi tin nhắn đến AI",
     *     description="Gửi tin nhắn và nhận phản hồi từ AI",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="conversation_id", type="integer", example=1),
     *             @OA\Property(property="content", type="string", example="Xin chào, tôi muốn đặt phòng"),
     *             @OA\Property(property="session_id", type="string", example="uuid"),
     *             @OA\Property(property="context", type="object", example={"booking_id": 1, "room_id": 5})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages sent and received",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'content' => 'required|string|max:5000',
                'session_id' => 'nullable|string',
                'context' => 'nullable|array',
            ], [
                'conversation_id.required' => 'Conversation ID là bắt buộc.',
                'conversation_id.exists' => 'Conversation không tồn tại.',
                'content.required' => 'Vui lòng nhập nội dung tin nhắn.',
                'content.max' => 'Nội dung tin nhắn không được vượt quá 5000 ký tự.',
            ]);

            $conversation = Conversation::findOrFail($validated['conversation_id']);

            // Verify conversation belongs to user or session
            if ($user) {
                if (!$conversation->participants->contains('id', $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền gửi tin nhắn trong conversation này.',
                    ], 403);
                }
            } else {
                if ($conversation->session_id !== $validated['session_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session ID không khớp.',
                    ], 403);
                }
            }

            // Build context
            $context = $validated['context'] ?? [];
            if ($user) {
                $context['user_id'] = $user->id;
                $context['user_name'] = $user->full_name ?? $user->email;
            }

            // Send message and get AI response automatically (if AI mode)
            $result = $this->chatService->sendMessage(
                $conversation,
                $validated['content'],
                $user,
                $context
            );

            // Load sender with role for user message
            $userMessage = $result['user_message']->load('sender:id,full_name,email,avatar_url,role');

            $responseData = [
                'user_message' => new MessageResource($userMessage),
            ];

            // Include AI message if available (only for AI mode)
            if ($result['ai_message']) {
                $responseData['ai_message'] = new MessageResource($result['ai_message']);
            }

            // Determine response message based on conversation type
            $hasTypeColumn = \Illuminate\Support\Facades\Schema::hasColumn('conversations', 'type');
            $conversationType = $hasTypeColumn ? ($conversation->type ?? 'user_to_ai') : 'user_to_ai';
            $isAIMode = $conversationType === 'user_to_ai';
            
            $responseMessage = $isAIMode 
                ? 'Tin nhắn đã được gửi và AI đã trả lời.'
                : 'Tin nhắn đã được gửi. Admin sẽ trả lời bạn sớm nhất.';

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'data' => $responseData,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ChatController@sendMessage failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại sau.',
            ], 500);
        }
    }

    /**
     * Get conversation messages
     * 
     * @OA\Get(
     *     path="/api/chat/messages",
     *     operationId="getAIMessages",
     *     tags={"AI Chat"},
     *     summary="Lấy danh sách tin nhắn",
     *     description="Lấy lịch sử tin nhắn của conversation với AI",
     *     @OA\Parameter(
     *         name="conversation_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         @OA\Schema(type="integer", default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getMessages(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'session_id' => 'nullable|string',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $conversation = Conversation::findOrFail($validated['conversation_id']);

            // Verify conversation belongs to user or session
            if ($user) {
                if (!$conversation->participants->contains('id', $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem tin nhắn này.',
                    ], 403);
                }
            } else {
                if ($conversation->session_id !== $validated['session_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session ID không khớp.',
                    ], 403);
                }
            }

            $perPage = $validated['per_page'] ?? 50;
            $messages = $this->chatService->getMessages($conversation, $perPage);
            
            // Load sender with role for all messages
            $messages->load('sender:id,full_name,email,avatar_url,role');

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'meta' => [
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'last_page' => $messages->lastPage(),
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
            Log::error('ChatController@getMessages failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy tin nhắn.',
            ], 500);
        }
    }

    /**
     * Clear conversation history
     * 
     * @OA\Post(
     *     path="/api/chat/clear-history",
     *     operationId="clearAIChatHistory",
     *     tags={"AI Chat"},
     *     summary="Xóa lịch sử chat",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="conversation_id", type="integer", example=1),
     *             @OA\Property(property="session_id", type="string", example="uuid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="History cleared",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function clearHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'session_id' => 'nullable|string',
            ]);

            $conversation = Conversation::findOrFail($validated['conversation_id']);

            // Verify conversation belongs to user or session
            if ($user) {
                if (!$conversation->participants->contains('id', $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xóa lịch sử này.',
                    ], 403);
                }
            } else {
                if ($conversation->session_id !== $validated['session_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session ID không khớp.',
                    ], 403);
                }
            }

            $success = $this->chatService->clearHistory($conversation);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Xóa lịch sử thành công' : 'Xóa lịch sử thất bại',
            ]);
        } catch (\Exception $e) {
            Log::error('ChatController@clearHistory failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa lịch sử.',
            ], 500);
        }
    }
}

