<?php

namespace App\Monitoring\DTOs;

class ProcessMetric extends MetricData
{
    public function __construct(
        public readonly int $totalProcesses,
        public readonly int $runningProcesses,
        public readonly int $sleepingProcesses,
        public readonly int $zombieProcesses,
        public readonly int $stoppedProcesses,
        public readonly array $topCpuProcesses,
        public readonly array $topMemoryProcesses,
        public readonly array $allProcesses,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('processes', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'total_processes' => $this->totalProcesses,
            'running_processes' => $this->runningProcesses,
            'sleeping_processes' => $this->sleepingProcesses,
            'zombie_processes' => $this->zombieProcesses,
            'stopped_processes' => $this->stoppedProcesses,
            'top_cpu_processes' => $this->topCpuProcesses,
            'top_memory_processes' => $this->topMemoryProcesses,
            'all_processes' => $this->allProcesses,
        ];
    }
}