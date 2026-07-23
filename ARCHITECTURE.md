# ChatGPT Clone - Architecture & Implementation Plan

## Project Overview

A ChatGPT clone built with Laravel 13, the Laravel AI SDK, Laravel Boost, and the Vue Starter Kit. Supports multiple AI providers (Ollama local/cloud, OpenAI, Anthropic, Gemini) with real-time streaming responses.

## Architecture Overview

| Layer | Technology |
|---|---|
| **Backend** | Laravel 13 + PHP 8.5 |
| **AI SDK** | `laravel/ai` (v0.9.x) |
| **Frontend** | Vue 3 + Inertia v3 + TypeScript |
| **Streaming** | Vercel AI SDK Protocol (SSE) |
| **Chat UI** | `@ai-sdk/vue` `useChat` composable |
| **Styling** | Tailwind CSS v4 |
| **Auth** | Laravel Fortify (already configured) |
| **Database** | SQLite (already configured) |
| **Providers** | Ollama (local + cloud), OpenAI, Anthropic, Gemini |

---

## Phase 1: Package Installation & Configuration

### Backend (Composer)

1. Install `laravel/ai` package
2. Publish AI SDK config + migrations (`vendor:publish --provider="Laravel\Ai\AiServiceProvider"`)
3. Run migrations (creates `agent_conversations` and `agent_conversation_messages` tables)
4. Configure `config/ai.php` with provider credentials
5. Add `.env` entries for `OLLAMA_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`

### Frontend (NPM)

1. Install `@ai-sdk/vue` for the `useChat` composable
2. Install `marked` or `markdown-it` for markdown rendering in chat messages
3. Install `highlight.js` for code syntax highlighting

---

## Phase 2: Database Migrations & Models

### Use AI SDK's Built-in Conversation Tables

The AI SDK provides `agent_conversations` and `agent_conversation_messages` tables. We'll use these directly rather than creating our own.

### User Model Updates

- Add `HasConversations` trait to `App\Models\User` for the `conversations` relationship

### Chat Agent

- Create `App\Ai\Agents\ChatAgent` implementing `Agent`, `Conversational`
- Use `RemembersConversations` trait for automatic persistence
- System prompt: general-purpose ChatGPT-like assistant
- Configurable provider via constructor parameter

### Provider Config Model

- A simple config approach (no new table needed - use `config/ai.php` and `.env`)

---

## Phase 3: AI Agent & Backend Logic

### `App\Ai\Agents\ChatAgent`

```php
class ChatAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    public function instructions(): string
    {
        return 'You are a helpful AI assistant. Provide clear, concise, and accurate responses.';
    }
}
```

Key features:

- Uses `RemembersConversations` for automatic conversation history storage/retrieval
- Configurable provider and model per-request
- Uses `forUser()` / `forParticipant()` for scoping
- Supports both user-level and team-level conversations via polymorphic participants

---

## Phase 4: API Routes & Controllers

### Routes (`routes/chat.php` or `routes/web.php`)

| Method | URI | Purpose |
|---|---|---|
| `GET` | `/chat` | Render chat page (Inertia) with conversation list |
| `GET` | `/chat/{conversation}` | Load a specific conversation |
| `POST` | `/chat` | Create new conversation |
| `POST` | `/chat/{conversation}/messages` | Send message + stream response (SSE) |
| `DELETE` | `/chat/{conversation}` | Delete a conversation |
| `GET` | `/chat/conversations` | Get conversation list (for sidebar) |

### `App\Http\Controllers\ChatController`

**Index**: Returns Inertia page with user's conversations (and team conversations if applicable)

**Store**: Creates a new conversation, sends first message, returns SSE stream using `->usingVercelDataProtocol()`

**SendMessage**: Continues existing conversation, streams response via SSE

**Destroy**: Soft-deletes a conversation

### Streaming Response Pattern

```php
public function sendMessage(Request $request, Conversation $conversation)
{
    $validated = $request->validate(['message' => 'required|string']);

    return (new ChatAgent(provider: $request->input('provider')))
        ->continue($conversation->id, as: $request->user())
        ->stream($validated['message'])
        ->usingVercelDataProtocol();
}
```

---

## Phase 5: Vue Frontend (Chat UI + Sidebar)

### Page Structure

```
resources/js/pages/chat/
├── Index.vue              # Main chat layout (sidebar + messages)
├── Show.vue               # Single conversation view (or use Index with dynamic)
└── components/
    ├── ChatSidebar.vue    # Conversation list + new chat button
    ├── ChatMessage.vue    # Single message bubble (user/assistant)
    ├── ChatInput.vue      # Message input + send button
    ├── MessageContent.vue # Markdown renderer for assistant messages
    └── ProviderSelector.vue # AI provider/model dropdown
```

### `useChat` Integration

The `@ai-sdk/vue` `useChat` composable handles:

- Message state management
- Streaming text display
- Automatic SSE parsing via Vercel AI SDK protocol
- Send/abort/regenerate functionality

```vue
<script setup>
import { useChat } from '@ai-sdk/vue'

const { messages, sendMessage, status, stop, regenerate } = useChat({
    api: '/chat/' + conversationId.value + '/messages',
})
</script>
```

### Layout Integration

- Use the existing `AppLayout` with the sidebar
- Sidebar shows conversation list (fetched via Inertia props)
- Main area shows chat messages with streaming
- Bottom has message input

### Key UI Features

- **Markdown rendering**: Render assistant messages with markdown (code blocks, lists, etc.)
- **Code highlighting**: Syntax highlighting for code blocks
- **Streaming animation**: Text appears token-by-token
- **New conversation**: Button to start fresh chat
- **Conversation list**: Sidebar with recent conversations, click to switch
- **Delete conversation**: Context menu or button on each conversation
- **Provider selector**: Dropdown to choose AI provider/model
- **Loading states**: Skeleton/typing indicator while waiting for response

---

## Phase 6: Tests

Following the project's Pest testing conventions:

1. **Feature Test**: `ChatConversationTest` - Test creating conversations, sending messages, listing conversations
2. **Feature Test**: `ChatAuthorizationTest` - Test that users can only access their own conversations (and team conversations they belong to)
3. **Feature Test**: `ChatStreamingTest` - Test that the streaming endpoint returns SSE response
4. **Unit Test**: `ChatAgentTest` - Test agent configuration and instructions

Use the AI SDK's faking utilities for testing without real API calls.

---

## Phase 7: Polish & Verification

1. Run `vendor/bin/pint --dirty --format agent` for PHP formatting
2. Run `npm run build` to compile frontend
3. Run `php artisan test --compact` to verify all tests pass
4. Manual testing: create conversation, send messages, verify streaming works
5. Verify conversation persistence: refresh page, switch conversations

---

## File Summary (New/Modified)

### New Files

| File | Purpose |
|---|---|
| `app/Ai/Agents/ChatAgent.php` | AI agent class |
| `app/Http/Controllers/ChatController.php` | Chat controller |
| `app/Http/Requests/StoreMessageRequest.php` | Validation |
| `resources/js/pages/chat/Index.vue` | Main chat page |
| `resources/js/pages/chat/components/ChatSidebar.vue` | Sidebar |
| `resources/js/pages/chat/components/ChatMessage.vue` | Message bubble |
| `resources/js/pages/chat/components/ChatInput.vue` | Input area |
| `resources/js/pages/chat/components/MessageContent.vue` | Markdown renderer |
| `resources/js/pages/chat/components/ProviderSelector.vue` | Provider picker |
| `routes/chat.php` | Chat routes |
| `tests/Feature/ChatConversationTest.php` | Tests |

### Modified Files

| File | Change |
|---|---|
| `app/Models/User.php` | Add `HasConversations` trait |
| `routes/web.php` | Include chat routes |
| `resources/js/app.ts` | Register chat layout |
| `config/ai.php` | Provider configuration |
| `.env` | API keys |

---

## Design Decisions

1. **Ollama cloud** - Configurable as an `OPENAI_COMPATIBLE` provider with a custom base URL
2. **Team conversations** - Team members see all conversations within their team
3. **Markdown rendering** - Full markdown with code syntax highlighting
4. **Model selection** - Users can pick specific models (e.g., `llama3.2`, `gpt-4o`)
