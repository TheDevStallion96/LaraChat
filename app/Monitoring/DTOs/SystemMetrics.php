<?php

namespace App\Monitoring\DTOs;

class SystemMetrics
{
    public function __construct(
        public readonly ?CpuMetric $cpu = null,
        public readonly ?MemoryMetric $memory = null,
        public readonly ?DiskMetric $disk = null,
        public readonly ?NetworkMetric $network = null,
        public readonly ?ProcessMetric $processes = null,
        public readonly ?TemperatureMetric $temperature = null,
        public readonly ?GpuMetric $gpu = null,
        public readonly ?DockerMetric $docker = null,
        public readonly ?QueueMetric $queue = null,
        public readonly ?OllamaMetric $ollama = null,
        public readonly int $timestamp = 0
    ) {
        $this->timestamp = $timestamp ?: time();
    }

    public function toArray(): array
    {
        $data = [
            'timestamp' => $this->timestamp,
        ];

        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'timestamp') {
                continue;
            }
            $data[$key] = $value?->toArray() ?? null;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}