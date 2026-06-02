<script setup lang="ts">
import { Eye } from 'lucide-vue-next';

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
    :title="title || 'Lihat Detail'"
    class="p-2 bg-cyan-500 hover:opacity-70 text-white rounded-[13px] transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
  >
    <Eye class="w-3.5 h-3.5" />
    <span class="sr-only">{{ title || 'Lihat Detail' }}</span>
  </button>
</template>
