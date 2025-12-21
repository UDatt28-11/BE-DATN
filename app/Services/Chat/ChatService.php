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
    private AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get or create conversation for user
     * Supports both user_to_user (admin) and user_to_ai (AI) conversation types
     *
     * @param User|null $user User object or null for guest
     * @param string|null $sessionId Session ID for guest users
     * @param string $type Conversation type: 'admin' (user_to_user) or 'ai' (user_to_ai)
     * @return Conversation
     */
    public function getOrCreateConversation(?User $user = null, ?string $sessionId = null, string $type = 'ai'): Conversation
    {
        try {
            // Map type parameter to conversation type
            $conversationType = $type === 'admin' ? 'user_to_user' : 'user_to_ai';
            
            if ($user) {
                // For logged-in users, find existing conversation by type
                $hasTypeColumn = Schema::hasColumn('conversations', 'type');
                
                if ($hasTypeColumn) {
                    $conversation = Conversation::where('type', $conversationType)
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

                // Create new conversation for logged-in user
                DB::beginTransaction();
                try {
                    $conversationData = [];
                    if ($hasTypeColumn) {
                        $conversationData['type'] = $conversationType;
                    }
                    
                    $conversation = Conversation::create($conversationData);
                    $conversation->participants()->attach($user->id);

                    DB::commit();
                    return $conversation;
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('ChatService: Failed to create conversation', [
                        'user_id' => $user->id,
                        'type' => $type,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            } else {
                // For guest users, use session_id
                if (!$sessionId) {
                    $sessionId = Str::uuid()->toString();
                }

                // Check if columns exist
                $hasTypeColumn = Schema::hasColumn('conversations', 'type');
                $hasSessionIdColumn = Schema::hasColumn('conversations', 'session_id');

                // For guest users, find by session_id and type
                if ($hasTypeColumn && $hasSessionIdColumn) {
                    $conversation = Conversation::where('type', $conversationType)
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
                        $conversationData['type'] = $conversationType;
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
                            $conversation = Conversation::where('type', $conversationType)
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
            Log::error('ChatService: getOrCreateConversation failed', [
                'user_id' => $user?->id,
                'session_id' => $sessionId,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Alias for backward compatibility
     */
    public function getOrCreateAIConversation(?User $user = null, ?string $sessionId = null, string $type = 'ai'): Conversation
    {
        return $this->getOrCreateConversation($user, $sessionId, $type);
    }

    /**
     * Send message and get AI response automatically (only for AI mode)
     * For admin mode, only save message and wait for admin reply
     *
     * @param Conversation $conversation
     * @param string $content User message content
     * @param User|null $user User object or null for guest
     * @param array $context Additional context
     * @return array ['user_message' => Message, 'ai_message' => Message|null]
     */
    public function sendMessage(Conversation $conversation, string $content, ?User $user = null, array $context = []): array
    {
        DB::beginTransaction();
        try {
            // Save user message
            $userMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user?->id ?? null,
                'content' => $content,
                'message_type' => 'user',
            ]);

            // Check conversation type to determine if AI should respond
            $hasTypeColumn = Schema::hasColumn('conversations', 'type');
            $conversationType = $hasTypeColumn ? ($conversation->type ?? 'user_to_ai') : 'user_to_ai';
            $isAIMode = $conversationType === 'user_to_ai';

            $aiMessage = null;
            
            // Only generate AI response for AI mode conversations
            if ($isAIMode) {
                // Get conversation history for AI context
                $historyMessages = $this->getConversationHistory($conversation);
                
                // Generate AI response
                $aiResponse = $this->aiService->generateResponse($historyMessages, $context);
                
                // Save AI message
                if (!empty($aiResponse['content'])) {
                    $aiMessage = Message::create([
                        'conversation_id' => $conversation->id,
                        'sender_id' => null, // AI messages have no sender
                        'content' => $aiResponse['content'],
                        'message_type' => 'ai',
                        'ai_provider' => $aiResponse['metadata']['provider'] ?? config('services.ai.provider'),
                        'ai_model' => $aiResponse['metadata']['model'] ?? config('services.ai.model'),
                        'metadata' => $aiResponse['metadata'] ?? [],
                    ]);
                }
            }

            // Log message sent
            Log::info('ChatService: Message sent', [
                'conversation_id' => $conversation->id,
                'conversation_type' => $conversationType,
                'user_id' => $user?->id,
                'is_guest' => !$user,
                'is_ai_mode' => $isAIMode,
                'ai_response_length' => $aiMessage ? strlen($aiMessage->content) : 0,
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
                'ai_message' => $aiMessage,
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

    /**
     * Get conversation history formatted for AI
     *
     * @param Conversation $conversation
     * @param int $limit Maximum number of messages to retrieve
     * @return array Array of messages with 'role' and 'content'
     */
    private function getConversationHistory(Conversation $conversation, int $limit = 20): array
    {
        $messages = Message::where('conversation_id', $conversation->id)
            ->visible()
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        $history = [];
        foreach ($messages as $message) {
            $history[] = [
                'role' => $message->message_type === 'ai' ? 'ai' : 'user',
                'content' => $message->content,
            ];
        }

        return $history;
    }

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

