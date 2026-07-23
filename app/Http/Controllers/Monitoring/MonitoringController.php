<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Monitoring\Contracts\MetricAggregatorInterface;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function __invoke(MetricAggregatorInterface $aggregator): Response
    {
        $metrics = $aggregator->aggregate();
        $availableCollectors = array_keys($aggregator->getAvailableCollectors());

        $aiRequests = AiRequest::query()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AiRequest $r) => [
                'id' => $r->id,
                'provider' => $r->provider,
                'model' => $r->model,
                'prompt_tokens' => $r->prompt_tokens,
                'completion_tokens' => $r->completion_tokens,
                'total_tokens' => $r->total_tokens,
                'duration_ms' => $r->duration_ms,
                'status' => $r->status,
                'started_at' => $r->started_at?->toISOString(),
                'completed_at' => $r->completed_at?->toISOString(),
            ])
            ->all();

        return Inertia::render('Monitoring/Index', [
            'initialMetrics' => $metrics->toArray(),
            'availableCollectors' => $availableCollectors,
            'channel' => config('monitoring.broadcast.channel', 'system-metrics'),
            'event' => '.'.config('monitoring.broadcast.event', 'metrics.updated'),
            'aiRequests' => $aiRequests,
        ]);
    }
}
