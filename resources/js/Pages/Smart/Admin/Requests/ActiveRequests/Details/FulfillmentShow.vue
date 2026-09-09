<script setup lang="ts">
/**
 * Unified Request Fulfillment Show Page (Admin)
 * Displays detailed request lifecycle, requester info, PIC approval,
 * asset allocation with accordion view (purple/green/red color-coded),
 * "Pilih Alokasi Aset" modal datatable, consumable LOT breakdown,
 * and fulfillment confirmation stepper workflow.
 */
import { ref, computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
import { 
  Check,
  Clock, 
  X, 
  AlertCircle,
  Package
} from 'lucide-vue-next';
import { REQUEST_STATUS_PILL_BASE, getRequestStatusBadgeClass, getRequestStatusLabel, getRequestStatusBadges } from '@/lib/requestStatus';
import PilihAlokasiAset from './modals/pilihAlokasiAset.vue';
import KonfirmasiPemenuhan from './modals/konfirmasiPemenuhan.vue';

// --- Types ---
interface AllocationSlot {
  slot_number: number;
  fulfillment_id: number | null;
  unit_id: number | null;
  asset_number: string | null;
  state: 'assigned' | 'borrowed' | 'unfulfilled';
  color: 'purple' | 'green' | 'red';
  label: string;
  is_locked: boolean;
}

interface AvailableUnit {
  id: number;
  asset_code: string;
  lot_code: string;
  status: string;
  condition: string;
  storage_location: string;
  is_currently_assigned: boolean;
  is_locked: boolean;
}

interface LotFulfillment {
  fulfillment_id: number;
  lot_id: number;
  lot_number: string;
  brand_name: string;
  item_name: string;
  specification: string;
  quantity_fulfilled: number;
  storage_location: string;
  date_of_receipt: string;
}

interface FulfillmentItem {
  id: number;
  barang_id?: number;
  subcategory_id?: number;
  category: string;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  quantity_requested: number;
  is_consumable: boolean;
  imageUrl?: string | null;
  uom: string;
  status?: string;
  allocation_slots?: AllocationSlot[];
  available_units?: AvailableUnit[];
  assets?: string[];
  lot_fulfillments?: LotFulfillment[];
  consumable_summary?: {
    quantity_requested: number;
    quantity_fulfilled: number;
    is_fully_fulfilled: boolean;
  };
}

interface FulfillmentRequestData {
  id: number;
  uuid: string;
  number: string;
  type: 'permintaan' | 'peminjaman';
  typeLabel: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  destination: string;
  reasoning: string;
  durationStart?: string | null;
  durationEnd?: string | null;
  durationDays?: number;
  durationHours?: number;
  borrowPeriod?: string | null;
  status: string;
  raw_status: string;
  created_at: string;
  requester: string;
  requester_email?: string;
  requester_department?: string;
  approver_name?: string;
  approval_by?: string;
  approval_at?: string;
  approval?: {
    id: number;
    note?: string | null;
    decision?: string;
    decided_at?: string;
    approver_name?: string;
  } | null;
  confirmation_by?: string;
  confirmation_at?: string;
  handover_method?: string;
  handover_time?: string;
  handover_location?: string;
  handover_note?: string;
  logs?: any[];
  items: FulfillmentItem[];
  fulfillment_summary: {
    total_items: number;
    total_quantity_requested: number;
    total_quantity_assigned: number;
    is_all_assigned: boolean;
    can_confirm_full: boolean;
    can_confirm_partial: boolean;
  };
}

interface Props {
  user: any;
  request: FulfillmentRequestData;
}

const props = defineProps<Props>();

const page = usePage();

// Navigation parent context (Permintaan Aktif)
const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
const fromQuery = urlParams.get('from');
const isFromPartial = computed(() => fromQuery === 'parsial' || props.request.raw_status === 'partial');
const parentLabel = 'Permintaan Aktif';
const parentRoute = computed(() => route('smart.requests.index', { tab: isFromPartial.value ? 'Parsial' : 'Perlu Alokasi' }));

const request = computed(() => props.request);
const isPeminjaman = computed(() => request.value.type === 'peminjaman');
const typeLabel = computed(() => isPeminjaman.value ? 'peminjaman' : 'permintaan');
const typeLabelTitle = computed(() => isPeminjaman.value ? 'Peminjaman' : 'Permintaan');


// ─────────────────────────────────────────────
// Modals State
// ─────────────────────────────────────────────
const isAssetModalOpen = ref(false);
const activeItemForAllocation = ref<FulfillmentItem | null>(null);

const openAllocationModal = (item: FulfillmentItem) => {
  activeItemForAllocation.value = item;
  isAssetModalOpen.value = true;
};

const isConfirmModalOpen = ref(false);
const openConfirmationModal = () => {
  isConfirmModalOpen.value = true;
};


// ─────────────────────────────────────────────
// Timeline Stepper Setup
// ─────────────────────────────────────────────
interface TimelineStep {
  title: string;
  time?: string;
  status: 'done' | 'active' | 'pending' | 'rejected';
  description?: string;
  user?: string;
  actionType?: 'confirm-allocation' | 'other';
}

const timelineSteps = computed((): TimelineStep[] => {
  const r = request.value;
  if (!r) return [];
  const steps: TimelineStep[] = [];

  // Step 1: Created
  steps.push({
    title: `${typeLabelTitle.value} dibuat`,
    time: r.created_at,
    status: 'done'
  });

  // Step 2: Historical Logs
  if (r.logs && Array.isArray(r.logs)) {
    const sortedLogs = [...r.logs].sort((a, b) => a.id - b.id);
    sortedLogs.forEach(log => {
      if (log.status_to === 'wait') return;

      let title = '';
      let status: 'done' | 'rejected' | 'pending' = 'done';
      let description = log.note || '';

      if (log.status_to === 'approve') {
        const approverName = log.user || r.approval_by || r.approver_name || '-';
        title = 'Di-approve';
        description = `${typeLabelTitle.value} disetujui Manager: <span class="font-bold text-foreground">${approverName}</span>${r.approval?.note ? `<br>Catatan: ${r.approval.note}` : ''}`;
      } else if (log.status_to === 'partial' || log.status_to?.includes('partial')) {
        const adminName = log.user || r.confirmation_by || 'Admin';
        title = 'Disetujui Sebagian (Parsial)';
        if (log.note) {
          description = log.note.replace(/Admin:\s*([^.<]+)/, 'Admin: <span class="font-bold text-foreground">$1</span>');
        } else {
          description = `${typeLabelTitle.value} disetujui sebagian oleh Admin: <span class="font-bold text-foreground">${adminName}</span>.`;
        }
      } else if (log.status_to === 'menunggu_serah_terima' || log.status_to === 'confirm') {
        const adminName = log.user || r.confirmation_by || 'Admin';
        title = log.status_from?.includes('partial') ? 'Alokasi Penuh Dikonfirmasi' : 'Dikonfirmasi Admin';
        if (log.note) {
          description = log.note.replace(/Admin:\s*([^.<]+)/, 'Admin: <span class="font-bold text-foreground">$1</span>');
        } else {
          description = `${typeLabelTitle.value} dikonfirmasi oleh Admin: <span class="font-bold text-foreground">${adminName}</span>.`;
        }
      } else if (log.status_to === 'borrow') {
        title = 'Serah Terima Selesai & Dipinjam';
        description = description || 'Aset telah diserahkan dan dipinjam.';
      } else if (log.status_to === 'reject') {
        title = 'Ditolak';
        status = 'rejected';
        description = `${typeLabelTitle.value} ditolak.`;
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

  // Step 3: Active step in Admin Fulfillment
  if (r.raw_status === 'confirm') {
    steps.push({
      title: 'Konfirmasi Alokasi',
      status: 'active',
      description: `Konfirmasi alokasi untuk memenuhi ${typeLabel.value} ini.`,
      actionType: 'confirm-allocation'
    });
  } else if (r.raw_status?.includes('partial')) {
    steps.push({
      title: 'Alokasi Sisa Unit (Parsial)',
      status: 'active',
      description: `Alokasikan sisa barang atau konfirmasi pemenuhan lanjutan.`,
      actionType: 'confirm-allocation'
    });
  } else if (r.raw_status?.includes('menunggu_serah_terima') || r.raw_status === 'handover') {
    steps.push({
      title: 'Menunggu Serah Terima',
      status: 'active',
      description: 'Menunggu serah terima barang kepada pemohon.'
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
  <Head :title="'Alokasi ' + request.number" />

  <AppLayout :title="'Alokasi ' + typeLabelTitle">
    <!-- ── Breadcrumb ── -->
    <Breadcrumb>
      <BreadcrumbList class="pb-3 text-xs md:text-sm">
        <BreadcrumbItem>
          <Link :href="parentRoute" class="text-muted-foreground hover:text-foreground transition-colors font-medium">
            {{ parentLabel }}
          </Link>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-foreground font-semibold">{{ request.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- ── Grid Layout Dua Kolom ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Kolom Kiri (Detail Permintaan & Daftar Barang) -->
      <div class="lg:col-span-2 space-y-6">
        
        <!-- Card Detail Info -->
        <div class="bg-card border border-border rounded-[0.875rem] p-5 space-y-2">
          <div class="space-y-1">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <h2 class="text-base font-bold text-foreground">
                <span class="font-normal text-muted-foreground">Nomor: </span>{{ request.number }}
              </h2>
              <div class="flex flex-wrap items-center gap-1.5">
                <span 
                  v-for="badge in getRequestStatusBadges(request.raw_status)" 
                  :key="badge.label" 
                  :class="badge.pillClass"
                >
                  {{ badge.label }}
                </span>
              </div>
            </div>

            <!-- Nama Pemohon (Di atas PIC Approval sesuai requirement) -->
            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">Nama pemohon:</span> 
              <span class="font-semibold ml-1 text-foreground">
                {{ request.requester || '-' }}
              </span>
              <span v-if="request.requester_department" class="text-muted-foreground text-xs ml-1">
                ({{ request.requester_department }})
              </span>
            </p>

            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">PIC Approval:</span> 
              <span class="font-semibold ml-1">
                {{ request.approver_name || '-' }}
              </span>
            </p>
            
            <p class="text-sm text-foreground">
              <span class="text-muted-foreground">Pemanfaatan:</span> 
              <span class="font-semibold ml-1">
                {{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}
              </span>
            </p>

            <p v-if="isPeminjaman && request.durationStart" class="text-sm text-foreground">
              <span class="text-muted-foreground">Durasi:</span>
              <span class="font-medium ml-1">
                <template v-if="request.durationEnd">
                  {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)
                </template>
                <template v-else>
                  {{ request.durationStart }} s.d. - (Tanpa Tenggat Waktu)
                </template>
              </span>
            </p>

            <p class="text-xs text-muted-foreground pt-1">
              <span>{{ typeLabelTitle }} dibuat pada:</span>
              <span class="font-medium text-foreground/80 ml-1">{{ request.created_at }}</span>
            </p>
          </div>
        </div>

        <!-- Card Daftar Barang -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-muted-foreground font-medium">Daftar Barang & Alokasi:</p>
            <div class="text-xs font-semibold text-muted-foreground">
              Total Diminta: <span class="text-foreground font-bold">{{ request.fulfillment_summary.total_quantity_requested }}</span> | 
              Teralokasi: <span :class="request.fulfillment_summary.is_all_assigned ? 'text-emerald-600 font-bold' : 'text-amber-600 font-bold'">{{ request.fulfillment_summary.total_quantity_assigned }}</span>
            </div>
          </div>
          
          <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-25rem)] min-h-[400px]">
            <div class="p-3 sm:p-5 space-y-4">
              <AssetItemCard 
                v-for="item in request.items" 
                :key="item.id"
                :brand="item.brand !== '-' ? (item.name && item.name !== 'Tidak Spesifik' ? `${item.brand} ${item.name} ${item.spec}` : `${item.brand} ${item.spec}`) : item.subcategory"
                :category="item.category"
                :subcategory="item.subcategory"
                :quantity="item.quantity_requested"
                :uom="item.uom"
                :image-url="item.imageUrl"
                :is-consumable="item.is_consumable"
                :allocation-slots="item.allocation_slots"
                :lot-fulfillments="item.lot_fulfillments"
                :consumable-summary="item.consumable_summary"
                :show-allocation-action="true"
                @select-allocation="openAllocationModal(item)"
              />
            </div>
          </ScrollArea>
        </div>

      </div>

      <!-- Kolom Kanan (Tahapan & Stepper Workflow) -->
      <div class="space-y-6">
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sm:p-6 relative">
          <p class="text-xs text-muted-foreground font-medium mb-4">Tahapan {{ typeLabelTitle }}:</p>

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
              <!-- Stepper Separator -->
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

                <!-- Stepper Action Button (Primary Button for Konfirmasi Alokasi) -->
                <div v-if="step.actionType === 'confirm-allocation'" class="mt-3">
                  <Button 
                    @click="openConfirmationModal"
                    variant="primary"
                    size="sm"
                    class="font-semibold text-xs h-8 px-4 bg-[#4F46E5] hover:bg-[#4338CA] text-white"
                  >
                    Konfirmasi Alokasi
                  </Button>
                </div>
              </div>
            </StepperItem>
          </Stepper>
        </div>
      </div>

    </div>

    <!-- Modal: Pilih Alokasi Aset -->
    <PilihAlokasiAset
      v-model:open="isAssetModalOpen"
      :item="activeItemForAllocation"
    />

    <!-- Modal: Konfirmasi Alokasi Pemenuhan -->
    <KonfirmasiPemenuhan
      v-model:open="isConfirmModalOpen"
      :request="request"
    />

  </AppLayout>
</template>
