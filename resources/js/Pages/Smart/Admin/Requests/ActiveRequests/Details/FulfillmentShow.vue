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
import TableSearch from '@/Components/TableSearch.vue';
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
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/Components/ui/dialog";
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  Check,
  Clock, 
  X, 
  AlertCircle,
  Package,
  CheckCircle2,
  AlertTriangle
} from 'lucide-vue-next';
import { REQUEST_STATUS_PILL_BASE, getRequestStatusBadgeClass, getRequestStatusLabel } from '@/lib/requestStatus';

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
// Asset Allocation Modal State
// ─────────────────────────────────────────────
const isAssetModalOpen = ref(false);
const activeItemForAllocation = ref<FulfillmentItem | null>(null);
const tempSelectedUnitIds = ref<number[]>([]);
const unitSearchQuery = ref('');
const isSavingAllocation = ref(false);

const openAllocationModal = (item: FulfillmentItem) => {
  activeItemForAllocation.value = item;
  unitSearchQuery.value = '';
  
  // Pre-fill selected units with current allocations
  const currentAssigned = (item.allocation_slots || [])
    .filter(slot => slot.unit_id !== null)
    .map(slot => slot.unit_id as number);

  tempSelectedUnitIds.value = [...currentAssigned];
  isAssetModalOpen.value = true;
};

const closeAllocationModal = () => {
  isAssetModalOpen.value = false;
  activeItemForAllocation.value = null;
  tempSelectedUnitIds.value = [];
};

const filteredAvailableUnits = computed(() => {
  const item = activeItemForAllocation.value;
  if (!item || !item.available_units) return [];

  const q = unitSearchQuery.value.trim().toLowerCase();
  if (!q) return item.available_units;

  return item.available_units.filter(u => 
    (u.asset_code && u.asset_code.toLowerCase().includes(q)) ||
    (u.lot_code && u.lot_code.toLowerCase().includes(q)) ||
    (u.status && u.status.toLowerCase().includes(q)) ||
    (u.condition && u.condition.toLowerCase().includes(q)) ||
    (u.storage_location && u.storage_location.toLowerCase().includes(q))
  );
});

const isUnitSelected = (unitId: number): boolean => {
  return tempSelectedUnitIds.value.includes(unitId);
};

const toggleUnitSelection = (unit: AvailableUnit) => {
  if (unit.is_locked) return; // Locked units cannot be toggled

  const idx = tempSelectedUnitIds.value.indexOf(unit.id);
  if (idx !== -1) {
    // Deselect
    tempSelectedUnitIds.value.splice(idx, 1);
  } else {
    // Select if under max quantity
    const max = activeItemForAllocation.value?.quantity_requested || 1;
    if (tempSelectedUnitIds.value.length >= max) {
      toast.warning(`Maksimal ${max} unit aset untuk barang ini.`);
      return;
    }
    tempSelectedUnitIds.value.push(unit.id);
  }
};

const saveAllocation = () => {
  const item = activeItemForAllocation.value;
  if (!item) return;

  isSavingAllocation.value = true;
  router.post(route('smart.fulfillment.items.assign', item.id), {
    unit_ids: tempSelectedUnitIds.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSavingAllocation.value = false;
      closeAllocationModal();
      toast.success('Alokasi unit aset berhasil diperbarui.');
    },
    onError: (errs) => {
      isSavingAllocation.value = false;
      toast.error(Object.values(errs).join(', '));
    }
  });
};

// ─────────────────────────────────────────────
// Confirmation Modal State (Full / Partial)
// ─────────────────────────────────────────────
const isConfirmModalOpen = ref(false);
const confirmationNote = ref('');
const isSubmittingConfirmation = ref(false);

const openConfirmationModal = () => {
  confirmationNote.value = '';
  isConfirmModalOpen.value = true;
};

const closeConfirmationModal = () => {
  isConfirmModalOpen.value = false;
};

const submitConfirmation = () => {
  const summary = request.value.fulfillment_summary;
  const isFull = summary.can_confirm_full;
  const allowPartial = !isFull && summary.can_confirm_partial;

  if (!isFull && !allowPartial) {
    toast.error('Belum ada barang yang dialokasikan. Alokasikan setidaknya satu barang.');
    return;
  }

  isSubmittingConfirmation.value = true;
  router.post(route('smart.fulfillment.confirm', request.value.uuid), {
    allow_partial: allowPartial,
    note: confirmationNote.value,
  }, {
    onSuccess: () => {
      isSubmittingConfirmation.value = false;
      closeConfirmationModal();
      toast.success(isFull 
        ? 'Alokasi penuh berhasil dikonfirmasi! Permintaan beralih ke tahap Serah Terima.'
        : 'Alokasi parsial berhasil dikonfirmasi! Permintaan beralih ke Parsial.'
      );
    },
    onError: (errs) => {
      isSubmittingConfirmation.value = false;
      toast.error(Object.values(errs).join(', '));
    }
  });
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
      } else if (log.status_to === 'partial') {
        const adminName = log.user || r.confirmation_by || 'Admin';
        title = 'Disetujui sebagian (Partial)';
        if (log.note) {
          description = log.note.replace(/Admin:\s*([^.<]+)/, 'Admin: <span class="font-bold text-foreground">$1</span>');
        } else {
          description = `${typeLabelTitle.value} disetujui sebagian oleh Admin: <span class="font-bold text-foreground">${adminName}</span>.`;
        }
      } else if (log.status_to === 'confirm') {
        const adminName = log.user || r.confirmation_by || 'Admin';
        title = log.status_from === 'partial' ? 'Alokasi Tambahan Dikonfirmasi' : 'Dikonfirmasi Admin';
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
  if (r.raw_status === 'confirm' || r.raw_status === 'partial') {
    steps.push({
      title: 'Konfirmasi Alokasi',
      status: 'active',
      description: `Konfirmasi alokasi untuk memenuhi ${typeLabel.value} ini.`,
      actionType: 'confirm-allocation'
    });
  } else if (r.raw_status === 'handover') {
    steps.push({
      title: 'Serah Terima',
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
              <span :class="[REQUEST_STATUS_PILL_BASE, getRequestStatusBadgeClass(request.raw_status)]">
                {{ getRequestStatusLabel(request.raw_status) }}
              </span>
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
                :show-allocation-action="!item.is_consumable"
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

    <!-- ============================================================
         MODAL: Pilih Alokasi Aset (Unit Datatable)
         ============================================================ -->
    <Dialog :open="isAssetModalOpen" @update:open="val => isAssetModalOpen = val">
      <DialogContent class="sm:max-w-[50rem] rounded-[0.875rem] bg-card p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <div class="flex items-center justify-between pt-4 pb-3 px-6 border-b border-border">
          <div>
            <DialogTitle class="text-base font-bold text-foreground">
              Pilih Alokasi Aset
            </DialogTitle>
            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
              {{ activeItemForAllocation?.brand }} {{ activeItemForAllocation?.name }} - Pilih maksimal {{ activeItemForAllocation?.quantity_requested }} unit aset.
            </DialogDescription>
          </div>
          <button @click="closeAllocationModal" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>

        <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
          <!-- Selection Counter & Search Filter -->
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="text-xs font-semibold">
              Terpilih: 
              <span :class="tempSelectedUnitIds.length === (activeItemForAllocation?.quantity_requested || 0) ? 'text-emerald-600 font-bold' : 'text-primary font-bold'">
                {{ tempSelectedUnitIds.length }} / {{ activeItemForAllocation?.quantity_requested }} unit
              </span>
            </div>

            <div class="w-full sm:w-72">
              <TableSearch 
                v-model="unitSearchQuery" 
                placeholder="Cari kode aset, LOT, lokasi..." 
                bg-class="bg-background"
              />
            </div>
          </div>

          <!-- Unit Datatable -->
          <div class="border border-border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-xs text-left">
                <thead class="bg-muted/50 text-foreground font-semibold border-b border-border">
                  <tr>
                    <th class="p-2.5 w-10 text-center">Pilih</th>
                    <th class="p-2.5">Kode Aset</th>
                    <th class="p-2.5">Kode LOT</th>
                    <th class="p-2.5">Status</th>
                    <th class="p-2.5">Kondisi</th>
                    <th class="p-2.5">Lokasi Penyimpanan</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                  <tr 
                    v-for="unit in filteredAvailableUnits" 
                    :key="unit.id"
                    class="hover:bg-muted/30 transition-colors cursor-pointer"
                    :class="isUnitSelected(unit.id) ? 'bg-primary/5' : ''"
                    @click="toggleUnitSelection(unit)"
                  >
                    <td class="p-2.5 text-center" @click.stop>
                      <input 
                        type="checkbox" 
                        :checked="isUnitSelected(unit.id)"
                        :disabled="unit.is_locked"
                        @change="toggleUnitSelection(unit)"
                        class="rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer disabled:opacity-50"
                      />
                    </td>
                    <td class="p-2.5 font-mono font-bold text-foreground">
                      {{ unit.asset_code }}
                    </td>
                    <td class="p-2.5 font-mono text-muted-foreground">
                      {{ unit.lot_code }}
                    </td>
                    <td class="p-2.5">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                        {{ unit.status }}
                      </span>
                    </td>
                    <td class="p-2.5 text-foreground">
                      {{ unit.condition }}
                    </td>
                    <td class="p-2.5 text-muted-foreground">
                      {{ unit.storage_location }}
                    </td>
                  </tr>

                  <tr v-if="filteredAvailableUnits.length === 0">
                    <td colspan="6" class="p-6 text-center text-muted-foreground">
                      Tidak ada unit aset yang tersedia atau cocok dengan pencarian.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <DialogFooter class="p-4 px-6 border-t border-border bg-muted/10 flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="closeAllocationModal" class="h-8">
            Batal
          </Button>
          <Button 
            variant="primary" 
            size="sm" 
            :disabled="isSavingAllocation"
            @click="saveAllocation" 
            class="h-8 bg-[#4F46E5] hover:bg-[#4338CA] text-white font-semibold"
          >
            {{ isSavingAllocation ? 'Menyimpan...' : 'Simpan Alokasi' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- ============================================================
         MODAL: Konfirmasi Alokasi Pemenuhan
         ============================================================ -->
    <Dialog :open="isConfirmModalOpen" @update:open="val => isConfirmModalOpen = val">
      <DialogContent class="sm:max-w-[32rem] rounded-[0.875rem] bg-card p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
        <div class="flex items-center justify-between pt-4 pb-3 px-6 border-b border-border">
          <DialogTitle class="text-base font-bold text-foreground">
            Konfirmasi Alokasi Permintaan
          </DialogTitle>
          <button @click="closeConfirmationModal" class="p-2 hover:bg-muted rounded-full transition-colors">
            <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
          </button>
        </div>

        <div class="px-6 py-4 space-y-4 text-xs">
          <!-- Status Alert -->
          <div 
            v-if="request.fulfillment_summary.can_confirm_full"
            class="p-3 rounded-lg border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 flex items-start gap-2.5"
          >
            <CheckCircle2 class="w-4 h-4 shrink-0 mt-0.5" />
            <div>
              <p class="font-bold">Pemenuhan Lengkap (100%)</p>
              <p class="mt-0.5 text-xs opacity-90 leading-relaxed">
                Seluruh {{ request.fulfillment_summary.total_quantity_requested }} barang telah teralokasi. Konfirmasi ini akan melanjutkan status permintaan ke <strong>Serah Terima</strong>.
              </p>
            </div>
          </div>

          <div 
            v-else-if="request.fulfillment_summary.can_confirm_partial"
            class="p-3 rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800 text-amber-800 dark:text-amber-300 flex items-start gap-2.5"
          >
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <div>
              <p class="font-bold">Pemenuhan Sebagian (Parsial)</p>
              <p class="mt-0.5 text-xs opacity-90 leading-relaxed">
                Teralokasi {{ request.fulfillment_summary.total_quantity_assigned }} dari {{ request.fulfillment_summary.total_quantity_requested }} barang. Konfirmasi ini akan mengubah status permintaan menjadi <strong>Partial</strong> sehingga pemohon dapat menerima barang yang siap terlebih dahulu.
              </p>
            </div>
          </div>

          <div 
            v-else
            class="p-3 rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/20 dark:border-red-800 text-red-800 dark:text-red-300 flex items-start gap-2.5"
          >
            <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
            <div>
              <p class="font-bold">Belum Ada Barang Teralokasi</p>
              <p class="mt-0.5 text-xs opacity-90 leading-relaxed">
                Anda belum mengalokasikan barang apa pun. Silakan alokasikan unit aset terlebih dahulu sebelum konfirmasi.
              </p>
            </div>
          </div>

          <!-- Catatan Optional -->
          <div class="space-y-1.5 pt-1">
            <label class="text-xs font-semibold text-foreground">Catatan Admin (Opsional):</label>
            <textarea 
              v-model="confirmationNote" 
              rows="3" 
              placeholder="Tuliskan catatan terkait alokasi ini jika diperlukan..."
              class="w-full text-xs rounded-lg border border-input bg-background p-2.5 focus:ring-1 focus:ring-primary focus:outline-none"
            ></textarea>
          </div>
        </div>

        <DialogFooter class="p-4 px-6 border-t border-border bg-muted/10 flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="closeConfirmationModal" class="h-8">
            Batal
          </Button>
          <Button 
            variant="primary" 
            size="sm" 
            :disabled="isSubmittingConfirmation || (!request.fulfillment_summary.can_confirm_full && !request.fulfillment_summary.can_confirm_partial)"
            @click="submitConfirmation" 
            class="h-8 bg-[#4F46E5] hover:bg-[#4338CA] text-white font-semibold"
          >
            {{ isSubmittingConfirmation ? 'Memproses...' : (request.fulfillment_summary.can_confirm_full ? 'Konfirmasi Alokasi Penuh' : 'Konfirmasi Parsial') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>
