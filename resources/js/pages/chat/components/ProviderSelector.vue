<script setup lang="ts">
import { Settings2 } from '@lucide/vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const provider = defineModel<string>('provider', { default: '' });
const model = defineModel<string>('model', { default: '' });

const providers = [
    {
        id: '',
        name: 'Default',
        models: [],
    },
    {
        id: 'ollama',
        name: 'Ollama (Local)',
        models: ['qwen3.5:4b', 'llama3.2', 'llama3.1', 'codellama', 'mistral', 'phi3'],
    },
    {
        id: 'ollama-cloud',
        name: 'Ollama (Cloud)',
        models: ['llama3.2', 'llama3.1', 'codellama', 'mistral'],
    },
    {
        id: 'openai',
        name: 'OpenAI',
        models: ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'o3-mini'],
    },
    {
        id: 'anthropic',
        name: 'Anthropic',
        models: ['claude-sonnet-4-20250514', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'],
    },
    {
        id: 'gemini',
        name: 'Gemini',
        models: ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
    },
];

const currentProvider = computed(() =>
    providers.find((p) => p.id === provider.value) ?? providers[0],
);

const currentModel = computed(() => {
    if (model.value) {
return model.value;
}

    return currentProvider.value.models[0] ?? 'Default';
});

function selectProvider(providerId: string) {
    provider.value = providerId;
    const p = providers.find((pr) => pr.id === providerId);

    if (p && p.models.length > 0) {
        model.value = p.models[0];
    } else {
        model.value = '';
    }
}

function selectModel(modelId: string) {
    model.value = modelId;
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs">
                <Settings2 class="h-3.5 w-3.5" />
                <span>{{ currentProvider.name }}</span>
                <span v-if="model" class="text-neutral-500 dark:text-neutral-400">
                    / {{ currentModel }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel>Provider</DropdownMenuLabel>
            <DropdownMenuItem
                v-for="p in providers"
                :key="p.id"
                @click="selectProvider(p.id)"
            >
                <span :class="{ 'font-medium': provider === p.id }">
                    {{ p.name }}
                </span>
            </DropdownMenuItem>

            <template v-if="currentProvider.models.length > 0">
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Model</DropdownMenuLabel>
                <DropdownMenuItem
                    v-for="m in currentProvider.models"
                    :key="m"
                    @click="selectModel(m)"
                >
                    <span :class="{ 'font-medium': model === m }">
                        {{ m }}
                    </span>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
