<script setup lang="ts">
import * as echarts from 'echarts/core'
import { GaugeChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { use } from 'echarts/core'
import { ref, watch, onMounted, onUnmounted, type Ref } from 'vue'

echarts.use([
  GaugeChart,
  TitleComponent,
  TooltipComponent,
  CanvasRenderer,
])

interface Props {
  value: number
  max?: number
  min?: number
  title?: string
  unit?: string
  color?: string
  thresholds?: Array<{ value: number; color: string }>
  height?: string
  width?: string
}

const props = withDefaults(defineProps<Props>(), {
  max: 100,
  min: 0,
  unit: '%',
  color: '#3b82f6',
  thresholds: [
    { value: 50, color: '#22c55e' },
    { value: 80, color: '#f59e0b' },
    { value: 100, color: '#ef4444' },
  ],
  height: '200px',
  width: '100%',
})

const chartRef = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const initChart = () => {
  if (!chartRef.value || chartInstance) return

  chartInstance = echarts.init(chartRef.value)

  const option = getOption()
  chartInstance.setOption(option)

  resizeObserver = new ResizeObserver(() => {
    chartInstance?.resize()
  })
  resizeObserver.observe(chartRef.value)
}

const getOption = () => ({
  title: props.title ? { text: props.title, left: 'center', top: '10', textStyle: { fontSize: 14, fontWeight: 500 } } : undefined,
  tooltip: {
    formatter: `{a}: {c}${props.unit}`,
  },
  series: [
    {
      name: props.title || 'Value',
      type: 'gauge',
      min: props.min,
      max: props.max,
      startAngle: 225,
      endAngle: -45,
      pointer: {
        show: true,
        length: '70%',
        width: 8,
      },
      progress: {
        show: true,
        width: 18,
        roundCap: true,
      },
      axisLine: {
        lineStyle: {
          width: 18,
          color: props.thresholds.map(t => [t.value / props.max, t.color]),
        },
      },
      axisTick: { show: false },
      splitLine: { show: false },
      axisLabel: { show: false },
      detail: {
        valueAnimation: true,
        formatter: `{value} ${props.unit}`,
        fontSize: 24,
        fontWeight: 600,
        color: '#1f2937',
        offsetCenter: [0, '40%'],
      },
      data: [{ value: props.value, name: props.title || 'Value' }],
    },
  ],
})

watch(
  () => props.value,
  (newVal) => {
    chartInstance?.setOption({
      series: [{ data: [{ value: newVal }] }],
    })
  }
)

watch(
  () => [props.max, props.min, props.title, props.unit, props.color, props.thresholds],
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
  <div ref="chartRef" class="w-full" :style="{ height, width }"></div>
</template>