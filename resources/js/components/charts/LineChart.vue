<script setup lang="ts">
import { LineChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, GridComponent, LegendComponent } from 'echarts/components'
import * as echarts from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'

echarts.use([
  LineChart,
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  CanvasRenderer,
])

interface DataPoint {
  timestamp: number
  value: number
  [key: string]: unknown
}

interface Props {
  data: DataPoint[]
  valueKey: string
  labelKey?: string
  title?: string
  unit?: string
  type?: 'line' | 'area'
  smooth?: boolean
  showSymbol?: boolean
  maxPoints?: number
  height?: string
  colors?: string[]
  yAxisMin?: number
  yAxisMax?: number
}

const props = withDefaults(defineProps<Props>(), {
  labelKey: 'timestamp',
  unit: '',
  type: 'line',
  smooth: true,
  showSymbol: false,
  maxPoints: 300,
  height: '300px',
  colors: () => ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
  yAxisMin: undefined,
  yAxisMax: undefined,
})

const chartRef = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const processedData = computed(() => {
  const data = props.data.slice(-props.maxPoints)

  return {
    xAxis: data.map(d => new Date((d[props.labelKey] as number) * 1000).toLocaleTimeString()),
    series: data.map(d => (d[props.valueKey] as number) ?? 0),
    raw: data,
  }
})

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
  const colors = props.colors || ['#3b82f6']
  
  return {
    title: props.title ? { text: props.title, left: 'center', top: 10, textStyle: { fontSize: 14, fontWeight: 500 } } : undefined,
    tooltip: {
      trigger: 'axis',
      formatter: (params: any[]) => {
        let result = params[0].axisValue + '<br/>'
        params.forEach(p => {
          result += `${p.marker} ${p.seriesName}: ${p.value}${props.unit}<br/>`
        })

        return result
      },
    },
    legend: {
      show: false,
      top: 30,
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '3%',
      top: props.title ? '45' : '30',
      containLabel: true,
    },
    xAxis: {
      type: 'category',
      data: processedData.value.xAxis,
      boundaryGap: false,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#6b7280', fontSize: 11 },
      axisTick: { show: false },
    },
    yAxis: {
      type: 'value',
      min: props.yAxisMin,
      max: props.yAxisMax,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#6b7280', fontSize: 11, formatter: `{value}${props.unit}` },
      splitLine: { lineStyle: { color: '#f3f4f6' } },
    },
      series: [
        {
          name: props.title || props.valueKey,
          type: 'line',
        data: processedData.value.series,
        smooth: props.smooth,
        showSymbol: props.showSymbol,
        symbolSize: 4,
        lineStyle: { width: 2, color: colors[0] },
        areaStyle: props.type === 'area' ? { opacity: 0.3, color: colors[0] } : undefined,
        itemStyle: { color: colors[0] },
      },
    ],
    animationDuration: 300,
    animationEasing: 'cubicOut' as const,
  }
}

watch(
  () => props.data,
  () => {
    chartInstance?.setOption({
      xAxis: { data: processedData.value.xAxis },
      series: [{ data: processedData.value.series }],
    })
  },
  { deep: true }
)

watch(
  () => [props.type, props.smooth, props.colors, props.yAxisMin, props.yAxisMax],
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