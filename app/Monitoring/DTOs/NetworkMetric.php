<?php

namespace App\Monitoring\DTOs;

class NetworkMetric extends MetricData
{
    public function __construct(
        public readonly array $interfaces,
        public readonly int $totalBytesReceived,
        public readonly int $totalBytesSent,
        public readonly int $totalPacketsReceived,
        public readonly int $totalPacketsSent,
        public readonly int $totalErrorsReceived,
        public readonly int $totalErrorsSent,
        public readonly int $totalDropsReceived,
        public readonly int $totalDropsSent,
        ?string $errorMessage = null,
        bool $success = true
    ) {
        parent::__construct('network', time(), $success, $errorMessage);
    }

    public function toArray(): array
    {
        return [
            'collector' => $this->collectorName,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->errorMessage,
            'interfaces' => $this->interfaces,
            'total_bytes_received' => $this->totalBytesReceived,
            'total_bytes_sent' => $this->totalBytesSent,
            'total_packets_received' => $this->totalPacketsReceived,
            'total_packets_sent' => $this->totalPacketsSent,
            'total_errors_received' => $this->totalErrorsReceived,
            'total_errors_sent' => $this->totalErrorsSent,
            'total_drops_received' => $this->totalDropsReceived,
            'total_drops_sent' => $this->totalDropsSent,
        ];
    }
}