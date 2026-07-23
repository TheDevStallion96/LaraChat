<?php

namespace App\Providers;

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
            $aggregator = new MetricAggregator();

            // Register all available collectors
            $collectors = [
                new CpuCollector(),
                new MemoryCollector(),
                new DiskCollector(),
                new NetworkCollector(),
                new ProcessCollector(),
                new TemperatureCollector(),
                new GpuCollector(),
                new DockerCollector(),
                new QueueCollector(),
                new OllamaCollector(),
            ];

            foreach ($collectors as $collector) {
                $aggregator->registerCollector($collector);
            }

            return $aggregator;
        });
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/monitoring.php' => config_path('monitoring.php'),
        ], 'monitoring-config');

        // Register command if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\MonitorSystemMetrics::class,
            ]);
        }
    }
}