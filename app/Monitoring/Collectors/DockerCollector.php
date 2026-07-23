<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\DockerMetric;
use App\Monitoring\DTOs\MetricData;

class DockerCollector implements CollectorInterface
{
    public function collect(): MetricData
    {
        if (! $this->isDockerAvailable()) {
            return new DockerMetric(
                containers: [],
                totalContainers: 0,
                runningContainers: 0,
                stoppedContainers: 0,
                totalCpuUsage: 0,
                totalMemoryUsage: 0,
                networkStats: [],
                diskStats: [],
                errorMessage: 'Docker not available',
                success: false
            );
        }

        try {
            $containers = $this->getContainerStats();
            $systemInfo = $this->getSystemInfo();

            $running = 0;
            $stopped = 0;
            $totalCpu = 0;
            $totalMem = 0;
            $networkStats = [];
            $diskStats = [];

            foreach ($containers as $container) {
                if ($container['status'] === 'running') {
                    $running++;
                    $totalCpu += $container['cpu_percent'] ?? 0;
                    $totalMem += $container['memory_usage_mb'] ?? 0;

                    $networkStats[$container['id']] = [
                        'name' => $container['name'],
                        'rx_bytes' => $container['network_rx_bytes'] ?? 0,
                        'tx_bytes' => $container['network_tx_bytes'] ?? 0,
                    ];

                    $diskStats[$container['id']] = [
                        'name' => $container['name'],
                        'read_bytes' => $container['block_read_bytes'] ?? 0,
                        'write_bytes' => $container['block_write_bytes'] ?? 0,
                    ];
                } else {
                    $stopped++;
                }
            }

            return new DockerMetric(
                containers: $containers,
                totalContainers: count($containers),
                runningContainers: $running,
                stoppedContainers: $stopped,
                totalCpuUsage: round($totalCpu, 2),
                totalMemoryUsage: round($totalMem, 2),
                networkStats: $networkStats,
                diskStats: $diskStats
            );
        } catch (\Throwable $e) {
            return new DockerMetric(
                containers: [],
                totalContainers: 0,
                runningContainers: 0,
                stoppedContainers: 0,
                totalCpuUsage: 0,
                totalMemoryUsage: 0,
                networkStats: [],
                diskStats: [],
                errorMessage: $e->getMessage(),
                success: false
            );
        }
    }

    public function getName(): string
    {
        return 'docker';
    }

    public function getInterval(): int
    {
        return 5;
    }

    public function isAvailable(): bool
    {
        return $this->isDockerAvailable();
    }

    private function isDockerAvailable(): bool
    {
        if (! shell_exec('which docker 2>/dev/null')) {
            return false;
        }

        $output = shell_exec('docker info --format "{{.ServerVersion}}" 2>/dev/null');

        return $output !== false && trim($output) !== '';
    }

    private function getContainerStats(): array
    {
        $output = shell_exec('docker stats --no-stream --format "{{.ID}},{{.Name}},{{.Container}},{{.CPUPerc}},{{.MemUsage}},{{.MemPerc}},{{.NetIO}},{{.BlockIO}},{{.PIDs}},{{.Status}}" 2>/dev/null');

        if (! $output) {
            return [];
        }

        $containers = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode(',', $line);
            if (count($parts) < 10) {
                continue;
            }

            $cpuPercent = (float) str_replace('%', '', $parts[3]);
            $memParts = explode(' / ', $parts[4]);
            $memUsage = $this->parseMemory($memParts[0] ?? '0');
            $memLimit = $this->parseMemory($memParts[1] ?? '0');
            $memPercent = (float) str_replace('%', '', $parts[5]);
            $netParts = explode(' / ', $parts[6]);
            $blockParts = explode(' / ', $parts[7]);

            $containers[] = [
                'id' => $parts[0],
                'name' => $parts[1],
                'container' => $parts[2],
                'status' => trim($parts[9]),
                'cpu_percent' => $cpuPercent,
                'memory_usage_bytes' => $memUsage,
                'memory_limit_bytes' => $memLimit,
                'memory_usage_mb' => round($memUsage / 1024 / 1024, 2),
                'memory_limit_mb' => round($memLimit / 1024 / 1024, 2),
                'memory_percent' => $memPercent,
                'network_rx_bytes' => $this->parseMemory($netParts[0] ?? '0'),
                'network_tx_bytes' => $this->parseMemory($netParts[1] ?? '0'),
                'block_read_bytes' => $this->parseMemory($blockParts[0] ?? '0'),
                'block_write_bytes' => $this->parseMemory($blockParts[1] ?? '0'),
                'pids' => (int) $parts[8],
            ];
        }

        return $containers;
    }

    private function getSystemInfo(): array
    {
        $output = shell_exec('docker system df --format "{{.Type}},{{.Total}},{{.Active}},{{.Size}},{{.Reclaimable}}" 2>/dev/null');
        $info = [];

        if ($output) {
            foreach (explode("\n", trim($output)) as $line) {
                $parts = explode(',', $line);
                if (count($parts) >= 5) {
                    $info[] = [
                        'type' => $parts[0],
                        'total' => (int) $parts[1],
                        'active' => (int) $parts[2],
                        'size' => $this->parseMemory($parts[3]),
                        'reclaimable' => $this->parseMemory($parts[4]),
                    ];
                }
            }
        }

        return $info;
    }

    private function parseMemory(string $value): int
    {
        $value = trim($value);
        $unit = strtoupper(substr($value, -2));
        $number = (float) substr($value, 0, -2);

        return match ($unit) {
            'KB' => (int) ($number * 1024),
            'MB' => (int) ($number * 1024 * 1024),
            'GB' => (int) ($number * 1024 * 1024 * 1024),
            'TB' => (int) ($number * 1024 * 1024 * 1024 * 1024),
            'B' => (int) $number,
            default => (int) $number,
        };
    }
}
