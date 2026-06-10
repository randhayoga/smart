<script setup lang="ts">
import { ref, computed, watch, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  Search,
  X,
  FileText,
  ArrowUpDown,
  AlertTriangle,
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
import ViewTableButton from '@/Components/ViewTableButton.vue';
import Tabs from '@/Components/Tabs.vue';

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
  status_label: string;
  decision: string;
  note: string | null;
  requested_by: string;
  requested_at: string;
  decided_at: string | null;
  approver_name: string | null;
  memo_path: string | null;
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
const decisionFilter = ref('Semua keputusan');
const rowsPerPage = ref('Semua baris');

// Filter options
const categoryOptions = computed(() => {
  const cats = new Set<string>();
  props.approvals.forEach(app => {
    if (app.category) cats.add(app.category);
  });
  return Array.from(cats);
});

// Filtered data
const filteredApprovals = computed(() => {
  let list = [...props.approvals];

  if (categoryFilter.value !== 'Semua kategori') {
    list = list.filter(app => app.category === categoryFilter.value);
  }

  if (decisionFilter.value !== 'Semua keputusan') {
    const dec = decisionFilter.value === 'Disetujui' ? 'approved' : 'rejected';
    list = list.filter(app => app.decision === dec);
  }

  // default sort by decided_at desc (fall back to id desc)
  list.sort((a, b) => {
    const timeA = a.decided_at ? new Date(a.decided_at).getTime() : 0;
    const timeB = b.decided_at ? new Date(b.decided_at).getTime() : 0;
    if (timeB !== timeA) return timeB - timeA;
    return b.id - a.id;
  });

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'Semua baris') {
    return filteredApprovals.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

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
  if (val === null || val === undefined || val === '') return 'Rp0';
  let num: number;
  if (typeof val === 'string') {
    const cleanStr = val.replace(/[^0-9,-]/g, '');
    if (!cleanStr) return 'Rp0';
    num = parseFloat(cleanStr.replace(/,/g, '.'));
  } else {
    num = val;
  }
  if (isNaN(num)) return 'Rp0';
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
const auditRowsPerPage = ref('Semua baris');

const openDetailPopup = (approval: ApprovalItem) => {
  activeApproval.value = approval;
  detailActiveTab.value = 'Detail';
  auditSearch.value = '';
  auditStatusFilter.value = 'semua';
  auditTimeFilter.value = 'semua';
  auditRowsPerPage.value = 'Semua baris';
  isDetailPopupOpen.value = true;
};

const closeDetailPopup = () => {
  isDetailPopupOpen.value = false;
  setTimeout(() => {
    activeApproval.value = null;
  }, 200);
};

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

const auditStatusOptions = computed(() => {
  if (!activeApproval.value) return [];
  const stats = new Set<string>();
  activeApproval.value.unit_details.lifecycles.forEach(l => {
    if (l.aksi_status) stats.add(l.aksi_status);
  });
  return Array.from(stats);
});

const columns: ColumnDef<ApprovalItem>[] = [
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
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('category')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('subcategory')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('brand')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate max-w-xs', title: row.getValue('specification') }, row.getValue('specification')),
  },
  {
    accessorKey: 'status_label',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Status Diajukan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground font-medium' }, row.getValue('status_label')),
  },
  {
    accessorKey: 'decision',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-center w-full'
      }, () => [
        'Keputusan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-center' }, [
      h('span', { 
        class: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold ' + 
          (row.getValue('decision') === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')
      }, row.getValue('decision') === 'approved' ? 'Disetujui' : 'Ditolak')
    ]),
  },
  {
    accessorKey: 'approver_name',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-center w-full'
      }, () => [
        'Diproses Oleh',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'text-center' }, [
        h('div', { class: 'font-semibold text-foreground' }, item.approver_name || '-'),
        h('div', { class: 'text-[10px] text-muted-foreground font-mono mt-0.5' }, item.decided_at || '-')
      ]);
    }
  },
  {
    id: 'actions',
    size: 100,
    header: () => h('div', { class: 'text-center font-semibold text-foreground' }, 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-center gap-2' }, [
        h('button', {
          onClick: () => openMemoFile(item.memo_path),
          class: 'w-8 h-8 rounded-full bg-[#6366F1] hover:bg-[#5850EC] text-white flex items-center justify-center transition-colors shadow-sm cursor-pointer',
          title: 'Buka Berita Acara / Memo'
        }, [
          h(FileText, { class: 'w-4 h-4' })
        ]),
        h(ViewTableButton, {
          onClick: () => openDetailPopup(item),
          title: 'Detail Aset'
        })
      ]);
    },
    enableSorting: false,
  }
];

const isVehicle = (item: ApprovalItem | null) => {
  if (!item) return false;
  const category = (item.category || '').toLowerCase();
  const subcategory = (item.subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
};
</script>

<template>
  <Head title="Approval Aset" />

  <AppLayout title="Approval Aset">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Approval Aset</h1>
      <p class="text-lg font-bold text-gray-900 mt-2">Sudah Diproses</p>
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
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!decisionFilter || decisionFilter === 'Semua keputusan') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ decisionFilter || 'Semua keputusan' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="decisionFilter = 'Semua keputusan'">Semua keputusan</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'Disetujui'">Disetujui</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'Ditolak'">Ditolak</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>Baris per halaman</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPage === 'Semua baris' ? 'Semua baris' : `${rowsPerPage} baris` }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = '5'">5 baris</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10 baris</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25 baris</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>

    <!-- ── Table Display ── -->
    <div class="pb-4">
      <DataTable 
        :columns="columns" 
        :data="filteredApprovals" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
        :show-selection-count="false"
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
              class="bg-card w-full max-w-[90%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col border border-border"
              @click.stop
            >
              <!-- Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Detail Aset</h3>
                <button @click="closeDetailPopup" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
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
                    <div class="flex-grow grid grid-cols-1 md:grid-cols-18 gap-4 text-foreground">
                      <!-- Column 1: Item Info -->
                      <div class="md:col-span-5">
                        <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ activeApproval.unit_details.barang_code }}</p>
                        <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ activeApproval.brand }}</p>
                        <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ activeApproval.nama }}</p>
                        <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ activeApproval.specification }}</p>
                        <p class="text-foreground">Kategori: {{ activeApproval.category }}</p>
                        <p class="text-foreground">Subkategori: {{ activeApproval.subcategory }}</p>
                        <p class="text-foreground">Satuan: {{ activeApproval.unit_details.barang_unit }}</p>
                      </div>

                      <!-- Column 2: LOT Info -->
                      <div class="md:col-span-6">
                        <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ activeApproval.unit_details.lot_code }}</p>
                        <p class="text-foreground">Organizer: {{ activeApproval.unit_details.organizer }}</p>
                        <p class="text-foreground">Tanggal masuk: {{ formatDateWithDashes(activeApproval.unit_details.date_of_receipt) }}</p>
                        <p class="text-foreground">Vendor: {{ activeApproval.unit_details.vendor }}</p>
                        <p class="text-foreground">Nomor PO: {{ activeApproval.unit_details.po_number }}</p>
                      </div>

                      <!-- Column 3: Asset Info -->
                      <div class="md:col-span-7">
                        <p class="font-bold text-foreground"><span class="text-foreground">Kode Aset:</span> {{ activeApproval.asset_code }}</p>
                        <!-- TNKB (Nopol) -->
                        <p v-if="isVehicle(activeApproval)" class="font-bold text-foreground">
                          <span class="text-foreground">Nopol:</span> {{ activeApproval.unit_details.vehicle_registration || '-' }}
                        </p>

                        <p class="text-foreground">
                          Status Sekarang: <span class="text-primary font-semibold">{{ activeApproval.unit_details.status }}</span>
                        </p>
                        <p class="text-foreground">
                          Status Diajukan: 
                          <span 
                            :class="[
                              'inline-flex items-center px-2 py-0.1 rounded-full font-semibold',
                              activeApproval.proposed_status === 'tersedia' ? 'bg-emerald-100 text-emerald-800' :
                              activeApproval.proposed_status === 'dipinjam' ? 'bg-amber-100 text-amber-800' :
                              activeApproval.proposed_status === 'dipakai' ? 'bg-blue-100 text-blue-800' :
                              'bg-rose-100 text-rose-800'
                            ]"
                          >
                            {{ activeApproval.status_label }}
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
                        <p class="text-foreground">
                          Keputusan: 
                          <span 
                            :class="[
                              'font-semibold',
                              activeApproval.decision === 'approved' ? 'text-emerald-600' : 'text-rose-600'
                            ]"
                          >
                            {{ activeApproval.decision === 'approved' ? 'Disetujui' : 'Ditolak' }}
                          </span>
                        </p>
                        <p class="text-foreground" v-if="activeApproval.note">
                          Catatan Manager: <span class="italic text-muted-foreground">"{{ activeApproval.note }}"</span>
                        </p>
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
                            {{ auditRowsPerPage === 'Semua baris' ? 'Semua baris' : `${auditRowsPerPage} baris` }}
                            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[140px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                          <DropdownMenuItem @select="auditRowsPerPage = '5'">5 baris</DropdownMenuItem>
                          <DropdownMenuItem @select="auditRowsPerPage = '10'">10 baris</DropdownMenuItem>
                          <DropdownMenuItem @select="auditRowsPerPage = '25'">25 baris</DropdownMenuItem>
                          <DropdownMenuItem @select="auditRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>

                  <!-- Log table via DataTable -->
                  <div class="pb-4">
                    <DataTable 
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
                <button 
                  @click="openMemoFile(activeApproval.memo_path)"
                  class="bg-[#6366F1] hover:bg-[#5850EC] text-white text-sm font-bold px-5 py-2.5 rounded-xl inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
                >
                  <FileText class="w-4 h-4" />
                  Buka Memo / Berita Acara
                </button>
>>>>>>> main

                <!-- White Kembali Button -->
                <button 
                  @click="closeDetailPopup"
                  class="bg-background border border-input hover:bg-muted text-foreground text-sm font-bold px-5 py-2.5 rounded-xl transition-colors cursor-pointer shadow-sm"
                >
                  Kembali
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>
