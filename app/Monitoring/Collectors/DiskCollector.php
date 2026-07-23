<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\DiskMetric;
use App\Monitoring\DTOs\MetricData;

class DiskCollector implements CollectorInterface
{
    private array $prevIoStats = [];
    private bool $firstRun = true;

    public function collect(): MetricData
    {
        try {
            $partitions = $this->getPartitions();
            $ioStats = $this->getDiskIoStats();

            $totalBytes = 0;
            $usedBytes = 0;
            $freeBytes = 0;

            foreach ($partitions as $partition) {
                $totalBytes += $partition['total_bytes'];
                $usedBytes += $partition['used_bytes'];
                $freeBytes += $partition['free_bytes'];
            }

            if ($this->firstRun) {
                $this->prevIoStats = $ioStats;
                $this->firstRun = false;

                return new DiskMetric(
                    partitions: $partitions,
                    totalBytes: $totalBytes,
                    usedBytes: $usedBytes,
                    freeBytes: $freeBytes,
                    ioStats: []
                );
            }

            $deltaIoStats = [];
            foreach ($ioStats as $device => $current) {
                $prev = $this->prevIoStats[$device] ?? $current;
                $deltaIoStats[$device] = [
                    'reads' => $current['reads'] - $prev['reads'],
                    'writes' => $current['writes'] - $prev['writes'],
                    'read_bytes' => $current['read_bytes'] - $prev['read_bytes'],
                    'write_bytes' => $current['write_bytes'] - $prev['write_bytes'],
                    'read_time' => $current['read_time'] - $prev['read_time'],
                    'write_time' => $current['write_time'] - $prev['write_time'],
                ];
            }

            $this->prevIoStats = $ioStats;

            return new DiskMetric(
                partitions: $partitions,
                totalBytes: $totalBytes,
                usedBytes: $usedBytes,
                freeBytes: $freeBytes,
                ioStats: $deltaIoStats
            );
        } catch (\Throwable $e) {
            return new DiskMetric(
                partitions: [],
                totalBytes: 0,
                usedBytes: 0,
                freeBytes: 0,
                ioStats: [],
                $e->getMessage(),
                false
            );
        }
    }

    public function getName(): string
    {
        return 'disk';
    }

    public function getInterval(): int
    {
        return 1;
    }

    public function isAvailable(): bool
    {
        return is_readable('/proc/partitions') && is_readable('/proc/diskstats');
    }

    private function getPartitions(): array
    {
        $partitions = [];
        $dfOutput = shell_exec('df -B1 --output=source,target,size,used,avail,pcent,fstype 2>/dev/null');

        if ($dfOutput === false) {
            return [];
        }

        $lines = explode("\n", trim($dfOutput));
        array_shift($lines); // Remove header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 7) {
                continue;
            }

            $device = $parts[0];
            $mountPoint = $parts[1];
            $totalBytes = (int) $parts[2];
            $usedBytes = (int) $parts[3];
            $freeBytes = (int) $parts[4];
            $usagePercent = (float) str_replace('%', '', $parts[5]);
            $fsType = $parts[6];

            // Skip virtual filesystems
            if (in_array($fsType, ['tmpfs', 'devtmpfs', 'proc', 'sysfs', 'cgroup', 'cgroup2', 'overlay', 'squashfs', 'autofs'])) {
                continue;
            }

            $partitions[] = [
                'device' => $device,
                'mount_point' => $mountPoint,
                'filesystem' => $fsType,
                'total_bytes' => $totalBytes,
                'used_bytes' => $usedBytes,
                'free_bytes' => $freeBytes,
                'usage_percent' => $usagePercent,
            ];
        }

        return $partitions;
    }

    private function getDiskIoStats(): array
    {
        $content = file_get_contents('/proc/diskstats');
        $stats = [];

        foreach (explode("\n", $content) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 14) {
                continue;
            }

            $device = $parts[2];
            // Skip partitions (e.g., sda1, sda2) - only track whole disks
            if (preg_match('/^(sd|vd|nvme|hd|xvd|dm-|md)\d+$/', $device) || preg_match('/^nvme\d+n\d+$/', $device)) {
                $stats[$device] = [
                    'reads' => (int) $parts[3],
                    'reads_merged' => (int) $parts[4],
                    'read_sectors' => (int) $parts[5],
                    'read_time' => (int) $parts[6],
                    'writes' => (int) $parts[7],
                    'writes_merged' => (int) $parts[8],
                    'write_sectors' => (int) $parts[9],
                    'write_time' => (int) $parts[10],
                    'io_in_progress' => (int) $parts[11],
                    'io_time' => (int) $parts[12],
                    'weighted_io_time' => (int) $parts[13],
                    'read_bytes' => (int) $parts[5] * 512,
                    'write_bytes' => (int) $parts[9] * 512,
                ];
            }
        }

        return $stats;
    }
}