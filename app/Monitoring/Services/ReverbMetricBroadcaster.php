<?php

namespace App\Monitoring\Services;

use App\Monitoring\Contracts\MetricBroadcasterInterface;
use App\Monitoring\DTOs\SystemMetrics;
use Illuminate\Support\Facades\Broadcast;

class ReverbMetricBroadcaster implements MetricBroadcasterInterface
{
    private string $channel;
    private string $event;
    private bool $enabled;

    public function __construct()
    {
        $this->channel = config('monitoring.broadcasting.channel', 'system-metrics');
        $this->event = config('monitoring.broadcasting.event', 'metrics.updated');
        $this->enabled = config('monitoring.broadcasting.enabled', true);
    }

    public function broadcast(SystemMetrics $metrics): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            Broadcast::channel($this->channel, function () {
                return true; // Public channel
            });

            broadcast($this->event, $metrics->toArray())
                ->to($this->channel);
        } catch (\Throwable $e) {
            // Log error but don't break the collection cycle
            logger()->error('Failed to broadcast metrics', [
                'error' => $e->getMessage(),
                'channel' => $this->channel,
            ]);
        }
    }
}