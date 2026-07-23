<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

#[MaxSteps(10)]
class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
        public array $enabledTools = [],
        public ?string $customInstructions = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $instructions = 'You are a helpful AI assistant. Provide clear, concise, and accurate responses.
Format your responses using markdown when appropriate, including code blocks with syntax highlighting for programming questions.';

        if ($this->customInstructions) {
            $instructions .= "\n\n".$this->customInstructions;
        }

        return $instructions;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<int, WebSearch|WebFetch>
     */
    public function tools(): iterable
    {
        $tools = [];

        if (in_array('web-search', $this->enabledTools, true)) {
            $tools[] = new WebSearch;
        }

        if (in_array('web-fetch', $this->enabledTools, true)) {
            $tools[] = new WebFetch;
        }

        return $tools;
    }
}
