<?php

namespace App\Monitoring\Collectors;

use App\Monitoring\Contracts\CollectorInterface;
use App\Monitoring\DTOs\MetricData;
use App\Monitoring\DTOs\TemperatureMetric;

class TemperatureCollector implements CollectorInterface
{
    public function collect(): MetricData
    {
        try {
            $temperatures = $this->readThermalZones();
            $fanSpeeds = $this->readFanSpeeds();

            $cpuTemp = 0;
            $gpuTemp = null;
            $count = 0;

            foreach ($temperatures as $temp) {
                if (stripos($temp['label'], 'cpu') !== false || stripos($temp['label'], 'core') !== false || stripos($temp['label'], 'package') !== false) {
                    $cpuTemp += $temp['temp_c'];
                    $count++;
                } elseif (stripos($temp['label'], 'gpu') !== false && $gpuTemp === null) {
                    $gpuTemp = $temp['temp_c'];
                }
            }

            $avgCpuTemp = $count > 0 ? $cpuTemp / $count : 0;

            return new TemperatureMetric(
                temperatures: $temperatures,
                cpuTemp: $avgCpuTemp,
                gpuTemp: $gpuTemp,
                fanSpeeds: $fanSpeeds
            );
        } catch (\Throwable $e) {
            return new TemperatureMetric(
                temperatures: [],
                cpuTemp: 0,
                gpuTemp: null,
                fanSpeeds: [],
                errorMessage: $e->getMessage(),
                success: false
            );
        }
    }

    public function getName(): string
    {
        return 'temperature';
    }

    public function getInterval(): int
    {
        return 2;
    }

    public function isAvailable(): bool
    {
        return is_dir('/sys/class/thermal') || is_dir('/sys/class/hwmon');
    }

    private function readThermalZones(): array
    {
        $temperatures = [];
        $thermalDir = '/sys/class/thermal';

        if (! is_dir($thermalDir)) {
            return $this->readHwmonTemperatures();
        }

        $zones = scandir($thermalDir);

        foreach ($zones as $zone) {
            if ($zone === '.' || $zone === '..' || ! str_starts_with($zone, 'thermal_zone')) {
                continue;
            }

            $zonePath = "$thermalDir/$zone";
            $tempFile = "$zonePath/temp";
            $typeFile = "$zonePath/type";

            if (! is_readable($tempFile) || ! is_readable($typeFile)) {
                continue;
            }

            $temp = (int) trim(file_get_contents($tempFile));
            $type = trim(file_get_contents($typeFile));

            // Temperature is in millidegrees Celsius
            $tempC = $temp / 1000;

            $temperatures[] = [
                'zone' => $zone,
                'label' => $type,
                'temp_c' => round($tempC, 1),
                'temp_f' => round(($tempC * 9 / 5) + 32, 1),
                'source' => 'thermal',
            ];
        }

        return $temperatures;
    }

    private function readHwmonTemperatures(): array
    {
        $temperatures = [];
        $hwmonDir = '/sys/class/hwmon';

        if (! is_dir($hwmonDir)) {
            return [];
        }

        $devices = scandir($hwmonDir);

        foreach ($devices as $device) {
            if ($device === '.' || $device === '..' || ! str_starts_with($device, 'hwmon')) {
                continue;
            }

            $devicePath = "$hwmonDir/$device";
            $nameFile = "$devicePath/name";

            $deviceName = is_readable($nameFile) ? trim(file_get_contents($nameFile)) : $device;

            // Read all temp*_input files
            $files = glob("$devicePath/temp*_input");

            foreach ($files as $file) {
                $baseName = basename($file, '_input');
                $labelFile = "$devicePath/{$baseName}_label";

                $temp = (int) trim(file_get_contents($file));
                $tempC = $temp / 1000;
                $label = is_readable($labelFile) ? trim(file_get_contents($labelFile)) : $baseName;

                $temperatures[] = [
                    'zone' => $device,
                    'label' => $label,
                    'temp_c' => round($tempC, 1),
                    'temp_f' => round(($tempC * 9 / 5) + 32, 1),
                    'source' => 'hwmon',
                    'device' => $deviceName,
                ];
            }
        }

        return $temperatures;
    }

    private function readFanSpeeds(): array
    {
        $fans = [];
        $hwmonDir = '/sys/class/hwmon';

        if (! is_dir($hwmonDir)) {
            return [];
        }

        $devices = scandir($hwmonDir);

        foreach ($devices as $device) {
            if ($device === '.' || $device === '..' || ! str_starts_with($device, 'hwmon')) {
                continue;
            }

            $devicePath = "$hwmonDir/$device";
            $nameFile = "$devicePath/name";
            $deviceName = is_readable($nameFile) ? trim(file_get_contents($nameFile)) : $device;

            // Read all fan*_input files
            $files = glob("$devicePath/fan*_input");

            foreach ($files as $file) {
                $baseName = basename($file, '_input');
                $labelFile = "$devicePath/{$baseName}_label";

                $rpm = (int) trim(file_get_contents($file));
                $label = is_readable($labelFile) ? trim(file_get_contents($labelFile)) : $baseName;

                $fans[] = [
                    'device' => $deviceName,
                    'label' => $label,
                    'rpm' => $rpm,
                ];
            }
        }

        return $fans;
    }
}
