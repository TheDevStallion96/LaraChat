<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

type Conversation = {
    id: string;
    title: string;
    updated_at: string;
};

const props = defineProps<{
    conversations: Conversation[];
    activeConversationId: string | null;
}>();

const emit = defineEmits<{
    newChat: [];
    select: [id: string];
    delete: [id: string];
}>();

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
return 'Today';
}

    if (diffDays === 1) {
return 'Yesterday';
}

    if (diffDays < 7) {
return `${diffDays} days ago`;
}

    return date.toLocaleDateString();
}
</script>

<template>
    <div class="flex w-64 flex-col border-r border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex items-center justify-between p-3">
            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                Conversations
            </span>
            <Button
                variant="ghost"
                size="sm"
                class="h-8 w-8 p-0"
                @click="emit('newChat')"
            >
                <Plus class="h-4 w-4" />
            </Button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div v-if="conversations.length === 0" class="px-3 py-2">
                <p class="text-xs text-neutral-500 dark:text-neutral-500">
                    No conversations yet.
                </p>
            </div>

            <div v-else class="space-y-0.5 px-2">
                <div
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="group flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    :class="{
                        'bg-neutral-100 dark:bg-neutral-800': activeConversationId === conversation.id,
                    }"
                >
                    <button
                        class="flex flex-1 items-center gap-2 text-left truncate"
                        @click="emit('select', conversation.id)"
                    >
                        <MessageSquare class="h-4 w-4 shrink-0 text-neutral-500" />
                        <span class="truncate text-neutral-700 dark:text-neutral-300">
                            {{ conversation.title }}
                        </span>
                    </button>

                    <span class="shrink-0 text-xs text-neutral-400 dark:text-neutral-600">
                        {{ formatDate(conversation.updated_at) }}
                    </span>

                    <button
                        class="shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                        @click.stop="emit('delete', conversation.id)"
                    >
                        <Trash2 class="h-3.5 w-3.5 text-neutral-400 hover:text-red-500" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
