export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant' | 'system';
    parts: Array<{
        type: 'text';
        text: string;
    }>;
    metadata?: Record<string, unknown>;
};

export type ToolName = 'web-search' | 'web-fetch';

export type ChatConfig = {
    provider: string;
    model: string;
    tools: ToolName[];
    instructions?: string;
};
