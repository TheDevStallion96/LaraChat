export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant' | 'system';
    parts: Array<{
        type: 'text';
        text: string;
    }>;
    metadata?: Record<string, unknown>;
};
