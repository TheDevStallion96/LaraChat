<?php

namespace App\Http\Controllers\Monitoring;

use App\Monitoring\Contracts\MetricAggregatorInterface;
use App\Monitoring\DTOs\SystemMetrics;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController
{
    public function __invoke(MetricAggregatorInterface $aggregator): Response
    {
        $metrics = $aggregator->aggregate();
        $availableCollectors = $aggregator->getAvailableCollectors();

        return Inertia::render('Monitoring/Index', [
            'initialMetrics' => $metrics->toArray(),
            'availableCollectors' => $availableCollectors,
            'channel' => config('monitoring.broadcasting.channel', 'system-metrics'),
            'event' => config('monitoring.broadcasting.event', 'metrics.updated'),
        ]);
    }
}