<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\MemoryMetric;
use App\Monitoring\DTOs\MetricData;

class MemoryCollector implements CollectorInterface
{
    public function collect(): MetricData
    {
        try {
            $memInfo = $this->parseMemInfo();
            $swapInfo = $this->parseSwapInfo();

            return new MemoryMetric(
                totalBytes: $memInfo['MemTotal'] * 1024,
                usedBytes: ($memInfo['MemTotal'] - $memInfo['MemAvailable']) * 1024,
                freeBytes: $memInfo['MemFree'] * 1024,
                availableBytes: $memInfo['MemAvailable'] * 1024,
                cachedBytes: $memInfo['Cached'] * 1024,
                buffersBytes: $memInfo['Buffers'] * 1024,
                swapTotalBytes: $swapInfo['SwapTotal'] * 1024,
                swapUsedBytes: ($swapInfo['SwapTotal'] - $swapInfo['SwapFree']) * 1024,
                swapFreeBytes: $swapInfo['SwapFree'] * 1024
            );
        } catch (\Throwable $e) {
            return new MemoryMetric(
                totalBytes: 0,
                usedBytes: 0,
                freeBytes: 0,
                availableBytes: 0,
                cachedBytes: 0,
                buffersBytes: 0,
                swapTotalBytes: 0,
                swapUsedBytes: 0,
                swapFreeBytes: 0,
                errorMessage: $e->getMessage(),
                success: false
            );
        }
    }

    public function getName(): string
    {
        return 'memory';
    }

    public function getInterval(): int
    {
        return 1;
    }

    public function isAvailable(): bool
    {
        return is_readable('/proc/meminfo');
    }

    private function parseMemInfo(): array
    {
        $content = file_get_contents('/proc/meminfo');
        $info = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+(\w+)/', $line, $matches)) {
                $info[$matches[1]] = (int) $matches[2];
            }
        }

        return array_merge([
            'MemTotal' => 0,
            'MemFree' => 0,
            'MemAvailable' => 0,
            'Buffers' => 0,
            'Cached' => 0,
        ], $info);
    }

    private function parseSwapInfo(): array
    {
        $content = file_get_contents('/proc/meminfo');
        $info = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^(Swap\w+):\s+(\d+)\s+(\w+)/', $line, $matches)) {
                $info[$matches[1]] = (int) $matches[2];
            }
        }

        return array_merge([
            'SwapTotal' => 0,
            'SwapFree' => 0,
        ], $info);
    }
}
