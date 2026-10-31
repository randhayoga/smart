<script setup lang="ts">
/**
 * Unified Return Request Detail Page (Admin)
 * Displays detailed information, status/type badges, returned assets with modal inspection,
 * scrollable item cards, and shadcn-vue vertical stepper timeline with return confirmation workflow.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  Stepper,
  StepperItem,
  StepperTrigger,
  StepperIndicator,
  StepperSeparator,
  StepperTitle,
  StepperDescription,
} from '@/Components/ui/stepper';
import {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import DetailAssetModal from '@/Pages/Smart/Admin/StockManagement/Modals/DetailAssetModal.vue';
import ReturnConfirmationModal from './modals/ReturnConfirmationModal.vue';
import { 
  ArrowLeft,
  Check, 
  Clock, 
  X, 
  AlertCircle,
  ExternalLink 
} from 'lucide-vue-next';
import { getRequestStatusBadges } from '@/lib/requestStatus';
import { getRequestTypeBadgeClass, getRequestTypeLabel, isAssignmentType } from '@/lib/requestTypeBadge';
import { parseAndLocalizeTimelineNote } from '@/lib/timelineNoteLocalization';

interface RequestItem {
  id: number;
  model_id?: number | null;
  barang_id?: number | null;
  brand: string;
  name?: string;
  spec?: string;
  category: string;
  category_code?: string;
  subcategory: string;
  type?: string;
  quantity: number;
  assets: string[];
  imageUrl?: string | null;
  is_consumable?: boolean;
  reasoning?: string | null;
}

interface RequestDetail {
  id: number;
  number: string;
  form_url?: string | null;
  is_manual?: boolean;
  requester: string;
  requester_department?: string | null;
  approver: string;
  approval_by?: string;
  confirmation_by?: string;
  return_confirmed_by?: string;
  created_by_admin?: string;
  createdAt: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string | null;
  durationEnd?: string | null;
  durationDays?: number;
  durationHours?: number;
  status: string;
  raw_status?: string;
  type: 'permintaan' | 'peminjaman';
  specific_type?: 'consumable' | 'st' | 'rent' | 'lt';
  items: RequestItem[];
  returnTime: string;
  location: string;
  method: string;
  logs?: any[];
}

interface Props {
  returnId: string | number;
  request: RequestDetail;
  user?: {
    name?: string;
    email?: string;
  };
}

const props = defineProps<Props>();
const { t } = useI18n();

const items = computed(() => props.request.items || []);

const typeKey = computed(() => {
  return props.request.specific_type || (props.request.type === 'peminjaman' ? 'st' : 'consumable');
});

const typeLabelTitle = computed(() => getRequestTypeLabel(typeKey.value, t));
const typeBadgeClass = computed(() => getRequestTypeBadgeClass(typeKey.value));
const isAssignment = computed(() => isAssignmentType(typeKey.value));

// Asset Detail Modal
const isAssetModalOpen = ref(false);
const selectedAssetData = ref<any>(null);
const isLoadingAsset = ref(false);

const openAssetDetail = async (assetNumber: string) => {
  if (!assetNumber) return;
  isLoadingAsset.value = true;
  try {
    const response = await axios.get(`/smart/stock-management/assets/by-code/${encodeURIComponent(assetNumber)}`);
    selectedAssetData.value = response.data;
    isAssetModalOpen.value = true;
  } catch (err: any) {
    console.error('Failed to load asset details:', err);
    toast.error(t('inventory.fetch_asset_failed', 'Gagal memuat detail aset.'));
  } finally {
    isLoadingAsset.value = false;
  }
};

// Return Confirmation Modal
const isConfirmReturnModalOpen = ref(false);
const openConfirmReturnModal = () => {
  isConfirmReturnModalOpen.value = true;
};

interface TimelineStep {
  title: string;
  time?: string;
  status: 'done' | 'active' | 'rejected' | 'pending';
  user?: string;
  description?: string;
  actionType?: 'confirm-return' | 'other';
}

const timelineSteps = computed((): TimelineStep[] => {
  const r = props.request;
  if (!r) return [];

  const steps: TimelineStep[] = [];
  const isManual = Boolean(r.is_manual || r.form_url);
  const adminName = r.created_by_admin || r.confirmation_by || 'Admin';

  if (isManual) {
    // For manual/Admin-driven requests, combine creation & confirmation into a unified step
    steps.push({
      title: t('fulfillment.manualTimelineTitle', { type: typeLabelTitle.value }),
      time: r.createdAt,
      status: 'done',
      description: t('fulfillment.manualTimelineDesc', {
        type: typeLabelTitle.value,
        requester: `<span class="font-bold text-foreground">${r.requester}</span>`,
        admin: `<span class="font-bold text-foreground">${adminName}</span>`
      })
    });
  } else {
    // Step 1: Initial creation (User-driven requests)
    steps.push({
      title: t('fulfillment.requestCreated'),
      time: r.createdAt,
      status: 'done',
      description: t('requests.timeline.createdDesc', { 
        type: typeLabelTitle.value,
        user: `<span class="font-bold text-foreground">${r.requester}</span>` 
      })
    });
  }

  // Step 2: Historical Logs
  if (r.logs && Array.isArray(r.logs)) {
    const sortedLogs = [...r.logs].sort((a, b) => a.id - b.id);
    sortedLogs.forEach(log => {
      if (log.status_to === 'wait') return;

      // Skip initial draft -> borrow/confirm log already captured in manual step
      if (isManual && (log.status_from === 'draft' || log.status_from === 'wait')) {
        return;
      }

      let title = '';
      let status: 'done' | 'active' | 'rejected' | 'pending' = 'done';
      let description = log.note || '';

      const localizedLog = parseAndLocalizeTimelineNote(log.note, t, isAssignment.value);

      if (localizedLog) {
        title = localizedLog.title || '';
        description = localizedLog.description;
      } else if (log.status_to === 'approve') {
        const approverName = log.user || r.approval_by || r.approver || 'Manager';
        title = t('fulfillment.approved');
        description = t('requests.timeline.approvedByManager', {
          name: `<span class="font-bold text-foreground">${approverName}</span>`
        });
      } else if (log.status_to === 'partial') {
        title = t('fulfillment.partiallyApproved');
      } else if (log.status_to === 'confirm') {
        const adminActor = log.user || r.confirmation_by || 'Admin';
        title = log.status_from?.includes('partial')
          ? t('fulfillment.additionalAllocationConfirmed')
          : (log.note && log.note.includes('diatur oleh pengguna')
              ? t('fulfillment.handoverScheduleSet')
              : t('fulfillment.confirmed'));
        if (log.note) {
          description = log.note.replace(/Admin:\s*([^.<]+)/, 'Admin: <span class="font-bold text-foreground">$1</span>');
        } else {
          description = t('fulfillment.confirmedByAdmin', {
            type: typeLabelTitle.value,
            user: `<span class="font-bold text-foreground">${adminActor}</span>`
          });
        }
      } else if (log.status_to === 'borrow') {
        title = isAssignment.value ? t('fulfillment.handoverCompletedAndAssigned') : t('fulfillment.handoverCompletedAndBorrowed');
        description = description || (isAssignment.value ? t('fulfillment.assetDeliveredAndAssigned') : t('fulfillment.assetDeliveredAndBorrowed'));
      } else if (log.status_to === 'return') {
        title = t('fulfillment.returnSubmitted');
      } else if (log.status_to === 'success') {
        title = log.status_from === 'return' ? t('fulfillment.returnCompleted') : t('fulfillment.handoverCompleted');
        if (log.status_from === 'return') {
          const confirmer = log.user || r.return_confirmed_by || 'Admin';
          description = description || t('fulfillment.confirmedByAdmin', {
            type: typeLabelTitle.value,
            user: `<span class="font-bold text-foreground">${confirmer}</span>`
          });
        }
      } else if (log.status_to === 'reject') {
        title = t('fulfillment.rejectedTimeline');
        status = 'rejected';
        description = t('fulfillment.rejectedDesc', { type: typeLabelTitle.value });
      } else if (log.status_to === 'cancel') {
        title = t('fulfillment.cancelledByUser');
        status = 'rejected';
      } else if (log.status_to === 'pending') {
        if (log.status_from === 'confirm') {
          title = t('fulfillment.partialHandoverReceived');
          status = 'done';
        } else {
          title = t('fulfillment.pendingBtn');
          status = 'pending';
        }
      }

      if (title) {
        steps.push({
          title,
          time: log.time,
          status,
          user: log.user || undefined,
          description
        });
      }
    });
  }

  // Step 3: Active step if waiting for return confirmation
  const currentStatus = r.raw_status || r.status;
  const isFinalStatus = ['success', 'reject', 'cancel'].includes(currentStatus);
  if (!isFinalStatus && currentStatus === 'return') {
    const returnDesc = `
      <div class="space-y-0.5 mt-1 text-xs">
        <p><span class="text-muted-foreground">${t('fulfillment.method')}:</span> <span class="font-medium text-foreground">${r.method || '-'}</span></p>
        <p><span class="text-muted-foreground">${t('fulfillment.location')}:</span> <span class="font-medium text-foreground">${r.location || '-'}</span></p>
      </div>
    `;

    steps.push({
      title: t('fulfillment.assetReturnTimeline'),
      time: r.returnTime !== '-' ? r.returnTime : undefined,
      status: 'active',
      description: returnDesc,
      actionType: 'confirm-return',
    });
  }

  return steps;
});

const activeStepIndex = computed(() => {
  const idx = timelineSteps.value.findIndex(s => s.status === 'active');
  if (idx !== -1) return idx + 1;
  return timelineSteps.value.length + 1;
});
</script>

<template>
  <Head :title="t('fulfillment.returnDetailTitle') + ' - ' + request.number" />

  <AppLayout :title="t('fulfillment.returnDetailTitle')">
    <!-- ── In-line Breadcrumb & Back Navigation ── -->
    <div class="flex items-center justify-between gap-4 mb-6">
      <Breadcrumb>
        <BreadcrumbList class="text-xs md:text-sm">
          <BreadcrumbItem>
            <Link :href="route('smart.returns')" class="text-muted-foreground hover:text-foreground transition-colors font-medium">
              {{ t('fulfillment.returns') }}
            </Link>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <span class="text-foreground font-semibold">{{ request.number }}</span>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      <Link :href="route('smart.returns')">
        <Button variant="outline" size="sm" class="flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-[0.875rem]">
          <ArrowLeft class="w-3.5 h-3.5" />
          {{ t('fulfillment.returns') }}
        </Button>
      </Link>
    </div>

    <!-- ── Grid Layout Dua Kolom ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Kolom Kiri (Detail Pengembalian & Daftar Barang) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Detail Card -->
        <div class="bg-card border border-border rounded-[0.875rem] p-5 space-y-2">
          <div class="space-y-1">
            <div class="flex items-center justify-between flex-wrap gap-2 pb-1">
              <h2 class="text-base font-bold text-foreground">
                <span class="font-normal text-muted-foreground">{{ t('fulfillment.number') }}: </span>{{ request.number }}
              </h2>
              <div class="flex flex-wrap items-center gap-1.5">
                <span 
                  class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold"
                  :class="typeBadgeClass"
                >
                  {{ typeLabelTitle }}
                </span>
                <span 
                  v-for="badge in getRequestStatusBadges(request.raw_status || request.status)" 
                  :key="badge.label" 
                  class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold"
                  :class="badge.class"
                >
                  {{ badge.label }}
                </span>
              </div>
            </div>

            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.requester') }}:</span> 
              <span class="font-semibold ml-1 text-foreground">{{ request.requester }}</span>
              <span v-if="request.requester_department" class="text-muted-foreground text-xs ml-1">
                ({{ request.requester_department }})
              </span>
            </p>

            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.picApproval') }}</span> 
              <span class="font-semibold ml-1">{{ request.approval_by || request.approver || '-' }}</span>
            </p>

            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.utilization') }}:</span> 
              <span class="font-semibold ml-1">
                {{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}
              </span>
            </p>

            <p v-if="request.durationStart" class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.duration') }}</span>
              <span class="font-medium ml-1">
                <template v-if="request.durationEnd">
                  {{ request.durationStart }} {{ t('fulfillment.until') }} {{ request.durationEnd }} ({{ request.durationDays || 0 }} {{ t('fulfillment.days') }}, {{ request.durationHours || 0 }} {{ t('fulfillment.hours') }})
                </template>
                <template v-else>
                  {{ request.durationStart }} {{ t('fulfillment.until') }} - ({{ t('fulfillment.noDeadline') }})
                </template>
              </span>
            </p>

            <p v-if="request.returnTime && request.returnTime !== '-'" class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.time') }}:</span>
              <span class="font-semibold ml-1 text-foreground">{{ request.returnTime }}</span>
            </p>

            <p v-if="request.location && request.location !== '-'" class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.location') }}:</span>
              <span class="font-semibold ml-1 text-foreground">{{ request.location }}</span>
            </p>

            <p v-if="request.method && request.method !== '-'" class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.method') }}:</span>
              <span class="font-semibold ml-1 text-foreground">{{ request.method }}</span>
            </p>

            <p v-if="request.form_url" class="text-sm text-foreground">
              <span class="text-muted-foreground">{{ t('fulfillment.physicalForm') }}:</span>
              <a 
                :href="request.form_url" 
                target="_blank" 
                rel="noopener noreferrer"
                class="font-semibold ml-1 text-primary hover:underline inline-flex items-center gap-1"
              >
                <span>{{ t('fulfillment.viewForm') }}</span>
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
            </p>

            <p class="text-xs text-muted-foreground pt-1">
              <span>{{ t('fulfillment.typeCreatedAt', { type: typeLabelTitle }) }}</span>
              <span class="font-medium text-foreground/80 ml-1">{{ request.createdAt }}</span>
            </p>
          </div>
        </div>

        <!-- Items Card with ScrollArea -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">{{ t('fulfillment.itemsListPrefix') }}</p>
            <div class="text-xs font-semibold text-muted-foreground">
              {{ t('fulfillment.totalRequested') }} <span class="text-foreground font-bold">{{ items.reduce((acc, it) => acc + (Number(it.quantity) || 0), 0) }}</span>
            </div>
          </div>

          <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-25rem)] min-h-[400px]">
            <div class="p-3 sm:p-5 space-y-4">
              <AssetItemCard 
                v-for="item in items" 
                :key="item.id"
                :brand="item.brand"
                :name="item.name"
                :spec="item.spec"
                :category="item.category"
                :subcategory="item.subcategory"
                :quantity="item.quantity"
                :assets="item.assets"
                :imageUrl="item.imageUrl"
                :is-consumable="item.is_consumable"
                @view-asset="openAssetDetail"
              />
            </div>
          </ScrollArea>
        </div>
      </div>

      <!-- Kolom Kanan (Timeline / Tahapan) -->
      <div class="space-y-6">
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sm:p-6 relative">
          <p class="text-xs text-muted-foreground font-medium mb-4">{{ t('fulfillment.stagesFor', { type: typeLabelTitle }) }}</p>

          <!-- shadcn-vue Vertical Stepper -->
          <Stepper
            orientation="vertical"
            :model-value="activeStepIndex"
            :linear="false"
            class="flex flex-col gap-6"
          >
            <StepperItem
              v-for="(step, idx) in timelineSteps"
              :key="idx"
              :step="idx + 1"
              :completed="step.status === 'done'"
              v-slot="{ state }"
              class="relative flex items-start gap-4 group"
            >
              <!-- Stepper Separator (Connecting Line) -->
              <StepperSeparator
                v-if="idx !== timelineSteps.length - 1"
                class="absolute left-4 -translate-x-1/2 top-8 -bottom-6 w-0.5 bg-border"
                :class="[
                  (step.status === 'done' || state === 'completed')
                    ? '!bg-green-700'
                    : 'group-data-[state=completed]:!bg-green-700'
                ]"
              />

              <!-- Stepper Indicator -->
              <StepperTrigger as-child class="cursor-default pointer-events-none">
                <StepperIndicator
                  class="z-10 w-8 h-8 rounded-full text-xs font-semibold shrink-0 border transition-all"
                  :class="[
                    step.status === 'rejected'
                      ? '!bg-destructive/10 !text-destructive !border-destructive/40'
                      : (step.status === 'done' || state === 'completed')
                        ? '!bg-green-700 !text-white !border-green-700'
                        : state === 'active'
                          ? '!bg-amber-500/15 !text-amber-600 dark:!text-amber-400 !border-amber-500 ring-2 ring-amber-500/40 ring-offset-2 ring-offset-background'
                          : '!bg-muted !text-muted-foreground !border-border'
                  ]"
                >
                  <Check v-if="step.status === 'done' || state === 'completed'" class="w-4 h-4 text-white" />
                  <X v-else-if="step.status === 'rejected'" class="w-4 h-4" />
                  <Clock v-else-if="state === 'active'" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                  <span v-else>{{ idx + 1 }}</span>
                </StepperIndicator>
              </StepperTrigger>

              <!-- Stepper Content -->
              <div class="flex-1 min-w-0 pt-0.5">
                <div class="flex flex-col gap-0.5">
                  <StepperTitle
                    class="text-sm transition-colors"
                    :class="[
                      step.status === 'rejected'
                        ? 'text-destructive font-bold'
                        : state === 'active'
                          ? 'text-amber-700 dark:text-amber-400 font-bold'
                          : (step.status === 'done' || state === 'completed')
                            ? 'text-green-700 font-semibold'
                            : 'text-muted-foreground font-medium'
                    ]"
                  >
                    {{ step.title }}
                  </StepperTitle>

                  <div v-if="step.time" class="text-[11px] text-muted-foreground">
                    <span>{{ step.time }}</span>
                  </div>

                  <StepperDescription
                    v-if="step.description"
                    class="text-xs text-muted-foreground leading-relaxed"
                  >
                    <span v-html="step.description"></span>
                  </StepperDescription>
                </div>

                <!-- Stepper Action Button (Primary Button for Konfirmasi Pengembalian) -->
                <div v-if="step.actionType === 'confirm-return'" class="mt-3">
                  <Button 
                    @click="openConfirmReturnModal"
                    variant="primary"
                    size="sm"
                    class="font-semibold text-xs h-8 px-4 bg-[#4F46E5] hover:bg-[#4338CA] text-white"
                  >
                    {{ t('fulfillment.confirmReturn') }}
                  </Button>
                </div>
              </div>
            </StepperItem>
          </Stepper>
        </div>
      </div>
    </div>

    <!-- Detail Asset Modal -->
    <DetailAssetModal
      v-model:open="isAssetModalOpen"
      :asset="selectedAssetData"
    />

    <!-- Return Confirmation Modal -->
    <ReturnConfirmationModal
      v-model:open="isConfirmReturnModalOpen"
      :request="request"
    />
  </AppLayout>
</template>
