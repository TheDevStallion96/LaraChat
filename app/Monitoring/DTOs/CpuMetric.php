<?php

namespace App\Monitoring\DTOs;

class CpuMetric extends MetricData
{
    public function __construct(
        public readonly float $totalUsage,
        public readonly array $perCoreUsage,
        public readonly int $coreCount,
        public readonly float $loadAverage1m,
        public readonly float $loadAverage5m,
        public readonly float $loadAverage15m,
        public readonly array $cpuTimes, // user, system, idle, iowait, irq, softirq, steal, guest
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('cpu', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'total_usage' => round($this->totalUsage, 2),
            'per_core_usage' => array_map(fn ($v) => round($v, 2), $this->perCoreUsage),
            'core_count' => $this->coreCount,
            'load_average' => [
                '1m' => round($this->loadAverage1m, 2),
                '5m' => round($this->loadAverage5m, 2),
                '15m' => round($this->loadAverage15m, 2),
            ],
            'cpu_times' => $this->cpuTimes,
        ];
    }
}
