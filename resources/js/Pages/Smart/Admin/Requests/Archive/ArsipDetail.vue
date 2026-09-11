<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  CheckCircle2,
  Check,
  Clock,
  X
} from 'lucide-vue-next';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';

const { t } = useI18n();

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
  logs?: any[];
}

interface Props {
  requestId: string | number;
  request: RequestDetail;
}

const props = defineProps<Props>();

const items = computed(() => props.request.items);

interface TimelineStep {
  status: string;
  user?: string;
  time?: string;
  completed?: boolean;
  rejected?: boolean;
  isPending?: boolean;
  note?: string;
  info?: string;
}

const timeline = computed((): TimelineStep[] => {
  const r = props.request;
  if (!r) return [];

  const steps = [];
  
  // Step 1: Initial creation
  steps.push({
    status: t('fulfillment.requestCreated'),
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
        statusName = t('fulfillment.approved');
      } else if (log.status_to === 'partial') {
        statusName = t('fulfillment.partiallyApproved');
      } else if (log.status_to === 'confirm') {
        if (log.status_from === 'partial') {
          statusName = t('fulfillment.additionalAllocationConfirmed');
        } else {
          if (log.note && log.note.includes('diatur oleh pengguna')) {
            statusName = t('fulfillment.handoverScheduleSet');
          } else {
            statusName = t('fulfillment.confirmed');
          }
        }
      } else if (log.status_to === 'borrow') {
        statusName = t('fulfillment.handoverCompletedAndBorrowed');
      } else if (log.status_to === 'return') {
        statusName = t('fulfillment.returnSubmitted');
      } else if (log.status_to === 'success') {
        if (log.status_from === 'return') {
          statusName = t('fulfillment.returnCompleted');
        } else {
          statusName = t('fulfillment.handoverCompleted');
        }
      } else if (log.status_to === 'reject') {
        statusName = t('fulfillment.rejectedTimeline');
        completed = false;
        rejected = true;
      } else if (log.status_to === 'cancel') {
        statusName = t('fulfillment.cancelledByUser');
      } else if (log.status_to === 'pending') {
        if (log.status_from === 'confirm') {
          statusName = t('fulfillment.partialHandoverReceived');
        } else {
          statusName = t('fulfillment.pendingBtn');
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

  return steps;
});
</script>

<template>
  <AppLayout :title="t('fulfillment.archiveDetailTitle')">
    <!-- Breadcrumb -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/arsip">{{ t('fulfillment.archive') }}</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ request.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="mb-6">
      <h1 class="text-xl font-bold text-foreground">{{ t('fulfillment.detailHead', { number: request.number }) }}</h1>
      <p class="text-sm text-muted-foreground">{{ t('fulfillment.createdOn', { date: request.createdAt }) }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column (Details & Items) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-sm font-medium text-muted-foreground mb-3">{{ t('fulfillment.detailsPrefix') }}</h3>
          <div class="space-y-2">
            <h2 class="text-lg md:text-xl font-extrabold text-foreground mb-3">
              {{ request.number }}
            </h2>
            
            <div class="space-y-1.5 text-sm text-foreground">
              <p>
                <span class="text-muted-foreground">{{ t('fulfillment.createdBy') }}</span> 
                <span class="font-semibold"> {{ request.requester }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">{{ t('fulfillment.picApproval') }}</span> 
                <span class="font-semibold"> {{ request.approval_by || request.approver }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">{{ t('fulfillment.createdAtTime') }}</span> 
                <span class="font-semibold"> {{ request.createdAt }}</span>
              </p>
              <p>
                <span class="text-muted-foreground">{{ t('fulfillment.utilization') }}:</span> 
                <span class="font-semibold">
                  {{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}
                </span>
              </p>
              <p v-if="request.durationStart">
                <span class="text-muted-foreground">{{ t('fulfillment.duration') }}</span>
                <span class="font-semibold">
                  {{ request.durationStart }} {{ t('fulfillment.until') }} {{ request.durationEnd }} ({{ request.durationDays }} {{ t('fulfillment.days') }}, {{ request.durationHours }} {{ t('fulfillment.hours') }})
                </span>
              </p>
            </div>
          </div>
        </div>

        <!-- Items Card -->
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-4">{{ t('fulfillment.itemsListPrefix') }}</h3>
          
          <AssetItemCard 
            v-for="item in items" 
            :key="item.id"
            :brand="item.brand"
            :category="item.category"
            :subcategory="item.subcategory"
            :quantity="item.quantity"
            :assets="item.assets"
            :imageUrl="item.imageUrl"
            :is-consumable="item.is_consumable"
          />
        </div>
      </div>

      <!-- Right Column (Timeline) -->
      <div class="space-y-6">
        <div class="bg-card border border-border rounded-[14px] p-6 shadow-sm relative overflow-hidden">
          <h3 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-6">{{ t('fulfillment.stagesPrefix') }}</h3>
          
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
                <!-- Status Done (Green Check Circle) -->
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
                      'text-red-600': step.rejected,
                      'text-green-600': !step.rejected
                    }"
                  >
                    {{ step.status }}
                  </h4>
                  <p 
                    v-if="step.user" 
                    class="text-xs font-semibold mt-0.5" 
                    :class="{
                      'text-red-600': step.rejected,
                      'text-green-600': !step.rejected
                    }"
                  >
                    {{ t('fulfillment.byUser', { user: step.user }) }}
                  </p>
                  <p v-if="step.time" class="text-xs text-muted-foreground mt-0.5">
                    {{ step.time }}
                  </p>
                  <p v-if="step.note" class="text-xs text-muted-foreground leading-relaxed pt-0.5">
                    {{ step.note }}
                  </p>
                  <p 
                    v-if="step.info" 
                    class="text-xs font-medium mt-0.5" 
                    :class="{
                      'text-red-600/80': step.rejected,
                      'text-green-600/80': !step.rejected
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
