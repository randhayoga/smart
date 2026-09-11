<script setup lang="ts">
/**
 * Table action button styled for row deletion.
 */
import { Trash2 } from 'lucide-vue-next';

defineProps<{
  title?: string;
}>();

defineEmits<{
  (e: 'click', event: MouseEvent): void;
}>();
</script>

<template>
  <button
    type="button"
    @click="$emit('click', $event)"
    :title="title || $t('common.delete')"
    class="p-2 bg-destructive hover:opacity-70 text-white rounded-[13px] transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50"
  >
    <Trash2 class="w-3.5 h-3.5" />
    <span class="sr-only">{{ title || $t('common.delete') }}</span>
  </button>
</template>
