<script setup lang="ts">
import { useChat } from '@ai-sdk/vue';
import { router } from '@inertiajs/vue3';
import { Search, Globe, Settings2 } from '@lucide/vue';
import { DefaultChatTransport } from 'ai';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { ChatConfig, ToolName } from '@/types/chat';
import ChatConfigSidebar from './components/ChatConfigSidebar.vue';
import ChatInput from './components/ChatInput.vue';
import ChatMessage from './components/ChatMessage.vue';

type Conversation = {
    id: string;
    title: string;
    updated_at: string;
};

type AiSdkMessage = {
    id: string;
    role: 'user' | 'assistant' | 'system';
    parts: Array<{ type: 'text'; text: string }>;
};

const props = defineProps<{
    conversations: Conversation[];
    activeConversationId?: string;
    initialMessages?: AiSdkMessage[];
}>();

const conversationId = ref<string | null>(props.activeConversationId ?? null);
const config = ref<ChatConfig>({
    provider: 'ollama',
    model: 'qwen3.5:4b',
    tools: [],
    instructions: undefined,
});
const showConfigSidebar = ref(false);

const { messages, sendMessage, status, stop } = useChat(() => ({
    messages: props.initialMessages ?? [],
    transport: new DefaultChatTransport({
        api: conversationId.value ? `/chat/${conversationId.value}/messages` : '/chat',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: () => ({
            provider: config.value.provider || undefined,
            model: config.value.model || undefined,
            tools: config.value.tools.length > 0 ? config.value.tools : undefined,
            instructions: config.value.instructions || undefined,
        }),
    }),
    onFinish: () => {
        refreshConversations();
    },
}));

const activeConversation = computed(() =>
    props.conversations.find((c) => c.id === conversationId.value),
);

const isStreaming = computed(() =>
    status.value === 'streaming' || status.value === 'submitted',
);

const activeToolLabels: Record<ToolName, string> = {
    'web-search': 'Web Search',
    'web-fetch': 'Web Fetch',
};

const activeToolIcons: Record<ToolName, typeof Search> = {
    'web-search': Search,
    'web-fetch': Globe,
};

function handleSend(message: string) {
    sendMessage({ text: message });
}

function refreshConversations() {
    router.reload({ only: ['conversations'] });
}
</script>

<template>
    <div class="flex h-[calc(100vh-4rem)] overflow-hidden">
        <div class="flex flex-1 flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-2 dark:border-neutral-700">
                <h1 class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {{ activeConversation?.title ?? 'New Chat' }}
                </h1>
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0"
                        @click="showConfigSidebar = !showConfigSidebar"
                    >
                        <Settings2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
                    <div class="text-center">
                        <h2 class="text-2xl font-semibold text-neutral-600 dark:text-neutral-400">
                            How can I help you today?
                        </h2>
                        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-500">
                            Ask me anything, or choose a conversation from the sidebar.
                        </p>
                    </div>
                </div>

                <div v-else class="mx-auto max-w-3xl px-4 py-6">
                    <ChatMessage
                        v-for="(message, index) in messages"
                        :key="message.id ?? index"
                        :message="message"
                    />
                </div>
            </div>

            <div class="border-t border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                <div class="mx-auto max-w-3xl px-4 py-2">
                    <div v-if="config.tools.length > 0" class="mb-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="tool in config.tools"
                            :key="tool"
                            class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                        >
                            <component :is="activeToolIcons[tool]" class="h-3 w-3" />
                            {{ activeToolLabels[tool] }}
                        </span>
                    </div>
                    <ChatInput
                        :is-streaming="isStreaming"
                        @send="handleSend"
                        @stop="stop"
                    />
                </div>
            </div>
        </div>

        <ChatConfigSidebar
            :open="showConfigSidebar"
            :provider="config.provider"
            :model="config.model"
            :tools="config.tools"
            :instructions="config.instructions"
            @update:open="showConfigSidebar = $event"
            @update:provider="config.provider = $event"
            @update:model="config.model = $event"
            @update:tools="config.tools = $event"
            @update:instructions="config.instructions = $event"
        />

        <div
            v-if="showConfigSidebar"
            class="fixed inset-0 z-30 bg-black/20"
            @click="showConfigSidebar = false"
        />
    </div>
</template>
