<?php

namespace App\Monitoring\DTOs;

class MemoryMetric extends MetricData
{
    public function __construct(
        public readonly int $totalBytes,
        public readonly int $usedBytes,
        public readonly int $freeBytes,
        public readonly int $availableBytes,
        public readonly int $cachedBytes,
        public readonly int $buffersBytes,
        public readonly int $swapTotalBytes,
        public readonly int $swapUsedBytes,
        public readonly int $swapFreeBytes,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('memory', time(), $success, $errorMessage);
    }

    public function getUsagePercent(): float
    {
        if ($this->totalBytes === 0) return 0;
        return round(($this->usedBytes / $this->totalBytes) * 100, 2);
    }

    public function getSwapUsagePercent(): float
    {
        if ($this->swapTotalBytes === 0) return 0;
        return round(($this->swapUsedBytes / $this->swapTotalBytes) * 100, 2);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'total_bytes' => $this->totalBytes,
            'used_bytes' => $this->usedBytes,
            'free_bytes' => $this->freeBytes,
            'available_bytes' => $this->availableBytes,
            'cached_bytes' => $this->cachedBytes,
            'buffers_bytes' => $this->buffersBytes,
            'swap_total_bytes' => $this->swapTotalBytes,
            'swap_used_bytes' => $this->swapUsedBytes,
            'swap_free_bytes' => $this->swapFreeBytes,
            'usage_percent' => $this->getUsagePercent(),
            'swap_usage_percent' => $this->getSwapUsagePercent(),
        ];
    }
}