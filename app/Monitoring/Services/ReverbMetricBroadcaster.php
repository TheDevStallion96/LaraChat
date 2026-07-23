<?php

namespace App\Monitoring\Services;

use App\Events\SystemMetricsUpdated;
use App\Monitoring\Contracts\MetricBroadcasterInterface;
use App\Monitoring\DTOs\SystemMetrics;

class ReverbMetricBroadcaster implements MetricBroadcasterInterface
{
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = config('monitoring.enabled', true);
    }

    public function broadcast(SystemMetrics $metrics): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            SystemMetricsUpdated::dispatch($metrics);
        } catch (\Throwable $e) {
            logger()->error('Failed to broadcast metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
