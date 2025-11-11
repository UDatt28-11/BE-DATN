<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Conversation;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class MessageController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 50;

    /**
     * Display a listing of messages for a conversation
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is participant
            if (!$conversation->participants->contains('id', $user->id) && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem tin nhắn này.',
                ], 403);
            }

            $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));

            $query = Message::query()
                ->where('conversation_id', $conversation->id)
                ->with('sender:id,full_name,email,avatar_url')
                ->visible() // Chỉ hiển thị messages chưa bị ẩn
                ->latest();

            // Paginate results
            $messages = $query->paginate($perPage);

            // Mark messages as read
            Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

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
            Log::error('MessageController@index failed', [
                'conversation_id' => $conversation->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách tin nhắn.',
            ], 500);
        }
    }

    /**
     * Store a newly created message
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is participant
            if (!$conversation->participants->contains('id', $user->id) && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền gửi tin nhắn trong cuộc hội thoại này.',
                ], 403);
            }

            $validatedData = $request->validate([
                'content' => 'required|string|max:5000',
            ], [
                'content.required' => 'Vui lòng nhập nội dung tin nhắn.',
                'content.max' => 'Nội dung tin nhắn không được vượt quá 5000 ký tự.',
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'content' => $validatedData['content'],
            ]);

            // Update conversation updated_at
            $conversation->touch();

            Log::info('Message created', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gửi tin nhắn thành công',
                'data' => new MessageResource($message->load('sender:id,full_name,email,avatar_url')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('MessageController@store failed', [
                'conversation_id' => $conversation->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi tin nhắn.',
            ], 500);
        }
    }

    /**
     * Display the specified message
     */
    public function show(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is participant in conversation
            $conversation = $message->conversation;
            if (!$conversation->participants->contains('id', $user->id) && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem tin nhắn này.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => new MessageResource($message->load('sender:id,full_name,email,avatar_url')),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tin nhắn.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('MessageController@show failed', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin tin nhắn.',
            ], 500);
        }
    }

    /**
     * Update the specified message
     */
    public function update(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is the sender
            if ($message->sender_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền chỉnh sửa tin nhắn này.',
                ], 403);
            }

            $validatedData = $request->validate([
                'content' => 'required|string|max:5000',
            ], [
                'content.required' => 'Vui lòng nhập nội dung tin nhắn.',
                'content.max' => 'Nội dung tin nhắn không được vượt quá 5000 ký tự.',
            ]);

            $message->update($validatedData);

            Log::info('Message updated', [
                'message_id' => $message->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tin nhắn thành công',
                'data' => new MessageResource($message->load('sender:id,full_name,email,avatar_url')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('MessageController@update failed', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật tin nhắn.',
            ], 500);
        }
    }

    /**
     * Remove the specified message
     */
    public function destroy(Message $message): JsonResponse
    {
        try {
            $user = request()->user();

            // Check if user is the sender or admin
            if ($message->sender_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa tin nhắn này.',
                ], 403);
            }

            $messageId = $message->id;

            $message->delete();

            Log::info('Message deleted', [
                'message_id' => $messageId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa tin nhắn thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('MessageController@destroy failed', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa tin nhắn.',
            ], 500);
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is participant in conversation
            $conversation = $message->conversation;
            if (!$conversation->participants->contains('id', $user->id) && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền đánh dấu tin nhắn này.',
                ], 403);
            }

            if ($message->sender_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không thể đánh dấu tin nhắn của chính mình là đã đọc.',
                ], 422);
            }

            $message->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Đánh dấu tin nhắn đã đọc thành công',
                'data' => new MessageResource($message->load('sender:id,full_name,email,avatar_url')),
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController@markAsRead failed', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đánh dấu tin nhắn.',
            ], 500);
        }
    }

    /**
     * Hide message (admin only)
     */
    public function hide(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            // Only admin can hide messages
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền ẩn tin nhắn này.',
                ], 403);
            }

            $message->update(['is_hidden' => true]);

            Log::info('Message hidden', [
                'message_id' => $message->id,
                'admin_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ẩn tin nhắn thành công',
                'data' => new MessageResource($message->load('sender:id,full_name,email,avatar_url')),
            ]);
        } catch (Exception $e) {
            Log::error('MessageController@hide failed', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi ẩn tin nhắn.',
            ], 500);
        }
    }

    /**
     * Unhide message (admin only)
     */
    public function unhide(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            // Only admin can unhide messages
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền bỏ ẩn tin nhắn này.',
                ], 403);
            }

            $message->update(['is_hidden' => false]);

            Log::info('Message unhidden', [
                'message_id' => $message->id,
                'admin_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bỏ ẩn tin nhắn thành công',
                'data' => new MessageResource($message->load('sender:id,full_name,email,avatar_url')),
            ]);
        } catch (Exception $e) {
            Log::error('MessageController@unhide failed', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi bỏ ẩn tin nhắn.',
            ], 500);
        }
    }
}

