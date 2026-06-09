<script setup lang="ts">
import { ref, computed, watch } from 'vue';
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
import Combobox from '@/Components/Combobox.vue';

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

// Sorting
const sortBy = ref('decided_at');
const sortDesc = ref(true);

const toggleSort = (field: string) => {
  if (sortBy.value === field) {
    sortDesc.value = !sortDesc.value;
  } else {
    sortBy.value = field;
    sortDesc.value = false;
  }
};

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

  if (searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(app => 
      app.asset_code.toLowerCase().includes(q) ||
      app.brand.toLowerCase().includes(q) ||
      app.specification.toLowerCase().includes(q) ||
      app.subcategory.toLowerCase().includes(q)
    );
  }

  if (categoryFilter.value !== 'Semua kategori') {
    list = list.filter(app => app.category === categoryFilter.value);
  }

  if (decisionFilter.value !== 'Semua keputusan') {
    const dec = decisionFilter.value === 'Disetujui' ? 'approved' : 'rejected';
    list = list.filter(app => app.decision === dec);
  }

  list.sort((a, b) => {
    let valA: any = a[sortBy.value as keyof ApprovalItem] ?? '';
    let valB: any = b[sortBy.value as keyof ApprovalItem] ?? '';

    if (sortBy.value === 'asset_code') {
      valA = a.asset_code;
      valB = b.asset_code;
    } else if (sortBy.value === 'category') {
      valA = a.category;
      valB = b.category;
    } else if (sortBy.value === 'subcategory') {
      valA = a.subcategory;
      valB = b.subcategory;
    } else if (sortBy.value === 'brand') {
      valA = a.brand;
      valB = b.brand;
    } else if (sortBy.value === 'specification') {
      valA = a.specification;
      valB = b.specification;
    } else if (sortBy.value === 'status') {
      valA = a.status_label;
      valB = b.status_label;
    } else if (sortBy.value === 'decision') {
      valA = a.decision;
      valB = b.decision;
    }

    if (typeof valA === 'string') {
      return sortDesc.value ? valB.localeCompare(valA) : valA.localeCompare(valB);
    } else {
      return sortDesc.value ? valB - valA : valA - valB;
    }
  });

  return list;
});

// Pagination
const currentPage = ref(1);

const totalPages = computed(() => {
  if (rowsPerPage.value === 'Semua baris') return 1;
  const limit = parseInt(rowsPerPage.value, 10);
  return Math.ceil(filteredApprovals.value.length / limit) || 1;
});

const paginatedApprovals = computed(() => {
  const list = filteredApprovals.value;
  if (rowsPerPage.value === 'Semua baris') return list;
  const limit = parseInt(rowsPerPage.value, 10);
  const start = (currentPage.value - 1) * limit;
  return list.slice(start, start + limit);
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
    <div class="flex flex-col gap-4 mb-6">
      <div class="flex flex-wrap gap-3 items-center justify-between">
        <div class="flex flex-wrap gap-3 items-center w-full lg:w-auto">
          <!-- Search input -->
          <div class="relative w-full sm:w-[220px]">
            <div class="absolute left-3 top-3 text-gray-400">
              <Search class="w-4 h-4" />
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari Aset..."
              class="w-full h-10 pl-9 pr-4 text-xs border border-gray-300 rounded-[14px] bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
            />
          </div>

          <!-- Kategori Filter -->
          <Combobox
            v-model="categoryFilter"
            :options="categoryOptions"
            search-placeholder="Cari kategori..."
            default-label="Semua kategori"
            width-class="w-full sm:w-auto min-w-[160px]"
          />

          <!-- Decision Filter -->
          <Combobox
            v-model="decisionFilter"
            :options="['Disetujui', 'Ditolak']"
            search-placeholder="Cari keputusan..."
            default-label="Semua keputusan"
            width-class="w-full sm:w-auto min-w-[160px]"
          />
        </div>

        <!-- Page Size Selection -->
        <div class="flex items-center gap-2 text-xs text-gray-600 justify-end w-full sm:w-auto">
          <span class="whitespace-nowrap">Baris per halaman</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" class="w-full sm:w-auto min-w-[130px] h-10 justify-between rounded-[14px] font-normal text-xs bg-white border-gray-300 shadow-sm text-gray-900 gap-2">
                <span>{{ rowsPerPage === 'Semua baris' ? 'Semua baris' : `${rowsPerPage} baris` }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0 ml-2" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="min-w-[130px] rounded-[14px]" align="start" :side-offset="4">
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
    <div class="rounded-xl border border-border shadow-sm overflow-x-auto bg-card">
      <table class="w-full text-xs text-left border-collapse">
        <thead class="bg-muted/50 border-b border-border text-foreground">
          <tr class="hover:bg-transparent text-foreground font-semibold">
            <th @click="toggleSort('asset_code')" class="py-4 px-6 cursor-pointer select-none font-semibold">
              <div class="flex items-center gap-1">
                Kode Aset
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('category')" class="py-4 px-4 cursor-pointer select-none font-semibold">
              <div class="flex items-center gap-1">
                Kategori
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('subcategory')" class="py-4 px-4 cursor-pointer select-none font-semibold">
              <div class="flex items-center gap-1">
                Subkategori
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('brand')" class="py-4 px-4 cursor-pointer select-none font-semibold">
              <div class="flex items-center gap-1">
                Merek
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('specification')" class="py-4 px-4 cursor-pointer select-none font-semibold">
              <div class="flex items-center gap-1">
                Spesifikasi
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('status')" class="py-4 px-4 cursor-pointer select-none font-semibold">
              <div class="flex items-center gap-1">
                Status Diajukan
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('decision')" class="py-4 px-4 cursor-pointer select-none text-center font-semibold">
              <div class="flex items-center justify-center gap-1">
                Keputusan
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th class="py-4 px-4 text-center font-semibold">Diproses Oleh</th>
            <th class="py-4 px-4 text-center w-28 font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="app in paginatedApprovals" 
            :key="app.id"
            class="border-b border-border hover:bg-muted/30 transition-colors last:border-none"
          >
            <td class="py-4 px-6 font-mono font-medium text-foreground">{{ app.asset_code }}</td>
            <td class="py-4 px-4 text-foreground">{{ app.category }}</td>
            <td class="py-4 px-4 text-foreground">{{ app.subcategory }}</td>
            <td class="py-4 px-4 text-foreground">{{ app.brand }}</td>
            <td class="py-4 px-4 text-foreground truncate max-w-xs">{{ app.specification }}</td>
            <!-- Plain Text Status Column (matches mockup) -->
            <td class="py-4 px-4 text-foreground font-medium">{{ app.status_label }}</td>
            <td class="py-4 px-4 text-center">
              <span 
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                :class="[
                  app.decision === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                ]"
              >
                {{ app.decision === 'approved' ? 'Disetujui' : 'Ditolak' }}
              </span>
            </td>
            <td class="py-4 px-4 text-center">
              <div class="font-semibold text-gray-900">{{ app.approver_name || '-' }}</div>
              <div class="text-[10px] text-gray-500 font-mono mt-0.5">{{ app.decided_at || '-' }}</div>
            </td>
            <td class="py-4 px-4">
              <div class="flex items-center justify-center gap-2">
                <!-- Purple Circle Action Button -->
                <button 
                  @click="openMemoFile(app.memo_path)"
                  class="w-8 h-8 rounded-full bg-[#6366F1] hover:bg-[#5850EC] text-white flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  title="Buka Berita Acara / Memo"
                >
                  <FileText class="w-4 h-4" />
                </button>
                <!-- Cyan Circle Action Button -->
                <button 
                  @click="openDetailPopup(app)"
                  class="w-8 h-8 rounded-full bg-[#00BCD4] hover:bg-[#06B6D4] text-white flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  title="Detail Aset"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="filteredApprovals.length === 0">
            <td colspan="9" class="py-12 text-center text-gray-500">
              <AlertTriangle class="w-8 h-8 mx-auto mb-2 text-gray-400" />
              <p class="font-medium">Tidak ada riwayat persetujuan status aset.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination & Count Display ── -->
    <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4 px-2 text-xs text-gray-500">
      <div>
        Menampilkan {{ filteredApprovals.length }} riwayat persetujuan
      </div>
      
      <!-- Page numbers navigation -->
      <div v-if="totalPages > 1" class="flex items-center gap-1">
        <button 
          :disabled="currentPage === 1"
          @click="currentPage--"
          class="h-8 px-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none transition-colors"
        >
          &lt; Sebelumnya
        </button>
        
        <button 
          v-for="page in totalPages" 
          :key="page"
          @click="currentPage = page"
          class="w-8 h-8 border rounded-lg transition-all"
          :class="[
            currentPage === page 
              ? 'border-indigo-600 bg-indigo-50 text-indigo-600 font-bold shadow-sm' 
              : 'border-gray-300 hover:bg-gray-50 text-gray-700'
          ]"
        >
          {{ page }}
        </button>

        <button 
          :disabled="currentPage === totalPages"
          @click="currentPage++"
          class="h-8 px-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none transition-colors"
        >
          Selanjutnya &gt;
        </button>
      </div>
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
