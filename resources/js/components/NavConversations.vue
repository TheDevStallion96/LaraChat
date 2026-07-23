<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { MessageSquare, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import chat from '@/routes/chat';

const page = usePage();

const conversations = page.props.conversations as Array<{
    id: string;
    title: string;
    updated_at: string;
}>;
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Conversations</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem>
                <Button variant="ghost" class="w-full justify-start gap-2 px-2 py-1.5" as-child>
                    <Link :href="chat.index().url" class="w-full justify-start gap-2">
                        <Plus class="h-4 w-4" />
                        <span>New Chat</span>
                    </Link>
                </Button>
            </SidebarMenuItem>
            <SidebarMenuItem v-for="conversation in conversations" :key="conversation.id">
                <SidebarMenuButton
                    as-child
                    class="gap-2"
                >
                    <Link :href="chat.show({ conversation: conversation.id }).url" class="flex flex-1 items-center gap-2 truncate">
                        <MessageSquare class="h-4 w-4 shrink-0 text-neutral-500" />
                        <span class="truncate text-neutral-700 dark:text-neutral-300">{{ conversation.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>