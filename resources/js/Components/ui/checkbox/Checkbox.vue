<script setup lang="ts">
import { type HTMLAttributes, computed } from 'vue'
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { Check } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
const modelValue = defineModel<boolean | 'indeterminate' | null>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <CheckboxRoot
    v-bind="delegatedProps"
    v-model="modelValue"
    :class="
      cn(
        'peer h-4 w-4 shrink-0 rounded border border-primary shadow focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
        props.class,
      )
    "
  >
    <CheckboxIndicator class="flex h-full w-full items-center justify-center text-current">
      <slot>
        <Check class="h-3.5 w-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
