<script setup lang="ts">
/**
 * Perlu Alokasi Tab Component for Permintaan Aktif
 * Lists newly confirmed requests awaiting asset allocation and admin fulfillment.
 */
import { ref, computed, watch, onMounted, h } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
  ArrowUpDown,
  ChevronDown,
  Eye
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import { REQUEST_STATUS_PILL_BASE, getRequestStatusBadgeClass, getRequestStatusLabel } from '@/lib/requestStatus';

interface SmartRequestListItem {
  id: number;
  uuid: string;
  number: string;
  requester: string;
  approver: string;
  type: string;
  typeLabel?: string;
  destination: string;
  pemanfaatan: string;
  pemanfaatanDetail?: string;
  total_items: number;
  total_requested: number;
  total_fulfilled: number;
  is_fully_fulfilled: boolean;
  status: string;
  raw_status: string;
  createdAt: string;
  created_at: string;
  durationStart?: string | null;
  durationEnd?: string | null;
}

interface Props {
  requests: SmartRequestListItem[];
}

const props = defineProps<Props>();

const requests = ref<SmartRequestListItem[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// --- Filter & Search State ---
const searchQuery = ref('');
const typeFilter = ref('Semua tipe');
const utilizationFilter = ref('Semua pemanfaatan');
const rowsPerPage = ref('Semua baris');

const dataTableRef = ref<any>(null);

// Filtered data
const filteredRequests = computed(() => {
  let list = [...requests.value];

  if (typeFilter.value !== 'Semua tipe') {
    const type = typeFilter.value === 'Peminjaman' ? 'peminjaman' : 'permintaan';
    list = list.filter(req => req.type === type);
  }

  if (utilizationFilter.value !== 'Semua pemanfaatan') {
    const util = utilizationFilter.value === 'Corporate' ? 'corporate' : 'project';
    list = list.filter(req => req.pemanfaatan === util);
  }

  // Pre-sort by id descending (newest first)
  list.sort((a, b) => b.id - a.id);

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'Semua baris') {
    return filteredRequests.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

const openShowPage = (item: SmartRequestListItem) => {
  router.visit(route('smart.fulfillment.show', { id: item.uuid || item.id, from: 'perlu-alokasi' }));
};

const columns: ColumnDef<SmartRequestListItem>[] = [
  {
    accessorKey: 'number',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Nomor',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('number')),
  },
  {
    accessorKey: 'type',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Tipe',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground capitalize' }, row.original.typeLabel || row.getValue('type')),
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Pemohon',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground font-semibold' }, row.getValue('requester')),
  },
  {
    accessorKey: 'pemanfaatan',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Pemanfaatan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      const isCorporate = item.pemanfaatan === 'corporate';
      return h('div', { class: 'text-foreground' }, [
        h('span', { class: 'font-semibold' }, isCorporate ? 'Corporate ' : 'Project '),
        h('span', { class: 'font-normal text-muted-foreground' }, isCorporate ? `(${item.pemanfaatanDetail})` : item.pemanfaatanDetail)
      ]);
    }
  },
  {
    accessorKey: 'status',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Status',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      const badgeClass = getRequestStatusBadgeClass(item.raw_status || item.status);
      const label = getRequestStatusLabel(item.raw_status || item.status);
      return h('div', { class: 'text-left' }, [
        h('span', { class: `${REQUEST_STATUS_PILL_BASE} ${badgeClass}` }, label)
      ]);
    }
  },
  {
    accessorKey: 'created_at',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Tanggal Dibuat',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.original.createdAt || row.getValue('created_at')),
  },
  {
    id: 'actions',
    size: 80,
    enableGlobalFilter: false,
    header: () => h('div', { class: 'text-right font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-end gap-1.5 no-print' }, [
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: 'Lihat Detail & Pemenuhan',
          onClick: () => openShowPage(item)
        }, () => [
          h(Eye, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, 'Lihat Detail')
        ]),
      ]);
    },
    enableSorting: false,
  }
];

const page = usePage();

const checkSearchParam = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search');
  if (searchParam) {
    searchQuery.value = searchParam;
  }
};

onMounted(() => {
  checkSearchParam();
});

watch(() => page.url, (newUrl) => {
  if (newUrl) {
    const url = new URL(newUrl, window.location.origin);
    const searchParam = url.searchParams.get('search');
    if (searchParam) {
      searchQuery.value = searchParam;
    }
  }
});
</script>

<template>
  <div>
    <!-- Filters & Rows per Page -->
    <div class="space-y-4 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
          <TableSearch 
            v-model="searchQuery"
            placeholder="Cari Nomor atau Nama Pemohon..." 
            bg-class="bg-white"
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!typeFilter || typeFilter === 'Semua tipe') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ typeFilter || 'Semua tipe' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="typeFilter = 'Semua tipe'">Semua tipe</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'Peminjaman'">Peminjaman</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'Permintaan'">Permintaan</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!utilizationFilter || utilizationFilter === 'Semua pemanfaatan') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ utilizationFilter || 'Semua pemanfaatan' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="utilizationFilter = 'Semua pemanfaatan'">Semua pemanfaatan</DropdownMenuItem>
            <DropdownMenuItem @select="utilizationFilter = 'Corporate'">Corporate</DropdownMenuItem>
            <DropdownMenuItem @select="utilizationFilter = 'Project'">Project</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>Baris per halaman</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPage }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
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
        :data="filteredRequests" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
      />
    </div>
  </div>
</template>
