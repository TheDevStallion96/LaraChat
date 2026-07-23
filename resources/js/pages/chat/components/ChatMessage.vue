<script setup lang="ts">
import { User, Bot } from '@lucide/vue';
import { computed } from 'vue';
import MessageContent from './MessageContent.vue';

type MessagePart = {
    type: 'text';
    text: string;
};

type Message = {
    id?: string;
    role: 'user' | 'assistant' | 'system';
    parts?: MessagePart[];
    content?: string;
};

const props = defineProps<{
    message: Message;
}>();

const isUser = computed(() => props.message.role === 'user');

const textContent = computed(() => {
    if (props.message.parts) {
        return props.message.parts
            .filter((p) => p.type === 'text')
            .map((p) => p.text)
            .join('');
    }

    return props.message.content ?? '';
});
</script>

<template>
    <div class="flex gap-4 py-4" :class="{ 'bg-neutral-50 dark:bg-neutral-800/50 -mx-4 px-4': isUser }">
        <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
            :class="isUser
                ? 'bg-blue-600 text-white'
                : 'bg-emerald-600 text-white'"
        >
            <User v-if="isUser" class="h-4 w-4" />
            <Bot v-else class="h-4 w-4" />
        </div>

        <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                {{ isUser ? 'You' : 'Assistant' }}
            </div>
            <div class="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
                <MessageContent :content="textContent" />
            </div>
        </div>
    </div>
</template>
