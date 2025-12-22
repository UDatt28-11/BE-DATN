# AI Chat Setup Guide

## Tổng quan

Hệ thống AI Chat đã được tích hợp vào hệ thống BookStay, cho phép khách hàng chat với AI để được hỗ trợ về đặt phòng, dịch vụ, và các câu hỏi khác.

## Cấu trúc Files

### Backend
- `app/Http/Controllers/Api/ChatController.php` - Controller xử lý API requests
- `app/Services/Chat/AIService.php` - Service xử lý AI API calls (OpenAI/Claude)
- `app/Services/Chat/ChatService.php` - Service quản lý conversation và messages
- `app/Models/Conversation.php` - Model đã được cập nhật với fields cho AI chat
- `app/Models/Message.php` - Model đã được cập nhật với fields cho AI messages
- `database/migrations/2025_01_25_000000_add_ai_chat_fields_to_conversations_and_messages.php` - Migration

### Frontend
- `src/components/Chatbox/` - Components cho chatbox UI
- `src/context/ChatContext.tsx` - Context quản lý state chat
- `src/service/chatService.ts` - Service gọi API
- `src/types/chat/chat.ts` - TypeScript types

## Setup

### 1. Chạy Migration

```bash
php artisan migrate
```

### 2. Cấu hình Environment Variables

Thêm vào file `.env`:

```env
# AI Chat Configuration
AI_PROVIDER=openai  # 'openai', 'claude', 'gemini'
AI_API_KEY=sk-...    # API key của bạn
AI_MODEL=gpt-4o-mini # Model name
AI_MAX_TOKENS=1000
AI_TEMPERATURE=0.7
AI_CHAT_ENABLED=true
```

### 3. Cấu hình AI Provider

#### OpenAI (Khuyến nghị)
```env
AI_PROVIDER=openai
AI_API_KEY=sk-your-openai-api-key
AI_MODEL=gpt-4o-mini  # hoặc gpt-4o, gpt-3.5-turbo
```

#### Claude (Anthropic)
```env
AI_PROVIDER=claude
AI_API_KEY=sk-ant-your-claude-api-key
AI_MODEL=claude-3-haiku  # hoặc claude-3-sonnet, claude-3-opus
```

### 4. Test API

```bash
# Test get conversation
curl -X GET "http://localhost:8000/api/chat/conversation?session_id=test-123" \
  -H "Accept: application/json"

# Test send message
curl -X POST "http://localhost:8000/api/chat/send-message" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "conversation_id": 1,
    "content": "Xin chào",
    "session_id": "test-123"
  }'
```

## API Endpoints

### Public Endpoints (Guest có thể dùng)

- `GET /api/chat/conversation` - Lấy hoặc tạo conversation
- `POST /api/chat/send-message` - Gửi tin nhắn
- `GET /api/chat/messages` - Lấy lịch sử tin nhắn

### Protected Endpoints (Cần authentication)

- `POST /api/chat/clear-history` - Xóa lịch sử chat

## Features

1. **Guest Support**: Guest users có thể chat với AI bằng session_id
2. **User Support**: Logged-in users có conversation riêng
3. **Context-aware**: AI có thể nhận context (booking_id, room_id, etc.)
4. **History**: Lưu lịch sử chat trong database
5. **Multi-provider**: Hỗ trợ OpenAI, Claude, và có thể mở rộng cho Gemini

## Frontend Usage

Chatbox sẽ tự động hiển thị ở góc dưới bên phải màn hình. User có thể:
- Click vào floating button để mở chat
- Gửi tin nhắn và nhận phản hồi từ AI
- Xem lịch sử chat
- Xóa lịch sử chat

## Troubleshooting

### AI không trả lời
1. Kiểm tra API key trong `.env`
2. Kiểm tra log: `storage/logs/laravel.log`
3. Kiểm tra network requests trong browser console

### Lỗi migration
```bash
php artisan migrate:rollback --step=1
php artisan migrate
```

### Guest user không thể chat
- Kiểm tra session_id được lưu trong localStorage
- Kiểm tra conversation được tạo với session_id đúng

## Notes

- Guest users sử dụng `session_id` để identify conversation
- Logged-in users có conversation riêng, không cần session_id
- AI responses được lưu với `message_type = 'ai'`
- User messages được lưu với `message_type = 'user'`

