<script setup lang="ts">
import { User, Bot, Pencil, RefreshCw, Check, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
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
    isLastMessage?: boolean;
    isStreaming?: boolean;
}>();

const emit = defineEmits<{
    edit: [id: string, text: string];
    regenerate: [id: string];
}>();

const editing = ref(false);
const editText = ref('');

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

function startEditing() {
    editText.value = textContent.value;
    editing.value = true;
}

function cancelEditing() {
    editing.value = false;
    editText.value = '';
}

function saveEditing() {
    const trimmed = editText.value.trim();
    if (!trimmed) return;

    emit('edit', props.message.id!, trimmed);
    editing.value = false;
}

function handleRegenerate() {
    if (props.message.id) {
        emit('regenerate', props.message.id);
    }
}
</script>

<template>
    <div
        class="group relative flex gap-4 rounded-lg px-4 py-4"
        :class="{ 'bg-muted/50': isUser }"
    >
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
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium text-foreground">
                    {{ isUser ? 'You' : 'Assistant' }}
                </span>

                <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                    <Button
                        v-if="isUser && !editing"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0"
                        @click="startEditing"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        v-if="!isUser && isLastMessage && !isStreaming"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0"
                        @click="handleRegenerate"
                    >
                        <RefreshCw class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            <div v-if="editing" class="mt-1">
                <textarea
                    v-model="editText"
                    class="w-full resize-none rounded-lg border border-input bg-background p-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    rows="3"
                    @keydown.ctrl.enter="saveEditing"
                    @keydown.meta.enter="saveEditing"
                />
                <div class="mt-2 flex items-center gap-2">
                    <Button variant="default" size="sm" @click="saveEditing">
                        <Check class="mr-1 h-3.5 w-3.5" />
                        Save
                    </Button>
                    <Button variant="ghost" size="sm" @click="cancelEditing">
                        <X class="mr-1 h-3.5 w-3.5" />
                        Cancel
                    </Button>
                </div>
            </div>

            <div v-else class="mt-1 text-sm text-foreground">
                <MessageContent :content="textContent" />
            </div>
        </div>
    </div>
</template>
