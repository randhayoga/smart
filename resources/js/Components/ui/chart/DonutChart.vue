<script setup lang="ts" generic="T extends Record<string, any>">
import type { BaseChartProps } from "./interface"
import { Donut } from "@unovis/ts"
import { VisDonut, VisSingleContainer } from "@unovis/vue"
import { computed } from "vue"
import { ChartLegend, ChartSingleTooltip } from "."

const props = withDefaults(
  defineProps<
    Omit<BaseChartProps<T>, "categories"> & {
      /**
       * Optional categories list (defaults to [category])
       */
      categories?: (keyof T & string)[]
      /**
       * Type of chart: 'donut' | 'pie'
       * @default 'donut'
       */
      type?: "donut" | "pie"
      /**
       * Value formatter function for legend/tooltip
       */
      valueFormatter?: (tick: number, i?: number, ticks?: number[]) => string
      /**
       * Key of the category value (number)
       */
      category: keyof T & string
      /**
       * Key of the index/label (string)
       */
      index: keyof T & string
      /**
       * Inner radius / arc width for donut chart
       * @default 30
       */
      arcWidth?: number
      /**
       * Center text label
       */
      centralLabel?: string
      /**
       * Center sub-label
       */
      centralSubLabel?: string
    }
  >(),
  {
    type: "donut",
    valueFormatter: (tick: number) => `${tick}`,
    showLegend: true,
    showTooltip: true,
    margin: () => ({ top: 0, bottom: 0, left: 0, right: 0 }),
    arcWidth: 30,
    filterOpacity: 0.2,
  },
)

const defaultChartColors = [
  '#6366F1', // Indigo 500
  '#10B981', // Emerald 500
  '#F59E0B', // Amber 500
  '#EC4899', // Pink 500
  '#0284C7', // Sky 600
  '#8B5CF6', // Purple 500
  '#EA580C', // Orange 600
  '#0D9488', // Teal 600
  '#E11D48', // Rose 600
  '#65A30D', // Lime 600
]

const colors = computed(() =>
  props.colors?.length ? props.colors : defaultChartColors,
)

const legendItems = computed(() => {
  return props.data.map((item, i) => ({
    name: String(item[props.index]),
    color: colors.value[i % colors.value.length],
    inactive: false,
  }))
})

const valueValue = (d: T) => {
  const val = Number(d[props.category])
  return isNaN(val) ? 0 : val
}

const totalSum = computed(() => {
  return props.data.reduce((sum, item) => sum + (Number(item[props.category]) || 0), 0)
})

const tooltipValueFormatter = (val: number) => {
  const baseFormatted = props.valueFormatter ? props.valueFormatter(val) : `${val.toLocaleString('id-ID')}`
  if (!totalSum.value || totalSum.value <= 0) return baseFormatted

  const pct = Math.round((val / totalSum.value) * 100)
  return `${baseFormatted} (${pct}%)`
}
</script>

<template>
  <div class="w-full flex flex-col items-center justify-center gap-4">
    <div class="w-full h-[220px] relative flex items-center justify-center">
      <VisSingleContainer :data="data" :margin="margin" height="220px">
        <VisDonut
          :value="valueValue"
          :color="(_d: T, i: number) => colors[i % colors.length]"
          :arc-width="type === 'pie' ? 0 : arcWidth"
          :pad-angle="0.02"
          :corner-radius="4"
          :central-label="centralLabel"
          :central-sub-label="centralSubLabel"
        />
        <ChartSingleTooltip
          v-if="showTooltip"
          :selector="Donut.selectors.segment"
          :index="index"
          :items="legendItems"
          :value-formatter="tooltipValueFormatter"
        />
      </VisSingleContainer>
    </div>

    <ChartLegend
      v-if="showLegend && legendItems.length"
      :items="legendItems"
      class="flex-wrap justify-center text-sm"
    />
  </div>
</template>
