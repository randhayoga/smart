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
  AlertTriangle,
  ArrowRight
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

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

// Filtered data
const filteredRequests = computed(() => {
  let list = [...requests.value];

  if (searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(req => 
      req.number.toLowerCase().includes(q) ||
      req.requester.toLowerCase().includes(q) ||
      req.pemanfaatanDetail.toLowerCase().includes(q)
    );
  }

  if (typeFilter.value !== 'Semua tipe') {
    const type = typeFilter.value === 'Peminjaman' ? 'peminjaman' : 'permintaan';
    list = list.filter(req => req.type === type);
  }

  if (utilizationFilter.value !== 'Semua pemanfaatan') {
    const util = utilizationFilter.value === 'Corporate' ? 'corporate' : 'project';
    list = list.filter(req => req.pemanfaatan === util);
  }

  list.sort((a, b) => {
    let valA: any = a[sortBy.value as keyof RequestHistory] ?? '';
    let valB: any = b[sortBy.value as keyof RequestHistory] ?? '';

    if (sortBy.value === 'number') {
      valA = a.number;
      valB = b.number;
    } else if (sortBy.value === 'requester') {
      valA = a.requester;
      valB = b.requester;
    } else if (sortBy.value === 'pemanfaatan') {
      valA = a.pemanfaatanDetail;
      valB = b.pemanfaatanDetail;
    } else if (sortBy.value === 'created_at') {
      valA = a.created_at;
      valB = b.created_at;
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
  return Math.ceil(filteredRequests.value.length / limit) || 1;
});

const paginatedRequests = computed(() => {
  const list = filteredRequests.value;
  if (rowsPerPage.value === 'Semua baris') return list;
  const limit = parseInt(rowsPerPage.value, 10);
  const start = (currentPage.value - 1) * limit;
  return list.slice(start, start + limit);
});

// Selection States
const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
  const currentList = paginatedRequests.value;
  if (currentList.length === 0) return false;
  return currentList.every(item => selectedIds.value.includes(item.id));
});

const toggleSelectAll = () => {
  const currentList = paginatedRequests.value;
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
      selectedIds.value = [];
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
      <span class="text-xs font-semibold text-gray-500 block">Filter</span>
      
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex flex-wrap gap-3 items-center w-full sm:w-auto">
          <!-- Search input -->
          <div class="relative w-full sm:w-[220px]">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari Permintaan..."
              class="w-full h-10 pl-4 pr-10 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
            />
            <div class="absolute right-3 top-3 text-gray-400">
              <Search class="w-4 h-4" />
            </div>
          </div>

          <!-- Tipe Filter -->
          <div class="w-full sm:w-[150px]">
            <select 
              v-model="typeFilter" 
              class="w-full h-10 px-3 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer shadow-sm"
            >
              <option value="Semua tipe">Semua tipe</option>
              <option value="Peminjaman">Peminjaman</option>
              <option value="Permintaan">Permintaan</option>
            </select>
          </div>

          <!-- Pemanfaatan Filter -->
          <div class="w-full sm:w-[150px]">
            <select 
              v-model="utilizationFilter" 
              class="w-full h-10 px-3 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer shadow-sm"
            >
              <option value="Semua pemanfaatan">Semua pemanfaatan</option>
              <option value="Corporate">Corporate</option>
              <option value="Project">Project</option>
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
            @click="openConfirmModal('approve', true)"
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
            @click="openConfirmModal('reject', true)"
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
            <th @click="toggleSort('number')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                No. Permintaan
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('type')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Tipe
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('requester')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Pemohon
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('pemanfaatan')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Pemanfaatan
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th @click="toggleSort('created_at')" class="py-4 px-4 cursor-pointer select-none">
              <div class="flex items-center gap-1">
                Tanggal Masuk
                <span class="text-gray-400 font-normal">↑↓</span>
              </div>
            </th>
            <th class="py-4 px-4">Status</th>
            <th class="py-4 px-4 text-center w-28">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-150">
          <tr 
            v-for="req in paginatedRequests" 
            :key="req.id"
            class="hover:bg-gray-50/50 transition-colors"
          >
            <td class="py-4 px-4 text-center">
              <div class="flex items-center justify-center">
                <!-- Circular Custom Checkbox -->
                <button 
                  @click="toggleSelectRow(req.id)" 
                  class="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center transition-all cursor-pointer"
                  :class="selectedIds.includes(req.id) ? 'bg-[#2ECC71] border-transparent text-white' : 'bg-white text-transparent'"
                >
                  <span class="text-[10px] font-bold">✓</span>
                </button>
              </div>
            </td>
            <td class="py-4 px-4 font-mono font-medium text-gray-900">{{ req.number }}</td>
            <td class="py-4 px-4 text-gray-900 capitalize">{{ req.type }}</td>
            <td class="py-4 px-4 text-gray-900">{{ req.requester }}</td>
            <td class="py-4 px-4 text-gray-900">
              <span class="font-semibold capitalize">{{ req.pemanfaatan }}:</span> {{ req.pemanfaatanDetail }}
            </td>
            <td class="py-4 px-4 text-gray-900">{{ req.created_at }}</td>
            <td class="py-4 px-4 text-gray-900 font-medium">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                {{ req.status }}
              </span>
            </td>
            <td class="py-4 px-4">
              <div class="flex items-center justify-center gap-2">
                <!-- Cyan Circle Action Button -->
                <button 
                  @click="openDetailPopup(req)"
                  class="w-8 h-8 rounded-full bg-[#00BCD4] hover:bg-[#06B6D4] text-white flex items-center justify-center transition-colors shadow-sm cursor-pointer"
                  title="Detail Permintaan"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="filteredRequests.length === 0">
            <td colspan="8" class="py-12 text-center text-gray-500">
              <AlertTriangle class="w-8 h-8 mx-auto mb-2 text-gray-400" />
              <p class="font-medium">Tidak ada data permintaan menunggu approval.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination & Count Display ── -->
    <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4 px-2 text-xs text-gray-500">
      <div>
        {{ selectedIds.length }} dari {{ filteredRequests.length }} baris dipilih
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
                        :src="item.imageUrl" 
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
                        <span v-if="item.stockQuantity !== undefined" class="text-gray-500">Stok tersedia: {{ item.stockQuantity }}</span>
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
                      <th class="py-3 px-4 w-28 text-center">Durasi ↑↓</th>
                      <th class="py-3 px-4">Catatan ↑↓</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-150">
                    <tr 
                      v-for="(lc, idx) in filteredLifecycles" 
                      :key="idx"
                      class="hover:bg-gray-50/50 transition-colors"
                    >
                      <td class="py-3 px-4 font-medium text-gray-900">{{ lc.waktu }}</td>
                      <td class="py-3 px-4 text-gray-900 font-medium">{{ lc.aksi_status }}</td>
                      <td class="py-3 px-4 text-gray-900">{{ lc.aktor }}</td>
                      <td class="py-3 px-4 text-center text-gray-900">{{ lc.durasi }}</td>
                      <td class="py-3 px-4 text-gray-700 max-w-sm truncate">{{ lc.catatan }}</td>
                    </tr>
                    
                    <tr v-if="filteredLifecycles.length === 0">
                      <td colspan="5" class="py-6 text-center text-gray-500 italic">
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
                <X class="w-5 h-5 text-gray-500" />
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
