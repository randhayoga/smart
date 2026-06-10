<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ChevronDown, ChevronUp, Trash2 } from 'lucide-vue-next';

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
  status: 'Menunggu approval' | 'Disetujui' | 'Ditolak' | 'Serah Terima' | 'Dipinjam' | 'Selesai' | 'Dibatalkan' | 'Pending';
  raw_status: 'wait' | 'approve' | 'confirm' | 'handover' | 'borrow' | 'return' | 'success' | 'reject' | 'cancel' | 'pending';
  created_at: string;
  items: RequestItem[];
}

const props = defineProps<{
  request: RequestHistory;
}>();

const emit = defineEmits<{
  (e: 'cancel', request: RequestHistory): void;
}>();

// ─────────────────────────────────────────────
// State & Collapsible Logic
// ─────────────────────────────────────────────
const isExpanded = ref(false);

const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value;
};

// Formatting Helper
const formatDate = (dateStr: string) => {
  const parts = dateStr.split(/[-/]/);
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}/${parts[1]}/${parts[0]}`; // DD/MM/YYYY
};

// ─────────────────────────────────────────────
// Status Styling Helper
// ─────────────────────────────────────────────
const getStatusClasses = (status: string) => {
  switch (status) {
    case 'Menunggu approval':
      return 'bg-orange-500 text-white border-transparent';
    case 'Disetujui':
      return 'bg-[#4B8DF8] text-white border-transparent'; // blue-ish
    case 'Selesai':
      return 'bg-emerald-600 text-white border-transparent';
    case 'Serah Terima':
      return 'bg-indigo-500 text-white border-transparent';
    case 'Dipinjam':
      return 'bg-[#EF4444] text-white border-transparent'; // red-pinkish for "Sedang dipinjam"
    case 'Ditolak':
    case 'Dibatalkan':
    case 'Pending':
      return 'bg-zinc-500 text-white border-transparent'; // gray
    default:
      return 'bg-muted text-muted-foreground border-border';
  }
};
</script>

<template>
  <div class="bg-card border border-border rounded-[14px] p-5 hover:border-primary/30 transition-all shadow-sm relative overflow-hidden">
    
    <!-- Badge Status & Tanggal Pembuatan -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <span 
          class="text-xs font-bold px-3 py-1 rounded-[14px] border"
          :class="getStatusClasses(request.status)"
        >
          {{ request.status === 'Disetujui' ? 'Di-approve' : (request.status === 'Dipinjam' ? 'Sedang dipinjam' : request.status) }}
        </span>
        <!-- Tenggat Pengembalian -->
        <span 
          v-if="request.status === 'Dipinjam' && request.durationEnd" 
          class="text-xs font-bold px-3 py-1 rounded-[14px] border border-[#EF4444] text-[#EF4444] bg-[#EF4444]/5"
        >
          Tenggat pengembalian: {{ request.durationEnd }}
        </span>
      </div>
      <span class="text-xs text-muted-foreground font-medium">
        Dibuat: {{ formatDate(request.created_at) }}
      </span>
    </div>

    <div class="flex flex-col md:flex-row gap-5 items-start">
      <!-- Gambar Thumbnail Grid (2x2) atau Single -->
      <div class="shrink-0">
        <!-- Jika item lebih dari 1, tampilkan grid 2x2 -->
        <div 
          v-if="request.items.length > 1" 
          class="grid grid-cols-2 gap-1 w-20 h-20 rounded-[14px] overflow-hidden bg-muted border border-border p-1"
        >
          <div 
            v-for="(item, idx) in request.items.slice(0, 4)" 
            :key="item.id"
            class="bg-background rounded-[8px] overflow-hidden flex items-center justify-center border border-border/40"
          >
            <img 
              v-if="item.imageUrl" 
              :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/storage/' + item.imageUrl" 
              class="w-full h-full object-cover" 
            />
            <div v-else class="text-[9px] font-bold text-muted-foreground/50 select-none">
              {{ item.subcategory.substring(0, 2).toUpperCase() }}
            </div>
          </div>
        </div>
        
        <!-- Jika hanya 1 item -->
        <div 
          v-else 
          class="w-20 h-20 rounded-[14px] bg-muted border border-border overflow-hidden flex items-center justify-center"
        >
          <img 
            v-if="request.items[0]?.imageUrl" 
            :src="request.items[0].imageUrl.startsWith('http') || request.items[0].imageUrl.startsWith('/') ? request.items[0].imageUrl : '/storage/' + request.items[0].imageUrl" 
            class="w-full h-full object-cover" 
          />
          <div v-else class="text-xs font-bold text-muted-foreground/60 select-none">
            {{ request.items[0]?.subcategory.substring(0, 3).toUpperCase() }}
          </div>
        </div>
      </div>

      <!-- Deskripsi/Info Permintaan -->
      <div class="flex-grow space-y-1 min-w-0">
        <h2 class="text-base font-bold text-foreground truncate">
          {{ request.number }}
        </h2>
        
        <!-- Pemanfaatan -->
        <p class="text-sm text-foreground">
          <span class="text-muted-foreground">Pemanfaatan:</span> 
          <span class="font-medium">
            {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
          </span>
        </p>

        <!-- Durasi (Hanya untuk Peminjaman/Aset) -->
        <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-sm text-foreground">
          <span class="text-muted-foreground">Durasi:</span>
          <span class="font-medium">
            <template v-if="request.durationEnd">
              {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours }} jam)
            </template>
            <template v-else>
              {{ request.durationStart }} s.d. - (Tanpa Tenggat Waktu)
            </template>
          </span>
        </p>

        <!-- Barang Collapsible Toggle -->
        <div class="pt-1">
          <button
            @click="toggleExpanded"
            class="text-xs font-bold text-primary hover:opacity-85 flex items-center gap-1 transition-all"
          >
            <span>{{ isExpanded ? 'Sembunyikan Barang' : 'Lihat Barang' }}</span>
            <ChevronUp v-if="isExpanded" class="w-3.5 h-3.5" />
            <ChevronDown v-else class="w-3.5 h-3.5" />
          </button>

          <!-- Collapsible Content -->
          <div 
            v-if="isExpanded" 
            class="mt-3 bg-muted/40 border border-border p-4 rounded-[14px] space-y-1.5 animate-in fade-in slide-in-from-top-1 duration-200"
          >
            <p class="text-xs font-bold text-foreground">Barang:</p>
            <ul class="space-y-1 pl-1">
              <li 
                v-for="item in request.items" 
                :key="item.id" 
                class="text-xs text-foreground font-medium"
              >
                • {{ item.subcategory }}: {{ item.brand }} {{ item.spec }} ({{ item.quantity }} satuan)
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Tombol Aksi di Kaki Card -->
    <div class="flex items-center justify-end gap-4 mt-4 pt-1">
      <Link
        :href="route('smart.history.show', request.id)"
        class="text-xs md:text-sm font-semibold text-primary hover:underline transition-colors"
      >
        Lihat Detail
      </Link>

      <!-- Tampilkan Batalkan Permintaan hanya jika status Menunggu approval -->
      <Button
        v-if="request.status === 'Menunggu approval'"
        variant="destructive"
        class="h-9 px-4 rounded-lg text-xs font-bold bg-[#D9534F] hover:bg-[#C9302C] text-white shadow-sm flex items-center gap-1.5"
        @click="emit('cancel', request)"
      >
        <Trash2 class="w-3.5 h-3.5" />
        Batalkan Permintaan
      </Button>

      <!-- Tampilkan Atur Serah Terima jika status Serah Terima (confirm) -->
      <Link
        v-if="request.raw_status === 'confirm'"
        :href="route('smart.history.show', request.id)"
        class="h-9 px-5 rounded-lg text-xs font-bold bg-[#6366F1] hover:bg-[#5558EB] text-white shadow-sm flex items-center justify-center transition-colors"
      >
        Atur Serah Terima
      </Link>

      <!-- Tampilkan Atur Pengembalian jika status Dipinjam (borrow) -->
      <Link
        v-if="request.raw_status === 'borrow'"
        :href="route('smart.history.show', request.id)"
        class="h-9 px-5 rounded-lg text-xs font-bold bg-[#6366F1] hover:bg-[#5558EB] text-white shadow-sm flex items-center justify-center transition-colors"
      >
        Atur Pengembalian
      </Link>
    </div>

  </div>
</template>

<style scoped>
.animate-in {
  animation-duration: 200ms;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
