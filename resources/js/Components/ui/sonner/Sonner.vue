<script setup lang="ts">
import { Toaster as RadixToaster, type ToasterProps } from 'vue-sonner'

const props = defineProps<ToasterProps>()
</script>

<template>
  <RadixToaster
    class="toaster group"
    v-bind="props"
    position="top-center"
    :toast-options="{
      classes: {
        toast: 'group toast group-[.toaster]:bg-card group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg group-[.toaster]:rounded-[14px] group-[.toaster]:p-4 group-[.toaster]:font-sans group-[.toaster]:!text-sm',
        description: 'group-[.toast]:text-muted-foreground group-[.toast]:text-xs',
        actionButton: 'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground group-[.toast]:text-xs',
        cancelButton: 'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground group-[.toast]:text-xs',
      },
    }"
  />
</template>
