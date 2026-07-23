<?php

namespace App\Monitoring\DTOs;

abstract class MetricData
{
    public function __construct(
        public readonly string $collectorName,
        public readonly int $timestamp,
        public readonly bool $success = true,
        public readonly ?string $errorMessage = null
    ) {}
}