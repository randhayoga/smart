<script setup lang="ts">
import type { BulletLegendItemInterface } from "@unovis/ts"
import { BulletLegend } from "@unovis/ts"
import { VisBulletLegend } from "@unovis/vue"
import { nextTick, onMounted, ref } from "vue"
import { buttonVariants } from '@/Components/ui/button'

const props = withDefaults(defineProps<{ items: BulletLegendItemInterface[] }>(), {
  items: () => [],
})

const emits = defineEmits<{
  "legendItemClick": [d: BulletLegendItemInterface, i: number]
  "update:items": [payload: BulletLegendItemInterface[]]
}>()

const elRef = ref<HTMLElement>()

function keepStyling() {
  const selector = `.${BulletLegend.selectors.item}`
  nextTick(() => {
    const elements = elRef.value?.querySelectorAll(selector)
    const classes = buttonVariants({ variant: "ghost", size: "sm" }).split(" ")

    elements?.forEach(el => el.classList.add(...classes, "!inline-flex", "!items-center", "!mr-2", "!gap-2", "!text-sm", "!font-medium"))
    
    const bullets = elRef.value?.querySelectorAll(`.${BulletLegend.selectors.bullet}`)
    bullets?.forEach(b => {
      (b as HTMLElement).style.marginRight = "6px"
    })
  })
}

onMounted(() => {
  keepStyling()
})

function onLegendItemClick(d: BulletLegendItemInterface, i: number) {
  emits("legendItemClick", d, i)
  const isBulletActive = !props.items[i].inactive
  const isFilterApplied = props.items.some(i => i.inactive)
  if (isFilterApplied && isBulletActive) {
    // reset filter
    emits("update:items", props.items.map(item => ({ ...item, inactive: false })))
  }
  else {
    // apply selection, set other item as inactive
    emits("update:items", props.items.map(item => item.name === d.name ? ({ ...d, inactive: false }) : { ...item, inactive: true }))
  }
  keepStyling()
}
</script>

<template>
  <div
    ref="elRef" class="w-max" :style="{
      '--vis-legend-bullet-size': '12px',
      '--vis-legend-bullet-label-space': '6px',
    }"
  >
    <VisBulletLegend
      :items="items"
      :on-legend-item-click="onLegendItemClick"
    />
  </div>
</template>

<style scoped>
:deep(.vis-bullet-legend-item) {
  display: inline-flex !important;
  align-items: center !important;
}

:deep(.vis-bullet-legend-bullet) {
  margin-right: 6px !important;
  display: inline-block !important;
  flex-shrink: 0 !important;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
}

:deep(.vis-bullet-legend-label),
:deep(span) {
  font-size: 0.875rem !important;
  font-weight: 500 !important;
  line-height: 1 !important;
  display: inline-flex !important;
  align-items: center !important;
}
</style>
