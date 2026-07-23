Build a production-quality, modular **Real-Time System Monitoring** feature for my application.
Create a monitoring subsystem that is modular, extensible, and event-driven.

Collect live metrics for:

- CPU (overall + per core)
- Memory
- Disk usage and I/O
- Network throughput
- Running processes
- System temperatures
- GPU usage (NVIDIA if available)
- Docker containers
- Laravel queues/workers
- Ollama models and inference statistics

Prefer reading from Linux `/proc` and `/sys` where possible, using shell commands only when necessary.

Broadcast updates every second using Laravel Reverb. Do not use frontend polling.

Create reusable collector classes so new metrics can be added easily.

Update the current dashboard using Apache ECharts with reusable Vue components.

Include:

- Gauges
- Line/Area charts
- Heatmaps
- Treemaps
- Scatter charts
- Live activity timeline

Follow SOLID principles, dependency injection, DTOs, interfaces, and clean architecture.