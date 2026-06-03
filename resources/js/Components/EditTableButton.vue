<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';

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
    :title="title || 'Edit'"
    class="p-2 bg-amber-400 hover:opacity-70 text-white rounded-[13px] transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-400/50"
  >
    <Pencil class="w-3.5 h-3.5" />
    <span class="sr-only">{{ title || 'Edit' }}</span>
  </button>
</template>
