<script setup lang="ts">
import { ref, computed, watch, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  Search,
  X,
  FileText,
  Eye,
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

  // Pre-sort by id descending (newest first)
  list.sort((a, b) => b.id - a.id);

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'Semua baris') {
    return filteredApprovals.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

// Memo document viewer
const openMemoFile = (path: string | null) => {
  if (!path) {
    toast.error('File berita acara / memo tidak ditemukan.');
    return;
  }
  window.open('/storage/' + path, '_blank');
};

// ─────────────────────────────────────────────
// Detail Popup States
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
        h('button', {
          onClick: () => openDetailPopup(item),
          class: 'w-8 h-8 rounded-full bg-[#00BCD4] hover:bg-[#06B6D4] text-white flex items-center justify-center transition-colors shadow-sm cursor-pointer',
          title: 'Detail Aset'
        }, [
          h(Eye, { class: 'w-4 h-4' })
        ])
      ]);
    },
    enableSorting: false,
  }
];
</script>

<template>
  <Head title="Approval Aset - Sudah Approve" />

  <AppLayout title="Approval Aset">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-[28px] font-bold text-gray-900 leading-none">Approval Aset</h1>
      <p class="text-base font-semibold text-gray-900 mt-2">Sudah Diproses</p>
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
      <div 
        v-if="isDetailPopupOpen && activeApproval" 
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
        @click="closeDetailPopup"
      >
        <div 
          class="bg-white text-gray-900 rounded-xl shadow-2xl w-full max-w-5xl flex flex-col overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-200"
          @click.stop
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-white shrink-0">
            <h3 class="text-base md:text-lg font-bold text-gray-900">Detail Aset</h3>
            <button @click="closeDetailPopup" class="p-1.5 hover:bg-gray-100 rounded-full transition-colors cursor-pointer">
              <X class="w-5 h-5 text-gray-500" />
            </button>
          </div>

          <!-- Popup Tab pills -->
          <div class="px-6 py-3 border-b border-gray-200 flex items-center gap-2 shrink-0">
            <button 
              @click="detailActiveTab = 'Detail'"
              class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all cursor-pointer"
              :class="[
                detailActiveTab === 'Detail' 
                  ? 'border-indigo-600 text-indigo-600 bg-white' 
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              ]"
            >
              Detail
            </button>
            <button 
              @click="detailActiveTab = 'Jejak Audit'"
              class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all cursor-pointer"
              :class="[
                detailActiveTab === 'Jejak Audit' 
                  ? 'border-indigo-600 text-indigo-600 bg-white' 
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              ]"
            >
              Jejak Audit
            </button>
          </div>

          <!-- Body contents -->
          <div class="overflow-y-auto max-h-[70vh] p-6">
            
            <!-- ── TAB 1: DETAIL ── -->
            <div v-if="detailActiveTab === 'Detail'" class="flex flex-col lg:flex-row gap-6">
              <!-- Left Side Image -->
              <div class="w-full lg:w-48 h-48 rounded-[12px] bg-gray-150 border border-gray-200 overflow-hidden shrink-0 flex items-center justify-center">
                <img 
                  v-if="activeApproval.unit_details.image_url" 
                  :src="activeApproval.unit_details.image_url" 
                  class="w-full h-full object-cover" 
                />
                <div v-else class="text-xs font-bold text-gray-400 select-none text-center p-4">
                  No Photo
                </div>
              </div>

              <!-- Right Side Metadata (Same line labels) -->
              <div class="flex-grow grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3.5 text-xs text-gray-900 align-top">
                <!-- Column 1 -->
                <div class="space-y-3.5">
                  <p class="leading-normal"><strong class="font-bold">Kode Barang:</strong> {{ activeApproval.unit_details.barang_code }}</p>
                  <p class="leading-normal"><strong class="font-bold">Merek:</strong> {{ activeApproval.brand }}</p>
                  <p class="leading-normal"><strong class="font-bold">Spesifikasi:</strong> {{ activeApproval.specification }}</p>
                  <p class="leading-normal"><strong class="font-bold">Kategori:</strong> {{ activeApproval.category }}</p>
                  <p class="leading-normal"><strong class="font-bold">Subkategori:</strong> {{ activeApproval.subcategory }}</p>
                  <p class="leading-normal"><strong class="font-bold">Satuan:</strong> {{ activeApproval.unit_details.barang_unit }}</p>
                </div>

                <!-- Column 2 -->
                <div class="space-y-3.5">
                  <p class="leading-normal"><strong class="font-bold">Kode LOT:</strong> {{ activeApproval.unit_details.lot_code }}</p>
                  <p class="leading-normal"><strong class="font-bold">Organizer:</strong> {{ activeApproval.unit_details.organizer }}</p>
                  <p class="leading-normal"><strong class="font-bold">Tanggal masuk:</strong> {{ activeApproval.unit_details.date_of_receipt }}</p>
                  <p class="leading-normal"><strong class="font-bold">Vendor:</strong> {{ activeApproval.unit_details.vendor }}</p>
                  <p class="leading-normal"><strong class="font-bold">Nomor PO:</strong> {{ activeApproval.unit_details.po_number }}</p>
                </div>

                <!-- Column 3 -->
                <div class="space-y-3.5">
                  <p class="leading-normal"><strong class="font-bold">Kode Aset:</strong> {{ activeApproval.asset_code }}</p>
                  <p class="leading-normal"><strong class="font-bold">Nopol:</strong> {{ activeApproval.unit_details.vehicle_registration || '-' }}</p>
                  <p class="leading-normal"><strong class="font-bold text-gray-900">Status Sekarang:</strong> <span class="text-indigo-600 font-semibold">{{ activeApproval.unit_details.status }}</span></p>
                  <p class="leading-normal"><strong class="font-bold">Kondisi:</strong> {{ activeApproval.unit_details.condition }}</p>
                  <p class="leading-normal"><strong class="font-bold">Nilai:</strong> Rp{{ activeApproval.unit_details.price }}</p>
                  <p class="leading-normal"><strong class="font-bold">Keputusan:</strong> <span class="font-semibold" :class="activeApproval.decision === 'approved' ? 'text-green-600' : 'text-red-600'">{{ activeApproval.decision === 'approved' ? 'Disetujui' : 'Ditolak' }}</span></p>
                  <p class="leading-normal" v-if="activeApproval.note"><strong class="font-bold">Catatan Manager:</strong> <span class="italic">"{{ activeApproval.note }}"</span></p>
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
                      <th class="py-3 px-4 w-28 text-center font-semibold">Durasi (hari) ↑↓</th>
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
                      <td colspan="5" class="py-6 text-center text-gray-500 italic">
                        Tidak ada log jejak audit.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Mini pagination inside Jejak Audit tab -->
              <div class="mt-4 flex items-center justify-end text-xs text-gray-500 px-1">
                <div class="flex items-center gap-1">
                  <button class="h-8 px-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 text-xs disabled:opacity-50" disabled>&lt; Sebelumnya</button>
                  <button class="w-8 h-8 border border-indigo-600 bg-indigo-50 text-indigo-600 font-bold rounded-lg text-xs">1</button>
                  <button class="h-8 px-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 text-xs disabled:opacity-50" disabled>Selanjutnya &gt;</button>
                </div>
              </div>
            </div>

          </div>

          <!-- Bottom Footer Buttons (Right Aligned group) -->
          <div class="p-5 border-t border-gray-200 flex justify-end gap-3 bg-gray-50 shrink-0">
            <!-- Purple Memo Button -->
            <button 
              @click="openMemoFile(activeApproval.memo_path)"
              class="bg-[#6366F1] hover:bg-[#5850EC] text-white font-medium text-xs px-5 py-2.5 rounded-lg inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
            >
              <FileText class="w-4 h-4" />
              Buka Memo / Berita Acara
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

  </AppLayout>
</template>
