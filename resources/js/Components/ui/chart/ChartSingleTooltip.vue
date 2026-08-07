<script setup lang="ts">
import type { BulletLegendItemInterface } from "@unovis/ts"
import type { Component } from "vue"
import { omit } from "@unovis/ts"
import { VisTooltip } from "@unovis/vue"
import { createApp } from "vue"
import { ChartTooltip } from "."

const props = defineProps<{
  selector: string
  index: string
  items?: BulletLegendItemInterface[]
  valueFormatter?: (tick: number, i?: number, ticks?: number[]) => string
  customTooltip?: Component
}>()

// Use weakmap to store reference to each datapoint for Tooltip
const wm = new WeakMap()
function template(d: any, i: number, elements: (HTMLElement | SVGElement)[]) {
  const valueFormatter = props.valueFormatter ?? ((tick: number) => `${tick}`)
  const data = d.data || d

  if (wm.has(d)) {
    return wm.get(d)
  }

  const name = String(data[props.index] ?? data.name ?? props.items?.[i]?.name ?? '')
  const style = elements?.[i] ? getComputedStyle(elements[i]) : null
  const color = style?.fill && style.fill !== 'none' ? style.fill : (props.items?.find(item => item.name === name)?.color || props.items?.[i]?.color || '')

  const numericEntry = Object.entries(data).find(([k, v]) => k !== props.index && !isNaN(Number(v)))
  const rawVal = numericEntry ? Number(numericEntry[1]) : Number(d.value || 0)
  const formattedVal = valueFormatter(isNaN(rawVal) ? 0 : rawVal, data)

  const omittedData = [{ name, value: formattedVal, color }]
  const componentDiv = document.createElement("div")
  const TooltipComponent = props.customTooltip ?? ChartTooltip
  createApp(TooltipComponent, { data: omittedData }).mount(componentDiv)
  wm.set(d, componentDiv.innerHTML)
  return componentDiv.innerHTML
}
</script>

<template>
  <VisTooltip
    :horizontal-shift="20" :vertical-shift="20" :triggers="{
      [selector]: template,
    }"
  />
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
  --vis-dark-tooltip-border-color: transparent !important;
  --vis-dark-tooltip-shadow-color: transparent !important;
}

div[class*="tooltip"] {
  background-color: transparent !important;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  padding: 0 !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
}
</style>
