# LaraChat

A ChatGPT clone with real-time system monitoring, built on Laravel 13 with multi-provider AI support.

## Features

- **Multi-Provider AI Chat** — Chat with AI models from Ollama (local/cloud), OpenAI, Anthropic, Gemini, Groq, DeepSeek, Mistral, xAI, OpenRouter, and more via the Laravel AI SDK
- **Real-Time Streaming** — Token-by-token streaming via the Vercel AI SDK protocol (SSE) with `@ai-sdk/vue` `useChat` composable
- **Conversation Management** — Persistent conversations with full history, organized per user/team
- **Markdown Rendering** — Full markdown with code syntax highlighting via `marked` + `highlight.js`
- **Provider & Model Selection** — Switch AI providers and models per conversation
- **Real-Time System Monitoring** — Live metrics dashboard with CPU, Memory, Disk, Network, Processes, Temperature, GPU (NVIDIA), Docker containers, Laravel Queues, and Ollama inference stats
- **ECharts Visualizations** — Gauges, line/area charts, heatmaps, treemaps, scatter charts, and live activity timeline
- **Reverb Broadcasting** — All metrics broadcast every second via Laravel Reverb (WebSocket)
- **Team Support** — Team-based conversations and dashboard scoping
- **Authentication** — Laravel Fortify with login, registration, 2FA (TOTP), passkeys (WebAuthn), and email verification

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 13, PHP 8.5 |
| **AI SDK** | `laravel/ai` (v0.10) |
| **Frontend** | Vue 3, Inertia v3, TypeScript |
| **Streaming** | Vercel AI SDK Protocol (SSE) |
| **Chat UI** | `@ai-sdk/vue` `useChat` |
| **Charts** | Apache ECharts via `vue-echarts` |
| **Real-Time** | Laravel Reverb (WebSocket) |
| **Styling** | Tailwind CSS v4 + shadcn-vue UI |
| **Auth** | Laravel Fortify + Passkeys |
| **Database** | SQLite (configurable) |
| **Testing** | Pest v4 + PHPUnit v12 |

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+
- An AI provider API key (or Ollama running locally)

### Installation

```bash
git clone <repository-url>
cd larachat
composer install
npm install

cp .env.example .env
php artisan key:generate

# Configure your database and AI provider keys in .env
php artisan migrate

npm run build
# or for development:
npm run dev
```

### Environment Configuration

Configure your AI providers in `.env`:

```env
# Pick your providers (Ollama default)
OLLAMA_API_KEY=
OLLAMA_URL=http://localhost:11434

OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...

# Reverb for real-time metrics broadcasting
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

## Usage

### Chat

Navigate to `/chat` to start a conversation. Select your AI provider and model from the dropdown, type your message, and receive streaming responses.

### System Monitoring

Navigate to the Monitoring dashboard from the sidebar to view real-time system metrics. All metrics are collected from `/proc` and `/sys` on Linux and broadcast via Reverb.

To start metrics collection:

```bash
php artisan monitoring:collect --broadcast
```

Available collection options:

| Option | Description |
|---|---|
| `--interval=1` | Collection interval in seconds |
| `--duration=0` | Duration in seconds (0 = infinite) |
| `--broadcast` | Enable Reverb broadcasting |
| `--output` | Print metrics to console |

## Architecture

### Chat Flow

```
User Message → Inertia POST → ChatController → ChatAgent → AI Provider
                                                      ↓
                                            Vercel Data Protocol (SSE)
                                                      ↓
                                            useChat composable → UI
```

### Monitoring Subsystem

```
/proc & /sys → Collectors (10 types) → MetricAggregator → SystemMetrics DTO
                                                               ↓
                                                    ReverbMetricBroadcaster
                                                               ↓
                                                    SystemMetricsUpdated Event
                                                               ↓
                                                    Reverb → Echo → Vue Dashboard
```

### Collectors

| Collector | Source | Interval |
|---|---|---|
| CPU | `/proc/stat`, `/proc/loadavg`, `/proc/cpuinfo` | 1s |
| Memory | `/proc/meminfo` | 1s |
| Disk | `df`, `/proc/diskstats` | 1s |
| Network | `/proc/net/dev` | 1s |
| Processes | `/proc/[pid]/stat` | 2s |
| Temperature | `/sys/class/thermal`, `/sys/class/hwmon` | 5s |
| GPU | `nvidia-smi` | 5s |
| Docker | `docker stats` | 10s |
| Queue | Redis / Horizon | 5s |
| Ollama | Ollama API | 10s |

## Testing

```bash
# Run all tests
composer test

# Run specific test
php artisan test --compact --filter=test_name

# Lint PHP
vendor/bin/pint --format agent
```

## License

MIT
