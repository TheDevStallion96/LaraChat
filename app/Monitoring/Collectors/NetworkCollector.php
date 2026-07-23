<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\MetricData;
use App\Monitoring\DTOs\NetworkMetric;

class NetworkCollector implements CollectorInterface
{
    private array $prevStats = [];

    private bool $firstRun = true;

    public function collect(): MetricData
    {
        try {
            $interfaces = $this->getNetworkInterfaces();

            if ($this->firstRun) {
                $this->prevStats = $interfaces;
                $this->firstRun = false;

                return new NetworkMetric(
                    interfaces: array_keys($interfaces),
                    totalBytesReceived: 0,
                    totalBytesSent: 0,
                    totalPacketsReceived: 0,
                    totalPacketsSent: 0,
                    totalErrorsReceived: 0,
                    totalErrorsSent: 0,
                    totalDropsReceived: 0,
                    totalDropsSent: 0
                );
            }

            $totalBytesReceived = 0;
            $totalBytesSent = 0;
            $totalPacketsReceived = 0;
            $totalPacketsSent = 0;
            $totalErrorsReceived = 0;
            $totalErrorsSent = 0;
            $totalDropsReceived = 0;
            $totalDropsSent = 0;

            $interfaceData = [];

            foreach ($interfaces as $name => $current) {
                $prev = $this->prevStats[$name] ?? $current;

                $rxBytes = $current['rx_bytes'] - $prev['rx_bytes'];
                $txBytes = $current['tx_bytes'] - $prev['tx_bytes'];
                $rxPackets = $current['rx_packets'] - $prev['rx_packets'];
                $txPackets = $current['tx_packets'] - $prev['tx_packets'];
                $rxErrors = $current['rx_errors'] - $prev['rx_errors'];
                $txErrors = $current['tx_errors'] - $prev['tx_errors'];
                $rxDrops = $current['rx_dropped'] - $prev['rx_dropped'];
                $txDrops = $current['tx_dropped'] - $prev['tx_dropped'];

                $totalBytesReceived += $rxBytes;
                $totalBytesSent += $txBytes;
                $totalPacketsReceived += $rxPackets;
                $totalPacketsSent += $txPackets;
                $totalErrorsReceived += $rxErrors;
                $totalErrorsSent += $txErrors;
                $totalDropsReceived += $rxDrops;
                $totalDropsSent += $txDrops;

                $interfaceData[$name] = [
                    'rx_bytes' => $rxBytes,
                    'tx_bytes' => $txBytes,
                    'rx_packets' => $rxPackets,
                    'tx_packets' => $txPackets,
                    'rx_errors' => $rxErrors,
                    'tx_errors' => $txErrors,
                    'rx_dropped' => $rxDrops,
                    'tx_dropped' => $txDrops,
                    'rx_bytes_total' => $current['rx_bytes'],
                    'tx_bytes_total' => $current['tx_bytes'],
                ];
            }

            $this->prevStats = $interfaces;

            return new NetworkMetric(
                interfaces: $interfaceData,
                totalBytesReceived: $totalBytesReceived,
                totalBytesSent: $totalBytesSent,
                totalPacketsReceived: $totalPacketsReceived,
                totalPacketsSent: $totalPacketsSent,
                totalErrorsReceived: $totalErrorsReceived,
                totalErrorsSent: $totalErrorsSent,
                totalDropsReceived: $totalDropsReceived,
                totalDropsSent: $totalDropsSent
            );
        } catch (\Throwable $e) {
            return new NetworkMetric(
                interfaces: [],
                totalBytesReceived: 0,
                totalBytesSent: 0,
                totalPacketsReceived: 0,
                totalPacketsSent: 0,
                totalErrorsReceived: 0,
                totalErrorsSent: 0,
                totalDropsReceived: 0,
                totalDropsSent: 0,
                errorMessage: $e->getMessage(),
                success: false
            );
        }
    }

    public function getName(): string
    {
        return 'network';
    }

    public function getInterval(): int
    {
        return 1;
    }

    public function isAvailable(): bool
    {
        return is_readable('/proc/net/dev');
    }

    private function getNetworkInterfaces(): array
    {
        $content = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $content);
        $interfaces = [];

        // Skip first two header lines
        for ($i = 2; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            $name = str_replace(':', '', $parts[0]);

            // Skip loopback
            if ($name === 'lo') {
                continue;
            }

            $interfaces[$name] = [
                'rx_bytes' => (int) $parts[1],
                'rx_packets' => (int) $parts[2],
                'rx_errors' => (int) $parts[3],
                'rx_dropped' => (int) $parts[4],
                'rx_fifo' => (int) $parts[5],
                'rx_frame' => (int) $parts[6],
                'rx_compressed' => (int) $parts[7],
                'rx_multicast' => (int) $parts[8],
                'tx_bytes' => (int) $parts[9],
                'tx_packets' => (int) $parts[10],
                'tx_errors' => (int) $parts[11],
                'tx_dropped' => (int) $parts[12],
                'tx_fifo' => (int) $parts[13],
                'tx_colls' => (int) $parts[14],
                'tx_carrier' => (int) $parts[15],
                'tx_compressed' => (int) $parts[16],
            ];
        }

        return $interfaces;
    }
}
