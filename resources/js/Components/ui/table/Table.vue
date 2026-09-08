<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
  containerClass?: HTMLAttributes["class"]
  noWrapper?: boolean
}>()
</script>

<template>
  <table v-if="props.noWrapper" :class="cn('w-full caption-bottom text-sm', props.class)">
    <slot />
  </table>
  <div v-else :class="cn('relative w-full overflow-auto', props.containerClass)">
    <table :class="cn('w-full caption-bottom text-sm', props.class)">
      <slot />
    </table>
  </div>
</template>
