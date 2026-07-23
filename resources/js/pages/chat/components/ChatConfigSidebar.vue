<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ToolName } from '@/types/chat';

export type { ToolName };

const props = defineProps<{
    open: boolean;
    provider: string;
    model: string;
    tools: ToolName[];
    instructions?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:provider': [value: string];
    'update:model': [value: string];
    'update:tools': [value: ToolName[]];
    'update:instructions': [value: string | undefined];
}>();

const localInstructions = ref(props.instructions ?? '');

const toolOptions: Array<{ id: ToolName; label: string; description: string; providers: string }> = [
    {
        id: 'web-search',
        label: 'Web Search',
        description: 'Search the web for real-time information',
        providers: 'Anthropic, OpenAI, Gemini',
    },
    {
        id: 'web-fetch',
        label: 'Web Fetch',
        description: 'Read the contents of a specific URL',
        providers: 'Anthropic, Gemini',
    },
];

const providers = [
    { id: '', name: 'Default' },
    { id: 'ollama', name: 'Ollama (Local)' },
    { id: 'ollama-cloud', name: 'Ollama (Cloud)' },
    { id: 'openai', name: 'OpenAI' },
    { id: 'anthropic', name: 'Anthropic' },
    { id: 'gemini', name: 'Gemini' },
];

const modelsByProvider: Record<string, string[]> = {
    '': [],
    ollama: ['qwen3.5:4b', 'llama3.2', 'llama3.1', 'codellama', 'mistral', 'phi3'],
    'ollama-cloud': ['llama3.2', 'llama3.1', 'codellama', 'mistral'],
    openai: ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'o3-mini'],
    anthropic: ['claude-sonnet-4-20250514', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'],
    gemini: ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
};

const availableModels = computed(() => modelsByProvider[props.provider] ?? []);

function isToolEnabled(toolId: ToolName): boolean {
    return props.tools.includes(toolId);
}

function toggleTool(toolId: ToolName, enabled: boolean) {
    if (enabled) {
        emit('update:tools', [...props.tools, toolId]);
    } else {
        emit('update:tools', props.tools.filter((t) => t !== toolId));
    }
}

function resetInstructions() {
    localInstructions.value = '';
    emit('update:instructions', undefined);
}

function onInstructionsInput() {
    const val = localInstructions.value.trim() || undefined;
    emit('update:instructions', val);
}
</script>

<template>
    <div
        class="fixed inset-y-0 right-0 z-40 flex translate-x-0 transition-transform duration-300"
        :class="open ? 'translate-x-0' : 'translate-x-full'"
    >
        <div class="flex h-full w-72 flex-col border-l border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <h2 class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                    Configuration
                </h2>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 w-7 p-0"
                    @click="emit('update:open', false)"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
                <div class="space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                        Model
                    </h3>

                    <div class="space-y-2">
                        <Label class="text-xs text-neutral-600 dark:text-neutral-400">Provider</Label>
                        <Select
                            :model-value="provider"
                            @update:model-value="emit('update:provider', $event)"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select provider" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="p in providers"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="availableModels.length > 0" class="space-y-2">
                        <Label class="text-xs text-neutral-600 dark:text-neutral-400">Model</Label>
                        <Select
                            :model-value="model"
                            @update:model-value="emit('update:model', $event)"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select model" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in availableModels"
                                    :key="m"
                                    :value="m"
                                >
                                    {{ m }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                        Tools
                    </h3>

                    <div v-for="tool in toolOptions" :key="tool.id" class="flex items-start gap-3">
                        <Checkbox
                            :id="tool.id"
                            :checked="isToolEnabled(tool.id)"
                            class="mt-0.5"
                            @update:checked="(val: boolean) => toggleTool(tool.id, val)"
                        />
                        <div class="space-y-0.5">
                            <Label :for="tool.id" class="text-sm font-medium cursor-pointer">
                                {{ tool.label }}
                            </Label>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ tool.description }}
                            </p>
                            <p class="text-xs text-neutral-400 dark:text-neutral-500">
                                {{ tool.providers }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Custom Instructions
                        </h3>
                        <button
                            v-if="localInstructions"
                            class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                            @click="resetInstructions"
                        >
                            Reset
                        </button>
                    </div>

                    <textarea
                        v-model="localInstructions"
                        placeholder="Override the system prompt with custom instructions for how the AI should behave..."
                        class="w-full resize-none rounded-lg border border-neutral-300 bg-neutral-50 p-3 text-xs text-neutral-800 placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:placeholder:text-neutral-500"
                        rows="5"
                        maxlength="2000"
                        @input="onInstructionsInput"
                    />
                    <p class="text-xs text-neutral-400 dark:text-neutral-500">
                        {{ localInstructions.length }} / 2000 characters
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
