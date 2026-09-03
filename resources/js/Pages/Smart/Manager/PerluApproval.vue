<script setup lang="ts">
/**
 * Perlu Approval page component listing pending borrow/requisition requests for manager review and bulk decision processing.
 */
import { ref, computed, watch, onMounted, onUnmounted, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  ThumbsUp,
  Ban,
  ArrowUpDown,
  ChevronDown
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
import ApprovalModal from '@/Pages/Smart/Manager/Modals/ApprovalModal.vue';

interface RequestItem {
  id: number;
  barang_id?: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
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
  items: RequestItem[];
  lifecycles: Array<{
    waktu: string;
    status: string;
    aktor: string;
    durasi: string | number;
    catatan: string;
  }>;
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
    accessorKey: 'status',
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
    cell: ({ row }) => h('div', { class: 'text-foreground font-medium' }, [
      h('span', { class: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold bg-amber-100 text-amber-800' }, row.getValue('status'))
    ]),
  },
  {
    id: 'actions',
    size: 100,
    header: () => h('div', { class: 'text-right font-semibold text-foreground' }, 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-end gap-1.5' }, [
        h(Button, {
          variant: 'success',
          size: 'icon-sm',
          title: 'Approve',
          onClick: () => openConfirmModal('approve', false, item)
        }, () => [
          h(ThumbsUp, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, 'Approve')
        ]),
        h(Button, {
          variant: 'destructive',
          size: 'icon-sm',
          title: 'Tolak',
          onClick: () => openConfirmModal('reject', false, item)
        }, () => [
          h(Ban, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, 'Tolak')
        ]),
      ]);
    },
    enableSorting: false,
  }
];

// ─────────────────────────────────────────────
// Confirmation Modal States
// ─────────────────────────────────────────────
const isConfirmModalOpen = ref(false);
const confirmActionType = ref<'approve' | 'reject'>('approve');
const confirmNote = ref('');
const isBulkAction = ref(false);
const selectedSingleRequest = ref<RequestHistory | null>(null);
const processing = ref(false);

const openConfirmModal = (type: 'approve' | 'reject', bulk: boolean, singleReq?: RequestHistory) => {
  confirmActionType.value = type;
  isBulkAction.value = bulk;
  selectedSingleRequest.value = singleReq || null;
  confirmNote.value = '';
  isConfirmModalOpen.value = true;
};

const closeConfirmModal = () => {
  isConfirmModalOpen.value = false;
  selectedSingleRequest.value = null;
};

const confirmRequestsList = computed(() => {
  if (isBulkAction.value) {
    return requests.value.filter(req => selectedIds.value.includes(req.id));
  } else if (selectedSingleRequest.value) {
    return [selectedSingleRequest.value];
  }
  return [];
});

const handleConfirmSubmit = (noteParam?: string) => {
  const idsToProcess = isBulkAction.value 
    ? selectedIds.value 
    : (selectedSingleRequest.value ? [selectedSingleRequest.value.id] : []);

  if (idsToProcess.length === 0) {
    toast.error('Tidak ada permintaan terpilih.');
    return;
  }

  const finalNote = noteParam !== undefined ? noteParam : confirmNote.value;

  router.post(route('smart.approve.bulk-action'), {
    ids: idsToProcess,
    action: confirmActionType.value,
    note: finalNote,
  }, {
    onStart: () => { processing.value = true; },
    onFinish: () => { processing.value = false; },
    onSuccess: () => {
      closeConfirmModal();
      if (dataTableRef.value && dataTableRef.value.table) {
        dataTableRef.value.table.resetRowSelection();
      }
      toast.success(confirmActionType.value === 'approve' 
        ? 'Permintaan berhasil disetujui.' 
        : 'Permintaan berhasil ditolak.'
      );
    },
    onError: (errs) => {
      toast.error(Object.values(errs).join(', '));
    }
  });
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isConfirmModalOpen.value) {
    closeConfirmModal();
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
  <Head title="Approval" />

  <AppLayout title="Approval">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Approval: Perlu Perhatian Anda</h1>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <!-- Filters Row -->
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
          <TableSearch 
            v-model="searchQuery"
            placeholder="Cari Permintaan..." 
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

      <!-- ── Bulk Actions ── -->
      <div class="space-y-2 flex-1 min-w-0 pt-2">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
        <div class="flex flex-wrap items-center gap-2">
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openConfirmModal('approve', true)"
            variant="success"
          >
            <ThumbsUp class="w-4 h-4" />
            <span class="hidden sm:inline">Approve Terpilih</span>
          </Button>
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openConfirmModal('reject', true)"
            variant="destructive"
          >
            <Ban class="w-4 h-4" />
            <span class="hidden sm:inline">Tolak Terpilih</span>
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
         Approval Confirmation Modal (Single & Bulk)
         ============================================================ -->
    <ApprovalModal
      :is-open="isConfirmModalOpen"
      :action-type="confirmActionType"
      :requests="confirmRequestsList"
      :processing="processing"
      @close="closeConfirmModal"
      @confirm="handleConfirmSubmit"
    />
  </AppLayout>
</template>
