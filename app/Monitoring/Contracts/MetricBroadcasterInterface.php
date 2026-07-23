<?php

namespace App\Monitoring\Contracts;

use App\Monitoring\DTOs\SystemMetrics;

interface MetricBroadcasterInterface
{
    public function broadcast(SystemMetrics $metrics): void;
}