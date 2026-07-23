<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\MetricData;
use App\Monitoring\DTOs\ProcessMetric;

class ProcessCollector implements CollectorInterface
{
    public function collect(): MetricData
    {
        try {
            $processes = $this->getProcesses();
            $stats = $this->calculateStats($processes);

            return new ProcessMetric(
                totalProcesses: $stats['total'],
                runningProcesses: $stats['running'],
                sleepingProcesses: $stats['sleeping'],
                zombieProcesses: $stats['zombie'],
                stoppedProcesses: $stats['stopped'],
                topCpuProcesses: $stats['top_cpu'],
                topMemoryProcesses: $stats['top_memory'],
                allProcesses: $processes
            );
        } catch (\Throwable $e) {
            return new ProcessMetric(
                totalProcesses: 0,
                runningProcesses: 0,
                sleepingProcesses: 0,
                zombieProcesses: 0,
                stoppedProcesses: 0,
                topCpuProcesses: [],
                topMemoryProcesses: [],
                allProcesses: [],
                errorMessage: $e->getMessage(),
                success: false
            );
        }
    }

    public function getName(): string
    {
        return 'processes';
    }

    public function getInterval(): int
    {
        return 1;
    }

    public function isAvailable(): bool
    {
        return is_dir('/proc');
    }

    private function getProcesses(): array
    {
        $processes = [];
        $procDir = '/proc';

        if (! is_dir($procDir)) {
            return [];
        }

        $entries = scandir($procDir);

        foreach ($entries as $entry) {
            if (! ctype_digit($entry)) {
                continue;
            }

            $pid = (int) $entry;
            $statPath = "/proc/{$pid}/stat";
            $statusPath = "/proc/{$pid}/status";
            $cmdlinePath = "/proc/{$pid}/cmdline";

            if (! is_readable($statPath) || ! is_readable($statusPath)) {
                continue;
            }

            try {
                $statContent = file_get_contents($statPath);
                $statusContent = file_get_contents($statusPath);
                $cmdline = file_exists($cmdlinePath) ? file_get_contents($cmdlinePath) : '';
                $cmdline = trim(str_replace("\0", ' ', $cmdline));

                $process = $this->parseProcessStat($pid, $statContent, $statusContent, $cmdline);
                if ($process) {
                    $processes[] = $process;
                }
            } catch (\Throwable) {
                // Process might have ended
                continue;
            }
        }

        return $processes;
    }

    private function parseProcessStat(int $pid, string $statContent, string $statusContent, string $cmdline): ?array
    {
        // Parse /proc/pid/stat
        // Format: pid (comm) state ppid pgrp session tty_nr tpgid flags minflt cmajflt ...
        $statRegex = '/^(\d+)\s+\(([^)]+)\)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/';

        if (! preg_match($statRegex, $statContent, $matches)) {
            return null;
        }

        $state = $matches[3];
        $utime = (int) $matches[13];
        $stime = (int) $matches[14];
        $cutime = (int) $matches[15];
        $cstime = (int) $matches[16];
        $startTime = (int) $matches[21];
        $vsize = (int) $matches[22];
        $rss = (int) $matches[23];

        // Parse /proc/pid/status for memory info
        $memInfo = $this->parseStatus($statusContent);

        // Calculate CPU usage percentage (approximate)
        $totalTime = $utime + $stime + $cutime + $cstime;
        $cpuPercent = 0; // Would need previous sample for accurate calculation

        return [
            'pid' => $pid,
            'name' => $matches[2],
            'cmdline' => $cmdline ?: $matches[2],
            'state' => $this->getStateName($state),
            'state_char' => $state,
            'ppid' => (int) $matches[4],
            'cpu_percent' => round($cpuPercent, 2),
            'memory_percent' => $memInfo['mem_percent'] ?? 0,
            'memory_rss' => $rss * 4096, // Convert pages to bytes
            'memory_vms' => $vsize,
            'threads' => (int) $matches[19],
            'priority' => (int) $matches[17],
            'nice' => (int) $matches[18],
            'start_time' => $startTime,
        ];
    }

    private function parseStatus(string $content): array
    {
        $info = [];
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s*(\w*)/', $line, $matches)) {
                $info[$matches[1]] = [
                    'value' => (int) $matches[2],
                    'unit' => $matches[3],
                ];
            }
        }

        $memPercent = 0;
        if (isset($info['VmRSS']) && isset($info['MemTotal'])) {
            $memPercent = ($info['VmRSS']['value'] / $info['MemTotal']['value']) * 100;
        }

        return [
            'mem_percent' => round($memPercent, 2),
        ];
    }

    private function getStateName(string $state): string
    {
        return match ($state) {
            'R' => 'Running',
            'S' => 'Sleeping',
            'D' => 'Disk Sleep',
            'Z' => 'Zombie',
            'T' => 'Stopped',
            't' => 'Tracing Stop',
            'X' => 'Dead',
            'x' => 'Dead',
            'K' => 'Wakekill',
            'W' => 'Waking',
            'P' => 'Parked',
            'I' => 'Idle',
            default => 'Unknown',
        };
    }

    private function calculateStats(array $processes): array
    {
        $stats = [
            'total' => count($processes),
            'running' => 0,
            'sleeping' => 0,
            'zombie' => 0,
            'stopped' => 0,
            'top_cpu' => [],
            'top_memory' => [],
        ];

        foreach ($processes as $proc) {
            match ($proc['state_char']) {
                'R' => $stats['running']++,
                'S', 'D', 'I' => $stats['sleeping']++,
                'Z' => $stats['zombie']++,
                'T', 't' => $stats['stopped']++,
                default => null,
            };
        }

        // Top 10 by CPU
        usort($processes, fn ($a, $b) => $b['cpu_percent'] <=> $a['cpu_percent']);
        $stats['top_cpu'] = array_slice($processes, 0, 10);

        // Top 10 by Memory
        usort($processes, fn ($a, $b) => $b['memory_percent'] <=> $a['memory_percent']);
        $stats['top_memory'] = array_slice($processes, 0, 10);

        return $stats;
    }
}
