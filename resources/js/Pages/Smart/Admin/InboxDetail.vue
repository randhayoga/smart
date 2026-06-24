<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { Head } from '@inertiajs/vue3';
import { 
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info,
  X,
  AlertCircle,
  Check
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface RequestItem {
  id: number;
  brand: string;
  category: string;
  subcategory: string;
  quantity: number;
  assets: string[];
  imageUrl?: string | null;
  stock?: number | null;
  status?: string;
  is_consumable?: boolean;
}

interface RequestDetail {
  id: number;
  number: string;
  requester: string;
  approver: string;
  approval_by?: string;
  confirmation_by?: string;
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
  approvedAt?: string;
  has_insufficient_stock?: boolean;
  logs?: any[];
}

interface Props {
  requestId: string | number;
  request: RequestDetail;
  user?: any;
  placements?: Record<string, string>;
}

const props = defineProps<Props>();

const items = computed(() => props.request.items);

const canPartiallyApprove = computed(() => {
  const pendingItems = items.value.filter((item: any) => item.status === 'pending');
  if (pendingItems.length === 0) return false;
  
  const hasInStock = pendingItems.some((item: any) => item.stock !== null && item.stock >= Number(item.quantity));
  const hasOutOfStock = pendingItems.some((item: any) => item.stock !== null && item.stock < Number(item.quantity));
  
  return hasInStock && hasOutOfStock;
});

const canConfirm = computed(() => {
  const pendingItems = items.value.filter((item: any) => item.status === 'pending');
  if (pendingItems.length === 0) return false;
  
  return pendingItems.every((item: any) => item.stock !== null && item.stock >= Number(item.quantity));
});

const isAllConsumable = computed(() => {
  return items.value.every((item: any) => item.is_consumable);
});

const hasPassedFirstHandover = computed(() => {
  const logs = props.request.logs || [];
  return logs.some((log: any) => log.status_to === 'partial');
});

interface TimelineStep {
  status: string;
  user?: string;
  time?: string;
  completed?: boolean;
  rejected?: boolean;
  isPending?: boolean;
  isAction?: boolean;
  active?: boolean;
  note?: string;
}

const timeline = computed((): TimelineStep[] => {
  const r = props.request;
  if (!r) return [];

  const steps = [];
  
  // Step 1: Initial creation
  steps.push({
    status: 'Permintaan dibuat',
    user: r.requester,
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
      let isPending = false;

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
        completed = true;
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
          isPending,
          note: log.note || '',
        });
      }
    });
  }

  // Step 3: Active / Next steps
  const isFinalStatus = ['success', 'reject', 'cancel'].includes(r.status);
  if (!isFinalStatus) {
    if (r.status === 'wait') {
      steps.push({
        status: 'Perlu approval Manager',
        completed: false,
        active: true,
      });
    } else if (r.status === 'approve' || r.status === 'pending' || r.status === 'partial') {
      steps.push({
        status: r.status === 'partial' 
          ? 'Perlu alokasi & konfirmasi sisa barang' 
          : 'Perlu alokasi & konfirmasi Admin',
        completed: false,
        active: true,
        isAction: true,
      });
    } else if (r.status === 'confirm') {
      steps.push({
        status: 'Serah Terima (Jadwal diatur)',
        completed: false,
        active: true,
      });
    } else if (r.status === 'borrow') {
      steps.push({
        status: 'Aset sedang dipinjam',
        completed: false,
        active: true,
      });
    } else if (r.status === 'return') {
      steps.push({
        status: 'Pengembalian aset',
        completed: false,
        active: true,
      });
    }
  }

  return steps;
});

const handleApprove = () => {
  router.post(route('smart.inbox.action', props.requestId), {
    action: 'approve'
  }, {
    onSuccess: () => {
      toast.success('Permintaan berhasil dikonfirmasi/disetujui!');
    },
    onError: (errors) => {
      toast.error(Object.values(errors).join(', '));
    }
  });
};

const handleReject = () => {
  openRejectModal();
};

const handlePartiallyApprove = () => {
  router.post(route('smart.inbox.action', props.requestId), {
    action: 'partially_approve'
  }, {
    onSuccess: () => {
      toast.success('Permintaan berhasil disetujui sebagian (Partial).');
    },
    onError: (errors) => {
      toast.error(Object.values(errors).join(', '));
    }
  });
};

const isPendingModalOpen = ref(false);
const pendingNote = ref('');

const openPendingModal = () => {
  pendingNote.value = '';
  isPendingModalOpen.value = true;
};
const closePendingModal = () => {
  isPendingModalOpen.value = false;
};

const submitPending = () => {
  router.post(route('smart.inbox.action', props.requestId), {
    action: 'pending',
    note: pendingNote.value,
  }, {
    onSuccess: () => {
      closePendingModal();
      toast.success('Permintaan berhasil di-pending.');
    },
    onError: (errors) => {
      toast.error(Object.values(errors).join(', '));
    }
  });
};

const isRejectModalOpen = ref(false);
const rejectReason = ref('');

const openRejectModal = () => {
  rejectReason.value = '';
  isRejectModalOpen.value = true;
};

const closeRejectModal = () => {
  isRejectModalOpen.value = false;
};

const submitReject = () => {
  router.post(route('smart.inbox.action', props.requestId), {
    action: 'reject',
    note: rejectReason.value
  }, {
    onSuccess: () => {
      closeRejectModal();
      toast.success('Permintaan berhasil ditolak.');
    },
    onError: (errors) => {
      toast.error(Object.values(errors).join(', '));
    }
  });
};

const handlePilihAlokasi = (item: any) => {
  router.get(route('smart.handover.show', props.requestId));
};

const handleAturSerahTerima = () => {
  router.get(route('smart.handover.show', props.requestId));
};
</script>

<template>
  <Head :title="'Detail ' + request.number" />
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3 text-xs md:text-sm">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inbox">Inbox</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ request.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan</h1>
    </div>

    <!-- Info Banner -->
    <div v-if="user?.role === 'admin' || user?.role === 'ifs_manager'" class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-white flex items-center justify-between gap-3 text-indigo-600">
      <p class="text-sm font-semibold">
        {{ isAllConsumable ? 'Tolong konfirmasi permintaan ini' : 'Tolong pastikan bahwa alokasi aset sudah sesuai dan konfirmasi permintaan/peminjaman ini' }}
      </p>
      <button 
        @click="handleAturSerahTerima"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2 rounded-lg transition-all"
      >
        Atur Serah Terima
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
                <span class="font-semibold"> {{ request.requester }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">PIC Approval:</span> 
                <span class="font-semibold"> {{ request.approval_by || request.approver }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Waktu dibuat:</span> 
                <span class="font-semibold"> {{ request.createdAt }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold">
                  {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
                </span>
              </p>
              <p v-if="request.type === 'peminjaman' && request.durationStart">
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-semibold">
                  {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours }} jam)
                </span>
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
            :placements="placements"
            :stock="item.stock"
            :status="item.status"
            :is-admin="true"
            :is-consumable="item.is_consumable"
          >
            <template #footer>
              <button 
                v-if="!item.is_consumable"
                @click="handlePilihAlokasi(item)"
                class="px-5 py-2.5 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-bold rounded-[14px] transition-all shadow-sm"
              >
                Pilih Alokasi Aset
              </button>
            </template>
          </AssetItemCard>
        </div>
      </div>

      <!-- Right Column (Timeline & Actions) -->
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
                <!-- Status Pending (Grey Dot) -->
                <div 
                  v-if="step.isPending" 
                  class="w-6 h-6 rounded-full border-2 border-muted-foreground/30 flex items-center justify-center bg-card"
                >
                  <div class="w-2 h-2 rounded-full bg-muted-foreground/30"></div>
                </div>

                <!-- Status Done (Green Check Circle) -->
                <div 
                  v-else-if="step.completed" 
                  class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card"
                >
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
                
                <!-- Status Rejected (Red X) -->
                <div 
                  v-else-if="step.rejected" 
                  class="w-7 h-7 rounded-full border-2 border-red-500 flex items-center justify-center bg-card"
                >
                  <X class="w-4 h-4 text-red-500 stroke-[3.5] cursor-pointer" />
                </div>

                <!-- Status Action Required / Active -->
                <div 
                  v-else
                  class="w-7 h-7 rounded-full border-2 border-[#6366F1] flex items-center justify-center bg-card relative"
                >
                  <span class="absolute inline-flex h-full w-full rounded-full bg-[#6366F1]/20 opacity-40 animate-ping"></span>
                  <span class="text-sm font-extrabold text-[#6366F1]">!</span>
                </div>
              </div>

              <!-- Content Step -->
              <div class="space-y-1">
                <div>
                  <h4 
                    class="text-sm font-bold"
                    :class="{
                      'text-green-600': step.completed && !step.isPending,
                      'text-muted-foreground': step.isPending,
                      'text-red-600': step.rejected,
                      'text-[#6366F1]': !step.completed && !step.rejected
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
                  
                  <!-- Action Buttons inside timeline step -->
                  <div v-if="step.isAction && (user?.role === 'admin' || user?.role === 'ifs_manager')" class="pt-3 flex gap-2">
                    <button 
                      v-if="canPartiallyApprove"
                      @click="handlePartiallyApprove"
                      class="px-4 py-1.5 bg-[#5BC0DE] hover:bg-[#46B8DA] text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      {{ hasPassedFirstHandover ? 'Partial' : 'Partially Approve' }}
                    </button>
                    <button 
                      v-if="canConfirm"
                      @click="handleApprove"
                      class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      Konfirmasi
                    </button>
                    <button 
                      v-if="request.has_insufficient_stock && request.status !== 'pending' && request.status !== 'partial'"
                      @click="openPendingModal"
                      class="px-4 py-1.5 bg-zinc-500 hover:bg-zinc-600 text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      Pending
                    </button>
                    <button 
                      @click="handleReject"
                      class="px-4 py-1.5 bg-[#D9534F] hover:bg-[#C9302C] text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      Tolak
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Teleport Modal Pending -->
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
          v-if="isPendingModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
        >
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-md flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-5 border-b border-border bg-card">
              <h3 class="text-base md:text-lg font-extrabold text-foreground">Pending Permintaan</h3>
              <button @click="closePendingModal" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>
            
            <!-- Body -->
            <div class="p-6 space-y-4">
              <p class="text-sm text-muted-foreground">
                Kirim informasi mengenai delay/pending untuk penyediaan barang ini ke pengguna.
              </p>
              
              <div class="space-y-2">
                <label class="text-xs font-semibold text-foreground">Catatan / Alasan Penundaan:</label>
                <textarea
                  v-model="pendingNote"
                  placeholder="Masukkan alasan penundaan (contoh: Stok habis di gudang pusat)..."
                  rows="4"
                  class="w-full text-sm border border-input rounded-[10px] bg-background p-3 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
                ></textarea>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 p-5 border-t border-border bg-muted/20">
              <button 
                @click="closePendingModal"
                class="px-5 py-2 text-sm font-semibold border border-input hover:bg-muted rounded-lg transition-colors"
              >
                Batal
              </button>
              <button 
                @click="submitPending"
                class="px-5 py-2 text-sm font-bold bg-zinc-500 hover:bg-zinc-600 text-white rounded-lg transition-all shadow-sm"
              >
                Kirim Pending
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
    <!-- Teleport Modal Reject -->
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
          v-if="isRejectModalOpen" 
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
          @click.self="closeRejectModal"
        >
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-lg flex flex-col overflow-hidden border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-5 border-b border-border bg-card">
              <h3 class="text-base md:text-lg font-extrabold text-foreground">Penolakan Permintaan/Peminjaman</h3>
              <button @click="closeRejectModal" class="p-1.5 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>
            
            <!-- Body -->
            <div class="p-6 space-y-4">
              <!-- Request Info -->
              <div class="space-y-1 pb-4 border-b border-border">
                <p class="text-sm font-extrabold text-foreground">{{ request.number }}</p>
                <p class="text-sm text-muted-foreground">Dibuat oleh: <span class="text-foreground font-medium">{{ request.requester }}</span></p>
                <p class="text-sm text-muted-foreground">PIC Approval: <span class="text-foreground font-medium">{{ request.approval_by || request.approver }}</span></p>
                <p class="text-sm text-muted-foreground">Waktu dibuat: <span class="text-foreground font-medium">{{ request.createdAt }}</span></p>
                <p class="text-sm text-muted-foreground">
                  Pemanfaatan: 
                  <span class="text-foreground font-medium">
                    {{ request.pemanfaatan === 'corporate' ? 'Corporate' : 'Project' }} ({{ request.pemanfaatanDetail }})
                  </span>
                </p>
                <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-sm text-muted-foreground">
                  Durasi: 
                  <span class="text-foreground font-medium">
                    {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours }} jam)
                  </span>
                </p>
              </div>

              <!-- Confirmation Question -->
              <div class="space-y-2">
                <p class="text-sm font-bold text-red-500">Apakah Anda yakin untuk menolak permintaan/peminjaman ini?</p>
                <label class="text-sm text-foreground">Catatan (opsional):</label>
                <textarea
                  v-model="rejectReason"
                  placeholder="Ketik alasan penolakan di sini..."
                  rows="4"
                  class="w-full text-sm border border-input rounded-[10px] bg-background p-3 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-400 transition-all resize-y"
                ></textarea>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 p-5 border-t border-border bg-muted/20">
              <button 
                @click="closeRejectModal"
                class="px-5 py-2 text-sm font-semibold border border-input hover:bg-muted rounded-lg transition-colors"
              >
                Tidak
              </button>
              <button 
                @click="submitReject"
                class="px-5 py-2 text-sm font-bold bg-[#D9534F] hover:bg-[#C9302C] text-white rounded-lg transition-all shadow-sm"
              >
                Iya
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
