<script setup lang="ts">
// Trigger re-build
import { ref, computed, watch, h, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
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
import TableSearch from '@/Components/TableSearch.vue';

import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

interface InboxItem {
  id: number;
  number: string;
  amount: number;
  requester: string;
  createdAt: string;
  startTime: string;
  endTime: string;
  type: string;
}

const dummyInbox: InboxItem[] = [
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
const rowsPerPage = ref('Semua baris');

const dataTableRef = ref<any>(null);

const columns: ColumnDef<InboxItem>[] = [
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
    size: 100,
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nomor',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.getValue('number')),
  },
  {
    accessorKey: 'amount',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground'
    }, () => [
      'Jumlah',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', row.getValue('amount')),
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nama Peminta',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('requester')),
  },
  {
    accessorKey: 'createdAt',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Dibuat',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('createdAt')),
  },
  {
    accessorKey: 'startTime',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Mulai',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('startTime')),
  },
  {
    accessorKey: 'endTime',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Selesai',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('endTime')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center no-print' }, [
      h('button', {
        class: 'p-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50'
      }, [h(Eye, { class: 'w-3.5 h-3.5' })])
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
}, { immediate: true });

onMounted(() => {
  if (dataTableRef.value && dataTableRef.value.table && rowsPerPage.value === 'Semua baris') {
    dataTableRef.value.table.setPageSize(999999);
  }
});

const displayData = computed<InboxItem[]>(() => {
  if (dataTableRef.value && dataTableRef.value.table) {
    return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
  }
  return dummyInbox;
});

const handlePrint = () => {
  window.print();
};

const handleExportCSV = () => {
  if (displayData.value.length === 0) return;
  
  const headers = ['Nomor', 'Jumlah', 'Nama Peminta', 'Waktu Dibuat', 'Waktu Mulai', 'Waktu Selesai', 'Jenis'];
  const rows = displayData.value.map((item: InboxItem) => [
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
    + rows.map((e: string[]) => e.join(",")).join("\n");

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
  handleExportCSV();
};

const handleExportPDF = () => {
  window.print();
};
</script>

<template>
  <AppLayout title="Inbox">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inbox">Inbox</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-4">
      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-5 no-print">
          <h2 class="text-lg font-bold text-foreground">Daftar Permintaan Baru</h2>

          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                  <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
                  <TableSearch 
                    v-model="searchQuery"
                    placeholder="Cari nomor permintaan..." 
                  />
                </div>

                <FilterDropdown 
                  v-model="typeFilter"
                  :options="['Habis Pakai', 'Pinjam']"
                  placeholder="Semua jenis"
                  all-label="Semua jenis"
                  class="w-[200px]"
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
                  class="w-[200px]"
                />
              </div>

              <!-- Rows Per Page -->
              <div class="flex items-center gap-3 text-sm text-muted-foreground pb-0.5">
                <span class="whitespace-nowrap">Baris per halaman</span>
                <FilterDropdown 
                  v-model="rowsPerPage"
                  :options="['10', '25', '50']"
                  placeholder="Semua baris"
                  all-label="Semua baris"
                  class="w-[140px]"
                />
              </div>
            </div>

            <!-- Row 2: Export Actions -->
            <div class="flex flex-wrap items-end justify-between gap-4 pt-2">
              <div class="space-y-2 flex-1 min-w-0">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
                <div class="flex flex-wrap gap-2">
                  <button 
                    @click="handlePrint"
                    class="flex items-center gap-2 px-4 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <Printer class="w-4 h-4" />
                    <span>Print</span>
                  </button>
                  <button 
                    @click="handleExportExcel"
                    class="flex items-center gap-2 px-4 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <FileDown class="w-4 h-4" />
                    <span>Excel</span>
                  </button>
                  <button 
                    @click="handleExportPDF"
                    class="flex items-center gap-2 px-4 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <FileDown class="w-4 h-4" />
                    <span>PDF</span>
                  </button>
                  <button 
                    @click="handleExportCSV"
                    class="flex items-center gap-2 px-4 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                  >
                    <FileDown class="w-4 h-4" />
                    <span>CSV</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
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
