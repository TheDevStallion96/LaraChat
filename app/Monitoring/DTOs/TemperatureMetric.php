<?php

namespace App\Monitoring\DTOs;

class TemperatureMetric extends MetricData
{
    public function __construct(
        public readonly array $temperatures,
        public readonly float $cpuTemp,
        public readonly ?float $gpuTemp,
        public readonly array $fanSpeeds,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('temperature', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'temperatures' => $this->temperatures,
            'cpu_temp' => round($this->cpuTemp, 1),
            'gpu_temp' => $this->gpuTemp !== null ? round($this->gpuTemp, 1) : null,
            'fan_speeds' => $this->fanSpeeds,
        ];
    }
}
