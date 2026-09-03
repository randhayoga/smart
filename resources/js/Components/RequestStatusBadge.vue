<script setup lang="ts">
/**
 * Standardized Request Status Badge component.
 * Renders consistent status pills across the application.
 */
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { 
  getRequestStatusLabel, 
  getRequestStatusBadgeClass, 
  REQUEST_STATUS_PILL_BASE 
} from '@/lib/requestStatus';

const props = defineProps<{
  status?: string | null;
  class?: string;
}>();

const label = computed(() => getRequestStatusLabel(props.status));
const badgeClass = computed(() => getRequestStatusBadgeClass(props.status));
</script>

<template>
  <span :class="cn(REQUEST_STATUS_PILL_BASE, badgeClass, props.class)">
    {{ label }}
  </span>
</template>
