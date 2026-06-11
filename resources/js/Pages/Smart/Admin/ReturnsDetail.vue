<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ChevronUp,
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info,
  X,
  AlertCircle,
  Check
} from 'lucide-vue-next';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface RequestItem {
  id: number;
  brand: string;
  category: string;
  subcategory: string;
  quantity: number;
  assets: string[];
  imageUrl?: string | null;
  is_consumable?: boolean;
}

interface RequestDetail {
  id: number;
  number: string;
  requester: string;
  approver: string;
  approval_by?: string;
  confirmation_by?: string;
  return_confirmed_by?: string;
  createdAt: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: string;
  type: 'permintaan' | 'peminjaman';
  items: RequestItem[];
  returnTime: string;
  location: string;
  method: string;
  logs?: any[];
}

interface Props {
  returnId: string | number;
  request: RequestDetail;
  placements?: Record<string, string>;
}

const props = defineProps<Props>();

const items = computed(() => props.request.items);

interface TimelineStep {
  status: string;
  time?: string;
  completed?: boolean;
  rejected?: boolean;
  active?: boolean;
  user?: string;
  note?: string;
  method?: string;
  location?: string;
  info?: string;
}

const timeline = computed((): TimelineStep[] => {
  const r = props.request;
  if (!r) return [];

  const steps = [];
  
  // Step 1: Initial creation
  steps.push({
    status: 'Permintaan dibuat',
    time: r.createdAt,
    completed: true,
  });

  // Step 2: Historical Logs
  if (r.logs && Array.isArray(r.logs)) {
    const sortedLogs = [...r.logs].sort((a, b) => a.id - b.id);
    sortedLogs.forEach(log => {
      if (log.status_to === 'wait') return;

      let statusName = '';
      let completed = true;
      let rejected = false;

      if (log.status_to === 'approve') {
        statusName = 'Di-approve';
      } else if (log.status_to === 'partial') {
        statusName = 'Disetujui sebagian (Partial)';
      } else if (log.status_to === 'confirm') {
        if (log.status_from === 'partial') {
          statusName = 'Alokasi Barang Tambahan Dikonfirmasi';
        } else {
          if (log.note && log.note.includes('diatur oleh pengguna')) {
            statusName = 'Jadwal Serah Terima Diatur';
          } else {
            statusName = 'Dikonfirmasi';
          }
        }
      } else if (log.status_to === 'borrow') {
        statusName = 'Serah Terima Selesai & Dipinjam';
      } else if (log.status_to === 'return') {
        statusName = 'Pengembalian Diajukan';
      } else if (log.status_to === 'success') {
        if (log.status_from === 'return') {
          statusName = 'Pengembalian Selesai';
        } else {
          statusName = 'Serah Terima Selesai';
        }
      } else if (log.status_to === 'reject') {
        statusName = 'Ditolak';
        completed = false;
        rejected = true;
      } else if (log.status_to === 'cancel') {
        statusName = 'Dibatalkan oleh Pengguna';
      } else if (log.status_to === 'pending') {
        if (log.status_from === 'confirm') {
          statusName = 'Serah Terima Sebagian Diterima';
        } else {
          statusName = 'Pending';
        }
      }

      if (statusName) {
        steps.push({
          status: statusName,
          user: log.user || undefined,
          time: log.time,
          completed,
          rejected,
          note: log.note || '',
        });
      }
    });
  }

  // Step 3: Active step if not final
  const isFinalStatus = ['success', 'reject', 'cancel'].includes(r.status);
  if (!isFinalStatus) {
    if (r.status === 'return') {
      steps.push({ 
        status: 'Pengembalian aset', 
        method: r.method,
        location: r.location,
        time: r.returnTime, 
        active: true,
      });
    }
  }

  return steps;
});

// Load placements from localStorage for read-only view
const loadPlacements = (): Record<string, string> => {
  try {
    const stored = localStorage.getItem('smart_asset_placements');
    if (stored) {
      return JSON.parse(stored);
    }
  } catch (e) {
    console.error(e);
  }
  return {
    'GPU-NVIDIA-2026-001': 'Mega Mendung',
    'WBD-SAKURA-2026-101': 'Tiga Negeri',
    'MON-DELL-2026-901': 'Mega Mendung',
    'MON-DELL-2026-902': 'Tiga Negeri',
  };
};

const assetPlacements = ref<Record<string, string>>({
  ...loadPlacements(),
  ...(props.placements || {})
});

const handleConfirmReturn = () => {
  router.post(route('smart.returns.confirm', props.returnId), {}, {
    onSuccess: () => {
      toast.success('Pengembalian aset berhasil dikonfirmasi!');
    }
  });
};
</script>

<template>
  <AppLayout title="Detail Pengembalian">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/returns">Pengembalian</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ request.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan {{ request.number }}</h1>
      <p class="text-sm text-muted-foreground">Permintaan dibuat pada {{ request.createdAt }}</p>
    </div>

    <!-- Info Banner -->
    <div class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-indigo-50/30 flex items-center justify-between gap-3 text-indigo-700">
      <p class="text-sm font-semibold">
        Tolong kembalikan semua aset pada <span class="font-bold">{{ request.returnTime }} di {{ request.location }}</span>
      </p>
      <button 
        @click="handleConfirmReturn"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2 rounded-lg transition-all"
      >
        Konfirmasi Pengembalian
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column (Details & Items) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-sm font-medium text-muted-foreground mb-3">Detail:</h3>
          <div class="space-y-2">
            <h2 class="text-lg md:text-xl font-extrabold text-foreground mb-3">
              {{ request.number }}
            </h2>
            
            <div class="space-y-1.5 text-sm text-foreground">
              <p>
                <span class="text-muted-foreground">Dibuat oleh:</span> 
                <span class="font-semibold">{{ request.requester }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">PIC Approval:</span> 
                <span class="font-semibold">{{ request.approval_by || request.approver }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Waktu dibuat:</span> 
                <span class="font-semibold">{{ request.createdAt }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">{{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})</span>
              </p>
              <p v-if="request.durationStart">
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">{{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours }} jam)</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-4">Daftar barang:</h3>
          
          <AssetItemCard 
            v-for="item in items" 
            :key="item.id"
            :brand="item.brand"
            :category="item.category"
            :subcategory="item.subcategory"
            :quantity="item.quantity"
            :assets="item.assets"
            :imageUrl="item.imageUrl"
            :placements="assetPlacements"
            :is-consumable="item.is_consumable"
          />
        </div>
      </div>

      <!-- Right Column (Timeline) -->
      <div class="space-y-6">
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm relative overflow-hidden">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-6">Tahapan:</h3>
          
          <!-- Vertical Timestep Stepper -->
          <div class="relative pl-8 space-y-8 before:absolute before:left-[15px] before:top-[10px] before:bottom-[10px] before:w-[2px] before:bg-border">
            <div 
              v-for="(step, index) in timeline" 
              :key="index" 
              class="relative"
            >
              <!-- Icon/Indicator -->
              <div class="absolute -left-[32px] top-0 w-8 h-8 rounded-full bg-card flex items-center justify-center z-10">
                <!-- Status Rejected (Red X) -->
                <div 
                  v-if="step.rejected" 
                  class="w-7 h-7 rounded-full border-2 border-red-500 flex items-center justify-center bg-card"
                >
                  <X class="w-4 h-4 text-red-500 stroke-[3.5]" />
                </div>

                <!-- Status Done (Green Check Circle) -->
                <div 
                  v-else-if="step.completed" 
                  class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card"
                >
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
                
                <!-- Status Active / Current (Pulsing Indigo Alert/Exclamation) -->
                <div 
                  v-else-if="step.active" 
                  class="w-7 h-7 rounded-full border-2 border-[#6366F1] flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-[#6366F1]/20 opacity-40 animate-ping"></span>
                  <span class="text-sm font-extrabold text-[#6366F1]">!</span>
                </div>

                <!-- Status Pending/Next (Grey Dot) -->
                <div 
                  v-else 
                  class="w-6 h-6 rounded-full border-2 border-muted-foreground/30 flex items-center justify-center bg-card"
                >
                  <div class="w-2 h-2 rounded-full bg-muted-foreground/30"></div>
                </div>
              </div>

              <!-- Content Step -->
              <div class="space-y-1">
                <div>
                  <h4 
                    class="text-sm font-bold"
                    :class="{
                      'text-green-600': step.completed,
                      'text-red-600': step.rejected,
                      'text-[#6366F1]': step.active && !step.completed,
                      'text-muted-foreground': !step.completed && !step.active && !step.rejected
                    }"
                  >
                    {{ step.status }}
                  </h4>
                  <p v-if="step.user" class="text-xs font-semibold text-green-600 mt-0.5">
                    oleh {{ step.user }}
                  </p>
                  <p v-if="step.time" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                  <p v-if="step.note" class="text-xs text-muted-foreground leading-relaxed pt-0.5">
                    {{ step.note }}
                  </p>
                  
                  <div v-if="step.active" class="space-y-0.5 mt-2 text-xs text-indigo-600">
                    <p class="font-semibold">Metode: {{ step.method }}</p>
                    <p class="font-semibold">Tempat: {{ step.location }}</p>
                    <p class="font-semibold">Waktu: {{ step.time }}</p>
                    
                    <div class="pt-3">
                      <button 
                        @click="handleConfirmReturn"
                        class="bg-[#6366F1] hover:bg-[#5558EB] text-white text-xs font-bold px-4 py-2 rounded-lg transition-all shadow-sm"
                      >
                        Konfirmasi Pengembalian
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
