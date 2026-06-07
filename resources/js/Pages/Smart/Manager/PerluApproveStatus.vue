<script setup lang="ts">
import { ref, computed, watch } from 'vue';
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
  AlertTriangle
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

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
  memo_path: string | null;
  memo_name: string | null;
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

// Sorting
const sortBy = ref('id');
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

  if (statusFilter.value !== 'Semua status') {
    list = list.filter(app => app.status_label === statusFilter.value);
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

// Selection States
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
  const currentList = paginatedApprovals.value;
  if (currentList.length === 0) return false;
  return currentList.every(item => selectedIds.value.includes(item.id));
});

const toggleSelectAll = () => {
  const currentList = paginatedApprovals.value;
  if (isAllSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !currentList.some(item => item.id === id));
  } else {
    const idsToAdd = currentList.map(item => item.id).filter(id => !selectedIds.value.includes(id));
    selectedIds.value = [...selectedIds.value, ...idsToAdd];
  }
};

const toggleSelectRow = (id: number) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(item => item !== id);
  } else {
    selectedIds.value.push(id);
  }
};

// Memo document opener
const openMemoFile = (path: string | null, name: string | null) => {
  if (!path) {
    toast.error('File berita acara / memo tidak ditemukan.');
    return;
  }
  window.open('/storage/' + path, '_blank');
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
const auditRowsPerPage = ref('semua');

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

  router.post(route('smart.approve-status.action'), {
    ids: idsToProcess,
    decision: confirmActionType.value,
    note: confirmNote.value,
  }, {
    onSuccess: () => {
      closeConfirmModal();
      closeDetailPopup();
      selectedIds.value = [];
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
</script>

<template>
  <Head title="Approval Aset" />

  <AppLayout title="Approval Aset">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-[28px] font-bold text-gray-900 leading-none">Approval Aset</h1>
      <p class="text-base font-semibold text-gray-900 mt-2">Perlu Perhatian Anda</p>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <span class="text-xs font-semibold text-gray-500 block">Filter</span>
      
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex flex-wrap gap-3 items-center w-full sm:w-auto">
          <!-- Search input -->
          <div class="relative w-full sm:w-[220px]">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari Aset..."
              class="w-full h-10 pl-4 pr-10 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
            />
            <div class="absolute right-3 top-3 text-gray-400">
              <Search class="w-4 h-4" />
            </div>
          </div>

          <!-- Kategori Filter -->
          <div class="w-full sm:w-[150px]">
            <select 
              v-model="categoryFilter" 
              class="w-full h-10 px-3 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer shadow-sm"
            >
              <option value="Semua kategori">Semua kategori</option>
              <option v-for="cat in categoryOptions" :key="cat" :value="cat">{{ cat }}</option>
            </select>
          </div>

          <!-- Status Filter -->
          <div class="w-full sm:w-[150px]">
            <select 
              v-model="statusFilter" 
              class="w-full h-10 px-3 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer shadow-sm"
            >
              <option value="Semua status">Semua status</option>
              <option v-for="st in statusOptions" :key="st" :value="st">{{ st }}</option>
            </select>
          </div>
        </div>

        <!-- Page Size Selection -->
        <div class="flex items-center gap-2 text-xs text-gray-600 justify-end w-full sm:w-auto">
          <span class="whitespace-nowrap">Baris per halaman</span>
          <select 
            v-model="rowsPerPage" 
            class="border border-gray-300 rounded-lg h-10 px-3 bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer w-[120px]"
          >
            <option value="5">5 baris</option>
            <option value="10">10 baris</option>
            <option value="25">25 baris</option>
            <option value="Semua baris">Semua baris</option>
          </select>
        </div>
      </div>

      <!-- ── Bulk Actions ── -->
      <div class="flex flex-col space-y-1.5 pt-2">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Aksi Terpilih</span>
        <div class="flex gap-2.5">
          <button 
            :disabled="selectedIds.length <= 1"
            @click="openConfirmModal('approved', true)"
            class="rounded-[10px] h-10 font-bold text-xs shadow-sm flex items-center gap-2 px-5 transition-all duration-200"
            :class="selectedIds.length > 1 
              ? 'bg-[#2ECC71] hover:bg-[#27AE60] text-white cursor-pointer' 
              : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
          >
            <ThumbsUp class="w-4 h-4" />
            Approve Terpilih
          </button>
          <button 
            :disabled="selectedIds.length <= 1"
            @click="openConfirmModal('rejected', true)"
            class="rounded-[10px] h-10 font-bold text-xs shadow-sm flex items-center gap-2 px-5 transition-all duration-200"
            :class="selectedIds.length > 1 
              ? 'bg-[#E74C3C] hover:bg-[#C0392B] text-white cursor-pointer' 
              : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
          >
            <Ban class="w-4 h-4" />
            Tolak Terpilih
          </button>
        </div>
      </div>
    </div>

    <!-- ── Table Display ── -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto shadow-sm">
      <table class="w-full text-xs text-left border-collapse">
        <thead>
          <tr class="bg-white border-b border-gray-200 text-gray-900 font-bold">
            <th class="py-4 px-4 w-12 text-center">
              <div class="flex items-center justify-center">
                <!-- Circular Custom Checkbox -->
                <button 
                  @click="toggleSelectAll" 
                  class="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center transition-all cursor-pointer"
                  :class="isAllSelected ? 'bg-[#2ECC71] border-transparent text-white' : 'bg-white text-transparent'"
                >
                  <span class="text-[10px] font-bold">✓</span>
                </button>
              </div>
            </th>
            <th @click="toggleSort('asset_code')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Kode Aset
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('category')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Kategori
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('subcategory')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Subkategori
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('brand')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Merek
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('specification')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Spesifikasi
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('status')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Status
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th class="py-4 px-4 text-center w-28">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-150">
          <tr 
            v-for="app in paginatedApprovals" 
            :key="app.id"
            class="hover:bg-gray-50/50 transition-colors"
          >
            <td class="py-4 px-4 text-center">
              <div class="flex items-center justify-center">
                <!-- Circular Custom Checkbox -->
                <button 
                  @click="toggleSelectRow(app.id)" 
                  class="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center transition-all cursor-pointer"
                  :class="selectedIds.includes(app.id) ? 'bg-[#2ECC71] border-transparent text-white' : 'bg-white text-transparent'"
                >
                  <span class="text-[10px] font-bold">✓</span>
                </button>
              </div>
            </td>
            <td class="py-4 px-4 font-mono font-medium text-gray-900">{{ app.asset_code }}</td>
            <td class="py-4 px-4 text-gray-900">{{ app.category }}</td>
            <td class="py-4 px-4 text-gray-900">{{ app.subcategory }}</td>
            <td class="py-4 px-4 text-gray-900">{{ app.brand }}</td>
            <td class="py-4 px-4 text-gray-900 truncate max-w-xs">{{ app.specification }}</td>
            <!-- Plain Text Status Column (matches mockup) -->
            <td class="py-4 px-4 text-gray-900 font-medium">{{ app.status_label }}</td>
            <td class="py-4 px-4">
              <div class="flex items-center justify-center gap-2">
                <!-- Purple Circle Action Button -->
                <button 
                  @click="openMemoFile(app.memo_path, app.memo_name)"
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
            <td colspan="8" class="py-12 text-center text-gray-500">
              <AlertTriangle class="w-8 h-8 mx-auto mb-2 text-gray-400" />
              <p class="font-medium">Tidak ada data persetujuan status aset.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination & Count Display ── -->
    <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4 px-2 text-xs text-gray-500">
      <div>
        {{ selectedIds.length }} dari {{ filteredApprovals.length }} baris dipilih
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
            <div v-if="detailActiveTab === 'Detail'" class="border border-gray-200 rounded-xl p-5 flex flex-col lg:flex-row gap-6 bg-white">
              <!-- Left Side Image -->
              <div class="w-full lg:w-48 h-48 rounded-[12px] bg-gray-100 border border-gray-200 overflow-hidden shrink-0 flex items-center justify-center">
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
                  <p class="leading-normal font-bold">Kode Barang: <span>{{ activeApproval.unit_details.barang_code }}</span></p>
                  <p class="leading-normal font-bold">Merek: <span>{{ activeApproval.brand }}</span></p>
                  <p class="leading-normal font-bold">Spesifikasi: <span>{{ activeApproval.specification }}</span></p>
                  <p class="leading-normal text-gray-600">Kategori: <span class="text-gray-900">{{ activeApproval.category }}</span></p>
                  <p class="leading-normal text-gray-600">Subkategori: <span class="text-gray-900">{{ activeApproval.subcategory }}</span></p>
                  <p class="leading-normal text-gray-600">Satuan: <span class="text-gray-900">{{ activeApproval.unit_details.barang_unit }}</span></p>
                </div>

                <!-- Column 2 -->
                <div class="space-y-3.5">
                  <p class="leading-normal font-bold">Kode LOT: <span>{{ activeApproval.unit_details.lot_code }}</span></p>
                  <p class="leading-normal text-gray-600">Organizer: <span class="text-gray-900">{{ activeApproval.unit_details.organizer }}</span></p>
                  <p class="leading-normal text-gray-600">Tanggal masuk: <span class="text-gray-900">{{ activeApproval.unit_details.date_of_receipt }}</span></p>
                  <p class="leading-normal text-gray-600">Vendor: <span class="text-gray-900">{{ activeApproval.unit_details.vendor }}</span></p>
                  <p class="leading-normal text-gray-600">Nomor PO: <span class="text-gray-900">{{ activeApproval.unit_details.po_number }}</span></p>
                </div>

                <!-- Column 3 -->
                <div class="space-y-3.5">
                  <p class="leading-normal font-bold">Kode Aset: <span>{{ activeApproval.asset_code }}</span></p>
                  <p class="leading-normal text-gray-600">Nopol: <span class="text-gray-900">{{ activeApproval.unit_details.vehicle_registration || '-' }}</span></p>
                  <!-- Red color and fully bold for Status -->
                  <p class="leading-normal text-[#E74C3C] font-bold">Status: {{ activeApproval.status_label }}</p>
                  <p class="leading-normal text-gray-600">Kondisi: <span class="text-gray-900">{{ activeApproval.unit_details.condition }}</span></p>
                  <p class="leading-normal text-gray-600">Nilai: <span class="text-gray-900">Rp{{ activeApproval.unit_details.price }}</span></p>
                  <p class="leading-normal text-gray-600">Lokasi penyimpanan: <span class="text-gray-900">{{ activeApproval.unit_details.location }}</span></p>
                  <p class="leading-normal text-gray-600">Pembaruan terakhir: <span class="text-gray-900">{{ activeApproval.requested_at }}</span></p>
                </div>
              </div>
            </div>

            <!-- ── TAB 2: JEJAK AUDIT ── -->
            <div v-if="detailActiveTab === 'Jejak Audit'" class="space-y-4">
              <!-- Internal Search & Local Filters -->
              <div class="flex flex-col sm:flex-row items-center gap-3 justify-between p-4 bg-gray-50 border border-gray-200 rounded-xl text-xs">
                <div class="flex flex-wrap gap-3 items-center w-full sm:w-auto">
                  <div class="relative w-full sm:w-[220px]">
                    <input
                      v-model="auditSearch"
                      type="text"
                      placeholder="Cari Jejak Audit"
                      class="w-full h-10 pl-3 pr-8 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <div class="absolute right-2.5 top-3 text-gray-400">
                      <Search class="w-4 h-4" />
                    </div>
                  </div>
                  
                  <div class="w-full sm:w-[140px]">
                    <select 
                      v-model="auditStatusFilter" 
                      class="w-full h-10 px-3 border border-gray-300 rounded-lg bg-white text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
                    >
                      <option value="semua">Semua Status</option>
                      <option v-for="st in auditStatusOptions" :key="st" :value="st">{{ st }}</option>
                    </select>
                  </div>

                  <div class="w-full sm:w-[150px]">
                    <select 
                      v-model="auditTimeFilter" 
                      class="w-full h-10 px-3 border border-gray-300 rounded-lg bg-white text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
                    >
                      <option value="semua">Semua Kurun Waktu</option>
                      <option value="7-hari">7 hari terakhir</option>
                      <option value="30-hari">30 hari terakhir</option>
                    </select>
                  </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end text-gray-500">
                  <span>Baris per halaman:</span>
                  <select class="border border-gray-300 rounded-lg h-10 px-3 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer w-[120px]">
                    <option value="semua">Semua baris</option>
                  </select>
                </div>
              </div>

              <!-- Log table -->
              <div class="border border-gray-200 rounded-xl overflow-x-auto shadow-sm">
                <table class="w-full text-xs text-left border-collapse">
                  <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-700 font-bold uppercase tracking-wider text-[10px]">
                      <th class="py-3 px-4 w-40">Waktu ↑↓</th>
                      <th class="py-3 px-4 w-36">Aksi / Status ↑↓</th>
                      <th class="py-3 px-4 w-40">Aktor ↑↓</th>
                      <th class="py-3 px-4 w-28 text-center">Durasi (hari) ↑↓</th>
                      <th class="py-3 px-4">Catatan ↑↓</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-150">
                    <tr 
                      v-for="(lc, idx) in paginatedLifecycles" 
                      :key="idx"
                      class="hover:bg-gray-50/50 transition-colors"
                    >
                      <td class="py-3 px-4 font-medium text-gray-900">{{ lc.waktu }}</td>
                      <td class="py-3 px-4 text-gray-900 font-medium">{{ lc.aksi_status }}</td>
                      <td class="py-3 px-4 text-gray-900">{{ lc.aktor }}</td>
                      <td class="py-3 px-4 text-center text-gray-900">{{ lc.durasi }}</td>
                      <td class="py-3 px-4 text-gray-700 max-w-sm truncate">{{ lc.catatan }}</td>
                    </tr>
                    
                    <tr v-if="paginatedLifecycles.length === 0">
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
          <div class="p-5 flex justify-end gap-3 bg-white shrink-0">
            <!-- Purple Memo Button -->
            <button 
              @click="openMemoFile(activeApproval.memo_path, activeApproval.memo_name)"
              class="bg-[#6366F1] hover:bg-[#5850EC] text-white font-bold text-xs h-10 px-5 rounded-full inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
            >
              <FileText class="w-4 h-4" />
              Buka Memo / Berita Acara
            </button>

            <!-- Green Approve Button -->
            <button 
              @click="openConfirmModal('approved', false)"
              class="bg-[#2ECC71] hover:bg-[#27AE60] text-white font-bold text-xs h-10 px-6 rounded-full inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
            >
              <ThumbsUp class="w-4 h-4" />
              Approve
            </button>

            <!-- Red Reject Button -->
            <button 
              @click="openConfirmModal('rejected', false)"
              class="bg-[#E74C3C] hover:bg-[#C0392B] text-white font-bold text-xs h-10 px-6 rounded-full inline-flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
            >
              <Ban class="w-4 h-4" />
              Tolak
            </button>

            <!-- White Kembali Button -->
            <button 
              @click="closeDetailPopup"
              class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-bold text-xs h-10 px-6 rounded-full transition-colors cursor-pointer shadow-sm"
            >
              Kembali
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ============================================================
         Confirmation Modal (Approve / Reject Action Dialog)
         ============================================================ -->
    <Teleport to="body">
      <div 
        v-if="isConfirmModalOpen" 
        class="fixed inset-0 z-[10000] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
        @click="closeConfirmModal"
      >
        <div 
          class="bg-white text-gray-900 rounded-xl shadow-2xl w-full max-w-xl flex flex-col overflow-hidden border border-gray-200 animate-in fade-in zoom-in-95 duration-200"
          @click.stop
        >
          <!-- Header (Always "Konfirmasi Approval" to match mockup) -->
          <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-white shrink-0">
            <h3 class="text-base md:text-lg font-bold text-gray-900">
              Konfirmasi Approval
            </h3>
            <button @click="closeConfirmModal" class="p-1.5 hover:bg-gray-100 rounded-full transition-colors cursor-pointer">
              <X class="w-5 h-5 text-gray-500" />
            </button>
          </div>

          <!-- Body content -->
          <div class="overflow-y-auto max-h-[70vh] p-6 space-y-5">
            <!-- Warning prompt message (Centered) -->
            <div class="flex flex-col items-center justify-center text-center space-y-3 pt-2">
              <!-- Warning icon indicator -->
              <div 
                class="w-14 h-14 rounded-full flex items-center justify-center"
                :class="confirmActionType === 'approved' ? 'bg-[#EBFDF4] text-[#2ECC71]' : 'bg-red-50 text-[#E74C3C]'"
              >
                <AlertTriangle class="w-7 h-7" />
              </div>
              <p 
                class="font-bold text-sm leading-normal max-w-md"
                :class="confirmActionType === 'approved' ? 'text-[#2ECC71]' : 'text-[#E74C3C]'"
              >
                Apakah Anda yakin untuk {{ confirmActionType === 'approved' ? 'meng-approve' : 'menolak' }} {{ confirmAssets.length }} perubahan aset yang Anda pilih?
              </p>
            </div>

            <!-- Vertical Key-Value Table (matches mockup 4 & 5) -->
            <div v-if="confirmAssets.length === 1" class="border border-gray-200 rounded-xl bg-gray-50 divide-y divide-gray-200/60 text-xs px-5 py-1">
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Kode Aset</span>
                <span class="text-gray-900 font-semibold font-mono">{{ confirmAssets[0].asset_code }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Status</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].status_label }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Kode LOT</span>
                <span class="text-gray-900 font-semibold font-mono">{{ confirmAssets[0].unit_details.lot_code }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Kategori</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].category }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Subkategori</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].subcategory }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Merek</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].brand }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Spesifikasi</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].specification }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Lokasi</span>
                <span class="text-gray-900 font-semibold">
                  {{ confirmAssets[0].unit_details.location }}
                  <span v-if="confirmAssets[0].unit_details.floor">, {{ confirmAssets[0].unit_details.floor }}</span>
                  <span v-if="confirmAssets[0].unit_details.room">, {{ confirmAssets[0].unit_details.room }}</span>
                </span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Nomor PO</span>
                <span class="text-gray-900 font-semibold font-mono">{{ confirmAssets[0].unit_details.po_number || '-' }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Tanggal masuk</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].unit_details.date_of_receipt }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Harga</span>
                <span class="text-gray-900 font-semibold">Rp{{ confirmAssets[0].unit_details.price }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Organizer</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].unit_details.organizer }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Vendor</span>
                <span class="text-gray-900 font-semibold">{{ confirmAssets[0].unit_details.vendor }}</span>
              </div>
              <div class="py-2.5 flex justify-between items-center">
                <span class="text-gray-500 font-medium">Pembaruan Terakhir</span>
                <span class="text-gray-900 font-semibold font-mono">{{ confirmAssets[0].requested_at }}</span>
              </div>
            </div>

            <!-- If Bulk: Display simple stack of items -->
            <div v-else class="space-y-4 max-h-[300px] overflow-y-auto">
              <div 
                v-for="asset in confirmAssets" 
                :key="asset.id"
                class="border border-gray-200 rounded-xl p-4 space-y-2 bg-gray-50/30 text-xs"
              >
                <div class="flex justify-between font-bold text-gray-900">
                  <span>{{ asset.asset_code }}</span>
                  <span class="text-[#E74C3C]">{{ asset.status_label }}</span>
                </div>
                <div class="text-gray-500 grid grid-cols-2 gap-1.5">
                  <div><strong>Merek:</strong> {{ asset.brand }}</div>
                  <div><strong>Kategori:</strong> {{ asset.category }}</div>
                  <div class="col-span-2"><strong>Spesifikasi:</strong> {{ asset.specification }}</div>
                </div>
              </div>
            </div>

            <!-- Catatan text area -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Catatan / Alasan (Opsional)</label>
              <textarea
                v-model="confirmNote"
                placeholder="Masukkan catatan persetujuan atau alasan penolakan..."
                rows="3"
                class="w-full text-xs border border-gray-300 rounded-lg bg-white p-3 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
              ></textarea>
            </div>
          </div>

          <!-- Footer Action Buttons -->
          <div class="p-5 flex justify-end gap-3 bg-white shrink-0">
            <button 
              @click="closeConfirmModal"
              class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-bold text-xs h-10 px-6 rounded-full transition-colors cursor-pointer shadow-sm"
            >
              Batal
            </button>
            <button 
              @click="handleConfirmSubmit"
              class="text-white font-bold text-xs h-10 px-6 rounded-full shadow-sm transition-colors cursor-pointer"
              :class="confirmActionType === 'approved' ? 'bg-[#2ECC71] hover:bg-[#27AE60]' : 'bg-[#E74C3C] hover:bg-[#C0392B]'"
            >
              {{ confirmActionType === 'approved' ? 'Konfirmasi Approval' : 'Konfirmasi Penolakan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>
