<script setup lang="ts">
import * as echarts from 'echarts/core'
import { TreemapChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'

echarts.use([
  TreemapChart,
  TitleComponent,
  TooltipComponent,
  CanvasRenderer,
])

interface TreemapDataItem {
  name: string
  value: number
  children?: TreemapDataItem[]
  itemStyle?: { color: string }
  [key: string]: unknown
}

interface Props {
  data: TreemapDataItem[]
  title?: string
  unit?: string
  height?: string
  colorMapping?: Array<{ value: number; color: string }>
  levels?: number
}

const props = withDefaults(defineProps<Props>(), {
  unit: '',
  height: '400px',
  levels: 2,
  colorMapping: () => [
    { value: 0, color: '#dbeafe' },
    { value: 50, color: '#3b82f6' },
    { value: 100, color: '#1e3a8a' },
  ],
})

const chartRef = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const initChart = () => {
  if (!chartRef.value || chartInstance) return

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
    formatter: (params: any) => {
      const data = params.data
      let result = `<strong>${data.name}</strong><br/>`
      result += `Value: ${data.value}${props.unit}<br/>`
      if (data.percent !== undefined) {
        result += `Percentage: ${data.percent}%`
      }
      return result
    },
  },
  series: [
    {
      name: props.title || 'Treemap',
      type: 'treemap',
      data: props.data,
      levels: [
        {
          itemStyle: {
            borderColor: '#fff',
            borderWidth: 2,
            gapWidth: 2,
          },
        },
        {
          itemStyle: {
            borderColor: '#e5e7eb',
            borderWidth: 1,
            gapWidth: 1,
          },
        },
      ],
      label: {
        show: true,
        fontSize: 12,
        color: '#1f2937',
        formatter: (params: any) => {
          if (params.data.value === undefined) return params.data.name
          return `${params.data.name}\n${params.data.value}${props.unit}`
        },
      },
      upperLabel: {
        show: true,
        height: 28,
        fontSize: 14,
        fontWeight: 600,
        color: '#1f2937',
        formatter: '{a}',
      },
      breadcrumb: {
        show: false,
      },
      roam: false,
      nodeClick: false,
    },
  ],
  animationDuration: 500,
  animationEasing: 'cubicOut' as const,
})

watch(
  () => props.data,
  () => {
    chartInstance?.setOption({ series: [{ data: props.data }] })
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