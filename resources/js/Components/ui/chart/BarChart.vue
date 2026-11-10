<script setup lang="ts" generic="T extends Record<string, any>">
import type { BaseChartProps } from "./interface"
import { GroupedBar } from "@unovis/ts"
import { VisAxis, VisGroupedBar, VisTooltip, VisXYContainer } from "@unovis/vue"
import { computed, createApp } from "vue"
import { ChartLegend, ChartTooltip } from "."

const props = withDefaults(
  defineProps<
    BaseChartProps<T> & {
      /**
       * Bar rounded corner radius
       * @default 4
       */
      roundedCorners?: number
      /**
       * Spacing between bars within a group
       * @default 0.2
       */
      barPadding?: number
      /**
       * Spacing between groups
       * @default 0.3
       */
      groupPadding?: number
      /**
       * Chart container height
       * @default '300px'
       */
      height?: string
      /**
       * Optional human-friendly category label map
       */
      categoryLabels?: Record<string, string>
    }
  >(),
  {
    height: "300px",
    margin: () => ({ top: 10, bottom: 25, left: 40, right: 15 }),
    showXAxis: true,
    showYAxis: true,
    showTooltip: true,
    showLegend: true,
    showGridLine: true,
    roundedCorners: 4,
    barPadding: 0.2,
    groupPadding: 0.3,
  },
)

const emits = defineEmits<{
  (e: "legendItemClick", d: any, i: number): void
}>()

const defaultBarColors = [
  "#10B981", // Emerald 500 (Stock In)
  "#F43F5E", // Rose 500 (Stock Out)
  "#6366F1", // Indigo 500
  "#F59E0B", // Amber 500
]

const colors = computed(() =>
  props.colors?.length ? props.colors : defaultBarColors,
)

const legendItems = computed(() => {
  return props.categories.map((cat, i) => ({
    name: props.categoryLabels?.[cat] ?? String(cat),
    color: colors.value[i % colors.value.length],
    inactive: false,
  }))
})

// X domain padding ensures single-point datasets (e.g. 1 year) are centered and multi-point datasets have half-band padding
const xDomain = computed(() => {
  if (!props.data || props.data.length === 0) return [0, 1]
  if (props.data.length === 1) return [-0.5, 0.5]
  return [-0.5, props.data.length - 0.5]
})

// Y domain configuration ensures empty or all-zero datasets have a clean integer scale [0, 10]
const yDomain = computed(() => {
  if (!props.data || props.data.length === 0) return [0, 10]
  let max = 0
  for (const item of props.data) {
    for (const cat of props.categories) {
      const val = Number(item[cat])
      if (!isNaN(val) && val > max) max = val
    }
  }
  if (max === 0) {
    return [0, 10]
  }
  return undefined
})

// Ticks array: place an axis tick at every single index so each bar (days or months) has its corresponding label
const tickValues = computed(() => {
  if (!props.data || props.data.length === 0) return undefined
  if (props.data.length <= 31) {
    return props.data.map((_, i) => i)
  }
  return undefined
})

// X accessor maps the index to array index
const xAccessor = (_d: T, i: number) => i

// Y accessors for each category
const yAccessors = computed(() =>
  props.categories.map((cat) => (d: T) => {
    const val = Number(d[cat])
    return isNaN(val) ? 0 : val
  }),
)

const colorAccessors = computed(() =>
  props.categories.map((_, i) => colors.value[i % colors.value.length]),
)

const defaultXFormatter = (tick: number | Date, i: number, ticks: number[] | Date[]) => {
  if (props.xFormatter) {
    return props.xFormatter(tick, i, ticks)
  }
  const num = Number(tick)
  if (isNaN(num)) return ""
  const dataIndex = Math.round(num)
  if (Math.abs(num - dataIndex) > 1e-3 || dataIndex < 0 || dataIndex >= props.data.length) {
    return ""
  }
  const item = props.data[dataIndex]
  return item ? String(item[props.index] ?? "") : ""
}

const defaultYFormatter = (tick: number | Date) => {
  const val = Number(tick)
  return isNaN(val) ? "0" : val.toLocaleString()
}

// Custom tooltip renderer using ChartTooltip component
const tooltipTemplate = (d: any, i: number) => {
  const item = (d && props.index in d) ? d : (props.data[i] || props.data[d?.dIndex] || d)
  if (!item) return ""

  const title = String(item[props.index] ?? "")
  const rows = props.categories.map((cat, idx) => {
    const rawVal = Number(item[cat])
    const formatted = isNaN(rawVal) ? "0" : rawVal.toLocaleString()
    return {
      name: props.categoryLabels?.[cat] ?? String(cat),
      color: colors.value[idx % colors.value.length],
      value: formatted,
    }
  })

  const componentDiv = document.createElement("div")
  createApp(ChartTooltip, {
    title,
    data: rows,
  }).mount(componentDiv)

  return componentDiv.innerHTML
}
</script>

<template>
  <div class="w-full flex flex-col items-center justify-center gap-4">
    <div class="w-full relative" :style="{ height }">
      <VisXYContainer
        :data="data"
        :margin="margin"
        :height="height"
        :x-domain="xDomain"
        :y-domain="yDomain"
        :prevent-empty-domain="true"
      >
        <VisGroupedBar
          :x="xAccessor"
          :y="yAccessors"
          :color="colorAccessors"
          :rounded-corners="roundedCorners"
          :bar-padding="barPadding"
          :group-padding="groupPadding"
        />

        <VisAxis
          v-if="showXAxis"
          type="x"
          :tick-values="tickValues"
          :tick-format="xFormatter ?? defaultXFormatter"
          :tick-text-hide-overlapping="true"
          :grid-line="false"
        />

        <VisAxis
          v-if="showYAxis"
          type="y"
          :tick-format="yFormatter ?? defaultYFormatter"
          :grid-line="showGridLine"
        />

        <VisTooltip
          v-if="showTooltip"
          :horizontal-shift="15"
          :vertical-shift="15"
          :triggers="{
            [GroupedBar.selectors.bar]: tooltipTemplate,
            [GroupedBar.selectors.barGroup]: tooltipTemplate,
          }"
        />
      </VisXYContainer>
    </div>

    <ChartLegend
      v-if="showLegend && legendItems.length"
      :items="legendItems"
      @legend-item-click="(d: any, i: number) => emits('legendItemClick', d, i)"
      class="flex-wrap justify-center text-sm"
    />
  </div>
</template>

<style>
:root {
  --vis-tooltip-background-color: transparent !important;
  --vis-tooltip-border-color: transparent !important;
  --vis-tooltip-padding: 0px !important;
  --vis-tooltip-border-radius: 0px !important;
  --vis-tooltip-box-shadow: none !important;
  --vis-tooltip-shadow-color: transparent !important;
  --vis-dark-tooltip-background-color: transparent !important;
}
</style>
