<?php

namespace App\Monitoring\DTOs;

class DockerMetric extends MetricData
{
    public function __construct(
        public readonly array $containers,
        public readonly int $totalContainers,
        public readonly int $runningContainers,
        public readonly int $stoppedContainers,
        public readonly float $totalCpuUsage,
        public readonly float $totalMemoryUsage,
        public readonly array $networkStats,
        public readonly array $diskStats,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('docker', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'containers' => $this->containers,
            'total_containers' => $this->totalContainers,
            'running_containers' => $this->runningContainers,
            'stopped_containers' => $this->stoppedContainers,
            'total_cpu_usage' => round($this->totalCpuUsage, 2),
            'total_memory_usage_mb' => round($this->totalMemoryUsage, 2),
            'network_stats' => $this->networkStats,
            'disk_stats' => $this->diskStats,
        ];
    }
}