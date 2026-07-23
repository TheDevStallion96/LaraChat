<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\GpuMetric;
use App\Monitoring\DTOs\MetricData;

class GpuCollector implements CollectorInterface
{
    private bool $nvidiaSmiAvailable = false;

    public function __construct()
    {
        $this->nvidiaSmiAvailable = $this->checkNvidiaSmi();
    }

    public function collect(): MetricData
    {
        if (!$this->nvidiaSmiAvailable) {
            return new GpuMetric(
                gpus: [],
                gpuCount: 0,
                totalGpuMemory: 0,
                usedGpuMemory: 0,
                avgGpuUtilization: 0,
                avgMemoryUtilization: 0,
                'nvidia-smi not available',
                false
            );
        }

        try {
            $output = shell_exec('nvidia-smi --query-gpu=index,name,temperature.gpu,utilization.gpu,utilization.memory,memory.total,memory.used,memory.free,power.draw,power.limit,fan.speed,pstate --format=csv,noheader,nounits 2>/dev/null');

            if (!$output) {
                throw new \Exception('nvidia-smi returned no output');
            }

            $gpus = [];
            $totalMemory = 0;
            $usedMemory = 0;
            $totalGpuUtil = 0;
            $totalMemUtil = 0;
            $count = 0;

            foreach (explode("\n", trim($output)) as $line) {
                $line = trim($line);
                if ($line === '') continue;

                $parts = array_map('trim', explode(',', $line));
                if (count($parts) < 11) continue;

                [
                    $index,
                    $name,
                    $temp,
                    $gpuUtil,
                    $memUtil,
                    $memTotal,
                    $memUsed,
                    $memFree,
                    $powerDraw,
                    $powerLimit,
                    $fanSpeed,
                    $pstate
                ] = $parts;

                $gpus[] = [
                    'index' => (int) $index,
                    'name' => $name,
                    'temperature_c' => (float) $temp,
                    'temperature_f' => round(($temp * 9/5) + 32, 1),
                    'gpu_utilization' => (float) $gpuUtil,
                    'memory_utilization' => (float) $memUtil,
                    'memory_total_mb' => (float) $memTotal,
                    'memory_used_mb' => (float) $memUsed,
                    'memory_free_mb' => (float) $memFree,
                    'power_draw_w' => (float) $powerDraw,
                    'power_limit_w' => (float) $powerLimit,
                    'fan_speed_percent' => $fanSpeed === '[Not Supported]' ? null : (float) $fanSpeed,
                    'pstate' => $pstate,
                ];

                $totalMemory += (float) $memTotal;
                $usedMemory += (float) $memUsed;
                $totalGpuUtil += (float) $gpuUtil;
                $totalMemUtil += (float) $memUtil;
                $count++;
            }

            return new GpuMetric(
                gpus: $gpus,
                gpuCount: $count,
                totalGpuMemory: $totalMemory,
                usedGpuMemory: $usedMemory,
                avgGpuUtilization: $count > 0 ? $totalGpuUtil / $count : 0,
                avgMemoryUtilization: $count > 0 ? $totalMemUtil / $count : 0
            );
        } catch (\Throwable $e) {
            return new GpuMetric(
                gpus: [],
                gpuCount: 0,
                totalGpuMemory: 0,
                usedGpuMemory: 0,
                avgGpuUtilization: 0,
                avgMemoryUtilization: 0,
                $e->getMessage(),
                false
            );
        }
    }

    public function getName(): string
    {
        return 'gpu';
    }

    public function getInterval(): int
    {
        return 2;
    }

    public function isAvailable(): bool
    {
        return $this->nvidiaSmiAvailable;
    }

    private function checkNvidiaSmi(): bool
    {
        $output = shell_exec('which nvidia-smi 2>/dev/null');
        return !empty(trim($output));
    }
}