<script setup lang="ts">
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
      <button
        v-for="tab in tabs"
        :key="tab"
        @click="$emit('update:modelValue', tab)"
        class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors whitespace-nowrap"
        :class="[
          modelValue === tab 
            ? 'border border-primary text-primary bg-primary/10' 
            : 'text-muted-foreground hover:text-primary hover:bg-primary/10'
        ]"
      >
        {{ tab }}
      </button>
    </div>
  </div>
</template>
