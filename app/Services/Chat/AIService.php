<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class AIService
{
    private string $provider;
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'openai');
        $this->apiKey = config('services.ai.api_key', '');
        $this->model = config('services.ai.model', 'gpt-4o-mini');
        $this->maxTokens = config('services.ai.max_tokens', 1000);
        $this->temperature = config('services.ai.temperature', 0.7);
        
        // Set base URL based on provider
        $this->baseUrl = match ($this->provider) {
            'openai' => 'https://api.openai.com/v1',
            'claude' => 'https://api.anthropic.com/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1',
            default => 'https://api.openai.com/v1',
        };
    }

    /**
     * Generate AI response from messages
     *
     * @param array $messages Array of messages with 'role' and 'content'
     * @param array $context Additional context (booking info, room info, etc.)
     * @return array ['content' => string, 'metadata' => array]
     */
    public function generateResponse(array $messages, array $context = []): array
    {
        if (empty($this->apiKey)) {
            Log::warning('AI Service: API key not configured');
            return [
                'content' => 'Xin lỗi, dịch vụ AI chat hiện đang tạm thời không khả dụng. Vui lòng thử lại sau hoặc liên hệ với chúng tôi qua email.',
                'metadata' => ['error' => 'api_key_not_configured'],
            ];
        }

        try {
            return match ($this->provider) {
                'openai' => $this->generateOpenAIResponse($messages, $context),
                'claude' => $this->generateClaudeResponse($messages, $context),
                'gemini' => $this->generateGeminiResponse($messages, $context),
                default => $this->generateOpenAIResponse($messages, $context),
            };
        } catch (Exception $e) {
            Log::error('AI Service: Error generating response', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'content' => 'Xin lỗi, đã xảy ra lỗi khi xử lý yêu cầu của bạn. Vui lòng thử lại sau.',
                'metadata' => [
                    'error' => $e->getMessage(),
                    'provider' => $this->provider,
                ],
            ];
        }
    }

    /**
     * Generate response using OpenAI API
     */
    private function generateOpenAIResponse(array $messages, array $context): array
    {
        // Build system prompt with context
        $systemPrompt = $this->buildSystemPrompt($context);
        
        // Prepend system message if not exists
        $formattedMessages = [];
        if (!empty($systemPrompt)) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }
        
        // Format messages for OpenAI
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] === 'ai' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("{$this->baseUrl}/chat/completions", [
            'model' => $this->model,
            'messages' => $formattedMessages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ]);

        if (!$response->successful()) {
            throw new Exception('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => trim($content),
            'metadata' => [
                'provider' => 'openai',
                'model' => $this->model,
                'tokens_used' => $usage['total_tokens'] ?? 0,
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
            ],
        ];
    }

    /**
     * Generate response using Claude API (Anthropic)
     */
    private function generateClaudeResponse(array $messages, array $context): array
    {
        $systemPrompt = $this->buildSystemPrompt($context);
        
        // Format messages for Claude
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] === 'ai' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages' => $formattedMessages,
        ];

        if (!empty($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("{$this->baseUrl}/messages", $payload);

        if (!$response->successful()) {
            throw new Exception('Claude API error: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => trim($content),
            'metadata' => [
                'provider' => 'claude',
                'model' => $this->model,
                'tokens_used' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                'prompt_tokens' => $usage['input_tokens'] ?? 0,
                'completion_tokens' => $usage['output_tokens'] ?? 0,
            ],
        ];
    }

    /**
     * Generate response using Google Gemini API
     */
    private function generateGeminiResponse(array $messages, array $context): array
    {
        // Gemini API implementation
        // Note: Gemini API structure is different, implement as needed
        throw new Exception('Gemini API not yet implemented');
    }

    /**
     * Build system prompt with context
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "Bạn là trợ lý AI thân thiện và chuyên nghiệp của hệ thống đặt phòng homestay BookStay. ";
        $prompt .= "Nhiệm vụ của bạn là hỗ trợ khách hàng với các câu hỏi về:\n";
        $prompt .= "- Thông tin phòng và dịch vụ\n";
        $prompt .= "- Quy trình đặt phòng và thanh toán\n";
        $prompt .= "- Chính sách hủy và hoàn tiền\n";
        $prompt .= "- Hướng dẫn check-in/check-out\n";
        $prompt .= "- Các câu hỏi khác liên quan đến homestay\n\n";
        $prompt .= "Hãy trả lời một cách thân thiện, chính xác và hữu ích. ";
        $prompt .= "Nếu không chắc chắn, hãy đề nghị khách hàng liên hệ trực tiếp với chúng tôi.\n\n";

        // Add context if available
        if (!empty($context)) {
            $prompt .= "Thông tin bổ sung:\n";
            if (isset($context['booking_id'])) {
                $prompt .= "- Booking ID: {$context['booking_id']}\n";
            }
            if (isset($context['room_id'])) {
                $prompt .= "- Room ID: {$context['room_id']}\n";
            }
            if (isset($context['user_name'])) {
                $prompt .= "- Khách hàng: {$context['user_name']}\n";
            }
        }

        return $prompt;
    }
}

