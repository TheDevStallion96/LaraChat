<?php

namespace App\Console\Commands;

use App\Monitoring\Contracts\MetricAggregatorInterface;
use App\Monitoring\Contracts\MetricBroadcasterInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSystemMetrics extends Command
{
    protected $signature = 'monitoring:collect 
                            {--interval=1 : Collection interval in seconds}
                            {--duration=0 : Duration to run (0 = infinite)}
                            {--broadcast : Broadcast metrics via Reverb}
                            {--output : Output metrics to console}
                            {--channels=* : Specific channels to broadcast to}';

    protected $description = 'Collect and broadcast system metrics in real-time';

    public function handle(MetricAggregatorInterface $aggregator, MetricBroadcasterInterface $broadcaster): int
    {
        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $broadcast = $this->option('broadcast');
        $output = $this->option('output');
        $channels = $this->option('channels');

        $this->info("Starting system metrics collection...");
        $this->info("Interval: {$interval}s");
        $this->info("Broadcast: " . ($broadcast ? 'enabled' : 'disabled'));
        $this->info("Output: " . ($output ? 'enabled' : 'disabled'));

        if ($channels) {
            $this->info("Channels: " . implode(', ', $channels));
        }

        $startTime = time();
        $iteration = 0;

        while (true) {
            $iteration++;
            $loopStart = microtime(true);

            try {
                $metrics = $aggregator->aggregate();

                if ($output) {
                    $this->outputMetrics($metrics);
                }

                if ($broadcast) {
                    $broadcaster->broadcast($metrics);
                    if ($output) {
                        $this->line("<info>Broadcasted to Reverb</info>");
                    }
                }

            } catch (\Throwable $e) {
                $this->error("Error collecting metrics: {$e->getMessage()}");
                Log::error('Monitoring collection failed', ['error' => $e->getMessage()]);
            }

            // Check duration
            if ($duration > 0 && (time() - $startTime) >= $duration) {
                $this->info("Duration reached. Stopping.");
                break;
            }

            // Sleep for the remaining interval time
            $elapsed = microtime(true) - $loopStart;
            $sleepTime = max(0, $interval - $elapsed);

            if ($sleepTime > 0) {
                usleep((int) ($sleepTime * 1_000_000));
            }
        }

        return Command::SUCCESS;
    }

    private function outputMetrics($metrics): void
    {
        $this->newLine();
        $this->line("<comment>=== Metrics at {$metrics->timestamp} ===</comment>");

        if ($metrics->cpu) {
            $cpu = $metrics->cpu;
            $this->line("CPU: {$cpu->totalUsage}% ({$cpu->coreCount} cores) | Load: {$cpu->loadAverage1m}/{$cpu->loadAverage5m}/{$cpu->loadAverage15m}");
        }

        if ($metrics->memory) {
            $mem = $metrics->memory;
            $this->line("Memory: {$mem->getUsagePercent()}% used ({$this->formatBytes($mem->usedBytes)} / {$this->formatBytes($mem->totalBytes)}) | Swap: {$mem->getSwapUsagePercent()}%");
        }

        if ($metrics->disk) {
            $disk = $metrics->disk;
            $this->line("Disk: {$disk->getUsagePercent()}% used ({$this->formatBytes($disk->usedBytes)} / {$this->formatBytes($disk->totalBytes)})");
        }

        if ($metrics->network) {
            $net = $metrics->network;
            $this->line("Network: RX {$this->formatBytes($net->totalBytesReceived)}/s | TX {$this->formatBytes($net->totalBytesSent)}/s");
        }

        if ($metrics->processes) {
            $proc = $metrics->processes;
            $this->line("Processes: {$proc->totalProcesses} total | {$proc->runningProcesses} running | {$proc->sleepingProcesses} sleeping");
        }

        if ($metrics->temperature) {
            $temp = $metrics->temperature;
            $this->line("Temperature: CPU {$temp->cpuTemp}°C" . ($temp->gpuTemp !== null ? " | GPU {$temp->gpuTemp}°C" : ""));
        }

        if ($metrics->gpu) {
            $gpu = $metrics->gpu;
            if ($gpu->gpuCount > 0) {
                $this->line("GPU: {$gpu->gpuCount} device(s) | Avg Util: {$gpu->avgGpuUtilization}% | Mem: {$gpu->avgMemoryUtilization}%");
            }
        }

        if ($metrics->docker) {
            $docker = $metrics->docker;
            $this->line("Docker: {$docker->runningContainers}/{$docker->totalContainers} running | CPU: {$docker->totalCpuUsage}% | Mem: {$docker->totalMemoryUsage}MB");
        }

        if ($metrics->queue) {
            $queue = $metrics->queue;
            $this->line("Queue: {$queue->pendingJobs} pending | {$queue->runningJobs} running | {$queue->failedJobs} failed | {$queue->workers} workers");
        }

        if ($metrics->ollama) {
            $ollama = $metrics->ollama;
            $this->line("Ollama: {$ollama->loadedModels}/{$ollama->totalModels} loaded | Memory: {$ollama->totalMemoryUsage}MB");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $value = $bytes / (1024 ** $pow);
        return round($value, 2) . ' ' . $units[$pow];
    }
}