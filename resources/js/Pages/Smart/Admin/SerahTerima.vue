<script setup lang="ts">
import { ref, computed, watch, h, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Printer,
  FileDown,
  Eye,
  X
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";

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

// Dummy Data
const dummyHandovers = [
  { id: 1, number: '052026-0001', requester: 'John Doe', method: 'Diambil sendiri', time: '12-05-2026 10:00', location: 'Ruang IFS' },
  { id: 2, number: '052026-0002', requester: 'Jane Smith', method: 'Diantar', time: '13-05-2026 14:30', location: 'Gudang Utama' },
  { id: 3, number: '052026-0003', requester: 'John Doe', method: 'Diantar', time: '14-05-2026 09:15', location: 'Ruang IFS' },
  { id: 4, number: '052026-0004', requester: 'Budi Utomo', method: 'Diambil sendiri', time: '15-05-2026 11:00', location: 'Tiga Negeri' },
  { id: 5, number: '052026-0005', requester: 'Siti Aminah', method: 'Diantar', time: '16-05-2026 13:45', location: 'Mega Mendung' },
];

const searchQuery = ref('');
const timeFilter = ref('');
const methodFilter = ref('');
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
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nomor',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.getValue('number')),
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
    accessorKey: 'method',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Metode',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('method')),
  },
  {
    accessorKey: 'time',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('time')),
  },
  {
    accessorKey: 'location',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Tempat',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('location')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center no-print' }, [
      h('button', {
        onClick: () => handleViewDetail(row.original),
        class: 'p-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50'
      }, [h(Eye, { class: 'w-3.5 h-3.5' })])
    ]),
  },
];

const handleViewDetail = (item: any) => {
  // Use Inertia router with fallback to prevent crash if route() is not loaded
  const url = `/smart/handover/${item.id}`;
  router.get(url);
};

const handlePrint = () => window.print();
const handleExportExcel = () => alert('Exporting to Excel...');
const handleExportPDF = () => alert('Exporting to PDF...');
const handleExportCSV = () => alert('Exporting to CSV...');

// Watchers for filters
watch(methodFilter, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.getColumn('method')?.setFilterValue(val);
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
</script>

<template>
  <AppLayout title="Serah Terima">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/handover">Serah Terima</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-4">
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-5 no-print">
          <h2 class="text-lg font-bold text-foreground">Daftar Jadwal Serah Terima</h2>
          
          <!-- Filters Row -->
          <div class="mt-4 flex flex-wrap items-end gap-4">
            <div class="space-y-1.5 flex-1 min-w-[300px] max-w-md">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
              <TableSearch 
                v-model="searchQuery"
                placeholder="Cari nomor permintaan atau nama peminta..." 
              />
            </div>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" class="w-[220px] justify-between rounded-[14px] font-normal text-muted-foreground">
                  <span class="truncate">{{ timeFilter || 'Semua kurun waktu' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="timeFilter = ''">Semua kurun waktu</DropdownMenuItem>
                <DropdownMenuItem @select="timeFilter = 'Hari ini'">Hari ini</DropdownMenuItem>
                <DropdownMenuItem @select="timeFilter = 'Minggu ini'">Minggu ini</DropdownMenuItem>
                <DropdownMenuItem @select="timeFilter = 'Bulan ini'">Bulan ini</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" class="w-[220px] justify-between rounded-[14px] font-normal text-muted-foreground">
                  <span class="truncate">{{ methodFilter || 'Semua metode' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="methodFilter = ''">Semua metode</DropdownMenuItem>
                <DropdownMenuItem @select="methodFilter = 'Diambil sendiri'">Diambil sendiri</DropdownMenuItem>
                <DropdownMenuItem @select="methodFilter = 'Diantar'">Diantar</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <!-- Actions Row -->
          <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-2">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
              <ExportButtonGroup 
                @print="handlePrint"
                @export-excel="handleExportExcel"
                @export-pdf="handleExportPDF"
                @export-csv="handleExportCSV"
              />
            </div>

            <div class="flex items-center gap-3 text-sm text-muted-foreground">
              <span>Baris per halaman</span>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" class="w-[160px] justify-between rounded-[14px] font-normal text-muted-foreground">
                    {{ rowsPerPage }}
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="dummyHandovers" 
            :filter-value="searchQuery"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
