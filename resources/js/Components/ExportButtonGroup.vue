<script setup lang="ts">
/**
 * Export Button Group component providing Print, Excel, PDF, and CSV download triggers.
 */
import { Printer, FileDown } from 'lucide-vue-next';

defineEmits<{
  (e: 'print'): void;
  (e: 'export-excel'): void;
  (e: 'export-pdf'): void;
  (e: 'export-csv'): void;
}>();
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <button 
      @click="$emit('print')"
      type="button"
      class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <Printer class="w-4 h-4" />
      <span>Print</span>
    </button>
    <button 
      @click="$emit('export-excel')"
      type="button"
      class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <FileDown class="w-4 h-4" />
      <span>Excel</span>
    </button>
    <button 
      @click="$emit('export-pdf')"
      type="button"
      class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <FileDown class="w-4 h-4" />
      <span>PDF</span>
    </button>
    <button 
      @click="$emit('export-csv')"
      type="button"
      class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <FileDown class="w-4 h-4" />
      <span>CSV</span>
    </button>
  </div>
</template>
