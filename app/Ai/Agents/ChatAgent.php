<?php

namespace App\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class ChatAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            You are a helpful AI assistant. Provide clear, concise, and accurate responses.
            Format your responses using markdown when appropriate, including code blocks with
            syntax highlighting for programming questions.
            PROMPT;
    }
}
