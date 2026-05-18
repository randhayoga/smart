<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ChevronUp,
  CheckCircle2,
  Clock,
  ArrowLeft,
  Info,
  X,
  AlertCircle
} from 'lucide-vue-next';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface Props {
  returnId: string | number;
  requestData: {
    id: number;
    number: string;
    requester_name: string;
    approver_name: string;
    created_at: string;
    utilization: string;
    duration: string;
    reason: string;
  };
  returnData: {
    id: number;
    number: string;
    borrower: string;
    returnTime: string;
    location: string;
    method: string;
  };
  items: Array<{
    id: number;
    brand: string;
    category: string;
    subcategory: string;
    quantity: number | string;
    assets: string[];
  }>;
  timeline: Array<{
    status: string;
    user?: string;
    time?: string;
    completed?: boolean;
    active?: boolean;
    info?: string;
    method?: string;
    location?: string;
  }>;
}

const props = defineProps<Props>();

const items = computed(() => props.items || []);
const timeline = computed(() => props.timeline || []);

const handleConfirmReturn = () => {
  alert('Konfirmasi Pengembalian Berhasil!');
};
</script>

<template>
  <AppLayout title="Detail Permintaan">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/returns">Pengembalian</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">#Request_ID</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan {{ requestData?.number || returnId }}</h1>
      <p class="text-sm text-muted-foreground">Permintaan dibuat pada {{ requestData?.created_at }}</p>
    </div>

    <!-- Info Banner -->
    <div class="mb-6 p-1.5 pl-6 rounded-xl border border-indigo-200 bg-indigo-50/30 flex items-center justify-between gap-3 text-indigo-700">
      <p class="text-sm font-semibold">
        Tolong kembalikan semua aset pada <span class="font-bold">{{ returnData?.returnTime }} di {{ returnData?.location }} ({{ returnData?.method }})</span>
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
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm">
          <div class="space-y-4">
            <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Detail:</h3>
            <div class="space-y-2">
                <h2 class="text-lg font-bold text-foreground">{{ requestData?.number }}</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-8 text-sm">
                    <div class="flex flex-col">
                        <span class="text-muted-foreground font-normal">Dibuat oleh:</span>
                        <span class="font-medium text-foreground">{{ requestData?.requester_name }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-muted-foreground font-normal">PIC Approval:</span>
                        <span class="font-medium text-foreground">{{ requestData?.approver_name }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-muted-foreground font-normal">Waktu dibuat:</span>
                        <span class="font-medium text-foreground">{{ requestData?.created_at }}</span>
                    </div>
                    <div class="flex flex-col sm:col-span-2">
                        <span class="text-muted-foreground font-normal">Pemanfaatan:</span>
                        <span class="font-medium text-foreground">{{ requestData?.utilization }}</span>
                    </div>
                    <div class="flex flex-col sm:col-span-2">
                        <span class="text-muted-foreground font-normal">Durasi:</span>
                        <span class="font-medium text-foreground">{{ requestData?.duration }}</span>
                    </div>
                    <div v-if="requestData?.reason" class="flex flex-col sm:col-span-2">
                        <span class="text-muted-foreground font-normal">Alasan Kebutuhan:</span>
                        <span class="font-medium text-foreground italic">"{{ requestData?.reason }}"</span>
                    </div>
                </div>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm space-y-6">
          <h3 class="text-lg font-bold text-foreground">Daftar barang:</h3>
          
          <AssetItemCard 
            v-for="item in items" 
            :key="item.id"
            :brand="item.brand"
            :category="item.category"
            :subcategory="item.subcategory"
            :quantity="item.quantity"
            :assets="item.assets"
          />
        </div>
      </div>

      <!-- Right Column (Timeline) -->
      <div class="space-y-6">
        <div class="bg-card rounded-xl border border-border p-5 shadow-sm">
          <h3 class="text-lg font-bold text-foreground mb-8">Tahapan:</h3>
          
          <div class="space-y-4">
            <div v-for="(step, index) in timeline" :key="index">
              <div class="flex items-start gap-4">
                <div class="flex flex-col items-center">
                  <div class="relative z-10 flex items-center justify-center w-10 h-10 rounded-full bg-card border-2"
                    :class="[
                        step.completed ? 'border-green-500' : 'border-indigo-600'
                    ]"
                  >
                    <CheckCircle2 v-if="step.completed" class="w-6 h-6 text-green-500" />
                    <AlertCircle v-else class="w-6 h-6 text-indigo-600" />
                  </div>
                </div>

                <div class="space-y-0.5 pt-0.5 flex-grow">
                  <p class="font-bold text-base leading-tight" :class="step.completed ? 'text-green-600' : 'text-indigo-600'">
                    {{ step.status }}
                  </p>
                  <p v-if="step.user" class="text-sm text-green-600/80 font-medium leading-tight">oleh {{ step.user }}</p>
                  <p v-if="step.time" class="text-sm text-muted-foreground leading-tight">{{ step.time }}</p>
                  
                  <div v-if="step.active" class="space-y-0.5 mt-1">
                    <p class="text-sm text-indigo-600 font-medium leading-tight">Metode: {{ step.method }}</p>
                    <p class="text-sm text-indigo-600 font-medium leading-tight">Tempat: {{ step.location }}</p>
                    <p class="text-sm text-indigo-600 font-medium leading-tight">Waktu: {{ step.time }}</p>
                    
                    <div class="pt-3">
                        <button 
                            @click="handleConfirmReturn"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-all"
                        >
                            Konfirmasi Pengembalian
                        </button>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="index < timeline.length - 1" class="flex justify-start pl-4 py-1">
                <div class="w-2.5 h-6 ml-0.5">
                  <svg width="12" height="24" viewBox="0 0 12 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 0V20M6 20L1 15M6 20L11 15" 
                      :stroke="index < 5 ? '#22C55E' : '#6366F1'" 
                      stroke-width="2" 
                      stroke-linecap="round" 
                      stroke-linejoin="round"
                    />
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
