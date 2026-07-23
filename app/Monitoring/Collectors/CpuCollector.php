<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\CpuMetric;
use App\Monitoring\DTOs\MetricData;

class CpuCollector implements CollectorInterface
{
    private array $prevCpuTimes = [];

    private bool $firstRun = true;

    public function collect(): MetricData
    {
        try {
            $cpuInfo = $this->parseCpuInfo();
            $loadAvg = $this->getLoadAverage();
            $cpuTimes = $this->getCpuTimes();

            if ($this->firstRun) {
                $this->prevCpuTimes = $cpuTimes;
                $this->firstRun = false;

                return new CpuMetric(
                    totalUsage: 0,
                    perCoreUsage: array_fill(0, $cpuInfo['coreCount'], 0),
                    coreCount: $cpuInfo['coreCount'],
                    loadAverage1m: $loadAvg[0],
                    loadAverage5m: $loadAvg[1],
                    loadAverage15m: $loadAvg[2],
                    cpuTimes: $cpuTimes['total']
                );
            }

            $perCoreUsage = [];
            $totalUsage = 0;

            foreach ($cpuTimes['cores'] as $coreId => $currentTimes) {
                $prevTimes = $this->prevCpuTimes['cores'][$coreId] ?? $currentTimes;
                $usage = $this->calculateCpuUsage($prevTimes, $currentTimes);
                $perCoreUsage[$coreId] = $usage;
                $totalUsage += $usage;
            }

            $avgUsage = $cpuInfo['coreCount'] > 0 ? $totalUsage / $cpuInfo['coreCount'] : 0;

            $this->prevCpuTimes = $cpuTimes;

            return new CpuMetric(
                totalUsage: round($avgUsage, 2),
                perCoreUsage: array_map(fn ($v) => round($v, 2), $perCoreUsage),
                coreCount: $cpuInfo['coreCount'],
                loadAverage1m: $loadAvg[0],
                loadAverage5m: $loadAvg[1],
                loadAverage15m: $loadAvg[2],
                cpuTimes: $cpuTimes['total']
            );
        } catch (\Throwable $e) {
            return new CpuMetric(
                totalUsage: 0,
                perCoreUsage: [],
                coreCount: 0,
                loadAverage1m: 0,
                loadAverage5m: 0,
                loadAverage15m: 0,
                cpuTimes: [],
                errorMessage: $e->getMessage(),
                success: false
            );
        }
    }

    public function getName(): string
    {
        return 'cpu';
    }

    public function getInterval(): int
    {
        return 1;
    }

    public function isAvailable(): bool
    {
        return is_readable('/proc/stat') && is_readable('/proc/loadavg');
    }

    private function parseCpuInfo(): array
    {
        $content = file_get_contents('/proc/cpuinfo');
        $coreCount = 0;
        $modelName = '';

        foreach (explode("\n", $content) as $line) {
            if (str_starts_with($line, 'processor')) {
                $coreCount++;
            }
            if (str_starts_with($line, 'model name') && $modelName === '') {
                $modelName = trim(explode(':', $line)[1] ?? '');
            }
        }

        return [
            'coreCount' => max(1, $coreCount),
            'modelName' => $modelName,
        ];
    }

    private function getLoadAverage(): array
    {
        $content = file_get_contents('/proc/loadavg');
        $parts = explode(' ', trim($content));

        return [
            (float) ($parts[0] ?? 0),
            (float) ($parts[1] ?? 0),
            (float) ($parts[2] ?? 0),
        ];
    }

    private function getCpuTimes(): array
    {
        $content = file_get_contents('/proc/stat');
        $lines = explode("\n", $content);
        $cores = [];
        $total = [];

        foreach ($lines as $line) {
            if (! str_starts_with($line, 'cpu')) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            $name = array_shift($parts);
            $times = array_map('intval', $parts);

            if ($name === 'cpu') {
                $total = array_combine(
                    ['user', 'nice', 'system', 'idle', 'iowait', 'irq', 'softirq', 'steal', 'guest', 'guest_nice'],
                    array_pad($times, 10, 0)
                );
            } elseif (preg_match('/^cpu(\d+)$/', $name, $matches)) {
                $cores[(int) $matches[1]] = array_combine(
                    ['user', 'nice', 'system', 'idle', 'iowait', 'irq', 'softirq', 'steal', 'guest', 'guest_nice'],
                    array_pad($times, 10, 0)
                );
            }
        }

        ksort($cores);

        return [
            'total' => $total,
            'cores' => $cores,
        ];
    }

    private function calculateCpuUsage(array $prev, array $current): float
    {
        $prevIdle = ($prev['idle'] ?? 0) + ($prev['iowait'] ?? 0);
        $currentIdle = ($current['idle'] ?? 0) + ($current['iowait'] ?? 0);

        $prevTotal = array_sum($prev);
        $currentTotal = array_sum($current);

        $totalDiff = $currentTotal - $prevTotal;
        $idleDiff = $currentIdle - $prevIdle;

        if ($totalDiff === 0) {
            return 0;
        }

        return (($totalDiff - $idleDiff) / $totalDiff) * 100;
    }
}
