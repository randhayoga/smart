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
import FilterDropdown from '@/Components/FilterDropdown.vue';

import { Button } from "@/Components/ui/button";
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
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Nomor',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-4 font-medium' }, row.getValue('number')),
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
    cell: ({ row }) => h('div', { class: 'pl-4' }, row.getValue('requester')),
  },
  {
    accessorKey: 'method',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Metode',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-4' }, row.getValue('method')),
  },
  {
    accessorKey: 'time',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Waktu',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-4 text-muted-foreground' }, row.getValue('time')),
  },
  {
    accessorKey: 'location',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'px-2 hover:bg-transparent font-bold text-foreground'
    }, () => [
      'Tempat',
      h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-4' }, row.getValue('location')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-bold text-foreground' }, 'Aksi'),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center' }, [
      h('button', {
        onClick: () => handleViewDetail(row.original),
        class: 'p-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-full transition-colors shadow-sm'
      }, [h(Eye, { class: 'w-4 h-4' })])
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
      <div class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="p-6">
          <h2 class="text-2xl font-bold text-foreground">Daftar Jadwal Serah Terima</h2>
          
          <!-- Filters Row -->
          <div class="mt-6 flex flex-wrap items-end gap-4">
            <div class="space-y-1.5 flex-1 min-w-[300px] max-w-md">
              <label class="text-xs text-muted-foreground font-medium block">Filter</label>
              <TableSearch 
                v-model="searchQuery"
                placeholder="Cari nomor permintaan atau nama peminta..." 
              />
            </div>

            <FilterDropdown 
              v-model="timeFilter"
              :options="[
                { label: 'Hari ini', value: 'Hari ini' },
                { label: 'Minggu ini', value: 'Minggu ini' },
                { label: 'Bulan ini', value: 'Bulan ini' }
              ]"
              placeholder="Semua kurun waktu"
              all-label="Semua kurun waktu"
              class="w-[220px]"
            />

            <FilterDropdown 
              v-model="methodFilter"
              :options="['Diambil sendiri', 'Diantar']"
              placeholder="Semua metode"
              all-label="Semua metode"
              class="w-[220px]"
            />
          </div>

          <!-- Actions Row -->
          <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-2">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
              <div class="flex flex-wrap gap-2">
                <button @click="handlePrint" class="flex items-center gap-2 px-6 py-2 bg-[#9B897B] hover:bg-[#8A786A] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm">
                  <Printer class="w-4 h-4" />
                  <span>Print</span>
                </button>
                <button @click="handleExportExcel" class="flex items-center gap-2 px-5 py-2 bg-[#66BB6A] hover:bg-[#57A85B] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm">
                  <FileDown class="w-4 h-4" />
                  <span>Export: Excel</span>
                </button>
                <button @click="handleExportPDF" class="flex items-center gap-2 px-5 py-2 bg-[#FFA726] hover:bg-[#FB8C00] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm">
                  <FileDown class="w-4 h-4" />
                  <span>Export: PDF</span>
                </button>
                <button @click="handleExportCSV" class="flex items-center gap-2 px-5 py-2 bg-[#BA68C8] hover:bg-[#AB47BC] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm">
                  <FileDown class="w-4 h-4" />
                  <span>Export: CSV</span>
                </button>
              </div>
            </div>

            <div class="flex items-center gap-3 text-sm text-muted-foreground">
              <span>Baris per halaman</span>
              <FilterDropdown 
                v-model="rowsPerPage"
                :options="['10', '25', '50']"
                placeholder="Semua baris"
                all-label="Semua baris"
                class="w-[160px]"
              />
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="px-4 pb-4">
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
