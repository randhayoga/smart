<script setup lang="ts">
import { ref, computed, watch, h, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  X,
  FileText,
  ThumbsUp,
  Ban,
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

import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface AuditTrail {
  waktu: string;
  aksi_status: string;
  aktor: string;
  durasi: string | number;
  catatan: string;
}

interface UnitDetails {
  id: number;
  number: string;
  status: string;
  condition: string;
  price: string;
  image_url: string | null;
  vehicle_registration: string | null;
  location: string;
  floor: string | null;
  room: string | null;
  lot_code: string;
  organizer: string;
  date_of_receipt: string;
  vendor: string;
  po_number: string;
  age?: number | null;
  barang_code: string;
  barang_spec: string;
  barang_unit: string;
  lifecycles: AuditTrail[];
}

interface ApprovalItem {
  id: number;
  unit_id: number;
  asset_code: string;
  category: string;
  subcategory: string;
  brand: string;
  nama: string;
  specification: string;
  proposed_status: string;
  previous_status: string;
  status_label: string;
  decision: string;
  note: string | null;
  requested_by: string;
  requested_at: string;
  memo_url: string | null;
  lost_doc_url: string | null;
  unit_details: UnitDetails;
}

interface Props {
  user: any;
  approvals: ApprovalItem[];
}

const props = defineProps<Props>();

// ─────────────────────────────────────────────
// States & Filters
// ─────────────────────────────────────────────
const searchQuery = ref('');
const categoryFilter = ref('Semua kategori');
const statusFilter = ref('Semua status');
const rowsPerPage = ref('Semua baris');

const dataTableRef = ref<any>(null);

// Selection States
const selectedIds = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return [];
  return dataTableRef.value.table.getFilteredRowModel().rows
    .filter((r: any) => r.getIsSelected())
    .map((r: any) => r.original.id);
});

// Filter options
const categoryOptions = computed(() => {
  const cats = new Set<string>();
  props.approvals.forEach(app => {
    if (app.category) cats.add(app.category);
  });
  return Array.from(cats);
});

const statusOptions = computed(() => {
  const stats = new Set<string>();
  props.approvals.forEach(app => {
    if (app.status_label) stats.add(app.status_label);
  });
  return Array.from(stats);
});

// Filtered data
const filteredApprovals = computed(() => {
  let list = [...props.approvals];

  if (categoryFilter.value !== 'Semua kategori') {
    list = list.filter(app => app.category === categoryFilter.value);
  }

  if (statusFilter.value !== 'Semua status') {
    list = list.filter(app => app.status_label === statusFilter.value);
  }

  // default sort by id desc
  list.sort((a, b) => b.id - a.id);

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'Semua baris') {
    return filteredApprovals.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

watch([categoryFilter, statusFilter], () => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
});

const columns: ColumnDef<ApprovalItem>[] = [
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
    accessorKey: 'asset_code',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kode Aset',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('asset_code')),
  },
  {
    accessorKey: 'category',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kategori',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('category')),
  },
  {
    accessorKey: 'subcategory',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Subkategori',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('subcategory')),
  },
  {
    accessorKey: 'brand',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Merek',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('brand')),
  },
  {
    accessorKey: 'nama',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Nama',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate', title: row.getValue('nama') }, row.getValue('nama')),
  },
  {
    accessorKey: 'specification',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Spesifikasi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate', title: row.getValue('specification') }, row.getValue('specification')),
  },
  {
    accessorKey: 'status_label',
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
    cell: ({ row }) => h('div', { class: 'text-destructive font-semibold truncate' }, row.getValue('status_label')),
  },
  {
    id: 'actions',
    size: 100,
    header: () => h('div', { class: 'text-right font-semibold text-foreground' }, 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      const buttons = [
        h(Button, {
          variant: 'table-primary',
          size: 'icon-sm',
          title: 'Buka Berita Acara / Memo',
          onClick: () => openMemoFile(item.memo_url)
        }, () => [
          h(FileText),
          h('span', { class: 'sr-only' }, 'Buka Berita Acara / Memo')
        ])
      ];
      if (item.proposed_status === 'Hilang') {
        buttons.push(
          h(Button, {
            variant: 'table-primary',
            size: 'icon-sm',
            title: 'Buka Surat Keterangan Kehilangan',
            onClick: () => openMemoFile(item.lost_doc_url)
          }, () => [
            h(FileText),
            h('span', { class: 'sr-only' }, 'Buka Surat Keterangan Kehilangan')
          ])
        );
      }
      buttons.push(
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: 'Detail Aset',
          onClick: () => openDetailPopup(item)
        }, () => [
          h(Eye),
          h('span', { class: 'sr-only' }, 'Detail Aset')
        ])
      );
      return h('div', { class: 'flex items-center justify-end gap-2' }, buttons);
    },
    enableSorting: false,
  }
];

const auditColumns: ColumnDef<AuditTrail>[] = [
  {
    accessorKey: 'waktu',
    size: 160,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Waktu',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('waktu')),
  },
  {
    accessorKey: 'aksi_status',
    size: 144,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Aksi / Status',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground font-semibold truncate' }, row.getValue('aksi_status')),
  },
  {
    accessorKey: 'aktor',
    size: 160,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Aktor',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('aktor')),
  },
  {
    accessorKey: 'durasi',
    size: 112,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-center w-full'
      }, () => [
        'Durasi (hari)',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-center text-foreground truncate' }, row.getValue('durasi')),
  },
  {
    accessorKey: 'catatan',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Catatan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground max-w-sm truncate' }, row.getValue('catatan')),
  }
];

// Memo document opener
const openMemoFile = (path: string | null) => {
  if (!path) {
    toast.error('File berita acara / memo tidak ditemukan.');
    return;
  }
  window.open('/storage/' + path, '_blank');
};

// Formats & Helpers
const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return '-';
  let num: number;
  if (typeof val === 'string') {
    const cleanStr = val.replace(/[^0-9,-]/g, '');
    if (!cleanStr) return '-';
    num = parseFloat(cleanStr.replace(/,/g, '.'));
  } else {
    num = val;
  }
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null | undefined, floor: string | null, room: string | null) => {
  const parts = [];
  if (loc && loc !== '-') parts.push(loc);
  if (floor && floor !== '-') parts.push(floor);
  if (room && room !== '-') parts.push(room);
  return parts.join(', ') || '-';
};

const formatDateWithDashes = (dateStr: string | null | undefined) => {
  if (!dateStr || dateStr === '-') return '-';
  return dateStr.replace(/\//g, '-');
};

// ─────────────────────────────────────────────
// Popup Detail Asset States
// ─────────────────────────────────────────────
const isDetailPopupOpen = ref(false);
const activeApproval = ref<ApprovalItem | null>(null);
const detailActiveTab = ref('Detail');

const auditSearch = ref('');
const auditStatusFilter = ref('semua');
const auditTimeFilter = ref('semua');

const openDetailPopup = (approval: ApprovalItem) => {
  activeApproval.value = approval;
  detailActiveTab.value = 'Detail';
  auditSearch.value = '';
  auditStatusFilter.value = 'semua';
  auditTimeFilter.value = 'semua';
  isDetailPopupOpen.value = true;
};

const closeDetailPopup = () => {
  isDetailPopupOpen.value = false;
  setTimeout(() => {
    activeApproval.value = null;
  }, 200);
};

// Jejak Audit tab local pagination
const auditCurrentPage = ref(1);
const auditRowsPerPage = ref('Semua baris');

const computedAuditPageSize = computed(() => {
  if (auditRowsPerPage.value === 'Semua baris') {
    return filteredLifecycles.value.length || 10;
  }
  return parseInt(auditRowsPerPage.value, 10);
});

const filteredLifecycles = computed(() => {
  if (!activeApproval.value) return [];
  let logs = [...activeApproval.value.unit_details.lifecycles];

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

const paginatedLifecycles = computed(() => {
  return filteredLifecycles.value; // For now keep it full to match the layout
});

const auditStatusOptions = computed(() => {
  if (!activeApproval.value) return [];
  const stats = new Set<string>();
  activeApproval.value.unit_details.lifecycles.forEach(l => {
    if (l.aksi_status) stats.add(l.aksi_status);
  });
  return Array.from(stats);
});

// ─────────────────────────────────────────────
// Confirmation Modal States
// ─────────────────────────────────────────────
const isConfirmModalOpen = ref(false);
const confirmActionType = ref<'approved' | 'rejected'>('approved');
const confirmNote = ref('');
const isBulkAction = ref(false);
const processing = ref(false);

const openConfirmModal = (type: 'approved' | 'rejected', bulk: boolean) => {
  confirmActionType.value = type;
  isBulkAction.value = bulk;
  confirmNote.value = '';
  isConfirmModalOpen.value = true;
};

const closeConfirmModal = () => {
  isConfirmModalOpen.value = false;
};

const confirmAssets = computed(() => {
  if (isBulkAction.value) {
    return props.approvals.filter(app => selectedIds.value.includes(app.id));
  } else if (activeApproval.value) {
    return [activeApproval.value];
  }
  return [];
});

const handleConfirmSubmit = () => {
  const idsToProcess = isBulkAction.value 
    ? selectedIds.value 
    : (activeApproval.value ? [activeApproval.value.id] : []);

  if (idsToProcess.length === 0) {
    toast.error('Tidak ada aset terpilih.');
    return;
  }

  router.post(route('smart.approve-status.bulk-store'), {
    ids: idsToProcess,
    decision: confirmActionType.value,
    note: confirmNote.value,
  }, {
    onStart: () => { processing.value = true; },
    onFinish: () => { processing.value = false; },
    onSuccess: () => {
      closeConfirmModal();
      closeDetailPopup();
      if (dataTableRef.value && dataTableRef.value.table) {
        dataTableRef.value.table.resetRowSelection();
      }
      toast.success(confirmActionType.value === 'approved' 
        ? 'Perubahan status aset berhasil disetujui.' 
        : 'Perubahan status aset berhasil ditolak.'
      );
    },
    onError: (errs) => {
      toast.error(Object.values(errs).join(', '));
    }
  });
};

const isVehicle = (item: ApprovalItem | null) => {
  if (!item) return false;
  const category = (item.category || '').toLowerCase();
  const subcategory = (item.subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isDetailPopupOpen.value) {
      closeDetailPopup();
    } else if (isConfirmModalOpen.value) {
      closeConfirmModal();
    }
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <Head title="Approval Aset" />

  <AppLayout title="Approval Aset">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Approval Aset</h1>
      <p class="text-lg font-bold text-gray-900 mt-2">Perlu Perhatian Anda</p>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <!-- Filters Row -->
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
          <TableSearch 
            v-model="searchQuery"
            placeholder="Cari Aset..." 
            bg-class="bg-white"
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!categoryFilter || categoryFilter === 'Semua kategori') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ categoryFilter || 'Semua kategori' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="categoryFilter = 'Semua kategori'">Semua kategori</DropdownMenuItem>
            <DropdownMenuItem v-for="cat in categoryOptions" :key="cat" @select="categoryFilter = cat">
              {{ cat }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!statusFilter || statusFilter === 'Semua status') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ statusFilter || 'Semua status' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="statusFilter = 'Semua status'">Semua status</DropdownMenuItem>
            <DropdownMenuItem v-for="st in statusOptions" :key="st" @select="statusFilter = st">
              {{ st }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <!-- ── Bulk Actions ── -->
      <div class="space-y-2 flex-1 min-w-0 pt-2">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
        <div class="flex flex-wrap items-center gap-2">
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openConfirmModal('approved', true)"
            variant="success"
          >
            <ThumbsUp class="w-4 h-4" />
            <span class="hidden sm:inline">Approve Terpilih</span>
          </Button>
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openConfirmModal('rejected', true)"
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
        :data="filteredApprovals" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
      />
    </div>

    <!-- ============================================================
         Detail Asset Popup (Overlay Backdrop & Modal Card)
         ============================================================ -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="isDetailPopupOpen && activeApproval" 
          class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          @click="closeDetailPopup"
        >
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isDetailPopupOpen && activeApproval"
              class="bg-card w-full max-w-[95%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col border border-border"
              @click.stop
            >
              <!-- Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Detail Aset</h3>
                <button @click="closeDetailPopup" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
                </button>
              </div>

              <!-- Body contents -->
              <div class="overflow-y-auto max-h-[70vh] px-6 py-3 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
                  <Tabs v-model="detailActiveTab" :tabs="['Detail', 'Jejak Audit']" />
                </div>
                
                <!-- ── TAB 1: DETAIL ── -->
                <div v-if="detailActiveTab === 'Detail'" class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
                  <div class="flex flex-col md:flex-row gap-6">
                    <!-- Image Column -->
                    <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                      <img 
                        v-if="activeApproval.unit_details.image_url" 
                        :src="activeApproval.unit_details.image_url.startsWith('http') || activeApproval.unit_details.image_url.startsWith('/') ? activeApproval.unit_details.image_url : '/storage/' + activeApproval.unit_details.image_url" 
                        class="w-full h-full object-cover" 
                      />
                      <img 
                        v-else 
                        src="https://placehold.co/400x400?text=Placeholder" 
                        class="w-full h-full object-cover opacity-50" 
                      />
                    </div>

                    <!-- Details Columns -->
                    <div class="flex-grow grid grid-cols-1 md:grid-cols-15 gap-4 text-foreground">
                      <!-- Column 1: Item Info -->
                      <div class="md:col-span-4">
                        <p class="font-bold text-foreground"><span class="text-foreground">Kode Tipe:</span> {{ activeApproval.unit_details.barang_code }}</p>
                        <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ activeApproval.brand }}</p>
                        <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ activeApproval.nama }}</p>
                        <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ activeApproval.specification }}</p>
                        <p class="text-foreground">Kategori: {{ activeApproval.category }}</p>
                        <p class="text-foreground">Subkategori: {{ activeApproval.subcategory }}</p>
                        <p class="text-foreground">Satuan: {{ activeApproval.unit_details.barang_unit }}</p>
                      </div>

                      <!-- Column 2: LOT Info -->
                      <div class="md:col-span-5">
                        <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ activeApproval.unit_details.lot_code }}</p>
                        <p class="text-foreground">Organizer: {{ activeApproval.unit_details.organizer }}</p>
                        <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(activeApproval.unit_details.date_of_receipt) }}</p>
                        <p class="text-foreground">Umur: {{ activeApproval.unit_details.age !== undefined && activeApproval.unit_details.age !== null ? `${activeApproval.unit_details.age} tahun` : '-' }}</p>
                        <p class="text-foreground">Vendor: {{ activeApproval.unit_details.vendor }}</p>
                        <p class="text-foreground">Nomor PO: {{ activeApproval.unit_details.po_number }}</p>
                      </div>

                      <!-- Column 3: Asset Info -->
                      <div class="md:col-span-6">
                        <p class="font-bold text-foreground"><span class="text-foreground">Kode Aset:</span> {{ activeApproval.asset_code }}</p>
                        <!-- TNKB (Nopol) -->
                        <p v-if="isVehicle(activeApproval)" class="font-bold text-foreground">
                          <span class="text-foreground">Nopol:</span> {{ activeApproval.unit_details.vehicle_registration || '-' }}
                        </p>
                        <p class="text-foreground flex flex-col gap-1.5">
                          <span>
                            Status sebelumnya: 
                            <StatusBadge :status="activeApproval.previous_status" />
                          </span>
                          <span>
                            Status diajukan: 
                            <StatusBadge :status="activeApproval.proposed_status" />
                          </span>
                        </p>
                        <p class="text-foreground">
                          Kondisi: 
                          <span 
                            :class="[
                              'font-semibold',
                              activeApproval.unit_details.condition === 'Baik' ? 'text-emerald-600' :
                              activeApproval.unit_details.condition === 'Kurang Baik' ? 'text-amber-600' :
                              'text-rose-600'
                            ]"
                          >
                            {{ activeApproval.unit_details.condition }}
                          </span>
                        </p>
                        <p class="text-foreground">Nilai: {{ formatRupiah(activeApproval.unit_details.price) }}</p>
                        <p class="text-foreground">Lokasi penyimpanan: {{ formatLocation(activeApproval.unit_details.location, activeApproval.unit_details.floor, activeApproval.unit_details.room) }}</p>
                        <p class="text-foreground">Pembaruan terakhir: {{ activeApproval.requested_at }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ── TAB 2: JEJAK AUDIT ── -->
                <div v-if="detailActiveTab === 'Jejak Audit'" class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden space-y-4">
                  <!-- Internal Search & Local Filters -->
                  <div class="flex flex-wrap items-end gap-4">
                    <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                      <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
                      <TableSearch 
                        v-model="auditSearch"
                        placeholder="Cari Jejak Audit..." 
                        bg-class="bg-white"
                      />
                    </div>

                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal bg-white', (auditStatusFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                          <span class="truncate">{{ auditStatusFilter === 'semua' ? 'Semua Status' : auditStatusFilter }}</span>
                          <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                        <DropdownMenuItem @select="auditStatusFilter = 'semua'">Semua Status</DropdownMenuItem>
                        <DropdownMenuItem v-for="st in auditStatusOptions" :key="st" @select="auditStatusFilter = st">
                          {{ st }}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-[240px] justify-between rounded-[14px] font-normal bg-white', (auditTimeFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                          <span class="truncate">
                            {{ 
                              auditTimeFilter === 'semua' ? 'Semua Kurun Waktu' : 
                              auditTimeFilter === '7-hari' ? '7 hari terakhir' : 
                              '30 hari terakhir' 
                            }}
                          </span>
                          <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                        <DropdownMenuItem @select="auditTimeFilter = 'semua'">Semua Kurun Waktu</DropdownMenuItem>
                        <DropdownMenuItem @select="auditTimeFilter = '7-hari'">7 hari terakhir</DropdownMenuItem>
                        <DropdownMenuItem @select="auditTimeFilter = '30-hari'">30 hari terakhir</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>

                    <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
                      <span>Baris per halaman</span>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (auditRowsPerPage === 'Semua baris' || !auditRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                            {{ auditRowsPerPage }}
                            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[140px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                          <DropdownMenuItem @select="auditRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                          <DropdownMenuItem @select="auditRowsPerPage = '10'">10</DropdownMenuItem>
                          <DropdownMenuItem @select="auditRowsPerPage = '25'">25</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>

                  <!-- Log table via DataTable -->
                  <div class="pb-4">
                    <DataTable 
                      class="mb-6"
                      cell-class="py-2.5"
                      :columns="auditColumns" 
                      :data="filteredLifecycles" 
                      :filter-value="auditSearch"
                      :page-size="computedAuditPageSize"
                      :show-selection-count="false"
                    />
                  </div>
                </div>

              </div>

              <!-- Bottom Footer Buttons (Right Aligned group) -->
              <div class="py-3 px-4 flex items-center justify-end gap-3 bg-muted/10 shrink-0">
                <!-- Purple Memo Button -->
                 <Button 
                  @click="openMemoFile(activeApproval.memo_url)"
                  variant="primary"
                  size="lg"
                >
                  <FileText class="w-4 h-4" />
                  Buka Memo / Berita Acara
                </Button>

                <!-- Lost Document Button (Conditional if Hilang) -->
                <Button 
                  v-if="activeApproval.proposed_status === 'Hilang'"
                  @click="openMemoFile(activeApproval.lost_doc_url)"
                  variant="primary"
                  size="lg"
                >
                  <FileText class="w-4 h-4" />
                  Surat Keterangan Kehilangan
                </Button>

                <!-- Green Approve Button -->
                <Button 
                  @click="openConfirmModal('approved', false)"
                  variant="success"
                  size="lg"
                >
                  <ThumbsUp class="w-4 h-4" />
                  Approve
                </Button>

                <!-- Red Reject Button -->
                <Button 
                  @click="openConfirmModal('rejected', false)"
                  variant="destructive"
                  size="lg"
                >
                  <Ban class="w-4 h-4" />
                  Tolak
                </Button>

                <!-- White Kembali Button -->
                <Button 
                  @click="closeDetailPopup"
                  variant="white"
                  size="lg"
                >
                  Kembali
                </Button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- ============================================================
         Confirmation Modal (Approve / Reject Action Dialog)
         ============================================================ -->
    <DeleteConfirmationModal
      :is-open="isConfirmModalOpen"
      :item-count="confirmAssets.length"
      item-name="Perubahan Status Aset"
      :item-data="confirmAssets"
      :action-type="confirmActionType"
      :processing="processing"
      @close="closeConfirmModal"
      @confirm="handleConfirmSubmit"
    >
      <!-- Catatan text area (passed to slot) -->
      <div class="space-y-1.5 text-left w-full max-w-[90%] mx-auto">
        <label class="text-base font-semibold text-foreground block">Catatan / Alasan (Opsional)</label>
        <textarea
          v-model="confirmNote"
          placeholder="Masukkan catatan persetujuan atau alasan penolakan..."
          rows="3"
          class="w-full text-base border border-input rounded-[14px] bg-background text-foreground p-3 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary shadow-sm"
        ></textarea>
      </div>
    </DeleteConfirmationModal>

  </AppLayout>
</template>
