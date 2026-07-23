<script setup lang="ts">
import { HeatmapChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, GridComponent, VisualMapComponent } from 'echarts/components'
import * as echarts from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'

echarts.use([
  HeatmapChart,
  TitleComponent,
  TooltipComponent,
  GridComponent,
  VisualMapComponent,
  CanvasRenderer,
])

interface HeatmapDataPoint {
  x: number
  y: number
  value: number
  label?: string
}

interface Props {
  data: HeatmapDataPoint[]
  xLabels: string[]
  yLabels: string[]
  title?: string
  unit?: string
  colorStops: Array<{ value: number; color: string }>
  height?: string
  cellSize?: number
}

const props = withDefaults(defineProps<Props>(), {
  unit: '',
  colorStops: () => [
    { value: 0, color: '#fef3c7' },
    { value: 0.5, color: '#f59e0b' },
    { value: 1, color: '#ef4444' },
  ],
  height: '300px',
  cellSize: 20,
})

const chartRef = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const maxValue = computed(() => Math.max(...props.data.map(d => d.value), 1))

const initChart = () => {
  if (!chartRef.value || chartInstance) {
return
}

  chartInstance = echarts.init(chartRef.value)
  chartInstance.setOption(getOption())

  resizeObserver = new ResizeObserver(() => {
    chartInstance?.resize()
  })
  resizeObserver.observe(chartRef.value)
}

const getOption = () => ({
  title: props.title ? { text: props.title, left: 'center', top: 10, textStyle: { fontSize: 14, fontWeight: 500 } } : undefined,
  tooltip: {
    position: 'top',
    formatter: (params: any) => {
      const data = params.data

      return `${props.yLabels[data[1]]} / ${props.xLabels[data[0]]}<br/>${data[2]}${props.unit}${data[3] ? ` (${data[3]})` : ''}`
    },
  },
  grid: {
    left: '15%',
    right: '10%',
    bottom: '10%',
    top: props.title ? 40 : 20,
    containLabel: true,
  },
  xAxis: {
    type: 'category',
    data: props.xLabels,
    splitArea: { show: true },
    axisLine: { lineStyle: { color: '#e5e7eb' } },
    axisLabel: { color: '#6b7280', fontSize: 11, rotate: 45 },
    axisTick: { show: false },
  },
  yAxis: {
    type: 'category',
    data: props.yLabels,
    splitArea: { show: true },
    axisLine: { lineStyle: { color: '#e5e7eb' } },
    axisLabel: { color: '#6b7280', fontSize: 11 },
    axisTick: { show: false },
  },
  visualMap: {
    min: 0,
    max: maxValue.value,
    calculable: true,
    orient: 'horizontal',
    left: 'center',
    bottom: '5%',
    inRange: {
      color: props.colorStops.map(s => s.color),
    },
    textStyle: { color: '#6b7280' },
  },
  series: [
    {
      name: props.title || 'Heatmap',
      type: 'heatmap',
      data: props.data.map(d => [d.x, d.y, d.value, d.label]),
      label: { show: false },
      emphasis: {
        itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0, 0, 0, 0.5)' },
      },
    },
  ],
  animationDuration: 300,
  animationEasing: 'cubicOut' as const,
})

watch(
  () => props.data,
  () => {
    chartInstance?.setOption({
      visualMap: { max: maxValue.value },
      series: [{ data: props.data.map(d => [d.x, d.y, d.value, d.label]) }],
    })
  },
  { deep: true }
)

watch(
  () => [props.xLabels, props.yLabels, props.colorStops],
  () => {
    chartInstance?.setOption(getOption())
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