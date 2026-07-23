<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\OllamaMetric;
use App\Monitoring\DTOs\MetricData;

class OllamaCollector implements CollectorInterface
{
    private string $ollamaHost;
    private bool $ollamaAvailable = false;

    public function __construct()
    {
        $this->ollamaHost = config('monitoring.ollama.host', 'http://localhost:11434');
        $this->ollamaAvailable = $this->checkOllama();
    }

    public function collect(): MetricData
    {
        if (!$this->ollamaAvailable) {
            return new OllamaMetric(
                models: [],
                loadedModels: 0,
                inferenceStats: [],
                totalMemoryUsage: 0,
                avgInferenceTime: 0,
                totalRequests: 0,
                successfulRequests: 0,
                failedRequests: 0,
                'Ollama not available',
                false
            );
        }

        try {
            $models = $this->getModels();
            $runningModels = $this->getRunningModels();
            $inferenceStats = $this->getInferenceStats();

            $loadedCount = count($runningModels);
            $totalMemory = array_sum(array_column($runningModels, 'memory_usage_mb'));
            $avgInferenceTime = array_sum(array_column($inferenceStats, 'avg_time_ms')) / max(1, count($inferenceStats));
            $totalRequests = array_sum(array_column($inferenceStats, 'total_requests'));
            $successfulRequests = array_sum(array_column($inferenceStats, 'successful_requests'));
            $failedRequests = array_sum(array_column($inferenceStats, 'failed_requests'));

            return new OllamaMetric(
                models: $models,
                loadedModels: $loadedCount,
                inferenceStats: $inferenceStats,
                totalMemoryUsage: $totalMemory,
                avgInferenceTime: $avgInferenceTime,
                totalRequests: $totalRequests,
                successfulRequests: $successfulRequests,
                failedRequests: $failedRequests
            );
        } catch (\Throwable $e) {
            return new OllamaMetric(
                models: [],
                loadedModels: 0,
                inferenceStats: [],
                totalMemoryUsage: 0,
                avgInferenceTime: 0,
                totalRequests: 0,
                successfulRequests: 0,
                failedRequests: 0,
                $e->getMessage(),
                false
            );
        }
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function getInterval(): int
    {
        return 5;
    }

    public function isAvailable(): bool
    {
        return $this->ollamaAvailable;
    }

    private function checkOllama(): bool
    {
        $ch = curl_init("{$this->ollamaHost}/api/version");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    private function getModels(): array
    {
        $ch = curl_init("{$this->ollamaHost}/api/tags");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) {
            return [];
        }

        $data = json_decode($result, true);
        $models = [];

        if (isset($data['models'])) {
            foreach ($data['models'] as $model) {
                $models[] = [
                    'name' => $model['name'] ?? '',
                    'size' => $model['size'] ?? 0,
                    'size_mb' => round(($model['size'] ?? 0) / 1024 / 1024, 1),
                    'digest' => $model['digest'] ?? '',
                    'modified_at' => $model['modified_at'] ?? '',
                    'details' => $model['details'] ?? [],
                ];
            }
        }

        return $models;
    }

    private function getRunningModels(): array
    {
        $ch = curl_init("{$this->ollamaHost}/api/ps");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) {
            return [];
        }

        $data = json_decode($result, true);
        $running = [];

        if (isset($data['models'])) {
            foreach ($data['models'] as $model) {
                $running[] = [
                    'name' => $model['name'] ?? '',
                    'model' => $model['model'] ?? '',
                    'size' => $model['size'] ?? 0,
                    'size_mb' => round(($model['size'] ?? 0) / 1024 / 1024, 1),
                    'digest' => $model['digest'] ?? '',
                    'memory_usage_mb' => round(($model['size'] ?? 0) / 1024 / 1024 * 1.2, 1), // Estimate
                    'expires_at' => $model['expires_at'] ?? null,
                ];
            }
        }

        return $running;
    }

    private function getInferenceStats(): array
    {
        // Ollama doesn't expose detailed inference stats via API
        // This would need to be tracked via middleware or logs
        // For now, return placeholder data structure

        return [
            [
                'model' => 'all',
                'avg_time_ms' => 0,
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'tokens_per_second' => 0,
            ]
        ];
    }
}