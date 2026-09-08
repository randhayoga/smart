<script setup lang="ts">
/**
 * Lacak Peminjaman Tab Component for Permintaan Aktif
 * Tracks ongoing borrowed assets and links to detail page.
 */
import { ref, computed, watch, h, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
  ChevronDown, 
  ArrowUpDown, 
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import ViewTableButton from '@/Components/ViewTableButton.vue';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';

interface Props {
  borrowedList: any[];
}

const props = defineProps<Props>();

const dummyBorrowed = computed(() => props.borrowedList);

const searchQuery = ref('');
const timeFilter = ref('');
const rowsPerPage = ref('Semua baris');

const dataTableRef = ref<any>(null);

const handleViewDetail = (item: any) => {
  const url = `/smart/borrowed/${item.id}`;
  router.get(url);
};

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
    accessorKey: 'borrower',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nama Peminjam',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('borrower')),
  },
  {
    accessorKey: 'daysPassed',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Hari Berlalu',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('daysPassed')),
  },
  {
    accessorKey: 'dueDate',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Tenggat',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('dueDate')),
  },
  {
    accessorKey: 'daysLeft',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Sisa Hari',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('daysLeft')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center no-print' }, [
      h(ViewTableButton, {
        onClick: () => handleViewDetail(row.original)
      })
    ]),
  },
];

// Watchers for filters
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
    if (rowsPerPage.value === 'Semua baris') {
      dataTableRef.value.table.setPageSize(999999);
    } else {
      dataTableRef.value.table.setPageSize(Number(rowsPerPage.value));
    }
  }
});
</script>

<template>
  <div>
    <!-- Filters Row -->
    <div class="space-y-4 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-md">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
          <TableSearch 
            v-model="searchQuery"
            placeholder="Cari nomor peminjaman atau nama peminjam..." 
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[220px] justify-between rounded-[14px] font-normal', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
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

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>Baris per halaman</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[160px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
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

    <!-- Table Display -->
    <div class="pb-4">
      <DataTable 
        ref="dataTableRef"
        :columns="columns" 
        :data="dummyBorrowed" 
        :filter-value="searchQuery"
      />
    </div>
  </div>
</template>
