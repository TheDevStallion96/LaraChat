<script setup lang="ts">
import { ScatterChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, GridComponent, LegendComponent, VisualMapComponent } from 'echarts/components'
import * as echarts from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'

echarts.use([
  ScatterChart,
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  VisualMapComponent,
  CanvasRenderer,
])

interface ScatterDataPoint {
  x: number
  y: number
  value?: number
  name?: string
  category?: string
  [key: string]: unknown
}

interface SeriesData {
  name: string
  data: ScatterDataPoint[]
  color?: string
  symbolSize?: number | ((val: any) => number)
}

interface Props {
  series: SeriesData[]
  title?: string
  xAxisLabel?: string
  yAxisLabel?: string
  height?: string
  showVisualMap?: boolean
  visualMapDimension?: number
}

const props = withDefaults(defineProps<Props>(), {
  xAxisLabel: 'X',
  yAxisLabel: 'Y',
  height: '300px',
  showVisualMap: false,
  visualMapDimension: 2,
})

const chartRef = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const allData = computed(() => props.series.flatMap(s => s.data))

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

const getOption = () => {
  const visualMapMax = Math.max(...allData.value.map(d => (d.value as number) || 0), 1)

  return {
    title: props.title ? { text: props.title, left: 'center', top: 10, textStyle: { fontSize: 14, fontWeight: 500 } } : undefined,
    tooltip: {
      formatter: (params: any) => {
        const data = params.data
        let result = `<strong>${params.seriesName}</strong><br/>`
        result += `${props.xAxisLabel}: ${data[0]}<br/>`
        result += `${props.yAxisLabel}: ${data[1]}<br/>`

        if (data[2] !== undefined) {
          result += `Value: ${data[2]}`
        }

        if (data.name) {
          result += `<br/>Name: ${data.name}`
        }

        return result
      },
    },
    legend: {
      show: props.series.length > 1,
      top: props.title ? 35 : 10,
      textStyle: { color: '#6b7280', fontSize: 12 },
    },
    grid: {
      left: '3%',
      right: props.showVisualMap ? '15%' : '4%',
      bottom: '3%',
      top: props.title ? 50 : 35,
      containLabel: true,
    },
    xAxis: {
      type: 'value',
      name: props.xAxisLabel,
      nameLocation: 'middle',
      nameGap: 30,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#6b7280', fontSize: 11 },
      splitLine: { lineStyle: { color: '#f3f4f6' } },
    },
    yAxis: {
      type: 'value',
      name: props.yAxisLabel,
      nameLocation: 'middle',
      nameGap: 40,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#6b7280', fontSize: 11 },
      splitLine: { lineStyle: { color: '#f3f4f6' } },
    },
    visualMap: props.showVisualMap ? {
      show: true,
      dimension: props.visualMapDimension,
      min: 0,
      max: visualMapMax,
      inRange: {
        color: ['#dbeafe', '#3b82f6', '#1e3a8a'],
        symbolSize: [10, 50],
      },
      calculable: true,
      left: 'right',
      top: 'center',
      textStyle: { color: '#6b7280' },
    } : undefined,
    series: props.series.map((s, i) => ({
      name: s.name,
      type: 'scatter',
      data: s.data.map(d => [d.x, d.y, d.value, d.name, d.category]),
      symbolSize: s.symbolSize || 10,
      itemStyle: { color: s.color || `hsl(${i * 60}, 70%, 50%)` },
      emphasis: {
        itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0, 0, 0, 0.5)' },
      },
    })),
    animationDuration: 500,
    animationEasing: 'cubicOut' as const,
  }
}

watch(
  () => props.series,
  () => {
    chartInstance?.setOption({
      series: props.series.map((s, i) => ({
        data: s.data.map(d => [d.x, d.y, d.value, d.name, d.category]),
        symbolSize: s.symbolSize || 10,
      })),
    })
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