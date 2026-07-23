<?php

namespace App\Events;

use App\Monitoring\DTOs\SystemMetrics;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemMetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SystemMetrics $metrics
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('system-metrics');
    }

    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }

    public function broadcastWith(): array
    {
        return $this->metrics->toArray();
    }
}