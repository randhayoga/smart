<script setup lang="ts">
/**
 * Standardized Request Status Badge component.
 * Renders consistent status pills across the application (supports multi-status).
 */
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { 
  getRequestStatusBadges, 
  REQUEST_STATUS_PILL_BASE 
} from '@/lib/requestStatus';

const props = defineProps<{
  status?: string | null;
  class?: string;
}>();

const badges = computed(() => getRequestStatusBadges(props.status));
</script>

<template>
  <div v-if="badges.length > 1" class="flex flex-wrap items-center gap-1.5">
    <span 
      v-for="(badge, index) in badges" 
      :key="index"
      :class="cn(REQUEST_STATUS_PILL_BASE, badge.class, props.class)"
    >
      {{ badge.label }}
    </span>
  </div>
  <span v-else-if="badges.length === 1" :class="cn(REQUEST_STATUS_PILL_BASE, badges[0].class, props.class)">
    {{ badges[0].label }}
  </span>
  <span v-else :class="cn(REQUEST_STATUS_PILL_BASE, 'bg-muted text-muted-foreground', props.class)">
    -
  </span>
</template>
