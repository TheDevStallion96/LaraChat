<script setup lang="ts">
import { Send, Square } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    isStreaming: boolean;
}>();

const emit = defineEmits<{
    send: [message: string];
    stop: [];
}>();

const input = ref('');
const textareaRef = ref<HTMLTextAreaElement | null>(null);

function handleSubmit() {
    const message = input.value.trim();

    if (!message || props.isStreaming) {
return;
}

    emit('send', message);
    input.value = '';

    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
    }
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSubmit();
    }
}

function handleInput() {
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = `${textareaRef.value.scrollHeight}px`;
    }
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="relative">
        <div class="flex items-end gap-2 rounded-xl border border-neutral-300 bg-white p-2 shadow-sm dark:border-neutral-600 dark:bg-neutral-800">
            <textarea
                ref="textareaRef"
                v-model="input"
                placeholder="Send a message..."
                rows="1"
                class="max-h-48 min-h-[2.5rem] flex-1 resize-none bg-transparent px-2 py-1.5 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none dark:text-neutral-100 dark:placeholder:text-neutral-500"
                :disabled="isStreaming"
                @keydown="handleKeydown"
                @input="handleInput"
            />

            <Button
                v-if="isStreaming"
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 w-8 shrink-0 p-0"
                @click="emit('stop')"
            >
                <Square class="h-4 w-4" />
            </Button>

            <Button
                v-else
                type="submit"
                variant="ghost"
                size="sm"
                class="h-8 w-8 shrink-0 p-0"
                :disabled="!input.trim()"
            >
                <Send class="h-4 w-4" />
            </Button>
        </div>

        <p class="mt-1 text-center text-xs text-neutral-400 dark:text-neutral-600">
            AI can make mistakes. Check important info.
        </p>
    </form>
</template>
