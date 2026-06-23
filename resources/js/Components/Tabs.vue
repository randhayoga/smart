<script setup lang="ts">
import { Button } from '@/Components/ui/button';

defineProps<{
  modelValue: string;
  tabs: string[];
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
        :key="tab"
        @click="$emit('update:modelValue', tab)"
        :variant="modelValue === tab ? 'default' : 'ghost'"
        class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors whitespace-nowrap h-auto"
        :class="[
          modelValue === tab 
            ? 'border border-primary text-primary bg-primary/10 hover:bg-primary/20 shadow-none' 
            : 'text-muted-foreground hover:text-primary hover:bg-primary/10'
        ]"
      >
        {{ tab }}
      </Button>
    </div>
  </div>
</template>
