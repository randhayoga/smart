<script setup lang="ts">
import { X } from 'lucide-vue-next';

defineProps<{
  title?: string;
}>();

defineEmits<{
  (e: 'click'): void;
}>();
</script>

<template>
  <button
    type="button"
    @click="$emit('click')"
    :title="title || 'Reset Filter'"
    class="group h-9 w-9 flex items-center justify-center rounded-[14px] border border-rose-200 bg-rose-50/50 text-rose-500 hover:bg-rose-500 hover:text-white hover:border-rose-500 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400 dark:hover:bg-rose-500 dark:hover:text-white dark:hover:border-rose-500 transition-all duration-200 cursor-pointer shadow-sm active:scale-95 shrink-0 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:ring-offset-1 dark:focus:ring-offset-slate-900"
  >
    <X class="w-4 h-4 transition-transform duration-200 group-hover:rotate-90" />
    <span class="sr-only">Reset Filter</span>
  </button>
</template>
