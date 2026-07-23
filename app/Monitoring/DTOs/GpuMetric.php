<?php

namespace App\Monitoring\DTOs;

class GpuMetric extends MetricData
{
    public function __construct(
        public readonly array $gpus,
        public readonly int $gpuCount,
        public readonly float $totalGpuMemory,
        public readonly float $usedGpuMemory,
        public readonly float $avgGpuUtilization,
        public readonly float $avgMemoryUtilization,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('gpu', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'gpus' => $this->gpus,
            'gpu_count' => $this->gpuCount,
            'total_gpu_memory_mb' => round($this->totalGpuMemory, 1),
            'used_gpu_memory_mb' => round($this->usedGpuMemory, 1),
            'avg_gpu_utilization' => round($this->avgGpuUtilization, 1),
            'avg_memory_utilization' => round($this->avgMemoryUtilization, 1),
        ];
    }
}
