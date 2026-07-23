<?php

namespace App\Monitoring\Contracts;

use App\Monitoring\DTOs\MetricData;

interface CollectorInterface
{
    public function collect(): MetricData;

    public function getName(): string;

    public function getInterval(): int;

    public function isAvailable(): bool;
}