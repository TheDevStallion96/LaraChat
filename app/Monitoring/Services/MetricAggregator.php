<?php

namespace App\Monitoring\Services;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\Contracts\MetricAggregatorInterface;
use App\Monitoring\DTOs\SystemMetrics;

class MetricAggregator implements MetricAggregatorInterface
{
    /** @var array<string, CollectorInterface> */
    private array $collectors = [];

    public function registerCollector(CollectorInterface $collector): void
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    public function aggregate(): SystemMetrics
    {
        $metrics = new SystemMetrics;

        foreach ($this->collectors as $name => $collector) {
            if (! $collector->isAvailable()) {
                continue;
            }

            $data = $collector->collect();
            $metrics = $this->assignMetric($metrics, $name, $data);
        }

        return $metrics;
    }

    public function getAvailableCollectors(): array
    {
        return array_filter($this->collectors, fn ($c) => $c->isAvailable());
    }

    public function getCollector(string $name): ?CollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    private function assignMetric(SystemMetrics $metrics, string $name, $data): SystemMetrics
    {
        $property = $name;
        if (property_exists($metrics, $property)) {
            return new SystemMetrics(
                cpu: $property === 'cpu' ? $data : $metrics->cpu,
                memory: $property === 'memory' ? $data : $metrics->memory,
                disk: $property === 'disk' ? $data : $metrics->disk,
                network: $property === 'network' ? $data : $metrics->network,
                processes: $property === 'processes' ? $data : $metrics->processes,
                temperature: $property === 'temperature' ? $data : $metrics->temperature,
                gpu: $property === 'gpu' ? $data : $metrics->gpu,
                docker: $property === 'docker' ? $data : $metrics->docker,
                queue: $property === 'queue' ? $data : $metrics->queue,
                ollama: $property === 'ollama' ? $data : $metrics->ollama,
                timestamp: time()
            );
        }

        return $metrics;
    }
}
