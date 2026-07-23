<?php

namespace App\Monitoring\DTOs;

class DiskMetric extends MetricData
{
    public function __construct(
        public readonly array $partitions,
        public readonly int $totalBytes,
        public readonly int $usedBytes,
        public readonly int $freeBytes,
        public readonly array $ioStats,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('disk', time(), $success, $errorMessage);
    }

    public function getUsagePercent(): float
    {
        if ($this->totalBytes === 0) {
            return 0;
        }
        return round(($this->usedBytes / $this->totalBytes) * 100, 2);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'partitions' => $this->partitions,
            'total_bytes' => $this->totalBytes,
            'used_bytes' => $this->usedBytes,
            'free_bytes' => $this->freeBytes,
            'usage_percent' => $this->getUsagePercent(),
            'io_stats' => $this->ioStats,
        ];
    }
}