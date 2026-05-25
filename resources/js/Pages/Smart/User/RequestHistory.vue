<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
} from '@/Components/ui/breadcrumb';
import { 
  Search, 
  Calendar, 
  AlertTriangle, 
  X
} from 'lucide-vue-next';
import RequestHistoryCard from '@/Components/RequestHistoryCard.vue';

// ─────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────
interface RequestItem {
  id: number;
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
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: 'Menunggu approval' | 'Disetujui' | 'Ditolak' | 'Serah Terima' | 'Dipinjam' | 'Selesai' | 'Dibatalkan';
  created_at: string; // format YYYY-MM-DD
  items: RequestItem[];
}

import { router } from '@inertiajs/vue3';

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
const filterType = ref('semua');       // 'semua' | 'permintaan' | 'peminjaman'
const filterCategory = ref('semua');   // 'semua' | 'Elektronik' | 'Alat Tulis'
const filterStatus = ref('semua');     // 'semua' | 'Menunggu approval' | etc.
const filterTimeRange = ref('semua');   // 'semua' | 'hari-ini' | '7-hari' | '30-hari'

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

// ─────────────────────────────────────────────
// Filtered Data
// ─────────────────────────────────────────────
const filteredRequests = computed(() => {
  return requests.value.filter(req => {
    // 1. Search Query Match
    const matchesSearch = 
      req.number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      req.items.some(item => 
        item.brand.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.subcategory.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        item.spec.toLowerCase().includes(searchQuery.value.toLowerCase())
      );

    // 2. Type Match
    const matchesType = filterType.value === 'semua' || req.type === filterType.value;

    // 3. Category Match
    const matchesCategory = filterCategory.value === 'semua' || 
      req.items.some(item => item.category === filterCategory.value);

    // 4. Status Match
    const matchesStatus = filterStatus.value === 'semua' || req.status === filterStatus.value;

    // 5. Time Range Match
    let matchesTime = true;
    if (filterTimeRange.value !== 'semua') {
      const reqDate = new Date(req.created_at);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (filterTimeRange.value === 'hari-ini') {
        const reqDateStr = req.created_at;
        const todayStr = today.toISOString().split('T')[0];
        matchesTime = reqDateStr === todayStr;
      } else if (filterTimeRange.value === '7-hari') {
        const diffTime = Math.abs(today.getTime() - reqDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        matchesTime = diffDays <= 7;
      } else if (filterTimeRange.value === '30-hari') {
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

const openCancelModal = (req: RequestHistory) => {
  activeRequestToCancel.value = req;
  isCancelModalOpen.value = true;
};

const closeCancelModal = () => {
  isCancelModalOpen.value = false;
  setTimeout(() => {
    activeRequestToCancel.value = null;
    cancelNote.value = '';
  }, 200);
};

const handleConfirmCancel = () => {
  if (!activeRequestToCancel.value) return;

  router.post(route('smart.history.cancel', activeRequestToCancel.value.id), {
    note: cancelNote.value
  }, {
    onSuccess: () => {
      closeCancelModal();
    }
  });
};
</script>

<template>
  <AppLayout title="Riwayat Permintaan">

    <!-- ── Breadcrumb ─────────────────────────────── -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <span class="text-muted-foreground">Riwayat Permintaan</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- ── Judul Halaman ──────────────────────────── -->
    <div class="mb-4">
      <h1 class="text-2xl font-bold text-foreground">Riwayat permintaan dan peminjaman</h1>
    </div>

    <!-- ── Input Pencarian (Rounded Capsule dengan Icon Search) ── -->
    <div class="relative w-full max-w-xl mb-6">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Cari nama barang yang Anda minta atau pinjam..."
        class="w-full h-11 pl-4 pr-12 text-sm border border-input rounded-full bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-sm"
      />
      <div class="absolute right-1 top-1 h-9 w-9 bg-primary/10 rounded-full flex items-center justify-center text-primary">
        <Search class="w-4 h-4" />
      </div>
    </div>

    <!-- ── Filter Controls ────────────────────────── -->
    <div class="space-y-2 mb-6">
      <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">Filter</span>
      
      <div class="flex flex-wrap gap-3 items-center">
        <!-- Filter Tipe (Permintaan / Peminjaman) -->
        <div class="w-full sm:w-[220px]">
          <Select v-model="filterType">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
              <SelectValue placeholder="Tipe Permintaan" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Permintaan &amp; Peminjaman</SelectItem>
              <SelectItem value="permintaan">Hanya Permintaan (Habis Pakai)</SelectItem>
              <SelectItem value="peminjaman">Hanya Peminjaman (Aset)</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Filter Kategori -->
        <div class="w-full sm:w-[180px]">
          <Select v-model="filterCategory">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
              <SelectValue placeholder="Kategori: Semua" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Kategori: Semua</SelectItem>
              <SelectItem 
                v-for="cat in categoryOptions" 
                :key="cat" 
                :value="cat"
              >
                Kategori: {{ cat }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Filter Status -->
        <div class="w-full sm:w-[180px]">
          <Select v-model="filterStatus">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm">
              <SelectValue placeholder="Status: Semua" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Status: Semua</SelectItem>
              <SelectItem value="Menunggu approval">Menunggu approval</SelectItem>
              <SelectItem value="Disetujui">Disetujui</SelectItem>
              <SelectItem value="Serah Terima">Serah Terima</SelectItem>
              <SelectItem value="Dipinjam">Dipinjam</SelectItem>
              <SelectItem value="Selesai">Selesai</SelectItem>
              <SelectItem value="Ditolak">Ditolak</SelectItem>
              <SelectItem value="Dibatalkan">Dibatalkan</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Filter Rentang Waktu -->
        <div class="w-full sm:w-[220px]">
          <Select v-model="filterTimeRange">
            <SelectTrigger class="w-full rounded-[14px] h-10 text-sm flex items-center gap-2">
              <Calendar class="w-4 h-4 text-muted-foreground shrink-0" />
              <SelectValue placeholder="Semua Rentang Waktu" />
            </SelectTrigger>
            <SelectContent class="rounded-[14px]">
              <SelectItem value="semua">Semua Rentang Waktu</SelectItem>
              <SelectItem value="hari-ini">Hari Ini</SelectItem>
              <SelectItem value="7-hari">7 Hari Terakhir</SelectItem>
              <SelectItem value="30-hari">30 Hari Terakhir</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>

    <!-- ── Hasil Pencarian Title ──────────────────── -->
    <div class="mb-4">
      <span class="text-sm font-semibold text-foreground">Hasil Pencarian dan Filter:</span>
    </div>

    <!-- ── Daftar Request Cards ───────────────────── -->
    <div class="space-y-4">
      <!-- Empty State -->
      <div 
        v-if="filteredRequests.length === 0" 
        class="bg-card border border-border rounded-[14px] p-12 text-center"
      >
        <AlertTriangle class="w-10 h-10 text-muted-foreground/60 mx-auto mb-3" />
        <p class="text-sm text-muted-foreground font-medium">Tidak ada riwayat permintaan yang cocok dengan filter atau kata kunci pencarian.</p>
      </div>

      <!-- Request Card -->
      <RequestHistoryCard
        v-for="req in filteredRequests"
        :key="req.id"
        :request="req"
        @cancel="openCancelModal"
      />
    </div>

    <!-- ============================================================
         Modal Pembatalan Permintaan (Teleport & Backdrop)
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
          v-if="isCancelModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
        >
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[800px] flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-5 border-b border-border bg-card shrink-0">
              <h3 class="text-base md:text-lg font-extrabold text-foreground">Pembatalan Permintaan/Peminjaman</h3>
              <button @click="closeCancelModal" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>
            
            <!-- Body -->
            <div class="overflow-y-auto max-h-[85vh]">
              <!-- Bagian Atas: Konfirmasi & Metadata Detail + Button Aksi (Side-by-side) -->
              <div class="p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-1.5 text-sm text-foreground flex-grow">
                  <p class="font-bold text-[#D9534F] text-base md:text-lg">
                    Apakah Anda yakin untuk membatalkan permintaan/peminjaman ini?
                  </p>
                  <p class="font-extrabold text-lg text-foreground">
                    {{ activeRequestToCancel?.number }}
                  </p>
                  <p class="text-foreground">
                    <span class="text-muted-foreground">Pemanfaatan:</span> 
                    <span class="font-medium">
                      {{ activeRequestToCancel?.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ activeRequestToCancel?.pemanfaatanDetail }})
                    </span>
                  </p>
                  <p v-if="activeRequestToCancel?.type === 'peminjaman' && activeRequestToCancel?.durationStart" class="text-foreground">
                    <span class="text-muted-foreground">Durasi:</span>
                    <span class="font-medium">
                      {{ activeRequestToCancel?.durationStart }} s.d. {{ activeRequestToCancel?.durationEnd }} ({{ activeRequestToCancel?.durationDays }} hari, {{ activeRequestToCancel?.durationHours || 0 }} jam)
                    </span>
                  </p>
                </div>

                <!-- Tombol Aksi di Sebelah Kanan Info -->
                <div class="flex items-center gap-2.5 shrink-0 self-end md:self-center">
                  <Button 
                    variant="outline"
                    @click="closeCancelModal"
                    class="rounded-full h-10 px-6 font-bold text-sm border-input hover:bg-muted transition-colors"
                  >
                    Tidak
                  </Button>
                  <Button 
                    @click="handleConfirmCancel"
                    class="rounded-full h-10 px-6 font-bold text-sm bg-[#D9534F] hover:bg-[#C9302C] text-white shadow-sm transition-colors"
                  >
                    Iya
                  </Button>
                </div>
              </div>

              <!-- Divider line -->
              <div class="border-t border-border/80 mx-6"></div>

              <!-- Bagian Bawah: Daftar Barang -->
              <div class="p-6 space-y-4">
                <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Daftar barang:</h4>
                
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                  <div 
                    v-for="item in activeRequestToCancel?.items" 
                    :key="item.id"
                    class="flex gap-4 p-4 border border-border/70 hover:border-primary/20 transition-all rounded-[14px] items-center bg-muted/10"
                  >
                    <!-- Thumbnail Barang -->
                    <div class="w-14 h-14 rounded-[10px] bg-muted border border-border overflow-hidden shrink-0 flex items-center justify-center">
                      <img 
                        v-if="item.imageUrl" 
                        :src="item.imageUrl" 
                        class="w-full h-full object-cover" 
                      />
                      <div v-else class="text-xs font-black text-muted-foreground/50 select-none">
                        {{ item.subcategory.substring(0, 3).toUpperCase() }}
                      </div>
                    </div>

                    <!-- Deskripsi Detail Barang -->
                    <div class="min-w-0 flex-grow space-y-0.5">
                      <h5 class="text-sm font-bold text-foreground truncate">
                        {{ item.brand }} {{ item.spec }}
                      </h5>
                      <p class="text-xs text-muted-foreground">
                        Kategori: {{ item.category }} ({{ item.subcategory }})
                      </p>
                      
                      <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold pt-0.5">
                        <span class="text-primary">
                          Jumlah diminta: {{ item.quantity }} satuan
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<style scoped>
.animate-in {
  animation-duration: 200ms;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
