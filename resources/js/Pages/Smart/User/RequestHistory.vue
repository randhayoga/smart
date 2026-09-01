<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TableSearch from '@/Components/TableSearch.vue';
import RequestHistoryCard from '@/Components/RequestHistoryCard.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "@/Components/ui/dialog";
import { 
  ChevronDown, 
  AlertTriangle, 
  X,
  Calendar
} from 'lucide-vue-next';

// ─────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────
interface RequestItem {
  id: number;
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
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: 'Menunggu approval' | 'Disetujui' | 'Ditolak' | 'Serah Terima' | 'Dipinjam' | 'Selesai' | 'Dibatalkan' | 'Pending' | 'Partial';
  raw_status: 'wait' | 'approve' | 'confirm' | 'handover' | 'borrow' | 'return' | 'success' | 'reject' | 'cancel' | 'pending' | 'partial';
  created_at: string; // format YYYY-MM-DD
  items: RequestItem[];
  approval_by?: string | null;
  approval_at?: string | null;
  confirmation_by?: string | null;
  confirmation_at?: string | null;
  handover_method?: string | null;
  handover_time?: string | null;
  handover_location?: string | null;
  handover_note?: string | null;
}

interface Props {
  user?: any;
  requests?: RequestHistory[];
}

const props = withDefaults(defineProps<Props>(), {
  requests: () => []
});

const requests = ref<RequestHistory[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// ─────────────────────────────────────────────
// State Filters & Search
// ─────────────────────────────────────────────
const searchQuery = ref('');
const filterType = ref('Semua tipe');            // 'Semua tipe' | 'Hanya Permintaan' | 'Hanya Peminjaman'
const filterCategory = ref('Semua kategori');    // 'Semua kategori' | ...
const filterStatus = ref('Semua status');        // 'Semua status' | 'Menunggu approval' | etc.
const filterTimeRange = ref('Semua rentang');    // 'Semua rentang' | 'Hari ini' | '7 hari terakhir' | '30 hari terakhir'

/** Reset all search and filter states */
const clearFilter = () => {
  searchQuery.value = '';
  filterType.value = 'Semua tipe';
  filterCategory.value = 'Semua kategori';
  filterStatus.value = 'Semua status';
  filterTimeRange.value = 'Semua rentang';
};

// Category Options (Unique categories from data)
const categoryOptions = computed(() => {
  const cats = new Set<string>();
  requests.value.forEach(req => {
    req.items.forEach(item => {
      if (item.category) cats.add(item.category);
    });
  });
  return Array.from(cats);
});

// Status Options
const statusOptions = [
  'Menunggu approval',
  'Disetujui',
  'Serah Terima',
  'Dipinjam',
  'Selesai',
  'Ditolak',
  'Dibatalkan',
  'Pending'
];

// Time Range Options
const timeRangeOptions = [
  { label: 'Semua rentang', value: 'Semua rentang' },
  { label: 'Hari ini', value: 'Hari ini' },
  { label: '7 hari terakhir', value: '7 hari terakhir' },
  { label: '30 hari terakhir', value: '30 hari terakhir' },
];

// ─────────────────────────────────────────────
// Filtered Data
// ─────────────────────────────────────────────
const filteredRequests = computed(() => {
  return requests.value.filter(req => {
    // 1. Search Query Match
    const matchesSearch = 
      !searchQuery.value.trim() ||
      req.number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      req.items.some(item => 
        item.brand.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.subcategory.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.spec.toLowerCase().includes(searchQuery.value.toLowerCase())
      );

    // 2. Type Match
    let matchesType = true;
    if (filterType.value === 'Hanya Permintaan') {
      matchesType = req.type === 'permintaan';
    } else if (filterType.value === 'Hanya Peminjaman') {
      matchesType = req.type === 'peminjaman';
    }

    // 3. Category Match
    const matchesCategory = filterCategory.value === 'Semua kategori' || 
      req.items.some(item => item.category === filterCategory.value);

    // 4. Status Match
    const matchesStatus = filterStatus.value === 'Semua status' || req.status === filterStatus.value;

    // 5. Time Range Match
    let matchesTime = true;
    if (filterTimeRange.value !== 'Semua rentang') {
      const reqDate = new Date(req.created_at);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (filterTimeRange.value === 'Hari ini') {
        const reqDateStr = req.created_at;
        const todayStr = today.toISOString().split('T')[0];
        matchesTime = reqDateStr === todayStr;
      } else if (filterTimeRange.value === '7 hari terakhir') {
        const diffTime = Math.abs(today.getTime() - reqDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        matchesTime = diffDays <= 7;
      } else if (filterTimeRange.value === '30 hari terakhir') {
        const diffTime = Math.abs(today.getTime() - reqDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        matchesTime = diffDays <= 30;
      }
    }

    return matchesSearch && matchesType && matchesCategory && matchesStatus && matchesTime;
  });
});

// ─────────────────────────────────────────────
// Modal Pembatalan State
// ─────────────────────────────────────────────
const isCancelModalOpen = ref(false);
const activeRequestToCancel = ref<RequestHistory | null>(null);
const cancelNote = ref('');
const isSubmitting = ref(false);

const openCancelModal = (req: RequestHistory) => {
  activeRequestToCancel.value = req;
  isCancelModalOpen.value = true;
};

const closeCancelModal = () => {
  if (isSubmitting.value) return;
  isCancelModalOpen.value = false;
  setTimeout(() => {
    activeRequestToCancel.value = null;
    cancelNote.value = '';
  }, 200);
};

const handleConfirmCancel = () => {
  if (!activeRequestToCancel.value || isSubmitting.value) return;

  router.post(route('smart.history.cancel', activeRequestToCancel.value.id), {
    note: cancelNote.value
  }, {
    onBefore: () => {
      isSubmitting.value = true;
    },
    onSuccess: () => {
      closeCancelModal();
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>

<template>
  <AppLayout title="Riwayat Permintaan">
    <div class="space-y-6">
      <div>
        <h1 class="text-lg font-bold text-gray-900 leading-none mb-5">Riwayat permintaan dan peminjaman</h1>
        
        <!-- Filter & Search Section -->
        <div class="space-y-4 mb-5">
          <!-- Search Row (Top) -->
          <div class="space-y-1.5 w-lg">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">Pencarian</label>
            <TableSearch 
              v-model="searchQuery" 
              placeholder="Cari barang atau no. permintaan..." 
              bg-class="bg-white"
            />
          </div>

          <!-- Filter Row (Below) -->
          <div class="space-y-1.5 w-full">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter & Urutkan</label>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <!-- Filter Tipe -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterType === 'Semua tipe' ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ filterType }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="filterType = 'Semua tipe'">Semua tipe</DropdownMenuItem>
                    <DropdownMenuItem @select="filterType = 'Hanya Permintaan'">Hanya Permintaan</DropdownMenuItem>
                    <DropdownMenuItem @select="filterType = 'Hanya Peminjaman'">Hanya Peminjaman</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Filter Kategori -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterCategory === 'Semua kategori' ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ filterCategory }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="filterCategory = 'Semua kategori'">Semua kategori</DropdownMenuItem>
                    <DropdownMenuItem 
                      v-for="cat in categoryOptions" 
                      :key="cat" 
                      @select="filterCategory = cat"
                    >
                      {{ cat }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Filter Status -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterStatus === 'Semua status' ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ filterStatus }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="filterStatus = 'Semua status'">Semua status</DropdownMenuItem>
                    <DropdownMenuItem 
                      v-for="status in statusOptions" 
                      :key="status" 
                      @select="filterStatus = status"
                    >
                      {{ status }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Filter Rentang Waktu -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full sm:w-[11.25rem] md:w-[12rem] justify-between rounded-[0.875rem] font-normal bg-white', filterTimeRange === 'Semua rentang' ? 'text-muted-foreground' : 'text-foreground']">
                      <div class="flex items-center gap-1.5 truncate">
                        <Calendar class="w-3.5 h-3.5 opacity-50 shrink-0" />
                        <span class="truncate">{{ filterTimeRange }}</span>
                      </div>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[12rem] rounded-[0.875rem]" align="start" :side-offset="4">
                    <DropdownMenuItem 
                      v-for="opt in timeRangeOptions" 
                      :key="opt.value" 
                      @select="filterTimeRange = opt.value"
                    >
                      {{ opt.label }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Clear Filter Button -->
                <Button variant="destructive" @click="clearFilter" class="hover:opacity-70 rounded-[0.875rem] px-0 sm:px-5 font-semibold text-white w-9 h-9 sm:w-auto sm:h-auto flex items-center justify-center shrink-0">
                  <X class="w-4 h-4 sm:hidden" />
                  <span class="hidden sm:inline">Hapus filter</span>
                </Button>
              </div>
            </div> 
        </div>

        <p class="text-xs text-muted-foreground font-medium mb-3">Hasil Pencarian dan Filter:</p>

        <!-- Grid / ScrollArea -->
        <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-25rem)] sm:h-[calc(100vh-21.5rem)]">
          <div class="p-2.5 sm:p-5">
            <!-- Empty State -->
            <div 
              v-if="filteredRequests.length === 0" 
              class="p-12 text-center"
            >
              <AlertTriangle class="w-10 h-10 text-muted-foreground/60 mx-auto mb-3" />
              <p class="text-sm text-muted-foreground font-medium">Riwayat permintaan kosong atau tidak ada riwayat permintaan yang cocok dengan filter atau kata kunci pencarian.</p>
            </div>

            <!-- Request Cards -->
            <div v-else class="space-y-4">
              <RequestHistoryCard
                v-for="req in filteredRequests"
                :key="req.id"
                :request="req"
                @cancel="openCancelModal"
              />
            </div>
          </div>
        </ScrollArea>
      </div>
    </div>

    <!-- Modal Pembatalan Permintaan -->
    <Dialog :open="isCancelModalOpen" @update:open="val => { if (!isSubmitting) isCancelModalOpen = val }">
      <DialogContent class="sm:max-w-[50rem] rounded-[0.875rem] bg-card shadow-2xl p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pt-3 pb-2 px-4 sm:px-6 border-b border-border">
          <div>
            <DialogTitle class="text-lg font-bold text-foreground">Pembatalan Permintaan/Peminjaman</DialogTitle>
            <DialogDescription class="sr-only">
              Konfirmasi untuk membatalkan permintaan atau peminjaman barang.
            </DialogDescription>
          </div>
          <button :disabled="isSubmitting" @click="closeCancelModal" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>
        
        <div v-if="activeRequestToCancel">
          <!-- Modal Body -->
          <div class="px-4 sm:px-6 py-4 overflow-y-auto max-h-[70vh] space-y-5">
            <!-- Alert & Detail Summary -->
            <div class="p-4 rounded-[0.875rem] bg-destructive/5 border border-destructive/20 space-y-2">
              <p class="font-bold text-destructive text-sm sm:text-base">
                Apakah Anda yakin untuk membatalkan permintaan/peminjaman ini?
              </p>
              <div class="space-y-1 text-sm text-foreground">
                <p class="font-extrabold text-base text-foreground">
                  {{ activeRequestToCancel.number }}
                </p>
                <p class="text-foreground">
                  <span class="text-muted-foreground">Pemanfaatan:</span> 
                  <span class="font-medium">
                    {{ activeRequestToCancel.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ activeRequestToCancel.pemanfaatanDetail }})
                  </span>
                </p>
                <p v-if="activeRequestToCancel.type === 'peminjaman' && activeRequestToCancel.durationStart" class="text-foreground">
                  <span class="text-muted-foreground">Durasi:</span>
                  <span class="font-medium">
                    {{ activeRequestToCancel.durationStart }} s.d. {{ activeRequestToCancel.durationEnd }} ({{ activeRequestToCancel.durationDays }} hari, {{ activeRequestToCancel.durationHours || 0 }} jam)
                  </span>
                </p>
              </div>
            </div>

            <!-- Item List -->
            <div class="space-y-2">
              <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Daftar barang:</h4>
              
              <ScrollArea class="max-h-[14rem] border border-border rounded-[0.875rem] bg-background">
                <div class="p-3 space-y-2.5">
                  <div 
                    v-for="item in activeRequestToCancel.items" 
                    :key="item.id"
                    class="flex gap-3.5 p-3 border border-border rounded-[0.875rem] items-center bg-card"
                  >
                    <!-- Thumbnail Barang -->
                    <div class="w-12 h-12 rounded-[0.625rem] bg-muted border border-border overflow-hidden shrink-0 flex items-center justify-center">
                      <img 
                        v-if="item.imageUrl" 
                        :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/media/' + item.imageUrl" 
                        class="w-full h-full object-cover" 
                      />
                      <div v-else class="text-xs font-black text-muted-foreground/50 select-none">
                        {{ item.subcategory.substring(0, 3).toUpperCase() }}
                      </div>
                    </div>

                    <!-- Deskripsi Detail Barang -->
                    <div class="min-w-0 flex-grow space-y-0.5">
                      <h5 class="text-sm font-bold text-foreground truncate">
                        {{ item.brand !== '-' ? item.brand : '' }} {{ item.name && item.name !== 'Tidak Spesifik' ? item.name : '' }} {{ item.spec }}
                      </h5>
                      <p class="text-xs text-muted-foreground">
                        Kategori: {{ item.category }} ({{ item.subcategory }})
                      </p>
                      <p class="text-xs font-semibold text-primary">
                        Jumlah diminta: {{ item.quantity }} {{ item.uom || 'satuan' }}
                      </p>
                    </div>
                  </div>
                </div>
              </ScrollArea>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="py-4 px-4 sm:px-6 border-t border-border flex items-center justify-end gap-3">
            <Button 
              @click="closeCancelModal"
              variant="white"
              size="lg"
              :disabled="isSubmitting"
            >
              Tidak
            </Button>
            <Button 
              @click="handleConfirmCancel"
              variant="destructive"
              size="lg"
              :disabled="isSubmitting"
              class="flex items-center gap-2"
            >
              <span v-if="isSubmitting">Memproses...</span>
              <span v-else>Iya, Batalkan</span>
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

