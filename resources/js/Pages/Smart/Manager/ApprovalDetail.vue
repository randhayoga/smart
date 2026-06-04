<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { Button } from '@/Components/ui/button';
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
  subcategory: string;
  brand: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  assets?: string[];
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
  approver?: string;
  approval_by?: string;
  confirmation_by?: string;
}

const props = defineProps<{
  requestId: string | number;
  user?: any;
  request: RequestHistory;
}>();

const isActionModalOpen = ref(false);
const actionType = ref<'approve' | 'reject'>('approve');
const actionNote = ref('');
const errorMessage = ref('');

const openActionModal = (type: 'approve' | 'reject') => {
  actionType.value = type;
  actionNote.value = '';
  errorMessage.value = '';
  isActionModalOpen.value = true;
};

const closeActionModal = () => {
  isActionModalOpen.value = false;
};

const handleActionSubmit = () => {
  router.post(route('smart.approve.action', props.requestId), {
    action: actionType.value,
    note: actionNote.value,
  }, {
    onSuccess: () => {
      closeActionModal();
    },
    onError: (errs) => {
      errorMessage.value = Object.values(errs).join(', ');
    }
  });
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parts[2]}/${parts[1]}/${parts[0]}`; // DD/MM/YYYY
};

const timeline = computed(() => {
  const r = props.request;
  if (!r) return [];

  const steps = [];
  steps.push({
    status: 'Permintaan dibuat',
    user: r.requester,
    time: formatDate(r.created_at),
    completed: true,
  });

  if (r.raw_status === 'wait') {
    steps.push({
      status: 'Perlu approval Anda!',
      completed: false,
      active: true,
      isAction: true,
    });
  } else if (r.raw_status === 'reject') {
    steps.push({
      status: 'Permintaan Ditolak',
      user: r.approval_by || r.approver || 'Manager',
      completed: false,
      active: true,
      rejected: true,
    });
  } else if (r.raw_status === 'approve') {
    steps.push({
      status: 'Di-approve',
      user: r.approval_by || r.approver || 'Manager',
      completed: true,
    });
    steps.push({
      status: 'Menunggu konfirmasi Admin',
      completed: false,
      active: true,
    });
  } else {
    steps.push({
      status: 'Di-approve',
      user: r.approval_by || r.approver || 'Manager',
      completed: true,
    });
    steps.push({
      status: 'Dikonfirmasi',
      user: r.confirmation_by || 'Admin',
      completed: true,
    });
  }

  return steps;
});
</script>

<template>
  <Head :title="'Detail ' + request.number" />

  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/approve">Perlu Approval</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ request.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan</h1>
    </div>

    <!-- Info Banner (Approve or Reject Prompt) -->
    <div 
      v-if="request.raw_status === 'wait' && (user?.role === 'manager' || user?.role === 'ifs_manager')" 
      class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-white flex items-center justify-between gap-3 text-indigo-600 animate-in fade-in duration-300"
    >
      <p class="text-sm font-semibold">
        Apakah permintaan/peminjaman di bawah akan Anda approve atau tolak?
      </p>
      <div class="flex items-center gap-2 pr-1.5">
        <button 
          @click="openActionModal('approve')"
          class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2 rounded-lg transition-all"
        >
          Approve
        </button>
        <button 
          @click="openActionModal('reject')"
          class="bg-[#D9534F] hover:bg-[#C9302C] text-white text-sm font-bold px-6 py-2 rounded-lg transition-all"
        >
          Tolak
        </button>
      </div>
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
                <span class="font-semibold"> {{ request.approver || 'Manager' }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">Waktu dibuat:</span> 
                <span class="font-semibold"> {{ formatDate(request.created_at) }}</span>
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
          
          <div class="space-y-4">
            <AssetItemCard 
              v-for="item in request.items" 
              :key="item.id"
              :brand="item.brand + ' ' + item.spec"
              :category="item.category"
              :subcategory="item.subcategory"
              :quantity="item.quantity"
              :assets="item.assets || []"
              :imageUrl="item.imageUrl"
              :quantityLabel="request.type === 'peminjaman' ? 'Jumlah dipinjam' : 'Jumlah diminta'"
            />
          </div>
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
                  <div v-if="step.isAction && (user?.role === 'manager' || user?.role === 'ifs_manager')" class="pt-3 flex gap-2">
                    <button 
                      @click="openActionModal('approve')"
                      class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-all shadow-sm cursor-pointer"
                    >
                      Approve
                    </button>
                    <button 
                      @click="openActionModal('reject')"
                      class="px-4 py-1.5 bg-[#D9534F] hover:bg-[#C9302C] text-white text-sm font-bold rounded-lg transition-all shadow-sm cursor-pointer"
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

    <!-- Approval / Rejection Modal -->
    <div 
      v-if="isActionModalOpen" 
      class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
    >
      <div 
        class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[500px] flex flex-col overflow-hidden border border-border"
        @click.stop
      >
        <div class="flex items-center justify-between p-5 border-b border-border bg-card shrink-0">
          <h3 class="text-base md:text-lg font-extrabold text-foreground">
            {{ actionType === 'approve' ? 'Setujui Permintaan' : 'Tolak Permintaan' }}
          </h3>
        </div>
        
        <div class="p-6 space-y-4">
          <p class="text-sm text-foreground">
            Apakah Anda yakin ingin <strong>{{ actionType === 'approve' ? 'menyetujui' : 'menolak' }}</strong> permintaan <strong>{{ request.number }}</strong>?
          </p>

          <div class="space-y-1.5">
            <label class="text-xs font-bold text-muted-foreground uppercase tracking-wider block">Catatan (Opsional)</label>
            <textarea
              v-model="actionNote"
              placeholder="Tambahkan catatan..."
              rows="3"
              class="w-full text-sm border border-input rounded-lg bg-background p-2.5 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-sm"
            ></textarea>
          </div>

          <p v-if="errorMessage" class="text-xs font-bold text-red-600">
            {{ errorMessage }}
          </p>
        </div>

        <div class="p-5 border-t border-border flex justify-end gap-3 bg-muted/20 shrink-0">
          <Button 
            variant="outline" 
            @click="closeActionModal" 
            class="rounded-full h-10 px-6 font-bold text-sm"
          >
            Batal
          </Button>
          <Button 
            @click="handleActionSubmit" 
            :class="actionType === 'approve' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-red-600 hover:bg-red-700'"
            class="text-white font-bold rounded-full h-10 px-6 text-sm"
          >
            Konfirmasi
          </Button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
