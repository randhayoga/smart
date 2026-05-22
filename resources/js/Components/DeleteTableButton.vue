<script setup lang="ts">
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
    :title="title || 'Hapus'"
    class="p-2 bg-rose-500 hover:bg-rose-700 text-white rounded-[13px] transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50"
  >
    <Trash2 class="w-3.5 h-3.5" />
    <span class="sr-only">{{ title || 'Hapus' }}</span>
  </button>
</template>
