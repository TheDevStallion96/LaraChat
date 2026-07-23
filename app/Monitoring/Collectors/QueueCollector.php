<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\QueueMetric;
use App\Monitoring\DTOs\MetricData;
use Illuminate\Support\Facades\Redis;

class QueueCollector implements CollectorInterface
{
    public function collect(): MetricData
    {
        try {
            $queues = $this->getQueueStats();
            $workers = $this->getWorkerStats();
            $failedJobs = $this->getFailedJobStats();

            $totalJobs = 0;
            $pendingJobs = 0;
            $runningJobs = 0;
            $failedJobs = 0;

            foreach ($queues as $queue) {
                $totalJobs += $queue['total'] ?? 0;
                $pendingJobs += $queue['pending'] ?? 0;
                $runningJobs += $queue['running'] ?? 0;
                $failedJobs += $queue['failed'] ?? 0;
            }

            return new QueueMetric(
                queues: $queues,
                totalJobs: $totalJobs,
                pendingJobs: $pendingJobs,
                runningJobs: $runningJobs,
                failedJobs: $failedJobs,
                workers: count($workers),
                workerDetails: $workers,
                failedJobDetails: $failedJobs
            );
        } catch (\Throwable $e) {
            return new QueueMetric(
                queues: [],
                totalJobs: 0,
                pendingJobs: 0,
                runningJobs: 0,
                failedJobs: 0,
                workers: 0,
                workerDetails: [],
                failedJobDetails: [],
                $e->getMessage(),
                false
            );
        }
    }

    public function getName(): string
    {
        return 'queue';
    }

    public function getInterval(): int
    {
        return 2;
    }

    public function isAvailable(): bool
    {
        return class_exists(\Illuminate\Queue\Worker::class) && Redis::connection()->ping() === '+PONG';
    }

    private function getQueueStats(): array
    {
        $queues = [];
        $connection = config('queue.default', 'redis');
        $prefix = config("queue.connections.{$connection}.queue", 'queues');
        $prefix = config("queue.connections.{$connection}.prefix", 'laravel');

        // Get all queue keys from Redis
        $keys = Redis::connection()->keys("{$prefix}:queues:*");

        foreach ($keys as $key) {
            $queueName = str_replace("{$prefix}:queues:", '', $key);
            $stats = $this->getQueueDetails($queueName, $prefix);
            if ($stats) {
                $queues[$queueName] = $stats;
            }
        }

        // Also check for horizon queues if using Horizon
        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            $horizonQueues = $this->getHorizonQueueStats();
            $queues = array_merge($queues, $horizonQueues);
        }

        return $queues;
    }

    private function getQueueDetails(string $queueName, string $prefix): ?array
    {
        $waitingKey = "{$prefix}:queues:{$queueName}";
        $delayedKey = "{$prefix}:delayed:{$queueName}";
        $reservedKey = "{$prefix}:reserved:{$queueName}";

        $waiting = Redis::connection()->llen($waitingKey);
        $delayed = Redis::connection()->zcard($delayedKey);
        $reserved = Redis::connection()->llen($reservedKey);

        // Failed jobs are in a separate set
        $failedKey = "{$prefix}:failed";
        $failed = 0;
        if (Redis::connection()->exists($failedKey)) {
            $failedJobs = Redis::connection()->lrange($failedKey, 0, -1);
            foreach ($failedJobs as $job) {
                $jobData = json_decode($job, true);
                if (isset($jobData['queue']) && $jobData['queue'] === $queueName) {
                    $failed++;
                }
            }
        }

        return [
            'name' => $queueName,
            'pending' => $waiting + $delayed,
            'running' => $reserved,
            'failed' => $failed,
            'total' => $waiting + $delayed + $reserved + $failed,
        ];
    }

    private function getHorizonQueueStats(): array
    {
        $queues = [];
        $keys = Redis::connection()->keys('horizon:*:queues:*');

        foreach ($keys as $key) {
            // Parse horizon queue keys
            $parts = explode(':', $key);
            if (count($parts) >= 4) {
                $queueName = $parts[3];
                $size = Redis::connection()->llen($key);
                $queues["horizon_{$queueName}"] = [
                    'name' => "horizon_{$queueName}",
                    'pending' => $size,
                    'running' => 0,
                    'failed' => 0,
                    'total' => $size,
                ];
            }
        }

        return $queues;
    }

    private function getWorkerStats(): array
    {
        $workers = [];

        // Check for Horizon workers
        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            $workers = $this->getHorizonWorkers();
        } else {
            $workers = $this->getStandardWorkers();
        }

        return $workers;
    }

    private function getHorizonWorkers(): array
    {
        $workers = [];
        $keys = Redis::connection()->keys('horizon:workers:*');

        foreach ($keys as $key) {
            $workerData = Redis::connection()->hgetall($key);
            if ($workerData) {
                $workers[] = [
                    'id' => $workerData['id'] ?? basename($key),
                    'name' => $workerData['name'] ?? 'Unknown',
                    'status' => $workerData['status'] ?? 'unknown',
                    'queue' => $workerData['queue'] ?? 'default',
                    'jobs_processed' => (int) ($workerData['jobs_processed'] ?? 0),
                    'memory_usage' => (int) ($workerData['memory_usage'] ?? 0),
                    'started_at' => $workerData['started_at'] ?? null,
                ];
            }
        }

        return $workers;
    }

    private function getStandardWorkers(): array
    {
        // For standard queue workers, we can check supervisor or process list
        $output = shell_exec('ps aux | grep "queue:work" | grep -v grep 2>/dev/null');
        $workers = [];

        if ($output) {
            foreach (explode("\n", trim($output)) as $line) {
                if (preg_match('/queue:work\s+(?:--queue=)?(\S+)?/', $line, $matches)) {
                    $queue = $matches[1] ?? 'default';
                    $workers[] = [
                        'id' => uniqid('worker_'),
                        'name' => 'queue:work',
                        'status' => 'running',
                        'queue' => $queue,
                        'jobs_processed' => 0,
                        'memory_usage' => 0,
                        'started_at' => null,
                    ];
                }
            }
        }

        return $workers;
    }

    private function getFailedJobStats(): array
    {
        $connection = config('queue.default', 'redis');
        $prefix = config("queue.connections.{$connection}.prefix", 'laravel');
        $failedKey = "{$prefix}:failed";

        if (!Redis::connection()->exists($failedKey)) {
            return [];
        }

        $failedJobs = Redis::connection()->lrange($failedKey, 0, 49); // Last 50
        $details = [];

        foreach ($failedJobs as $job) {
            $jobData = json_decode($job, true);
            if ($jobData) {
                $details[] = [
                    'id' => $jobData['uuid'] ?? $jobData['id'] ?? 'unknown',
                    'queue' => $jobData['queue'] ?? 'unknown',
                    'connection' => $jobData['connection'] ?? $connection,
                    'failed_at' => $jobData['failed_at'] ?? null,
                    'exception' => $jobData['exception'] ?? 'Unknown',
                    'exception_message' => $jobData['exception_message'] ?? '',
                ];
            }
        }

        return $details;
    }
}