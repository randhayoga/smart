<script setup lang="ts">
/**
 * Admin Inbox page component listing approved requests awaiting Admin confirmation or rejection.
 */
import { ref, computed, watch, onMounted, onUnmounted, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  ArrowUpDown,
  ChevronDown,
  Eye
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
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
import AdminConfirmationModal from '@/Pages/Smart/Admin/Modals/AdminConfirmationModal.vue';
import { REQUEST_STATUS_PILL_BASE } from '@/lib/requestStatus';

interface RequestItem {
  id: number;
  barang_id?: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  stock?: number;
  imageUrl?: string;
  category: string;
  uom?: string;
}

interface RequestHistory {
  id: number;
  number: string;
  type: 'permintaan' | 'peminjaman';
  requester: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: string;
  raw_status: string;
  created_at: string;
  is_stock_sufficient: boolean;
  items: RequestItem[];
}

interface Props {
  user: any;
  requests: RequestHistory[];
}

const props = defineProps<Props>();

const requests = ref<RequestHistory[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// ─────────────────────────────────────────────
// States & Filters
// ─────────────────────────────────────────────
const searchQuery = ref('');
const typeFilter = ref('Semua tipe');
const utilizationFilter = ref('Semua pemanfaatan');
const rowsPerPage = ref('Semua baris');

const dataTableRef = ref<any>(null);

// Selection States
const selectedIds = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return [];
  return dataTableRef.value.table.getFilteredRowModel().rows
    .filter((r: any) => r.getIsSelected())
    .map((r: any) => r.original.id);
});

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

watch([typeFilter, utilizationFilter], () => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
});

const columns: ColumnDef<RequestHistory>[] = [
  {
    id: 'select',
    size: 40,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
        'aria-label': 'Select all',
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
        'aria-label': 'Select row',
      })
    ]),
    enableSorting: false,
    enableHiding: false,
  },
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
    cell: ({ row }) => h('div', { class: 'text-foreground capitalize' }, row.getValue('type')),
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
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('created_at')),
  },
  {
    accessorKey: 'is_stock_sufficient',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kecukupan Stok',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const isSufficient = Boolean(row.original.is_stock_sufficient);
      if (isSufficient) {
        return h('div', { class: 'text-left' }, [
          h('span', { class: `${REQUEST_STATUS_PILL_BASE} bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300` }, 'Cukup')
        ]);
      }
      return h('div', { class: 'text-left' }, [
        h('span', { class: `${REQUEST_STATUS_PILL_BASE} bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300` }, 'Tidak Cukup')
      ]);
    },
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
          title: 'Lihat Detail & Konfirmasi',
          onClick: () => openDetailModal(item)
        }, () => [
          h(Eye, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, 'Lihat Detail')
        ]),
      ]);
    },
    enableSorting: false,
  }
];

// ─────────────────────────────────────────────
// Confirmation Modal States
// ─────────────────────────────────────────────
const isDetailModalOpen = ref(false);
const isBulkModalOpen = ref(false);
const selectedSingleRequest = ref<RequestHistory | null>(null);
const processing = ref(false);

const openDetailModal = (item: RequestHistory) => {
  selectedSingleRequest.value = item;
  isDetailModalOpen.value = true;
};

const closeDetailModal = () => {
  isDetailModalOpen.value = false;
  selectedSingleRequest.value = null;
};

const openBulkModal = () => {
  if (selectedIds.value.length === 0) {
    toast.error('Pilih setidaknya satu permintaan terlebih dahulu.');
    return;
  }
  isBulkModalOpen.value = true;
};

const closeBulkModal = () => {
  isBulkModalOpen.value = false;
};

const singleRequestList = computed(() => {
  return selectedSingleRequest.value ? [selectedSingleRequest.value] : [];
});

const bulkRequestsList = computed(() => {
  return requests.value.filter(req => selectedIds.value.includes(req.id));
});

const handleModalAction = ({ action, note }: { action: 'confirm' | 'reject'; note: string }, isBulk: boolean) => {
  const idsToProcess = isBulk
    ? selectedIds.value 
    : (selectedSingleRequest.value ? [selectedSingleRequest.value.id] : []);

  if (idsToProcess.length === 0) {
    toast.error('Tidak ada permintaan terpilih.');
    return;
  }

  router.post(route('smart.inbox.confirmation'), {
    ids: idsToProcess,
    action: action,
    note: note,
  }, {
    onStart: () => { processing.value = true; },
    onFinish: () => { processing.value = false; },
    onSuccess: () => {
      if (isBulk) {
        closeBulkModal();
      } else {
        closeDetailModal();
      }
      if (dataTableRef.value && dataTableRef.value.table) {
        dataTableRef.value.table.resetRowSelection();
      }
      toast.success(action === 'confirm' 
        ? 'Permintaan berhasil dikonfirmasi.' 
        : 'Permintaan berhasil ditolak.'
      );
    },
    onError: (errs) => {
      toast.error(Object.values(errs).join(', '));
    }
  });
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isDetailModalOpen.value) closeDetailModal();
    if (isBulkModalOpen.value) closeBulkModal();
  }
};

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
  document.addEventListener('keydown', closeOnEscape);
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

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <Head title="Inbox Admin" />

  <AppLayout title="Inbox Admin">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Inbox: Permintaan Masuk</h1>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <!-- Filters Row -->
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
      </div>

      <!-- ── Bulk Actions & Rows per Page ── -->
      <div class="space-y-2 flex-1 min-w-0 pt-2">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
        <div class="flex flex-wrap items-center gap-2">
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openBulkModal"
            variant="view"
            class="rounded-[14px]"
          >
            <Eye class="w-4 h-4" />
            <span class="hidden sm:inline">Konfirmasi Terpilih ({{ selectedIds.length }})</span>
          </Button>
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
    </div>

    <!-- ── Table Display ── -->
    <div class="pb-4">
      <DataTable 
        ref="dataTableRef"
        :columns="columns" 
        :data="filteredRequests" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
      />
    </div>

    <!-- ============================================================
         Single Detail & Confirmation Modal
         ============================================================ -->
    <AdminConfirmationModal
      :is-open="isDetailModalOpen"
      :requests="singleRequestList"
      :processing="processing"
      @close="closeDetailModal"
      @action="(payload) => handleModalAction(payload, false)"
    />

    <!-- ============================================================
         Bulk Confirmation Modal
         ============================================================ -->
    <AdminConfirmationModal
      :is-open="isBulkModalOpen"
      :requests="bulkRequestsList"
      :processing="processing"
      @close="closeBulkModal"
      @action="(payload) => handleModalAction(payload, true)"
    />
  </AppLayout>
</template>
