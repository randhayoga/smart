<script setup lang="ts">
// Trigger re-build
import { ref, computed, watch, h, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  Search, 
  ChevronDown, 
  ArrowUpDown, 
  ArrowUp,
  ArrowDown,
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  Printer,
  FileDown,
  FileText,
  X,
  Eye
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import FilterDropdown from '@/Components/FilterDropdown.vue';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

const dummyInbox = [
  { 
    id: 1, 
    number: '052026-0001', 
    amount: 12, 
    requester: 'John Doe', 
    createdAt: '05-05-2026 09:30', 
    startTime: '05-05-2026 10:00', 
    endTime: '-',
    type: 'Habis Pakai'
  },
  { 
    id: 2, 
    number: '052026-0002', 
    amount: 1, 
    requester: 'Jane Smith', 
    createdAt: '05-05-2026 09:45', 
    startTime: '05-05-2026 11:00', 
    endTime: '06-05-2026 11:00',
    type: 'Pinjam'
  },
  { 
    id: 3, 
    number: '052026-0003', 
    amount: 5, 
    requester: 'Budi Santoso', 
    createdAt: '05-05-2026 10:15', 
    startTime: '05-05-2026 13:00', 
    endTime: '-',
    type: 'Habis Pakai'
  },
  { 
    id: 4, 
    number: '052026-0004', 
    amount: 2, 
    requester: 'Siti Aminah', 
    createdAt: '05-05-2026 11:00', 
    startTime: '05-05-2026 14:00', 
    endTime: '05-05-2026 17:00',
    type: 'Pinjam'
  },
];

const searchQuery = ref('');
const typeFilter = ref('');
const timeFilter = ref('');
const rowsPerPage = ref('10');

const dataTableRef = ref<any>(null);

const columns: ColumnDef<any>[] = [
  {
    id: 'select',
    size: 50,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
      })
    ]),
  },
  {
    accessorKey: 'number',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Nomor',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-medium pl-2' }, row.getValue('number')),
  },
  {
    accessorKey: 'amount',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground justify-center w-full'
    }, () => [
      'Jumlah',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-center' }, row.getValue('amount')),
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Nama Peminta',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-2' }, row.getValue('requester')),
  },
  {
    accessorKey: 'createdAt',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Waktu Dibuat',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground pl-2' }, row.getValue('createdAt')),
  },
  {
    accessorKey: 'startTime',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Waktu Mulai',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground pl-2' }, row.getValue('startTime')),
  },
  {
    accessorKey: 'endTime',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Waktu Selesai',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground pl-2' }, row.getValue('endTime')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-bold text-foreground' }, 'Aksi'),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center' }, [
      h('button', {
        class: 'p-2 bg-cyan-400 hover:bg-cyan-500 text-white rounded-[14px] transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-400/50'
      }, [h(Eye, { class: 'w-4 h-4' })])
    ]),
  },
];

// Watchers for filters
watch(typeFilter, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.getColumn('type')?.setFilterValue(val);
  }
});

watch(rowsPerPage, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    if (val === 'Semua baris' || !val) {
      dataTableRef.value.table.setPageSize(999999);
    } else {
      dataTableRef.value.table.setPageSize(Number(val));
    }
  }
});

onMounted(() => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.setPageSize(Number(rowsPerPage.value));
  }
});

const handlePrint = () => {
  window.print();
};

const handleExportCSV = () => {
  if (displayData.value.length === 0) return;
  
  const headers = ['Nomor', 'Jumlah', 'Nama Peminta', 'Waktu Dibuat', 'Waktu Mulai', 'Waktu Selesai', 'Jenis'];
  const rows = displayData.value.map(item => [
    `"${item.number}"`,
    `"${item.amount}"`,
    `"${item.requester}"`,
    `"${item.createdAt}"`,
    `"${item.startTime}"`,
    `"${item.endTime}"`,
    `"${item.type}"`
  ]);

  // Use \uFEFF BOM for Excel compatibility (UTF-8)
  // Prepend "sep=," to tell Excel explicitly that the comma is the delimiter
  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map(e => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `inbox_export_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  // Simple Excel-compatible CSV with tab separator or just call CSV for now
  handleExportCSV();
};

const handleExportPDF = () => {
  // PDF is often just a print of the page
  window.print();
};
</script>

<template>
  <AppLayout title="Inbox">
    <div class="space-y-6">
      <div class="flex flex-col gap-1">
        <h2 class="text-2xl font-bold text-foreground">Daftar Permintaan Baru</h2>
      </div>

      <!-- Main Card -->
      <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="p-5">
          <!-- Filters -->
          <div class="space-y-4">
            <div class="flex flex-col sm:flex-row items-end gap-4">
              <div class="space-y-1.5 flex-1 max-w-md">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <div class="relative">
                  <input 
                    type="text" 
                    v-model="searchQuery"
                    placeholder="Cari nomor permintaan atau nama peminta..." 
                    class="w-full pl-9 pr-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                  />
                  <Search class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                </div>
              </div>

              <FilterDropdown 
                v-model="typeFilter"
                :options="['Habis Pakai', 'Pinjam']"
                placeholder="Semua jenis"
                all-label="Semua jenis"
                class="flex-1 max-w-[200px]"
              />

              <FilterDropdown 
                v-model="timeFilter"
                :options="[
                  { label: 'Hari Ini', value: 'today' },
                  { label: 'Minggu Ini', value: 'week' },
                  { label: 'Bulan Ini', value: 'month' }
                ]"
                placeholder="Semua kurun waktu"
                all-label="Semua kurun waktu"
                class="flex-1 max-w-[200px]"
              />
            </div>

            <!-- Export Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
              <div class="space-y-2">
                <label class="text-xs text-muted-foreground font-medium block">Aksi Terpilih</label>
                <div class="flex flex-wrap gap-2">
                  <button 
                    @click="handlePrint"
                    class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <Printer class="w-4 h-4" />
                    Print
                  </button>
                  <button 
                    @click="handleExportExcel"
                    class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <FileDown class="w-4 h-4" />
                    Export: Excel
                  </button>
                  <button 
                    @click="handleExportPDF"
                    class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <FileDown class="w-4 h-4" />
                    Export: PDF
                  </button>
                  <button 
                    @click="handleExportCSV"
                    class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <FileDown class="w-4 h-4" />
                    Export: CSV
                  </button>
                </div>
              </div>

              <div class="flex items-center gap-3 text-sm text-muted-foreground pt-6">
                <span>Baris per halaman</span>
              <FilterDropdown 
                v-model="rowsPerPage"
                :options="['10', '25', '50']"
                placeholder="Semua baris"
                all-label="Semua baris"
                class="w-[140px]"
              />
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="px-[10px] pb-[10px]">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="dummyInbox" 
            :filter-value="searchQuery"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@media print {
  /* Hide browser headers/footers (Title/URL) */
  @page {
    size: landscape;
    margin: 0; /* Set margin to 0 to hide default headers/footers */
  }

  body {
    padding: 1.5cm !important; /* Add padding to compensate for 0 margin */
    background: white !important;
  }

  /* Hide unnecessary elements */
  :deep(aside), 
  :deep(header),
  :deep(nav),
  .no-print,
  .bg-card > .p-5,
  .flex.items-center.justify-between.gap-4.text-sm.text-muted-foreground,
  th:first-child, td:first-child, /* Hide Checkbox column */
  th:last-child, td:last-child, /* Hide Action column */
  th svg, /* Hide sort icons */
  .lucide /* Hide any other icons in the table */
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
    border-radius: 0 !important; /* Edge 0 */
  }

  .bg-card div {
    border-radius: 0 !important;
  }

  /* Shrink table for total fit */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 9px !important; /* Even smaller font */
    table-layout: fixed !important; /* Force fixed layout to avoid overflow */
  }

  th, td {
    border: 1px solid #000 !important;
    padding: 4px 2px !important;
    word-break: break-all !important;
    border-radius: 0 !important;
  }

  th {
    background-color: #f1f5f9 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    color: black !important;
  }

  .truncate {
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: clip !important;
  }

  /* Remove all padding/margin from wrappers */
  .px-\[10px\], .pb-\[10px\], .p-4, .p-5 {
    padding: 0 !important;
    margin: 0 !important;
  }
}
</style>
