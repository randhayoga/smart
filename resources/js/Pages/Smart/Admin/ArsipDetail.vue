<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

interface Props {
  requestId: string | number;
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
  }>;
}

const props = defineProps<Props>();

const items = computed(() => props.items || []);
const timeline = computed(() => props.timeline || []);

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
          <span class="text-muted-foreground">{{ requestId || '#Request_ID' }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">Detail Permintaan {{ requestData?.number || requestId }}</h1>
      <p class="text-sm text-muted-foreground">Permintaan dibuat pada {{ requestData?.created_at }}</p>
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
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-8 text-sm mt-3">
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
                      step.completed ? 'border-green-500' : (step.active ? 'border-indigo-600' : 'border-red-500')
                    ]"
                  >
                    <CheckCircle2 v-if="step.completed" class="w-6 h-6 text-green-500" />
                    <AlertCircle v-else class="w-6 h-6" :class="step.active ? 'text-indigo-600' : 'text-red-500'" />
                  </div>
                </div>

                <div class="space-y-0.5 pt-0.5 flex-grow">
                  <p class="font-bold text-base leading-tight" :class="step.completed ? 'text-green-600' : (step.active ? 'text-indigo-600' : 'text-red-600')">
                    {{ step.status }}
                  </p>
                  <p v-if="step.user" class="text-sm font-medium leading-tight text-foreground/80">oleh {{ step.user }}</p>
                  <p v-if="step.info" class="text-sm font-medium leading-tight text-muted-foreground">{{ step.info }}</p>
                  <p v-if="step.time" class="text-sm text-muted-foreground leading-tight">{{ step.time }}</p>
                </div>
              </div>

              <div v-if="index < timeline.length - 1" class="flex justify-start pl-4 py-1">
                <div class="w-2.5 h-8 ml-0.5">
                  <svg width="12" height="32" viewBox="0 0 12 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 0V28M6 28L1 23M6 28L11 23" 
                      :stroke="index < timeline.length - 2 ? '#22C55E' : (timeline[index+1].completed ? '#22C55E' : '#9CA3AF')" 
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
