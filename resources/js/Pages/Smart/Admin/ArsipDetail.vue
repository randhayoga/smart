<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  CheckCircle2,
  Check,
  Clock,
  X
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
  approval_by?: string;
  confirmation_by?: string;
  return_confirmed_by?: string;
  createdAt: string;
  updatedAt: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: string;
  type: 'permintaan' | 'peminjaman';
  items: RequestItem[];
}

interface Props {
  requestId: string | number;
  request: RequestDetail;
  placements?: Record<string, string>;
}

const props = defineProps<Props>();

const items = computed(() => props.request.items);

const timeline = computed(() => {
  const r = props.request;
  if (!r) return [];

  const steps = [];
  steps.push({ status: 'Permintaan dibuat', time: r.createdAt, completed: true });
  
  if (r.status === 'reject') {
    steps.push({ 
      status: 'Ditolak', 
      user: r.approval_by || r.approver || 'Manager', 
      time: r.updatedAt || r.createdAt, 
      rejected: true 
    });
  } else if (r.status === 'cancel') {
    steps.push({ status: 'Dibatalkan oleh Pengguna', time: r.updatedAt || r.createdAt, completed: true });
  } else if (r.status === 'pending') {
    steps.push({ status: 'Di-approve', user: r.approval_by || r.approver || 'Manager', time: r.createdAt, completed: true });
    steps.push({ status: 'Pending', user: r.confirmation_by || 'Admin', time: r.updatedAt || r.createdAt, completed: true, isPending: true });
  } else {
    steps.push({ status: 'Di-approve', user: r.approval_by || r.approver || 'Manager', time: r.createdAt, completed: true });
    steps.push({ status: 'Dikonfirmasi', user: r.confirmation_by || 'Admin', time: r.createdAt, completed: true });
    steps.push({ status: 'Serah Terima', time: r.createdAt, completed: true });
    if (r.type === 'peminjaman') {
      steps.push({ status: 'Aset selesai dipinjam', time: r.createdAt, completed: true });
      steps.push({ status: 'Pengembalian aset', info: r.return_confirmed_by ? `dikonfirmasi oleh ${r.return_confirmed_by}` : 'dikonfirmasi oleh Admin', time: r.updatedAt || r.createdAt, completed: true });
    }
    steps.push({ status: 'Selesai', time: r.updatedAt || r.createdAt, completed: true });
  }
  return steps;
});
</script>

<template>
  <AppLayout title="Detail Arsip">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/arsip">Arsip</BreadcrumbLink>
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
              <p v-if="request.durationStart">
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
                <!-- Status Pending (Grey Dot) -->
                <div v-if="step.isPending" class="w-6 h-6 rounded-full border-2 border-muted-foreground/30 flex items-center justify-center bg-card">
                  <div class="w-2 h-2 rounded-full bg-muted-foreground/30"></div>
                </div>
                <!-- Status Done (Green Check Circle) -->
                <!-- Status Done (Green Check Circle) / Status Rejected (Red X) -->
                <div v-else-if="step.rejected" class="w-7 h-7 rounded-full border-2 border-red-500 flex items-center justify-center bg-card">
                  <X class="w-4 h-4 text-red-500 stroke-[3.5] cursor-pointer" />
                </div>
                <div v-else class="w-7 h-7 rounded-full border-2 border-green-500 flex items-center justify-center bg-card">
                  <Check class="w-4 h-4 text-green-500 stroke-[3.5]" />
                </div>
              </div>

              <!-- Content Step -->
              <div class="space-y-1">
                <div>
                  <h4 
                    class="text-sm font-bold"
                    :class="{
                      'text-muted-foreground': step.isPending,
                      'text-red-600': step.rejected,
                      'text-green-600': !step.isPending && !step.rejected
                    }"
                  >
                    {{ step.status }}
                  </h4>
                  <p 
                    v-if="step.user" 
                    class="text-xs font-semibold mt-0.5" 
                    :class="{
                      'text-muted-foreground/80': step.isPending,
                      'text-red-600': step.rejected,
                      'text-green-600': !step.isPending && !step.rejected
                    }"
                  >
                    oleh {{ step.user }}
                  </p>
                  <p v-if="step.time" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                  <p 
                    v-if="step.info" 
                    class="text-xs font-medium mt-0.5" 
                    :class="{
                      'text-muted-foreground/80': step.isPending,
                      'text-red-600/80': step.rejected,
                      'text-green-600/80': !step.isPending && !step.rejected
                    }"
                  >
                    {{ step.info }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
