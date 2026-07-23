<script setup lang="ts">
import { onMounted, onUnmounted, ref, reactive, computed, watch } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { GaugeChart, LineChart, HeatmapChart, TreemapChart, ScatterChart, TimelineChart } from '@/components/charts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

interface MetricData {
  collector: string
  timestamp: number
  success: boolean
  error?: string
  [key: string]: unknown
}

interface CpuMetric extends MetricData {
  total_usage: number
  per_core_usage: number[]
  core_count: number
  load_average: { '1m': number; '5m': number; '15m': number }
  cpu_times: Record<string, number>
}

interface MemoryMetric extends MetricData {
  total_bytes: number
  used_bytes: number
  free_bytes: number
  available_bytes: number
  cached_bytes: number
  buffers_bytes: number
  swap_total_bytes: number
  swap_used_bytes: number
  swap_free_bytes: number
  usage_percent: number
  swap_usage_percent: number
}

interface DiskMetric extends MetricData {
  partitions: Array<{
    device: string
    mount_point: string
    filesystem: string
    total_bytes: number
    used_bytes: number
    free_bytes: number
    usage_percent: number
  }>
  total_bytes: number
  used_bytes: number
  free_bytes: number
  usage_percent: number
  io_stats: Record<string, {
    reads: number
    writes: number
    read_bytes: number
    write_bytes: number
    read_time: number
    write_time: number
  }>
}

interface NetworkMetric extends MetricData {
  interfaces: Record<string, {
    rx_bytes: number
    tx_bytes: number
    rx_packets: number
    tx_packets: number
    rx_errors: number
    tx_errors: number
    rx_dropped: number
    tx_dropped: number
    rx_bytes_total: number
    tx_bytes_total: number
  }>
  total_bytes_received: number
  total_bytes_sent: number
  total_packets_received: number
  total_packets_sent: number
  total_errors_received: number
  total_errors_sent: number
  total_drops_received: number
  total_drops_sent: number
}

interface ProcessMetric extends MetricData {
  total_processes: number
  running_processes: number
  sleeping_processes: number
  zombie_processes: number
  stopped_processes: number
  top_cpu_processes: Array<{
    pid: number
    name: string
    cmdline: string
    state: string
    state_char: string
    ppid: number
    cpu_percent: number
    memory_percent: number
    memory_rss: number
    memory_vms: number
    threads: number
    priority: number
    nice: number
    start_time: number
  }>
  top_memory_processes: Array<{
    pid: number
    name: string
    cmdline: string
    state: string
    state_char: string
    ppid: number
    cpu_percent: number
    memory_percent: number
    memory_rss: number
    memory_vms: number
    threads: number
    priority: number
    nice: number
    start_time: number
  }>
  all_processes: Array<{
    pid: number
    name: string
    cmdline: string
    state: string
    state_char: string
    ppid: number
    cpu_percent: number
    memory_percent: number
    memory_rss: number
    memory_vms: number
    threads: number
    priority: number
    nice: number
    start_time: number
  }>
}

interface TemperatureMetric extends MetricData {
  temperatures: Array<{
    zone: string
    label: string
    temp_c: number
    temp_f: number
    source: string
    device?: string
  }>
  cpu_temp: number
  gpu_temp: number | null
  fan_speeds: Array<{
    device: string
    label: string
    rpm: number
  }>
}

interface GpuMetric extends MetricData {
  gpus: Array<{
    index: number
    name: string
    uuid: string
    driver_version: string
    temperature_c: number
    temperature_f: number
    gpu_utilization: number
    memory_utilization: number
    memory_total_mb: number
    memory_used_mb: number
    memory_free_mb: number
    power_draw_w: number
    power_limit_w: number
    fan_speed_percent: number | null
    pstate: string
  }>
  gpu_count: number
  total_gpu_memory_mb: number
  used_gpu_memory_mb: number
  avg_gpu_utilization: number
  avg_memory_utilization: number
}

interface DockerMetric extends MetricData {
  containers: Array<{
    id: string
    name: string
    container: string
    status: string
    cpu_percent: number
    memory_usage_bytes: number
    memory_limit_bytes: number
    memory_usage_mb: number
    memory_limit_mb: number
    memory_percent: number
    network_rx_bytes: number
    network_tx_bytes: number
    block_read_bytes: number
    block_write_bytes: number
    pids: number
  }>
  total_containers: number
  running_containers: number
  stopped_containers: number
  total_cpu_usage: number
  total_memory_usage_mb: number
  network_stats: Record<string, { name: string; rx_bytes: number; tx_bytes: number }>
  disk_stats: Record<string, { name: string; read_bytes: number; write_bytes: number }>
}

interface QueueMetric extends MetricData {
  queues: Record<string, {
    name: string
    pending: number
    running: number
    failed: number
    total: number
  }>
  total_jobs: number
  pending_jobs: number
  running_jobs: number
  failed_jobs: number
  workers: number
  worker_details: Array<{
    id: string
    name: string
    status: string
    queue: string
    jobs_processed: number
    memory_usage: number
    started_at: string | null
  }>
  failed_job_details: Array<{
    id: string
    queue: string
    connection: string
    failed_at: string | null
    exception: string
    exception_message: string
  }>
}

interface OllamaMetric extends MetricData {
  models: Array<{
    name: string
    size: number
    size_mb: number
    digest: string
    modified_at: string
    details: Record<string, unknown>
  }>
  loaded_models: number
  total_models: number
  total_memory_usage_mb: number
  avg_inference_time_ms: number
  total_requests: number
  successful_requests: number
  failed_requests: number
  inference_stats: Array<{
    model: string
    avg_time_ms: number
    total_requests: number
    successful_requests: number
    failed_requests: number
    tokens_per_second: number
  }>
}

interface SystemMetrics {
  timestamp: number
  cpu?: CpuMetric
  memory?: MemoryMetric
  disk?: DiskMetric
  network?: NetworkMetric
  processes?: ProcessMetric
  temperature?: TemperatureMetric
  gpu?: GpuMetric
  docker?: DockerMetric
  queue?: QueueMetric
  ollama?: OllamaMetric
}

interface AiRequestData {
  id: number
  provider: string | null
  model: string | null
  prompt_tokens: number | null
  completion_tokens: number | null
  total_tokens: number | null
  duration_ms: number | null
  status: string
  started_at: string | null
  completed_at: string | null
}

interface Props {
  initialMetrics: SystemMetrics
  availableCollectors: string[]
  channel: string
  event: string
  aiRequests: AiRequestData[]
}

const props = defineProps<Props>()

const metrics = reactive<SystemMetrics>({ ...props.initialMetrics })
const history = reactive<Record<string, Array<{ timestamp: number; value: number }>>>({})
const events = ref<Array<{ timestamp: number; type: string; message: string; severity: 'success' | 'info' | 'warning' | 'critical'; source: string }>>([])
const aiRequests = ref<AiRequestData[]>(props.aiRequests)
const connected = ref(false)
const echo = ref<any | null>(null)
const activeTab = ref('cpu')

const MAX_HISTORY_POINTS = 300

function initEcho() {
  const pusherKey = import.meta.env.VITE_REVERB_APP_KEY
  const wsHost = import.meta.env.VITE_REVERB_HOST
  const wsPort = import.meta.env.VITE_REVERB_PORT
  const wsScheme = import.meta.env.VITE_REVERB_SCHEME

  if (!pusherKey || !wsHost) {
    console.warn('Reverb configuration not found')
    return
  }

  ;(window as any).Pusher = Pusher

  echo.value = new Echo({
    broadcaster: 'reverb',
    key: pusherKey,
    wsHost,
    wsPort: wsPort ? parseInt(wsPort) : 443,
    wssPort: wsPort ? parseInt(wsPort) : 443,
    forceTLS: wsScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
  })

  echo.value.connector.pusher.connection.bind('connected', () => {
    connected.value = true
    console.log('Connected to Reverb')
  })

  echo.value.connector.pusher.connection.bind('disconnected', () => {
    connected.value = false
    console.log('Disconnected from Reverb')
  })

  const channel = echo.value.channel(props.channel)
  channel.listen(props.event, (data: SystemMetrics) => {
    updateMetrics(data)
  })

  channel.listen('.metrics.updated', (data: SystemMetrics) => {
    updateMetrics(data)
  })

  echo.value.channel('ai-requests').listen('.ai.request.updated', (data: AiRequestData) => {
    const idx = aiRequests.value.findIndex((r) => r.id === data.id)
    if (idx !== -1) {
      aiRequests.value[idx] = data
    } else {
      aiRequests.value.unshift(data)
      if (aiRequests.value.length > 50) aiRequests.value.pop()
    }
  })
}

function updateMetrics(data: SystemMetrics) {
  metrics.timestamp = data.timestamp

  Object.keys(data).forEach(key => {
    if (key === 'timestamp') return
    const metric = data[key as keyof SystemMetrics] as MetricData | undefined
    if (metric) {
      ;(metrics as any)[key] = metric
      addToHistory(key, metric)
    }
  })

  addEvent({
    timestamp: data.timestamp,
    type: 'metrics_update',
    message: 'Metrics updated',
    severity: 'info',
    source: 'system',
  })
}

function addToHistory(collector: string, metric: MetricData) {
  if (!history[collector]) {
    history[collector] = []
  }

  let value: number | null = null

  switch (collector) {
    case 'cpu':
      value = (metric as CpuMetric).total_usage ?? null
      break
    case 'memory':
      value = (metric as MemoryMetric).usage_percent ?? null
      break
    case 'disk':
      value = (metric as DiskMetric).usage_percent ?? null
      break
    case 'network':
      value = ((metric as NetworkMetric).total_bytes_received + (metric as NetworkMetric).total_bytes_sent)
      break
    case 'temperature':
      value = (metric as TemperatureMetric).cpu_temp ?? null
      break
    case 'gpu':
      value = (metric as GpuMetric).avg_gpu_utilization ?? null
      break
    case 'docker':
      value = (metric as DockerMetric).total_cpu_usage ?? null
      break
    case 'queue':
      value = (metric as QueueMetric).pending_jobs ?? null
      break
    case 'ollama':
      value = (metric as OllamaMetric).loaded_models ?? null
      break
  }

  if (value !== null) {
    history[collector].push({ timestamp: metric.timestamp, value })
    if (history[collector].length > MAX_HISTORY_POINTS) {
      history[collector].shift()
    }
  }
}

function addEvent(event: { timestamp: number; type: string; message: string; severity: 'success' | 'info' | 'warning' | 'critical'; source: string }) {
  events.value.push(event)
  if (events.value.length > 100) {
    events.value.shift()
  }
}

function getHistory(collector: string) {
  return history[collector] || []
}

function getHistoryForChart(collector: string) {
  const data = getHistory(collector)
  return data.map(d => ({ timestamp: d.timestamp, value: d.value }))
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function formatNumber(num: number): string {
  if (num >= 1e9) return (num / 1e9).toFixed(1) + 'B'
  if (num >= 1e6) return (num / 1e6).toFixed(1) + 'M'
  if (num >= 1e3) return (num / 1e3).toFixed(1) + 'K'
  return num.toString()
}

onMounted(() => {
  initEcho()
})

onUnmounted(() => {
  echo.value?.disconnect()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">System Monitoring</h1>
        <p class="text-muted-foreground">Real-time system metrics and performance monitoring</p>
      </div>
      <Badge :class="connected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
        {{ connected ? 'Connected' : 'Disconnected' }}
      </Badge>
    </div>

    <!-- Overview Gauges -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">CPU Usage</CardTitle>
        </CardHeader>
        <CardContent>
          <GaugeChart
            :value="metrics.cpu?.total_usage || 0"
            :max="100"
            title="Total CPU"
            unit="%"
            height="150px"
          />
          <div class="mt-2 text-sm text-muted-foreground">
            {{ metrics.cpu?.core_count || 0 }} cores | Load: {{ metrics.cpu?.load_average?.['1m'] || 0 }} / {{ metrics.cpu?.load_average?.['5m'] || 0 }} / {{ metrics.cpu?.load_average?.['15m'] || 0 }}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Memory</CardTitle>
        </CardHeader>
        <CardContent>
          <GaugeChart
            :value="metrics.memory?.usage_percent || 0"
            :max="100"
            title="Memory Usage"
            unit="%"
            height="150px"
          />
          <div class="mt-2 text-sm text-muted-foreground">
            {{ formatBytes(metrics.memory?.used_bytes || 0) }} / {{ formatBytes(metrics.memory?.total_bytes || 0) }}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Disk</CardTitle>
        </CardHeader>
        <CardContent>
          <GaugeChart
            :value="metrics.disk?.usage_percent || 0"
            :max="100"
            title="Disk Usage"
            unit="%"
            height="150px"
          />
          <div class="mt-2 text-sm text-muted-foreground">
            {{ formatBytes(metrics.disk?.used_bytes || 0) }} / {{ formatBytes(metrics.disk?.total_bytes || 0) }}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Temperature</CardTitle>
        </CardHeader>
        <CardContent>
          <GaugeChart
            :value="metrics.temperature?.cpu_temp || 0"
            :max="100"
            title="CPU Temp"
            unit="°C"
            height="150px"
            :thresholds="[
              { value: 50, color: '#22c55e' },
              { value: 70, color: '#f59e0b' },
              { value: 85, color: '#ef4444' },
              { value: 100, color: '#dc2626' }
            ]"
          />
          <div class="mt-2 text-sm text-muted-foreground">
            GPU: {{ metrics.temperature?.gpu_temp !== undefined ? metrics.temperature.gpu_temp + '°C' : 'N/A' }}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">GPU</CardTitle>
        </CardHeader>
        <CardContent>
          <GaugeChart
            v-if="metrics.gpu && metrics.gpu.gpu_count > 0"
            :value="metrics.gpu.avg_gpu_utilization || 0"
            :max="100"
            title="GPU Utilization"
            unit="%"
            height="150px"
          />
          <div v-else class="text-center text-muted-foreground py-8">No GPU detected</div>
          <div class="mt-2 text-sm text-muted-foreground" v-if="metrics.gpu && metrics.gpu.gpu_count > 0">
            Mem: {{ metrics.gpu.avg_memory_utilization }}% | {{ formatBytes((metrics.gpu.used_gpu_memory_mb || 0) * 1024 * 1024) }} / {{ formatBytes((metrics.gpu.total_gpu_memory_mb || 0) * 1024 * 1024) }}
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Detail Tabs -->
    <div class="space-y-4">
      <div class="flex gap-2 border-b border-border">
        <button
          v-for="tab in ['cpu', 'memory', 'disk', 'network', 'processes', 'ai-requests']"
          :key="tab"
          @click="activeTab = tab"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
            activeTab === tab ? 'bg-background text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          ]"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>

      <!-- CPU Tab -->
      <div v-show="activeTab === 'cpu'" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>CPU Usage Over Time</CardTitle>
            </CardHeader>
            <CardContent>
              <LineChart
                :data="getHistoryForChart('cpu')"
                value-key="value"
                title="CPU Usage"
                unit="%"
                type="area"
                height="300px"
                :y-axis-min="0"
                :y-axis-max="100"
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Per-Core Usage</CardTitle>
            </CardHeader>
            <CardContent>
              <HeatmapChart
                v-if="metrics.cpu?.per_core_usage"
                :data="metrics.cpu.per_core_usage.map((v, i) => ({ x: i % 8, y: Math.floor(i / 8), value: v }))"
                :x-labels="Array.from({ length: 8 }, (_, i) => `Core ${i}`)"
                :y-labels="Array.from({ length: Math.ceil((metrics.cpu.per_core_usage?.length || 0) / 8) }, (_, i) => `Row ${i}`)"
                title="CPU Core Heatmap"
                unit="%"
                height="300px"
                :color-stops="[
                  { value: 0, color: '#fef3c7' },
                  { value: 0.5, color: '#f59e0b' },
                  { value: 1, color: '#ef4444' }
                ]"
              />
              <div v-else class="text-center text-muted-foreground py-8">No per-core data available</div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Memory Tab -->
      <div v-show="activeTab === 'memory'" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Memory Usage Over Time</CardTitle>
            </CardHeader>
            <CardContent>
              <LineChart
                :data="getHistoryForChart('memory')"
                value-key="value"
                title="Memory Usage"
                unit="%"
                type="area"
                height="300px"
                :y-axis-min="0"
                :y-axis-max="100"
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Memory Breakdown</CardTitle>
            </CardHeader>
            <CardContent>
              <TreemapChart
                v-if="metrics.memory"
                :data="[
                  { name: 'Used', value: metrics.memory.used_bytes, itemStyle: { color: '#ef4444' } },
                  { name: 'Cached', value: metrics.memory.cached_bytes, itemStyle: { color: '#f59e0b' } },
                  { name: 'Buffers', value: metrics.memory.buffers_bytes, itemStyle: { color: '#3b82f6' } },
                  { name: 'Free', value: metrics.memory.free_bytes, itemStyle: { color: '#22c55e' } },
                ]"
                title="Memory Allocation"
                unit="B"
                height="300px"
              />
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Disk Tab -->
      <div v-show="activeTab === 'disk'" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Disk Usage Over Time</CardTitle>
            </CardHeader>
            <CardContent>
              <LineChart
                :data="getHistoryForChart('disk')"
                value-key="value"
                title="Disk Usage"
                unit="%"
                type="line"
                height="300px"
                :y-axis-min="0"
                :y-axis-max="100"
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Partition Usage</CardTitle>
            </CardHeader>
            <CardContent>
              <TreemapChart
                v-if="metrics.disk?.partitions?.length"
                :data="metrics.disk.partitions.map((p: any) => ({
                  name: p.mount_point,
                  value: p.used_bytes,
                  itemStyle: { color: p.usage_percent > 90 ? '#ef4444' : p.usage_percent > 70 ? '#f59e0b' : '#22c55e' }
                }))"
                title="Disk Partitions"
                unit="B"
                height="300px"
              />
              <div v-else class="text-center text-muted-foreground py-8">No partition data</div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Network Tab -->
      <div v-show="activeTab === 'network'" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Network Throughput</CardTitle>
            </CardHeader>
            <CardContent>
              <LineChart
                :data="getHistoryForChart('network').map(d => ({ timestamp: d.timestamp, value: d.value ? d.value / 2 : 0 }))"
                value-key="value"
                title="Network I/O"
                unit="B/s"
                type="area"
                height="300px"
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Interface Details</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-2" v-if="metrics.network?.interfaces">
                <div v-for="(iface, name) in metrics.network.interfaces" :key="name" class="flex justify-between text-sm">
                  <span>{{ name }}</span>
                  <span class="font-mono">{{ formatBytes(iface.rx_bytes || 0) }}/s ↓ {{ formatBytes(iface.tx_bytes || 0) }}/s ↑</span>
                </div>
              </div>
              <div v-else class="text-center text-muted-foreground py-8">No interface data</div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Processes Tab -->
      <div v-show="activeTab === 'processes'" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Process States</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid gap-2 md:grid-cols-4" v-if="metrics.processes">
                <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                  <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ metrics.processes.running_processes }}</p>
                  <p class="text-sm text-muted-foreground">Running</p>
                </div>
                <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                  <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ metrics.processes.sleeping_processes }}</p>
                  <p class="text-sm text-muted-foreground">Sleeping</p>
                </div>
                <div class="p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                  <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ metrics.processes.zombie_processes }}</p>
                  <p class="text-sm text-muted-foreground">Zombie</p>
                </div>
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/20">
                  <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ metrics.processes.stopped_processes }}</p>
                  <p class="text-sm text-muted-foreground">Stopped</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Top CPU Processes</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-2 max-h-96 overflow-y-auto" v-if="metrics.processes?.top_cpu_processes?.length">
                <div v-for="(proc, i) in metrics.processes.top_cpu_processes.slice(0, 10)" :key="proc.pid" class="flex items-center justify-between text-sm px-3 py-2 hover:bg-accent rounded">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="text-muted-foreground">{{ i + 1 }}.</span>
                    <span class="font-mono truncate">{{ proc.name }}</span>
                    <span class="text-muted-foreground">({{ proc.pid }})</span>
                  </div>
                  <div class="flex items-center gap-4">
                    <span class="font-mono text-blue-600">{{ proc.cpu_percent }}%</span>
                    <span class="text-muted-foreground">{{ formatBytes(proc.memory_rss) }}</span>
                  </div>
                </div>
              </div>
              <div v-else class="text-center text-muted-foreground py-8">No process data</div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>

    <!-- Additional Tabs for Docker, Queue, Ollama -->
    <div v-if="availableCollectors.some(c => ['docker', 'queue', 'ollama'].includes(c))" class="space-y-4 mt-6">
      <div class="flex gap-2 border-b border-border">
        <button
          v-for="tab in ['docker', 'queue', 'ollama']"
          :key="tab"
          v-show="availableCollectors.includes(tab)"
          @click="activeTab = tab"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
            activeTab === tab ? 'bg-background text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          ]"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>

      <!-- Docker Tab -->
      <div v-show="activeTab === 'docker' && availableCollectors.includes('docker')" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Container Status</CardTitle>
            </CardHeader>
            <CardContent>
              <div v-if="metrics.docker" class="space-y-2">
                <div class="grid gap-2 md:grid-cols-3">
                  <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                    <p class="text-2xl font-bold text-green-600">{{ metrics.docker.running_containers }}</p>
                    <p class="text-sm text-muted-foreground">Running</p>
                  </div>
                  <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                    <p class="text-2xl font-bold text-red-600">{{ metrics.docker.stopped_containers }}</p>
                    <p class="text-sm text-muted-foreground">Stopped</p>
                  </div>
                  <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <p class="text-2xl font-bold text-blue-600">{{ metrics.docker.total_containers }}</p>
                    <p class="text-sm text-muted-foreground">Total</p>
                  </div>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-2">
                  <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/20">
                    <p class="text-2xl font-bold">{{ metrics.docker.total_cpu_usage }}%</p>
                    <p class="text-sm text-muted-foreground">Total CPU</p>
                  </div>
                  <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/20">
                    <p class="text-2xl font-bold">{{ metrics.docker.total_memory_usage }} MB</p>
                    <p class="text-sm text-muted-foreground">Total Memory</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Containers</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-2 max-h-96 overflow-y-auto" v-if="metrics.docker?.containers?.length">
                <div v-for="container in metrics.docker.containers" :key="container.id" class="flex items-center justify-between text-sm px-3 py-2 hover:bg-accent rounded">
                  <div class="flex items-center gap-2 min-w-0">
                    <Badge :class="container.status === 'running' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                      {{ container.status }}
                    </Badge>
                    <span class="font-mono truncate max-w-[200px]">{{ container.name }}</span>
                  </div>
                  <div class="flex items-center gap-4 text-right">
                    <span class="font-mono">{{ container.cpu_percent }}%</span>
                    <span class="text-muted-foreground">{{ container.memory_usage_mb }} MB</span>
                  </div>
                </div>
              </div>
              <div v-else class="text-center text-muted-foreground py-8">No containers</div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Queue Tab -->
      <div v-show="activeTab === 'queue' && availableCollectors.includes('queue')" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Queue Status</CardTitle>
            </CardHeader>
            <CardContent>
              <div v-if="metrics.queue" class="space-y-2">
                <div class="grid gap-2 md:grid-cols-4">
                  <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <p class="text-2xl font-bold text-blue-600">{{ metrics.queue.pending_jobs }}</p>
                    <p class="text-sm text-muted-foreground">Pending</p>
                  </div>
                  <div class="p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                    <p class="text-2xl font-bold text-yellow-600">{{ metrics.queue.running_jobs }}</p>
                    <p class="text-sm text-muted-foreground">Running</p>
                  </div>
                  <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                    <p class="text-2xl font-bold text-red-600">{{ metrics.queue.failed_jobs }}</p>
                    <p class="text-sm text-muted-foreground">Failed</p>
                  </div>
                  <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                    <p class="text-2xl font-bold text-green-600">{{ metrics.queue.workers }}</p>
                    <p class="text-sm text-muted-foreground">Workers</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Queues</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-2" v-if="metrics.queue?.queues">
                <div v-for="(queue, name) in metrics.queue.queues" :key="name" class="flex items-center justify-between text-sm px-3 py-2 hover:bg-accent rounded">
                  <span class="font-mono">{{ name }}</span>
                  <div class="flex items-center gap-4">
                    <Badge variant="secondary">{{ queue.pending }} pending</Badge>
                    <Badge variant="secondary">{{ queue.running }} running</Badge>
                    <Badge v-if="queue.failed > 0" variant="destructive">{{ queue.failed }} failed</Badge>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- AI Requests Tab -->
      <div v-show="activeTab === 'ai-requests'" class="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle>
              <span class="flex items-center gap-2">
                AI Request Log
                <Badge variant="secondary" class="ml-2">{{ aiRequests.filter(r => r.status === 'completed').length }} completed</Badge>
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="aiRequests.length === 0" class="text-center text-muted-foreground py-8">
              No AI requests yet. Start a chat to see request statistics here.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b text-left text-muted-foreground">
                    <th class="pb-2 pr-4 font-medium">Status</th>
                    <th class="pb-2 pr-4 font-medium">Provider</th>
                    <th class="pb-2 pr-4 font-medium">Model</th>
                    <th class="pb-2 pr-4 font-medium text-right">Tokens</th>
                    <th class="pb-2 pr-4 font-medium text-right">Duration</th>
                    <th class="pb-2 font-medium">Started</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="req in aiRequests" :key="req.id" class="border-b last:border-none hover:bg-accent/50">
                    <td class="py-2 pr-4">
                      <Badge :class="req.status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'">
                        {{ req.status }}
                      </Badge>
                    </td>
                    <td class="py-2 pr-4 font-mono text-muted-foreground">{{ req.provider || '—' }}</td>
                    <td class="py-2 pr-4 font-mono max-w-[160px] truncate">{{ req.model || '—' }}</td>
                    <td class="py-2 pr-4 text-right font-mono">
                      <template v-if="req.total_tokens !== null">
                        {{ req.total_tokens.toLocaleString() }}
                        <span class="text-muted-foreground text-xs">
                          ({{ req.prompt_tokens ?? '?' }}·{{ req.completion_tokens ?? '?' }})
                        </span>
                      </template>
                      <span v-else class="text-muted-foreground">—</span>
                    </td>
                    <td class="py-2 pr-4 text-right font-mono">
                      <template v-if="req.duration_ms !== null">
                        {{ req.duration_ms < 1000 ? req.duration_ms + 'ms' : (req.duration_ms / 1000).toFixed(1) + 's' }}
                      </template>
                      <span v-else class="text-muted-foreground">—</span>
                    </td>
                    <td class="py-2 font-mono text-muted-foreground text-xs">{{ req.started_at ? new Date(req.started_at).toLocaleTimeString() : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Ollama Tab -->
      <div v-show="activeTab === 'ollama' && availableCollectors.includes('ollama')" class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Ollama Models</CardTitle>
            </CardHeader>
            <CardContent>
              <div v-if="metrics.ollama" class="space-y-2">
                <div class="grid gap-2 md:grid-cols-3">
                  <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <p class="text-2xl font-bold text-blue-600">{{ metrics.ollama.loaded_models }}</p>
                    <p class="text-sm text-muted-foreground">Loaded</p>
                  </div>
                  <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/20">
                    <p class="text-2xl font-bold">{{ metrics.ollama.total_models }}</p>
                    <p class="text-sm text-muted-foreground">Total Models</p>
                  </div>
                  <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                    <p class="text-2xl font-bold text-purple-600">{{ metrics.ollama.total_memory_usage_mb }} MB</p>
                    <p class="text-sm text-muted-foreground">Memory Usage</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Loaded Models</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-2" v-if="metrics.ollama?.models?.length">
                <div v-for="model in metrics.ollama.models" :key="model.name" class="flex items-center justify-between text-sm px-3 py-2 hover:bg-accent rounded">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="font-mono truncate max-w-[200px]">{{ model.name }}</span>
                    <Badge variant="outline">{{ model.size_mb }} MB</Badge>
                  </div>
                  <span class="text-muted-foreground">{{ model.details?.family || 'unknown' }}</span>
                </div>
              </div>
              <div v-else class="text-center text-muted-foreground py-8">No models loaded</div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>

    <!-- Timeline / Events -->
    <Card class="mt-6">
      <CardHeader>
        <CardTitle>Live Activity Timeline</CardTitle>
      </CardHeader>
      <CardContent>
        <TimelineChart
          :events="events"
          :metrics="[
            { name: 'CPU %', data: getHistoryForChart('cpu').map(d => d.value) },
            { name: 'Memory %', data: getHistoryForChart('memory').map(d => d.value) }
          ]"
          title="System Activity"
          height="400px"
        />
      </CardContent>
    </Card>
  </div>
</template>
