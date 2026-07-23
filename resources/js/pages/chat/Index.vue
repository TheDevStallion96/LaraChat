<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useChat } from '@ai-sdk/vue';
import ChatMessage from './components/ChatMessage.vue';
import ChatInput from './components/ChatInput.vue';
import ProviderSelector from './components/ProviderSelector.vue';

type Conversation = {
    id: string;
    title: string;
    updated_at: string;
};

const props = defineProps<{
    conversations: Conversation[];
    activeConversationId?: string;
}>();

const conversationId = ref<string | null>(props.activeConversationId ?? null);
const selectedProvider = ref<string>('');
const selectedModel = ref<string>('');

const chatApi = computed(() =>
    conversationId.value ? `/chat/${conversationId.value}/messages` : '/chat',
);

const { messages, sendMessage, status, stop, setMessages, error } = useChat({
    api: chatApi,
    body: computed(() => ({
        provider: selectedProvider.value || undefined,
        model: selectedModel.value || undefined,
    })),
    onFinish: () => {
        refreshConversations();
    },
});

const activeConversation = computed(() =>
    props.conversations.find((c) => c.id === conversationId.value),
);

const isStreaming = computed(() =>
    status.value === 'streaming' || status.value === 'submitted',
);

function handleSend(message: string) {
    sendMessage({ text: message });
}

function handleNewChat() {
    conversationId.value = null;
    setMessages([]);
    router.visit('/chat', {
        preserveState: true,
        preserveScroll: true,
        only: [],
    });
}

function handleSelectConversation(id: string) {
    conversationId.value = id;
    setMessages([]);
    router.visit(`/chat/${id}`, {
        preserveState: true,
        preserveScroll: true,
        only: [],
    });
}

function handleDeleteConversation(id: string) {
    router.delete(`/chat/${id}`, {
        preserveState: true,
        onSuccess: () => {
            if (conversationId.value === id) {
                handleNewChat();
            }
            refreshConversations();
        },
    });
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
                <ProviderSelector
                    v-model:provider="selectedProvider"
                    v-model:model="selectedModel"
                />
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
                <div class="mx-auto max-w-3xl px-4 py-4">
                    <ChatInput
                        :is-streaming="isStreaming"
                        @send="handleSend"
                        @stop="stop"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
