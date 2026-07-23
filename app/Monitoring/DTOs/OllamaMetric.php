<?php

namespace App\Monitoring\DTOs;

class OllamaMetric extends MetricData
{
    public function __construct(
        public readonly array $models,
        public readonly int $loadedModels,
        public readonly array $inferenceStats,
        public readonly float $totalMemoryUsage,
        public readonly float $avgInferenceTime,
        public readonly int $totalRequests,
        public readonly int $successfulRequests,
        public readonly int $failedRequests,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('ollama', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'models' => $this->models,
            'loaded_models' => $this->loadedModels,
            'inference_stats' => $this->inferenceStats,
            'total_memory_usage_mb' => round($this->totalMemoryUsage, 2),
            'avg_inference_time_ms' => round($this->avgInferenceTime, 2),
            'total_requests' => $this->totalRequests,
            'successful_requests' => $this->successfulRequests,
            'failed_requests' => $this->failedRequests,
        ];
    }
}