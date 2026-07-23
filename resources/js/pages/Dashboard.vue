<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { onMounted, onUnmounted, ref, reactive } from 'vue';
import { GaugeChart, LineChart } from '@/components/charts';
import Heading from '@/components/Heading.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { monitoring } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';

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
}

interface MemoryMetric extends MetricData {
  total_bytes: number
  used_bytes: number
  free_bytes: number
  available_bytes: number
  usage_percent: number
  swap_usage_percent: number
}

interface DiskMetric extends MetricData {
  total_bytes: number
  used_bytes: number
  free_bytes: number
  usage_percent: number
}

interface NetworkMetric extends MetricData {
  total_bytes_received: number
  total_bytes_sent: number
}

interface SystemMetrics {
  timestamp: number
  cpu?: any
  memory?: any
  disk?: any
  network?: any
}

defineProps<{
  pendingInvitations?: DashboardInvitation[];
}>();

const metrics = reactive<SystemMetrics>({ timestamp: 0 })
const cpuHistory = ref<Array<{ timestamp: number; value: number }>>([])
const memHistory = ref<Array<{ timestamp: number; value: number }>>([])
const connected = ref(false)
const echo = ref<any | null>(null)
const chartChannel = ref('')

const MAX_POINTS = 120

function formatBytes(bytes: number): string {
  if (bytes === 0) {
return '0 B'
}

  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(k))

  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function initEcho() {
  const pusherKey = import.meta.env.VITE_REVERB_APP_KEY
  const wsHost = import.meta.env.VITE_REVERB_HOST
  const wsPort = import.meta.env.VITE_REVERB_PORT
  const wsScheme = import.meta.env.VITE_REVERB_SCHEME

  if (!pusherKey || !wsHost) {
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

  function subscribeToChannel() {
    const ch = echo.value.channel('system-metrics')
    ch.listen('.metrics.updated', (data: any) => {
      metrics.timestamp = data.timestamp

      if (data.cpu) {
        metrics.cpu = data.cpu
        cpuHistory.value.push({ timestamp: data.timestamp, value: data.cpu.total_usage })

        if (cpuHistory.value.length > MAX_POINTS) {
cpuHistory.value.shift()
}
      }

      if (data.memory) {
        metrics.memory = data.memory
        memHistory.value.push({ timestamp: data.timestamp, value: data.memory.usage_percent })

        if (memHistory.value.length > MAX_POINTS) {
memHistory.value.shift()
}
      }

      if (data.disk) {
metrics.disk = data.disk
}

      if (data.network) {
metrics.network = data.network
}
    })
  }

  // Subscribe immediately (handles already-connected case)
  subscribeToChannel()

  // Re-subscribe on reconnect
  echo.value.connector.pusher.connection.bind('connected', () => {
    connected.value = true
  })

  echo.value.connector.pusher.connection.bind('disconnected', () => {
    connected.value = false
  })
}

defineOptions({
  layout: (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
      {
        title: 'Dashboard',
        href: props.currentTeam
          ? monitoring(props.currentTeam.slug)
          : '/',
      },
    ],
  }),
});

onMounted(() => {
  initEcho()
})

onUnmounted(() => {
  echo.value?.disconnect()
})
</script>

<template>
  <Head title="Dashboard" />

  <h1 class="sr-only">Dashboard</h1>

  <PendingInvitationsModal
    v-if="pendingInvitations && pendingInvitations.length > 0"
    :invitations="pendingInvitations"
  />

  <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <!-- Connection Status -->
    <div class="flex items-center justify-between">
      <div>
        <Heading
          title="System Overview"
          description="Real-time server metrics"
        />
      </div>
      <div class="flex items-center gap-3">
        <Badge :class="connected ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'">
          {{ connected ? 'Live' : 'Disconnected' }}
        </Badge>
        <Button variant="outline" size="sm" as-child>
          <Link :href="monitoring($page.props.currentTeam?.slug || '').url">Full Dashboard</Link>
        </Button>
      </div>
    </div>

    <!-- Gauges -->
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">CPU Usage</CardTitle>
        </CardHeader>
        <CardContent>
          <GaugeChart
            :value="metrics.cpu?.total_usage || 0"
            :max="100"
            title="CPU"
            unit="%"
            height="120px"
          />
          <div class="mt-1 text-xs text-muted-foreground text-center">
            {{ metrics.cpu?.core_count || '?' }} cores | Load {{ metrics.cpu?.load_average?.['1m']?.toFixed(1) || '?' }}
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
            title="RAM"
            unit="%"
            height="120px"
          />
          <div class="mt-1 text-xs text-muted-foreground text-center">
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
            title="Disk"
            unit="%"
            height="120px"
          />
          <div class="mt-1 text-xs text-muted-foreground text-center">
            {{ formatBytes(metrics.disk?.used_bytes || 0) }} / {{ formatBytes(metrics.disk?.total_bytes || 0) }}
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- CPU + Memory Sparklines -->
    <div class="grid gap-4 md:grid-cols-2">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">CPU % (Last 2 min)</CardTitle>
        </CardHeader>
        <CardContent>
          <LineChart
            :data="cpuHistory"
            value-key="value"
            title=""
            unit="%"
            type="area"
            height="200px"
            :y-axis-min="0"
            :y-axis-max="100"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium">Memory % (Last 2 min)</CardTitle>
        </CardHeader>
        <CardContent>
          <LineChart
            :data="memHistory"
            value-key="value"
            title=""
            unit="%"
            type="area"
            height="200px"
            :y-axis-min="0"
            :y-axis-max="100"
          />
        </CardContent>
      </Card>
    </div>

    <!-- Quick Stats -->
    <Card>
      <CardHeader class="pb-2">
        <CardTitle class="text-sm font-medium">Quick Stats</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ metrics.network?.total_bytes_received ? formatBytes(metrics.network.total_bytes_received) + '/s' : '0 B/s' }}</p>
            <p class="text-xs text-muted-foreground">Network RX</p>
          </div>
          <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
            <p class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ metrics.network?.total_bytes_sent ? formatBytes(metrics.network.total_bytes_sent) + '/s' : '0 B/s' }}</p>
            <p class="text-xs text-muted-foreground">Network TX</p>
          </div>
          <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20">
            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ metrics.memory?.swap_usage_percent?.toFixed(1) || '0' }}%</p>
            <p class="text-xs text-muted-foreground">Swap Usage</p>
          </div>
          <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ metrics.memory?.available_bytes ? formatBytes(metrics.memory.available_bytes) : '0 B' }}</p>
            <p class="text-xs text-muted-foreground">Available RAM</p>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
