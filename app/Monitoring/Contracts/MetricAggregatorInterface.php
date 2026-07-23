<?php

namespace App\Monitoring\Contracts;

use App\Monitoring\DTOs\SystemMetrics;

interface MetricAggregatorInterface
{
    public function aggregate(): SystemMetrics;

    public function registerCollector(CollectorInterface $collector): void;
}
