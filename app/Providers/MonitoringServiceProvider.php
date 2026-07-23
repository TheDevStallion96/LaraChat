<?php

namespace App\Providers;

use App\Console\Commands\MonitorSystemMetrics;
use App\Monitoring\Collectors\CpuCollector;
use App\Monitoring\Collectors\DiskCollector;
use App\Monitoring\Collectors\DockerCollector;
use App\Monitoring\Collectors\GpuCollector;
use App\Monitoring\Collectors\MemoryCollector;
use App\Monitoring\Collectors\NetworkCollector;
use App\Monitoring\Collectors\OllamaCollector;
use App\Monitoring\Collectors\ProcessCollector;
use App\Monitoring\Collectors\QueueCollector;
use App\Monitoring\Collectors\TemperatureCollector;
use App\Monitoring\Contracts\MetricAggregatorInterface;
use App\Monitoring\Contracts\MetricBroadcasterInterface;
use App\Monitoring\Services\MetricAggregator;
use App\Monitoring\Services\ReverbMetricBroadcaster;
use Illuminate\Support\ServiceProvider;

class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricAggregatorInterface::class, MetricAggregator::class);
        $this->app->singleton(MetricBroadcasterInterface::class, ReverbMetricBroadcaster::class);

        $this->app->singleton(MetricAggregator::class, function ($app) {
            $aggregator = new MetricAggregator;

            $collectorClasses = [
                'cpu' => CpuCollector::class,
                'memory' => MemoryCollector::class,
                'disk' => DiskCollector::class,
                'network' => NetworkCollector::class,
                'processes' => ProcessCollector::class,
                'temperature' => TemperatureCollector::class,
                'gpu' => GpuCollector::class,
                'docker' => DockerCollector::class,
                'queue' => QueueCollector::class,
                'ollama' => OllamaCollector::class,
            ];

            foreach ($collectorClasses as $name => $class) {
                $enabled = config("monitoring.collectors.{$name}.enabled", true);

                if ($enabled) {
                    $aggregator->registerCollector(new $class);
                }
            }

            return $aggregator;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            config_path('monitoring.php') => config_path('monitoring.php'),
        ], 'monitoring-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MonitorSystemMetrics::class,
            ]);
        }
    }
}
