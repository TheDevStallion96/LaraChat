<?php

namespace App\Monitoring\DTOs;

class QueueMetric extends MetricData
{
    public function __construct(
        public readonly array $queues,
        public readonly int $totalJobs,
        public readonly int $pendingJobs,
        public readonly int $runningJobs,
        public readonly int $failedJobs,
        public readonly int $workers,
        public readonly array $workerDetails,
        public readonly array $failedJobDetails,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('queue', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'queues' => $this->queues,
            'total_jobs' => $this->totalJobs,
            'pending_jobs' => $this->pendingJobs,
            'running_jobs' => $this->runningJobs,
            'failed_jobs' => $this->failedJobs,
            'workers' => $this->workers,
            'worker_details' => $this->workerDetails,
            'failed_job_details' => $this->failedJobDetails,
        ];
    }
}