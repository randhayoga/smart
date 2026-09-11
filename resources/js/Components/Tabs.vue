<script setup lang="ts">
/**
 * Horizontal Pill Tabs component for section navigation and filtering.
 */
import { Button } from '@/Components/ui/button';

defineProps<{
  modelValue: string;
  tabs: (string | { id: string; label: string })[];
}>();

defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
  <div class="flex overflow-x-auto pb-2 scrollbar-hide">
    <div class="flex items-center border border-border rounded-full bg-card p-1 shadow-sm w-max">
      <Button
        v-for="tab in tabs"
        :key="typeof tab === 'string' ? tab : tab.id"
        @click="$emit('update:modelValue', typeof tab === 'string' ? tab : tab.id)"
        :variant="modelValue === (typeof tab === 'string' ? tab : tab.id) ? 'default' : 'ghost'"
        class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors whitespace-nowrap h-auto"
        :class="[
          modelValue === (typeof tab === 'string' ? tab : tab.id) 
            ? 'border border-primary text-primary bg-primary/10 hover:bg-primary/20 shadow-none' 
            : 'text-muted-foreground hover:text-primary hover:bg-primary/10'
        ]"
      >
        {{ typeof tab === 'string' ? tab : tab.label }}
      </Button>
    </div>
  </div>
</template>
