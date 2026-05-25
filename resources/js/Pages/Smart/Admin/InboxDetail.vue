<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
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
}

interface RequestDetail {
  id: number;
  number: string;
  requester: string;
  approver: string;
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
}

interface Props {
  requestId: string | number;
  request: RequestDetail;
}

const props = defineProps<Props>();

const items = computed(() => props.request.items);

const timeline = computed(() => {
  const r = props.request;
  if (!r) return [];

  const steps = [];
  steps.push({
    status: 'Permintaan dibuat',
    user: r.requester,
    time: r.createdAt,
    completed: true,
  });

  if (r.status === 'wait') {
    steps.push({
      status: 'Menunggu approval manager',
      user: r.approver,
      completed: false,
      active: true,
      isAction: true,
    });
  } else if (r.status === 'approve') {
    steps.push({
      status: 'Di-approve oleh Manager',
      user: r.approver,
      time: r.approvedAt || r.createdAt,
      completed: true,
    });
    steps.push({
      status: 'Perlu konfirmasi Anda!',
      completed: false,
      active: true,
      isAction: true,
    });
  } else if (r.status === 'reject') {
    steps.push({
      status: 'Ditolak',
      completed: false,
      active: true,
      rejected: true,
    });
  } else {
    steps.push({
      status: 'Di-approve oleh Manager',
      user: r.approver,
      time: r.approvedAt || r.createdAt,
      completed: true,
    });
    steps.push({
      status: 'Telah dikonfirmasi oleh Admin',
      completed: true,
    });
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
  const reason = prompt('Masukkan alasan penolakan (opsional):');
  if (reason === null) return;
  
  router.post(route('smart.inbox.action', props.requestId), {
    action: 'reject',
    note: reason
  }, {
    onSuccess: () => {
      toast.success('Permintaan berhasil ditolak.');
    },
    onError: (errors) => {
      toast.error(Object.values(errors).join(', '));
    }
  });
};

const handleAturSerahTerima = () => {
  router.get('/smart/handover');
};

const handlePilihAlokasi = (item: any) => {
  toast.info('Alokasi aset dilakukan secara otomatis saat persetujuan/konfirmasi.');
};
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
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
    <div class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-white flex items-center justify-between gap-3 text-indigo-600">
      <p class="text-sm font-semibold">
        Tolong pastikan bahwa alokasi aset sudah sesuai dan konfirmasi permintaan/peminjaman ini
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
                <span class="font-semibold"> {{ request.approver }}</span>
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
          >
            <template #footer>
              <button 
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
                <!-- Status Done (Green Check Circle) -->
                <div 
                  v-if="step.completed" 
                  class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card"
                >
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
                
                <!-- Status Rejected (Red X) -->
                <div 
                  v-else-if="step.rejected" 
                  class="w-7 h-7 rounded-full border-2 border-red-500 flex items-center justify-center bg-card"
                >
                  <X class="w-4 h-4 text-red-500 stroke-[3.5]" />
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
                      'text-green-600': step.completed,
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
                  
                  <!-- Action Buttons inside timeline step -->
                  <div v-if="step.isAction" class="pt-3 flex gap-2">
                    <button 
                      @click="handleApprove"
                      class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-all shadow-sm"
                    >
                      Konfirmasi
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
  </AppLayout>
</template>
