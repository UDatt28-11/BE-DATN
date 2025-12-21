<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatService
{
    // No longer need AIService - admin will reply manually
    // private AIService $aiService;

    public function __construct()
    {
        // No longer need AIService
        // $this->aiService = $aiService;
    }

    /**
     * Get or create conversation for user
     * For logged-in users: create user_to_user conversation (admin will reply)
     * For guest users: create user_to_ai conversation (can still use AI if needed)
     *
     * @param User|null $user User object or null for guest
     * @param string|null $sessionId Session ID for guest users
     * @return Conversation
     */
    public function getOrCreateAIConversation(?User $user = null, ?string $sessionId = null): Conversation
    {
        try {
            if ($user) {
                // For logged-in users, find existing conversation (user_to_user type)
                // Check if 'type' column exists before using it
                $hasTypeColumn = Schema::hasColumn('conversations', 'type');
                
                if ($hasTypeColumn) {
                    $conversation = Conversation::where('type', 'user_to_user')
                        ->whereHas('participants', function ($q) use ($user) {
                            $q->where('user_id', $user->id);
                        })
                        ->first();
                } else {
                    // Fallback: find by participants only if type column doesn't exist
                    $conversation = Conversation::whereHas('participants', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })->first();
                }

                if ($conversation) {
                    return $conversation;
                }

                // Create new conversation for logged-in user (user_to_user - admin will reply)
                DB::beginTransaction();
                try {
                    $conversationData = [];
                    if ($hasTypeColumn) {
                        $conversationData['type'] = 'user_to_user';
                    }
                    
                    $conversation = Conversation::create($conversationData);
                    $conversation->participants()->attach($user->id);

                    DB::commit();
                    return $conversation;
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('ChatService: Failed to create conversation', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            } else {
                // For guest users, use session_id (still user_to_ai for backward compatibility)
                if (!$sessionId) {
                    $sessionId = Str::uuid()->toString();
                }

                // Check if columns exist
                $hasTypeColumn = Schema::hasColumn('conversations', 'type');
                $hasSessionIdColumn = Schema::hasColumn('conversations', 'session_id');

                // For guest users, find by session_id
                if ($hasTypeColumn && $hasSessionIdColumn) {
                    $conversation = Conversation::where('type', 'user_to_ai')
                        ->where('session_id', $sessionId)
                        ->first();
                } elseif ($hasSessionIdColumn) {
                    // Fallback: find by session_id only
                    $conversation = Conversation::where('session_id', $sessionId)->first();
                } else {
                    $conversation = null;
                }

                if ($conversation) {
                    return $conversation;
                }

                // Create new conversation for guest
                // Handle unique constraint on session_id (race condition)
                try {
                    $conversationData = [];
                    if ($hasTypeColumn) {
                        $conversationData['type'] = 'user_to_ai';
                    }
                    if ($hasSessionIdColumn) {
                        $conversationData['session_id'] = $sessionId;
                    }

                    return Conversation::create($conversationData);
                } catch (\Illuminate\Database\QueryException $e) {
                    // If unique constraint violation (23000 = Integrity constraint violation)
                    // This can happen in race conditions when multiple requests create conversation at the same time
                    if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 23000) {
                        Log::warning('ChatService: Unique constraint violation, retrying to find conversation', [
                            'session_id' => $sessionId,
                        ]);
                        
                        // Try to find existing conversation again
                        if ($hasTypeColumn && $hasSessionIdColumn) {
                            $conversation = Conversation::where('type', 'user_to_ai')
                                ->where('session_id', $sessionId)
                                ->first();
                        } elseif ($hasSessionIdColumn) {
                            $conversation = Conversation::where('session_id', $sessionId)->first();
                        } else {
                            $conversation = null;
                        }
                        
                        if ($conversation) {
                            return $conversation;
                        }
                    }
                    // Re-throw if it's not a unique constraint error or conversation not found
                    throw $e;
                }
            }
        } catch (\Exception $e) {
            Log::error('ChatService: getOrCreateAIConversation failed', [
                'user_id' => $user?->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Send message - no AI response anymore
     * For all users (logged-in and guests), only save message and wait for admin reply
     *
     * @param Conversation $conversation
     * @param string $content User message content
     * @param User|null $user User object or null for guest
     * @param array $context Additional context
     * @return array ['user_message' => Message, 'ai_message' => null]
     */
    public function sendMessage(Conversation $conversation, string $content, ?User $user = null, array $context = []): array
    {
        DB::beginTransaction();
        try {
            // Save user message
            // For logged-in users, use their actual user ID to show name and avatar
            // For guest users, set sender_id to null
            $userMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user?->id ?? null, // Use user ID for logged-in users
                'content' => $content,
                'message_type' => 'user',
            ]);

            // No AI response anymore - admin will reply manually
            $aiMessage = null;

            // Log message sent
            Log::info('ChatService: Message sent (waiting for admin reply)', [
                'conversation_id' => $conversation->id,
                'user_id' => $user?->id,
                'is_guest' => !$user,
            ]);

            // Update conversation
            $conversation->touch();

            // Update context if provided
            if (!empty($context)) {
                $currentContext = $conversation->context_data ?? [];
                $conversation->update([
                    'context_data' => array_merge($currentContext, $context),
                ]);
            }

            DB::commit();

            return [
                'user_message' => $userMessage,
                'ai_message' => null, // No AI response
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ChatService: Failed to send message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // Removed getConversationHistory - no longer needed without AI

    /**
     * Get conversation messages
     *
     * @param Conversation $conversation
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getMessages(Conversation $conversation, int $perPage = 50)
    {
        return Message::where('conversation_id', $conversation->id)
            ->with('sender:id,full_name,email,avatar_url,role')
            ->visible()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Clear conversation history
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function clearHistory(Conversation $conversation): bool
    {
        try {
            Message::where('conversation_id', $conversation->id)->delete();
            $conversation->update(['context_data' => null]);
            
            Log::info('ChatService: Conversation history cleared', [
                'conversation_id' => $conversation->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('ChatService: Failed to clear history', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

