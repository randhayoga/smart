<script setup lang="ts">
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
      class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
    >
      <Printer class="w-4 h-4" />
      <span>Print</span>
    </button>
    <button 
      @click="$emit('export-excel')"
      class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
    >
      <FileDown class="w-4 h-4" />
      <span>Excel</span>
    </button>
    <button 
      @click="$emit('export-pdf')"
      class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
    >
      <FileDown class="w-4 h-4" />
      <span>PDF</span>
    </button>
    <button 
      @click="$emit('export-csv')"
      class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
    >
      <FileDown class="w-4 h-4" />
      <span>CSV</span>
    </button>
  </div>
</template>
