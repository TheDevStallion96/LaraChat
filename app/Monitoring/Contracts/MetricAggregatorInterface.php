<?php

namespace App\Monitoring\Contracts;

use App\Monitoring\DTOs\SystemMetrics;

interface MetricAggregatorInterface
{
    public function aggregate(): SystemMetrics;

    public function registerCollector(\App\Monitoring\Contracts\CollectorInterface $collector): void;
}