<script setup lang="ts">
import { Printer, FileDown } from 'lucide-vue-next';

defineEmits<{
  (e: 'export-excel'): void;
  (e: 'export-pdf'): void;
  (e: 'export-csv'): void;
}>();

const handlePrint = () => {
  window.print();
};
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <button 
      @click="handlePrint"
      class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <Printer class="w-4 h-4" />
      <span>Print</span>
    </button>
    <button 
      @click="$emit('export-excel')"
      class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <FileDown class="w-4 h-4" />
      <span>Excel</span>
    </button>
    <button 
      @click="$emit('export-pdf')"
      class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <FileDown class="w-4 h-4" />
      <span>PDF</span>
    </button>
    <button 
      @click="$emit('export-csv')"
      class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm cursor-pointer"
    >
      <FileDown class="w-4 h-4" />
      <span>CSV</span>
    </button>
  </div>
</template>

<style>
.print-only {
  display: none !important;
}

@media print {
  .print-only {
    display: block !important;
  }

  /* Hide browser headers/footers (Title/URL) */
  @page {
    size: landscape;
    margin: 1.5cm;
  }

  body {
    background: white !important;
    color: black !important;
  }

  /* Hide navigation, sidebar, breadcrumbs, header, dashboard elements, etc. */
  aside,
  header,
  nav,
  .no-print,
  /* Hide filter block & actions block */
  .bg-card > .py-5,
  .bg-card > div:not(.pb-4):not(:has(table)),
  /* Hide paginations / page sizes if any */
  .flex.items-center.justify-between.gap-4.text-sm.text-muted-foreground,
  .flex.items-center.justify-between.gap-2.flex-wrap,
  /* Hide first column (checkbox) and last column (Aksi) */
  th:first-child, td:first-child,
  th:last-child, td:last-child,
  /* Hide sort indicators, etc. */
  th svg,
  .lucide,
  button:not(th button),
  input
  {
    display: none !important;
  }

  /* Reset layout and borders */
  .bg-card {
    border: none !important;
    box-shadow: none !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    border-radius: 0 !important;
  }

  .bg-card div {
    border-radius: 0 !important;
  }

  /* Hide unselected rows only when at least one row is selected */
  tbody:has(tr[data-state="selected"]) tr:not([data-state="selected"]) {
    display: none !important;
  }

  /* Shrink table for total fit */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 10px !important;
    table-layout: auto !important;
  }

  th, td {
    border: 1px solid #ddd !important;
    word-break: break-word !important;
    border-radius: 0 !important;
    text-align: left !important;
  }

  th {
    background-color: #f8fafc !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    color: black !important;
    font-weight: 600 !important;
  }

  th button {
    display: inline-flex !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    color: inherit !important;
    font-size: inherit !important;
    font-weight: inherit !important;
    box-shadow: none !important;
    pointer-events: none !important;
  }

  td {
    padding: 6px 8px !important;
  }

  .font-mono {
    font-size: 10px !important;
  }

  .truncate {
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: clip !important;
  }
}
</style>
