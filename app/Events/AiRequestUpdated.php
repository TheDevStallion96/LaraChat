<?php

namespace App\Events;

use App\Models\AiRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AiRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly AiRequest $aiRequest
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('ai-requests');
    }

    public function broadcastAs(): string
    {
        return 'ai.request.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->aiRequest->id,
            'user_id' => $this->aiRequest->user_id,
            'conversation_id' => $this->aiRequest->conversation_id,
            'provider' => $this->aiRequest->provider,
            'model' => $this->aiRequest->model,
            'prompt_tokens' => $this->aiRequest->prompt_tokens,
            'completion_tokens' => $this->aiRequest->completion_tokens,
            'total_tokens' => $this->aiRequest->total_tokens,
            'duration_ms' => $this->aiRequest->duration_ms,
            'status' => $this->aiRequest->status,
            'started_at' => $this->aiRequest->started_at?->toISOString(),
            'completed_at' => $this->aiRequest->completed_at?->toISOString(),
        ];
    }
}
