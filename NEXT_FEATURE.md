# Next Feature: AI Tools (WebSearch/WebFetch), Configuration Sidebar & Custom Instructions

> **GitHub Issue:** [#12](https://github.com/TheDevStallion96/LaraChat/issues/12)

## Problem

The current chat experience lacks several key capabilities that users expect from a modern AI chat application:

1. **No tool use** — The AI cannot search the web, fetch URLs, or perform any actions beyond text generation. This severely limits the assistant's usefulness for current events, research, and data-gathering tasks.
2. **No configuration sidebar** — Provider and model selection is crammed into a compact dropdown, with no room for additional settings like toggling tools or customizing the AI's behavior.
3. **No custom instructions** — Users have no way to customize the system prompt per conversation, forcing a one-size-fits-all assistant personality.

## Architecture

### Backend

| Layer | Technology |
|---|---|
| Tool framework | `Laravel\Ai\Contracts\HasTools` interface |
| Provider tools | `WebSearch` (Anthropic/OpenAI/Gemini), `WebFetch` (Anthropic/Gemini) |
| Agent attribute | `#[MaxSteps(10)]` to limit tool call loops |
| Custom instructions | Optional string merged into agent's `instructions()` |

### Frontend

| Layer | Technology |
|---|---|
| Config panel | New `ChatConfigSidebar.vue` with slide-in/out |
| Model picker | Extracted from `ProviderSelector` into sidebar |
| Tool toggles | Toggle switches for WebSearch, WebFetch |
| Instructions | Textarea with "Reset to default" link |

## Implementation Plan

### Step 1: Backend — ChatAgent Tools

**File: `app/Ai/Agents/ChatAgent.php`**

- Add `HasTools` interface to class declaration
- Add `#[MaxSteps(10)]` attribute
- Add constructor parameters: `public array $enabledTools = []`, `public ?string $customInstructions = null`
- Implement `tools()` returning `WebSearch` and `WebFetch` based on `$this->enabledTools`
- Merge `$this->customInstructions` into instructions output when provided

```php
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

#[MaxSteps(10)]
class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
        public array $enabledTools = [],
        public ?string $customInstructions = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $instructions = 'You are a helpful AI assistant. Provide clear, concise, and accurate responses.
Format your responses using markdown when appropriate, including code blocks with syntax highlighting for programming questions.';

        if ($this->customInstructions) {
            $instructions .= "\n\n" . $this->customInstructions;
        }

        return $instructions;
    }

    public function tools(): iterable
    {
        $tools = [];

        if (in_array('web-search', $this->enabledTools)) {
            $tools[] = new WebSearch();
        }

        if (in_array('web-fetch', $this->enabledTools)) {
            $tools[] = new WebFetch();
        }

        return $tools;
    }
}
```

### Step 2: Backend — ChatController Updates

**File: `app/Http/Controllers/ChatController.php`**

- Add `tools` (array) and `instructions` (nullable string) to request validation
- Pass `enabledTools` and `customInstructions` to ChatAgent constructor

```php
// In store() and messages() validation:
'tools' => ['sometimes', 'array'],
'tools.*' => ['string', 'in:web-search,web-fetch'],
'instructions' => ['nullable', 'string', 'max:2000'],

// Agent construction:
$agent = new ChatAgent(
    provider: $provider,
    model: $model,
    enabledTools: $validated['tools'] ?? [],
    customInstructions: $validated['instructions'] ?? null,
);
```

### Step 3: Frontend — Types

**File: `resources/js/types/chat.ts`**

```typescript
export type ToolName = 'web-search' | 'web-fetch';

export type ChatConfig = {
    provider: string;
    model: string;
    tools: ToolName[];
    instructions?: string;
};
```

### Step 4: Frontend — ChatConfigSidebar

**File: `resources/js/pages/chat/components/ChatConfigSidebar.vue`**

- Fixed right panel, toggled by a button in the chat header
- Collapsible with smooth slide animation (Tailwind translate/transition)
- Sections:
  1. **Model** — Provider dropdown + Model dropdown (same data as current `ProviderSelector`)
  2. **Tools** — Toggle switches for Web Search and Web Fetch with descriptions
  3. **Custom Instructions** — Textarea with placeholder, character count, reset link
- Emits config changes up to parent
- Props: current config values
- Two-way binding via v-model or emit/update pattern

### Step 5: Frontend — Chat Page Layout Update

**File: `resources/js/pages/chat/Index.vue`**

- Add sidebar toggle button to header bar (replacing the old ProviderSelector)
- Import and render `ChatConfigSidebar`
- Pass config state (provider, model, tools, instructions) to both the sidebar and the transport body
- Show mini tool indicator chips above the input when tools are active

```typescript
const config = ref<ChatConfig>({
    provider: 'ollama',
    model: 'qwen3.5:4b',
    tools: [],
    instructions: undefined,
});

const showConfigSidebar = ref(false);

// In DefaultChatTransport body:
body: () => ({
    provider: config.value.provider || undefined,
    model: config.value.model || undefined,
    tools: config.value.tools.length > 0 ? config.value.tools : undefined,
    instructions: config.value.instructions || undefined,
}),
```

### Step 6: Tests

**File: `tests/Feature/ChatConversationTest.php`**

- Test that tools and instructions are accepted in store/messages endpoints
- Test that tools array is validated (only valid tool names)
- Test that instructions string is validated (max length)

## UX Flow

```
┌─────────────────────────────────────────────────────┐
│  Chat Header: "New Chat"               [⚙️ Toggle] │
├──────────────────────────┬──────────────────────────┤
│                          │  ChatConfigSidebar        │
│                          │  ──────────────────       │
│     Messages Area        │  Provider: [▼ OpenAI]     │
│                          │  Model:    [▼ gpt-4o]     │
│                          │                          │
│                          │  Tools                    │
│                          │  ☑ Web Search             │
│                          │     Search the web for    │
│                          │     real-time info        │
│                          │  ☐ Web Fetch              │
│                          │     Read contents of a    │
│                          │     specific URL          │
│                          │                          │
│                          │  Custom Instructions      │
│                          │  ┌────────────────────┐  │
│                          │  │ You are a helpful...│  │
│                          │  └────────────────────┘  │
│                          │  [Reset to default]       │
├──────────────────────────┴──────────────────────────┤
│  [🔍 Web Search] [🌐 Web Fetch]    [Message input]  │
│         (mini tool indicators)                       │
└─────────────────────────────────────────────────────┘
```

## Testing Strategy

- **Backend unit**: Verify `ChatAgent::tools()` returns `WebSearch` when `web-search` is in `$enabledTools`, returns `WebFetch` when `web-fetch` is enabled, returns empty when no tools enabled
- **Backend unit**: Verify `ChatAgent::instructions()` includes custom instructions when provided
- **Feature test**: POST to `chat.store` with `tools: ['web-search']` and `instructions: 'Be concise.'` — assert 200, assert agent was prompted
- **Feature test**: POST with invalid tool name — assert 422 validation error
- **Existing tests**: Must continue to pass (backward-compatible — tools and instructions are optional)

## Out of Scope

- Custom tool creation (Calculator, DateTime) — future enhancement
- MCP tool integration — needs external MCP server setup
- File attachments / multimodal inputs — separate feature
- Per-conversation tool setting persistence — follow-up
