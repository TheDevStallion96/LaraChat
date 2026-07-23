<script setup lang="ts">
import * as echarts from 'echarts/core'
import { BarChart, LineChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, GridComponent, LegendComponent, XAxisComponent, YAxisComponent, DataZoomComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'

echarts.use([
  BarChart,
  LineChart,
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  XAxisComponent,
  YAxisComponent,
  DataZoomComponent,
  CanvasRenderer,
])

interface TimelineEvent {
  timestamp: number
  type: string
  message: string
  severity: 'info' | 'warning' | 'critical' | 'success'
  source: string
  details?: Record<string, unknown>
}

interface SeriesData {
  name: string
  data: number[]
  type?: 'line' | 'bar'
}

interface Props {
  events: TimelineEvent[]
  metrics?: SeriesData[]
  title?: string
  height?: string
  timeWindow?: number // seconds
  maxEvents?: number
}

const props = withDefaults(defineProps<Props>(), {
  height: '400px',
  timeWindow: 300, // 5 minutes
  maxEvents: 100,
})

const chartRef = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const filteredEvents = computed(() => {
  const now = Date.now() / 1000
  const cutoff = now - props.timeWindow
  return props.events
    .filter(e => e.timestamp >= cutoff)
    .slice(-props.maxEvents)
    .sort((a, b) => a.timestamp - b.timestamp)
})

const eventData = computed(() => {
  const events = filteredEvents.value
  return {
    timestamps: events.map(e => new Date(e.timestamp * 1000).toLocaleTimeString()),
    events: events.map(e => ({
      timestamp: e.timestamp,
      type: e.type,
      message: e.message,
      severity: e.severity,
      source: e.source,
      details: e.details,
    })),
  }
})

const severityColors = {
  info: '#3b82f6',
  warning: '#f59e0b',
  critical: '#ef4444',
  success: '#22c55e',
}

const initChart = () => {
  if (!chartRef.value || chartInstance) return

  chartInstance = echarts.init(chartRef.value)
  chartInstance.setOption(getOption())

  resizeObserver = new ResizeObserver(() => {
    chartInstance?.resize()
  })
  resizeObserver.observe(chartRef.value)
}

const getOption = () => {
  const hasMetrics = props.metrics && props.metrics.length > 0
  const series: any[] = []

  // Add metric series
  if (hasMetrics) {
    props.metrics!.forEach((metric, i) => {
      series.push({
        name: metric.name,
        type: metric.type || 'line',
        data: metric.data,
        smooth: true,
        showSymbol: false,
        lineStyle: { width: 2 },
        yAxisIndex: 0,
      })
    })
  }

  // Add event markers as scatter on secondary y-axis
  const eventSeries = {
    name: 'Events',
    type: 'scatter',
    data: eventData.value.events.map((e, i) => [
      i,
      hasMetrics ? Math.max(...(props.metrics?.[0]?.data || [1])) * 1.1 : 1,
      e.severity,
      e.type,
      e.message,
    ]),
    symbolSize: 12,
    symbol: 'pin',
    itemStyle: {
      color: (params: any) => severityColors[params.data[2] as keyof typeof severityColors] || '#6b7280',
    },
    label: {
      show: false,
    },
    yAxisIndex: hasMetrics ? 1 : 0,
    z: 10,
  }
  series.push(eventSeries)

  return {
    title: props.title ? { text: props.title, left: 'center', top: 10, textStyle: { fontSize: 14, fontWeight: 500 } } : undefined,
    tooltip: {
      trigger: 'axis',
      formatter: (params: any[]) => {
        let result = ''
        params.forEach(p => {
          if (p.seriesName === 'Events') {
            result += `<strong>${p.data[3]}</strong> (${p.data[2]})<br/>`
            result += `${p.data[4]}<br/>`
          } else {
            result += `${p.marker} ${p.seriesName}: ${p.value}<br/>`
          }
        })
        return result
      },
    },
    legend: {
      show: true,
      top: props.title ? 35 : 10,
      textStyle: { color: '#6b7280', fontSize: 11 },
    },
    dataZoom: [
      {
        type: 'inside',
        start: 0,
        end: 100,
      },
      {
        type: 'slider',
        show: true,
        start: 0,
        end: 100,
        bottom: 0,
        height: 20,
        fillerColor: 'rgba(59, 130, 246, 0.2)',
        borderColor: '#e5e7eb',
        handleStyle: { color: '#3b82f6' },
      },
    ],
    grid: {
      left: '3%',
      right: '4%',
      bottom: hasMetrics ? 60 : 10,
      top: props.title ? 50 : 40,
      containLabel: true,
    },
    xAxis: {
      type: 'category',
      data: eventData.value.timestamps,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#6b7280', fontSize: 10, rotate: 30 },
      axisTick: { show: false },
      splitLine: { show: false },
    },
    yAxis: hasMetrics ? [
      {
        type: 'value',
        name: 'Metrics',
        axisLine: { lineStyle: { color: '#e5e7eb' } },
        axisLabel: { color: '#6b7280', fontSize: 11 },
        splitLine: { lineStyle: { color: '#f3f4f6' } },
      },
      {
        type: 'value',
        show: false,
        min: 0,
        max: 2,
      },
    ] : [
      {
        type: 'value',
        show: false,
        min: 0,
        max: 2,
      },
    ],
    series,
    animationDuration: 300,
    animationEasing: 'cubicOut',
  }
}

watch(
  () => [props.events, props.metrics],
  () => {
    if (chartInstance) {
      const option = getOption()
      chartInstance.setOption({
        xAxis: { data: option.xAxis?.data },
        series: option.series,
      })
    }
  },
  { deep: true }
)

onMounted(() => {
  initChart()
})

onUnmounted(() => {
  resizeObserver?.disconnect()
  chartInstance?.dispose()
  chartInstance = null
})
</script>

<template>
  <div ref="chartRef" class="w-full" :style="{ height }"></div>
</template>