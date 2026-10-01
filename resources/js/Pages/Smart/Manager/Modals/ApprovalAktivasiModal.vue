<script setup lang="ts">
/**
 * Manager Activation Approval Modal Component
 * Displays full asset specification, registration history, location, and comprehensive audit trail
 * for newly created assets pending activation approval or already decided.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { X, ThumbsUp, Ban, FileText } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatDate } from '@/lib/utils';
import Tabs from '@/Components/Tabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import JejakAuditTab from '@/Pages/Smart/MultiRoles/Tabs/JejakAuditTab.vue';

interface Props {
  open: boolean;
  approval: any;
  mode: 'pending' | 'decided';
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'approve'): void;
  (e: 'reject'): void;
}>();

const { t, te } = useI18n();

useModalLock(computed(() => props.open && !!props.approval));

const activeTab = ref<'detail' | 'audit'>('detail');

const tabList = computed(() => [t('approvals.assetDetail'), t('approvals.auditTrail')]);

const currentTabLabel = computed({
  get: () => activeTab.value === 'detail' ? t('approvals.assetDetail') : t('approvals.auditTrail'),
  set: (val: string) => {
    if (val === t('approvals.auditTrail')) {
      activeTab.value = 'audit';
    } else {
      activeTab.value = 'detail';
    }
  }
});

watch(() => props.open, (newVal) => {
  if (newVal) {
    activeTab.value = 'detail';
  }
});

const isVehicle = (item: any) => {
  if (!item) return false;
  const category = (item.category || '').toLowerCase();
  const subcategory = (item.subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return '-';
  let num: number;
  if (typeof val === 'string') {
    const cleanStr = val.replace(/[^0-9,-]/g, '');
    if (!cleanStr) return '-';
    num = parseFloat(cleanStr.replace(/,/g, '.'));
  } else {
    num = val;
  }
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null | undefined, floor: string | null, room: string | null) => {
  const parts = [];
  if (loc && loc !== '-') parts.push(loc);
  if (floor && floor !== '-') parts.push(floor);
  if (room && room !== '-') parts.push(room);
  return parts.join(', ') || '-';
};

const openMemoFile = (path?: string | null) => {
  if (!path) return;
  window.open('/media/' + path, '_blank');
};

const getConditionClass = (cond?: string | null) => {
  if (!cond) return 'text-foreground';
  if (cond === 'Bagus' || cond === 'QC Passed') return 'text-emerald-600 font-semibold';
  if (cond === 'Lelang/Hibah') return 'text-purple-600 font-semibold';
  if (cond === 'Rusak' || cond === 'Rusak Total' || cond === 'Hilang' || cond === 'Verifikasi Ditolak') return 'text-rose-600 font-semibold';
  if (cond === 'Belum Diverifikasi') return 'text-amber-600 font-semibold';
  return 'text-foreground';
};

const CONDITION_KEY_MAP: Record<string, string> = {
  'bagus': 'inventory.conditionGood',
  'rusak': 'inventory.conditionDamaged',
  'qc passed': 'inventory.conditionQcPassed',
  'lelang/hibah': 'inventory.conditionAuctionGrant',
  'rusak total': 'inventory.conditionTotalDamage',
  'hilang': 'inventory.conditionLost',
  'belum diverifikasi': 'inventory.conditionUnverified',
  'verifikasi ditolak': 'inventory.conditionVerificationRejected',
};

const getConditionLabel = (cond?: string | null) => {
  if (!cond) return '';
  const key = CONDITION_KEY_MAP[cond.toLowerCase()];
  return key && te(key) ? t(key) : cond;
};
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="open && approval" 
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain"
        @click="emit('update:open', false)"
      >
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open && approval"
            class="bg-card w-full max-w-[95%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col border border-border"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between pt-2 px-4 border-b border-border">
              <Tabs v-model="currentTabLabel" :tabs="tabList" />
              <button @click="emit('update:open', false)" class="p-2 hover:bg-muted rounded-full transition-colors" :aria-label="$t('common.close')">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body contents -->
            <div class="overflow-y-auto max-h-[70vh] px-6 py-3 space-y-4 overscroll-contain">
              
              <!-- ── TAB 1: DETAIL ── -->
              <div v-if="activeTab === 'detail'" class="flex flex-col md:flex-row gap-6">
                <!-- Image Column -->
                <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                  <img 
                    v-if="approval.unit_details.image_url" 
                    :src="approval.unit_details.image_url.startsWith('http') || approval.unit_details.image_url.startsWith('/') ? approval.unit_details.image_url : '/media/' + approval.unit_details.image_url" 
                    class="w-full h-full object-cover" 
                  />
                  <img 
                    v-else 
                    src="https://placehold.co/400x400?text=Placeholder" 
                    class="w-full h-full object-cover opacity-50" 
                  />
                </div>

                <!-- Details Columns -->
                <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4 text-foreground">
                  <!-- Column 1: Item Info -->
                  <div class="md:col-span-3">
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ $t('approvals.typeCode') }}</span> {{ approval.unit_details.barang_code }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ $t('approvals.brandLabel') }}</span> {{ approval.brand }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ $t('approvals.nameLabel') }}</span> {{ approval.nama }}</p>
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ $t('approvals.specLabel') }}</span> {{ approval.specification }}</p>
                    <p class="text-foreground">{{ $t('approvals.categoryLabel') }} {{ approval.category }}</p>
                    <p class="text-foreground">{{ $t('approvals.subcategoryLabel') }} {{ approval.subcategory }}</p>
                    <p class="text-foreground">{{ $t('approvals.unitLabel') }} {{ approval.unit_details.barang_unit }}</p>
                  </div>

                  <!-- Column 2: LOT Info -->
                  <div class="md:col-span-4">
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ $t('approvals.lotCodeLabel') }}</span> {{ approval.unit_details.lot_code }}</p>
                    <p class="text-foreground">{{ $t('approvals.organizerLabel') }} {{ approval.unit_details.organizer }}</p>
                    <p class="text-foreground">{{ $t('approvals.regDateLabel') }} {{ formatDate(approval.unit_details.date_of_receipt) }}</p>
                    <p class="text-foreground">{{ $t('approvals.ageLabel') }} {{ approval.unit_details.age !== undefined && approval.unit_details.age !== null ? `${approval.unit_details.age} ${$t('approvals.years')}` : '-' }}</p>
                    <p class="text-foreground">{{ $t('approvals.vendorLabel') }} {{ approval.unit_details.vendor }}</p>
                    <p class="text-foreground">{{ $t('approvals.poNumberLabel') }} {{ approval.unit_details.po_number }}</p>
                  </div>

                  <!-- Column 3: Asset Info -->
                  <div class="md:col-span-5">
                    <p class="font-bold text-foreground"><span class="text-foreground">{{ $t('approvals.assetCodeLabel') }}</span> {{ approval.asset_code }}</p>
                    <!-- TNKB (Nopol) -->
                    <p v-if="isVehicle(approval)" class="font-bold text-foreground">
                      <span class="text-foreground">{{ $t('approvals.plateLabel') }}</span> {{ approval.unit_details.vehicle_registration || '-' }}
                    </p>

                    <!-- Status & Kondisi for Pending Mode -->
                    <div v-if="mode === 'pending'" class="flex items-center gap-1.5 flex-wrap">
                      <span>{{ $t('inventory.statusAndCondition') }}:</span>
                      <StatusBadge :status="approval.unit_details.status" />
                      <span :class="getConditionClass(approval.unit_details.condition)">
                        {{ getConditionLabel(approval.unit_details.condition) }}
                      </span>
                    </div>

                    <!-- Decided mode fields -->
                    <template v-if="mode === 'decided'">
                      <p class="text-foreground">
                        {{ $t('approvals.decisionLabel') }} 
                        <span 
                          :class="[
                            'font-semibold',
                            approval.decision === 'approved' ? 'text-emerald-600' : 'text-rose-600'
                          ]"
                        >
                          {{ approval.decision === 'approved' ? $t('approvals.approved') : $t('approvals.rejected') }}
                        </span>
                      </p>

                      <!-- Single Status & Kondisi showing current unit status and condition -->
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <span>{{ $t('inventory.statusAndCondition') }}:</span>
                        <StatusBadge :status="approval.unit_details.status" />
                        <span :class="getConditionClass(approval.unit_details.condition)">
                          {{ getConditionLabel(approval.unit_details.condition) }}
                        </span>
                      </div>

                      <p class="text-foreground mt-1.5" v-if="approval.note">
                        {{ $t('approvals.managerNoteLabel') }} <span class="italic text-muted-foreground">"{{ approval.note }}"</span>
                      </p>
                    </template>

                    <p class="text-foreground mt-1.5">{{ $t('approvals.priceLabel') }} {{ formatRupiah(approval.unit_details.price) }}</p>
                    <p class="text-foreground">{{ $t('approvals.storageLocationLabel') }} {{ formatLocation(approval.unit_details.location, approval.unit_details.floor, approval.unit_details.room) }}</p>
                    <p class="text-foreground" v-if="mode === 'pending'">{{ $t('approvals.lastUpdateLabel') }} {{ approval.requested_at }}</p>
                  </div>
                </div>
              </div>

              <!-- ── TAB 2: JEJAK AUDIT ── -->
              <JejakAuditTab 
                v-if="activeTab === 'audit'"
                :lifecycles="approval.unit_details.lifecycles" 
              />
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10 shrink-0">
              <!-- Memo Button -->
              <Button 
                v-if="approval.memo_url"
                @click="openMemoFile(approval.memo_url)"
                variant="warning"
                size="lg"
                class="inline-flex items-center gap-2"
              >
                <FileText class="w-4 h-4" />
                {{ $t('approvals.openMemo') }}
              </Button>
              <!-- Pending mode buttons -->
              <template v-if="mode === 'pending'">
                <!-- Green Approve Button -->
                <Button 
                  @click="emit('approve')"
                  variant="success"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <ThumbsUp class="w-4 h-4" />
                  {{ $t('approvals.approve') }}
                </Button>

                <!-- Red Reject Button -->
                <Button 
                  @click="emit('reject')"
                  variant="destructive"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <Ban class="w-4 h-4" />
                  {{ $t('approvals.reject') }}
                </Button>
              </template>

              <!-- White Kembali Button -->
              <Button 
                @click="emit('update:open', false)"
                variant="white"
                size="lg"
              >
                {{ $t('common.back') }}
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
