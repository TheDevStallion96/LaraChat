<script setup lang="ts">
import { computed, onMounted, ref, nextTick, watch } from 'vue';
import { marked } from 'marked';
import hljs from 'highlight.js';

const props = defineProps<{
    content: string;
}>();

const renderedContent = ref('');

marked.setOptions({
    gfm: true,
    breaks: true,
});

const renderer = new marked.Renderer();

renderer.code = function ({ text, lang }: { text: string; lang?: string }) {
    const language = lang && hljs.getLanguage(lang) ? lang : 'plaintext';
    const highlighted = hljs.highlight(text, { language }).value;
    return `<pre class="hljs rounded-lg bg-neutral-900 p-4 text-sm text-neutral-100 overflow-x-auto"><code class="language-${language}">${highlighted}</code></pre>`;
};

renderer.inlineCode = function ({ text }: { text: string }) {
    return `<code class="rounded bg-neutral-100 px-1.5 py-0.5 text-sm font-mono text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">${text}</code>`;
};

marked.use({ renderer });

watch(
    () => props.content,
    (newContent) => {
        if (newContent) {
            renderedContent.value = marked.parse(newContent) as string;
        } else {
            renderedContent.value = '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="content"
        class="prose prose-sm dark:prose-invert max-w-none"
        v-html="renderedContent"
    />
    <div v-else class="flex items-center gap-1">
        <span class="h-2 w-2 animate-pulse rounded-full bg-neutral-400" />
        <span class="h-2 w-2 animate-pulse rounded-full bg-neutral-400 [animation-delay:0.2s]" />
        <span class="h-2 w-2 animate-pulse rounded-full bg-neutral-400 [animation-delay:0.4s]" />
    </div>
</template>
