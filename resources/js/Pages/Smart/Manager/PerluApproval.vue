<script setup lang="ts">
import { ref, computed, watch, h } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  Search,
  X,
  FileText,
  Eye,
  ThumbsUp,
  Ban,
  ArrowUpDown,
  AlertTriangle,
  ArrowRight,
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

interface RequestItem {
  id: number;
  barang_id: number;
  subcategory: string;
  brand: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
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
    aksi_status: string;
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
        'No. Permintaan',
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
      return h('div', { class: 'text-foreground' }, [
        h('span', { class: 'font-semibold capitalize' }, item.pemanfaatan + ': '),
        h('span', { class: 'text-muted-foreground font-normal' }, item.pemanfaatanDetail)
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
        'Tanggal Registrasi',
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
      h('span', { class: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800' }, row.getValue('status'))
    ]),
  },
  {
    id: 'actions',
    size: 100,
    header: () => h('div', { class: 'text-center font-semibold text-foreground' }, 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-center gap-2' }, [
        h('button', {
          onClick: () => openDetailPopup(item),
          class: 'w-8 h-8 rounded-full bg-[#00BCD4] hover:bg-[#06B6D4] text-white flex items-center justify-center transition-colors shadow-sm cursor-pointer',
          title: 'Detail Permintaan'
        }, [
          h(Eye, { class: 'w-4 h-4' })
        ])
      ]);
    },
    enableSorting: false,
  }
];

// ─────────────────────────────────────────────
// Popup Detail Request States
// ─────────────────────────────────────────────
const isDetailPopupOpen = ref(false);
const activeRequest = ref<RequestHistory | null>(null);
const detailActiveTab = ref('Detail');

const auditSearch = ref('');
const auditStatusFilter = ref('semua');
const auditTimeFilter = ref('semua');

const openDetailPopup = (req: RequestHistory) => {
  activeRequest.value = req;
  detailActiveTab.value = 'Detail';
  auditSearch.value = '';
  auditStatusFilter.value = 'semua';
  auditTimeFilter.value = 'semua';
  isDetailPopupOpen.value = true;
};

const closeDetailPopup = () => {
  isDetailPopupOpen.value = false;
  setTimeout(() => {
    activeRequest.value = null;
  }, 200);
};

const filteredLifecycles = computed(() => {
  if (!activeRequest.value) return [];
  let logs = [...activeRequest.value.lifecycles];

  if (auditSearch.value.trim() !== '') {
    const q = auditSearch.value.toLowerCase();
    logs = logs.filter(l => 
      l.aktor.toLowerCase().includes(q) || 
      l.catatan.toLowerCase().includes(q) || 
      l.aksi_status.toLowerCase().includes(q)
    );
  }

  if (auditStatusFilter.value !== 'semua') {
    logs = logs.filter(l => l.aksi_status === auditStatusFilter.value);
  }

  if (auditTimeFilter.value !== 'semua') {
    const now = new Date();
    logs = logs.filter(l => {
      if (!l.waktu || l.waktu === '-') return false;
      const [day, month, yearTime] = l.waktu.split('-');
      const [year, time] = yearTime.split(' ');
      const logDate = new Date(`${year}-${month}-${day}T${time}:00`);
      const diffTime = Math.abs(now.getTime() - logDate.getTime());
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if (auditTimeFilter.value === '7-hari') return diffDays <= 7;
      if (auditTimeFilter.value === '30-hari') return diffDays <= 30;
      return true;
    });
  }

  return logs;
});

const auditStatusOptions = computed(() => {
  if (!activeRequest.value) return [];
  const stats = new Set<string>();
  activeRequest.value.lifecycles.forEach(l => {
    if (l.aksi_status) stats.add(l.aksi_status);
  });
  return Array.from(stats);
});

// ─────────────────────────────────────────────
// Confirmation Modal States
// ─────────────────────────────────────────────
const isConfirmModalOpen = ref(false);
const confirmActionType = ref<'approve' | 'reject'>('approve');
const confirmNote = ref('');
const isBulkAction = ref(false);

const openConfirmModal = (type: 'approve' | 'reject', bulk: boolean) => {
  confirmActionType.value = type;
  isBulkAction.value = bulk;
  confirmNote.value = '';
  isConfirmModalOpen.value = true;
};

const closeConfirmModal = () => {
  isConfirmModalOpen.value = false;
};

const confirmRequestsList = computed(() => {
  if (isBulkAction.value) {
    return requests.value.filter(req => selectedIds.value.includes(req.id));
  } else if (activeRequest.value) {
    return [activeRequest.value];
  }
  return [];
});

const handleConfirmSubmit = () => {
  const idsToProcess = isBulkAction.value 
    ? selectedIds.value 
    : (activeRequest.value ? [activeRequest.value.id] : []);

  if (idsToProcess.length === 0) {
    toast.error('Tidak ada permintaan terpilih.');
    return;
  }

  router.post(route('smart.approve.bulk-action'), {
    ids: idsToProcess,
    action: confirmActionType.value,
    note: confirmNote.value,
  }, {
    onSuccess: () => {
      closeConfirmModal();
      closeDetailPopup();
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
</script>

<template>
  <Head title="Approval" />

  <AppLayout title="Approval">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-[28px] font-bold text-gray-900 leading-none">Approval</h1>
      <p class="text-base font-semibold text-gray-900 mt-2">Perlu Perhatian Anda</p>
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
              <DropdownMenuItem @select="rowsPerPage = '20'">20</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <!-- ── Bulk Actions ── -->
      <div class="space-y-2 flex-1 min-w-0 pt-2">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
        <div class="flex flex-wrap gap-2">
          <Button 
            :disabled="selectedIds.length <= 1"
            @click="openConfirmModal('approve', true)"
            variant="success"
          >
            <ThumbsUp class="w-4 h-4" />
            <span class="hidden sm:inline">Approve Terpilih</span>
          </Button>
          <Button 
            :disabled="selectedIds.length <= 1"
            @click="openConfirmModal('reject', true)"
            variant="destructive"
          >
            <Ban class="w-4 h-4" />
            <span class="hidden sm:inline">Tolak Terpilih</span>
          </Button>
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
         Detail Request Popup (Overlay Backdrop & Modal Card)
         ============================================================ -->
    <Teleport to="body">
      <div 
        v-if="isDetailPopupOpen && activeRequest" 
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
        @click="closeDetailPopup"
      >
        <div 
          class="bg-white text-gray-900 rounded-xl shadow-2xl w-full max-w-5xl flex flex-col overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-200"
          @click.stop
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-white shrink-0">
            <h3 class="text-base md:text-lg font-bold text-gray-900">Detail Permintaan</h3>
            <button @click="closeDetailPopup" class="p-1.5 hover:bg-gray-100 rounded-full transition-colors cursor-pointer">
              <X class="w-5 h-5 text-gray-500 cursor-pointer" />
            </button>
          </div>

          <!-- Popup Tab pills -->
          <div class="px-6 py-3 border-b border-gray-200 flex items-center gap-2 shrink-0">
            <button 
              @click="detailActiveTab = 'Detail'"
              class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all cursor-pointer shadow-sm"
              :class="[
                detailActiveTab === 'Detail' 
                  ? 'border-indigo-600 text-indigo-600 bg-white' 
                  : 'border-gray-300 text-gray-500 hover:text-gray-700 bg-white'
              ]"
            >
              Detail
            </button>
            <button 
              @click="detailActiveTab = 'Jejak Audit'"
              class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all cursor-pointer shadow-sm"
              :class="[
                detailActiveTab === 'Jejak Audit' 
                  ? 'border-indigo-600 text-indigo-600 bg-white' 
                  : 'border-gray-300 text-gray-500 hover:text-gray-700 bg-white'
              ]"
            >
              Jejak Audit
            </button>
          </div>

          <!-- Body contents -->
          <div class="overflow-y-auto max-h-[70vh] p-6">
            
            <!-- ── TAB 1: DETAIL ── -->
            <div v-if="detailActiveTab === 'Detail'" class="space-y-6">
              <div class="border border-gray-200 rounded-xl p-5 flex flex-col lg:flex-row gap-6 bg-white">
                <!-- Info Permintaan -->
                <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3.5 text-xs text-gray-900 align-top">
                  <div class="space-y-3.5">
                    <p class="leading-normal font-bold">No. Permintaan: <span>{{ activeRequest.number }}</span></p>
                    <p class="leading-normal"><strong class="font-bold">Pemohon:</strong> {{ activeRequest.requester }}</p>
                    <p class="leading-normal"><strong class="font-bold">Tipe:</strong> <span class="capitalize">{{ activeRequest.type }}</span></p>
                    <p class="leading-normal text-[#E74C3C] font-bold">Status: {{ activeRequest.status }}</p>
                  </div>

                  <div class="space-y-3.5">
                    <p class="leading-normal"><strong class="font-bold">Pemanfaatan:</strong> <span class="capitalize">{{ activeRequest.pemanfaatan }}</span> ({{ activeRequest.pemanfaatanDetail }})</p>
                    <p class="leading-normal"><strong class="font-bold">Tanggal Pengajuan:</strong> {{ activeRequest.created_at }}</p>
                    <p v-if="activeRequest.type === 'peminjaman' && activeRequest.durationStart" class="leading-normal font-medium">
                      <strong class="font-bold">Durasi:</strong> {{ activeRequest.durationStart }} s.d {{ activeRequest.durationEnd }} ({{ activeRequest.durationDays }} hari)
                    </p>
                  </div>
                </div>
              </div>

              <!-- Daftar Barang yang Diminta -->
              <div class="space-y-3">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Daftar Barang</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div 
                    v-for="item in activeRequest.items" 
                    :key="item.id"
                    class="flex gap-4 p-4 border border-gray-200 hover:border-indigo-500/20 transition-all rounded-[14px] items-center bg-gray-50/50"
                  >
                    <!-- Thumbnail Barang -->
                    <div class="w-14 h-14 rounded-[10px] bg-gray-100 border border-gray-200 overflow-hidden shrink-0 flex items-center justify-center">
                      <img 
                        v-if="item.imageUrl" 
                        :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/storage/' + item.imageUrl" 
                        class="w-full h-full object-cover" 
                      />
                      <div v-else class="text-xs font-black text-gray-400 select-none">
                        {{ item.subcategory.substring(0, 3).toUpperCase() }}
                      </div>
                    </div>

                    <!-- Deskripsi Detail Barang -->
                    <div class="min-w-0 flex-grow space-y-0.5 text-xs">
                      <h5 class="font-bold text-gray-900 truncate">
                        {{ item.brand }} {{ item.spec }}
                      </h5>
                      <p class="text-gray-500">
                        Kategori ({{ item.subcategory }})
                      </p>
                      
                      <div class="flex flex-wrap gap-x-4 gap-y-1 font-semibold pt-0.5 text-indigo-600">
                        <span>Jumlah diminta: {{ item.quantity }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── TAB 2: JEJAK AUDIT ── -->
            <div v-if="detailActiveTab === 'Jejak Audit'" class="space-y-4">
              <!-- Internal Search & Local Filters -->
              <div class="flex flex-col sm:flex-row items-center gap-3 justify-between p-4 bg-gray-50 border border-gray-200 rounded-xl text-xs">
                <div class="flex flex-wrap gap-3 items-center w-full sm:w-auto">
                  <div class="relative w-full sm:w-[220px]">
                    <div class="absolute left-3 top-3 text-gray-400">
                      <Search class="w-4 h-4" />
                    </div>
                    <input
                      v-model="auditSearch"
                      type="text"
                      placeholder="Cari Jejak Audit"
                      class="w-full h-10 pl-9 pr-3 text-xs border border-gray-300 rounded-[14px] bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                  </div>
                  
                  <Combobox
                    v-model="auditStatusFilter"
                    :options="auditStatusOptions"
                    search-placeholder="Cari Status..."
                    default-label="Semua Status"
                    width-class="w-full sm:w-auto min-w-[140px]"
                  />

                  <Combobox
                    v-model="auditTimeFilter"
                    :options="[{ id: '7-hari', name: '7 hari terakhir' }, { id: '30-hari', name: '30 hari terakhir' }]"
                    search-placeholder="Cari kurun waktu..."
                    default-label="Semua Kurun Waktu"
                    width-class="w-full sm:w-auto min-w-[170px]"
                  />
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end text-gray-500">
                  <span>Baris per halaman:</span>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" class="w-full sm:w-auto min-w-[130px] h-10 justify-between rounded-[14px] font-normal text-xs bg-white border-gray-300 shadow-sm text-gray-900 gap-2">
                        <span>Semua baris</span>
                        <ChevronDown class="w-4 h-4 opacity-50 shrink-0 ml-2" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="min-w-[130px] rounded-[14px] z-[10000]" align="start" :side-offset="4">
                      <DropdownMenuItem>Semua baris</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </div>

              <!-- Log table -->
              <div class="rounded-xl border border-border shadow-sm overflow-x-auto bg-card">
                <table class="w-full text-xs text-left border-collapse">
                  <thead class="bg-muted/50 border-b border-border text-foreground">
                    <tr class="hover:bg-transparent text-foreground font-semibold uppercase tracking-wider text-[10px]">
                      <th class="py-3 px-4 w-40 font-semibold">Waktu ↑↓</th>
                      <th class="py-3 px-4 w-36 font-semibold">Aksi / Status ↑↓</th>
                      <th class="py-3 px-4 w-40 font-semibold">Aktor ↑↓</th>
                      <th class="py-3 px-4 w-28 text-center font-semibold">Durasi ↑↓</th>
                      <th class="py-3 px-4 font-semibold">Catatan ↑↓</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr 
                      v-for="(lc, idx) in filteredLifecycles" 
                      :key="idx"
                      class="border-b border-border hover:bg-muted/30 transition-colors last:border-none"
                    >
                      <td class="py-3 px-4 font-medium text-foreground">{{ lc.waktu }}</td>
                      <td class="py-3 px-4 text-foreground font-medium">{{ lc.aksi_status }}</td>
                      <td class="py-3 px-4 text-foreground">{{ lc.aktor }}</td>
                      <td class="py-3 px-4 text-center text-foreground">{{ lc.durasi }}</td>
                      <td class="py-3 px-4 text-muted-foreground max-w-sm truncate">{{ lc.catatan }}</td>
                    </tr>
                    
                    <tr v-if="filteredLifecycles.length === 0">
                      <td colspan="5" class="py-6 text-center text-muted-foreground italic">
                        Tidak ada log jejak audit.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>

          <!-- Bottom Footer Buttons (Right Aligned group) -->
          <div class="p-5 border-t border-gray-200 flex justify-end gap-3 bg-gray-50 shrink-0">
            <!-- Green Approve Button -->
            <button 
              @click="openConfirmModal('approve', false)"
              class="bg-[#2ECC71] hover:bg-[#27AE60] text-white font-semibold text-xs px-5 py-2.5 rounded-lg inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
            >
              <ThumbsUp class="w-4 h-4" />
              Approve Permintaan
            </button>

            <!-- Red Reject Button -->
            <button 
              @click="openConfirmModal('reject', false)"
              class="bg-[#E74C3C] hover:bg-[#C0392B] text-white font-semibold text-xs px-5 py-2.5 rounded-lg inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
            >
              <Ban class="w-4 h-4" />
              Tolak Permintaan
            </button>

            <!-- White Kembali Button -->
            <button 
              @click="closeDetailPopup"
              class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-medium text-xs px-5 py-2.5 rounded-lg transition-colors cursor-pointer shadow-sm"
            >
              Kembali
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ============================================================
         Confirmation Dialog Modal (Teleport & Backdrop)
         ============================================================ -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="isConfirmModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
        >
          <div 
            class="bg-white text-gray-900 rounded-xl shadow-2xl w-full max-w-[500px] flex flex-col overflow-hidden border border-gray-200"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-white shrink-0">
              <h3 class="text-base md:text-lg font-bold text-gray-900">
                {{ confirmActionType === 'approve' ? 'Setujui Permintaan' : 'Tolak Permintaan' }}
              </h3>
              <button @click="closeConfirmModal" class="p-1.5 hover:bg-gray-100 rounded-full transition-colors cursor-pointer">
                <X class="w-5 h-5 text-gray-500 cursor-pointer" />
              </button>
            </div>
            
            <!-- Body -->
            <div class="p-6 space-y-4">
              <div class="text-sm space-y-2 text-gray-900">
                <p 
                  class="font-bold text-base"
                  :class="confirmActionType === 'approve' ? 'text-green-600' : 'text-red-600'"
                >
                  Apakah Anda yakin ingin {{ confirmActionType === 'approve' ? 'menyetujui' : 'menolak' }} permintaan ini?
                </p>
                <div class="bg-gray-50 p-3.5 rounded-lg border border-gray-200 space-y-1">
                  <p v-for="cReq in confirmRequestsList" :key="cReq.id" class="text-xs">
                    • <span class="font-bold">{{ cReq.number }}</span> oleh {{ cReq.requester }}
                  </p>
                </div>
              </div>

              <!-- Input Catatan/Alasan -->
              <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Catatan / Alasan (Opsional)</label>
                <textarea
                  v-model="confirmNote"
                  placeholder="Masukkan catatan keputusan..."
                  rows="3"
                  class="w-full text-xs border border-gray-300 rounded-lg bg-white p-2.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm"
                ></textarea>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 p-5 border-t border-gray-200 bg-gray-50 shrink-0">
              <button 
                @click="closeConfirmModal"
                class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-semibold text-xs px-5 py-2.5 rounded-lg transition-colors cursor-pointer shadow-sm"
              >
                Batal
              </button>
              <button 
                @click="handleConfirmSubmit"
                class="text-white font-semibold text-xs px-5 py-2.5 rounded-lg transition-all duration-200 shadow-sm cursor-pointer"
                :class="confirmActionType === 'approve' 
                  ? 'bg-[#2ECC71] hover:bg-[#27AE60]' 
                  : 'bg-[#E74C3C] hover:bg-[#C0392B]'"
              >
                {{ confirmActionType === 'approve' ? 'Ya, Setujui' : 'Ya, Tolak' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
