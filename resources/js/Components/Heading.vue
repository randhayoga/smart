<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  as?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
  variant?: 'page-title' | 'section-title' | 'card-title';
}

const props = withDefaults(defineProps<Props>(), {
  as: 'h1',
});

const classes = computed(() => {
  // Base classes for all headings
  const base = 'font-bold text-foreground';
  
  // Specific variants
  if (props.variant === 'page-title' || props.as === 'h1') {
    return `${base} text-xl sm:text-3xl leading-tight pb-3`;
  }
  
  if (props.variant === 'section-title' || props.as === 'h2') {
    return `${base} text-lg`;
  }

  if (props.variant === 'card-title') {
    return `${base} text-base`;
  }

  return base;
});
</script>

<template>
  <component :is="as" :class="classes">
    <slot />
  </component>
</template>
