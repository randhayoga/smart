<script setup lang="ts">
/**
 * Manager Activation Approval Modal Component
 * Displays full asset specification, registration history, location, and comprehensive audit trail
 * for newly created assets pending activation approval or already decided.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { X, ThumbsUp, Ban, FileText, ChevronDown } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatDate, formatLocation } from '@/lib/utils';
import Tabs from '@/Components/Tabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuditTrailTab from '@/Pages/Smart/MultiRoles/Tabs/AuditTrailTab.vue';
import { formatQcStatus, getQcColorClass } from '@/lib/qualityControl';

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
const showMoreInfo = ref(false);

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
    showMoreInfo.value = false;
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

/** Determines whether the given asset belongs to software category */
const isSoft = (item: any) => {
  if (!item) return false;
  const catCode = item.category_code || item.unit_details?.category_code || item.unit_details?.model_category_code || '';
  const cat = (item.category || item.unit_details?.category || '').toLowerCase();
  return catCode === 'SOFT' || cat === 'software' || cat === 'soft';
};

const hasCondition = (cond?: string | null) => Boolean(cond && cond !== '-' && cond.trim() !== '');

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

const openMemoFile = (path?: string | null) => {
  if (!path) return;
  window.open('/media/' + path, '_blank');
};

const getConditionClass = (cond?: string | null) => {
  if (!cond) return 'text-foreground';
  const c = cond.trim().toLowerCase();
  if (c === 'bagus' || c === 'good') return 'text-emerald-600 font-semibold';
  if (c === 'rusak' || c === 'rusak total' || c === 'hilang' || c === 'damaged' || c === 'total damage' || c === 'lost') {
    return 'text-rose-600 font-semibold';
  }
  return 'text-foreground';
};

const CONDITION_KEY_MAP: Record<string, string> = {
  'bagus': 'inventory.conditionGood',
  'rusak': 'inventory.conditionDamaged',
  'rusak total': 'inventory.conditionTotalDamage',
  'hilang': 'inventory.conditionLost',
};

const getConditionLabel = (cond?: string | null) => {
  if (!cond || cond === '-') return '';
  const key = CONDITION_KEY_MAP[cond.toLowerCase()];
  return key && te(key) ? t(key) : cond;
};

const isComp = computed(() => {
  const catCode = props.approval?.unit_details?.category_code || 
                  props.approval?.unit_details?.barang_category_code || 
                  props.approval?.category_code || 
                  props.approval?.model_category_code || '';
  if (catCode === 'COMP') return true;
  const cat = props.approval?.category || props.approval?.unit_details?.category || '';
  return cat === 'Computer' || cat === 'Komputer' || cat === 'computer' || cat === 'komputer' || cat === 'comp';
});

const modelDisplay = computed(() => {
  if (!props.approval) return '-';
  const brand = props.approval.brand && props.approval.brand !== '-' ? props.approval.brand : '';
  const nama = (props.approval.name || props.approval.nama) && (props.approval.name || props.approval.nama) !== '-' ? (props.approval.name || props.approval.nama) : '';
  const spec = isComp.value
    ? (props.approval.unit_details?.comp_specification || props.approval.comp_specification || props.approval.unit_details?.specification || props.approval.specification || '')
    : (props.approval.specification && props.approval.specification !== '-' ? props.approval.specification : (props.approval.unit_details?.specification || ''));

  return [brand, nama, spec].filter(s => Boolean(s && String(s).trim() !== '' && String(s).trim() !== '-')).join(' ') || '-';
});

const categoryWithSubcategory = computed(() => {
  if (!props.approval) return '-';
  const cat = props.approval.category;
  const sub = props.approval.subcategory;
  if (cat && sub && cat !== '-' && sub !== '-') {
    return `${cat} (${sub})`;
  }
  return cat || sub || '-';
});

const burdenDisplay = computed(() => {
  const unit = props.approval?.unit_details;
  const burden = unit?.burden || props.approval?.burden;
  if (!burden) return '-';
  if (burden === 'Project') {
    const prjNo = unit?.project_no || props.approval?.project_no;
    const prjName = unit?.project_name || props.approval?.project_name;
    if (prjNo && prjName) return `Project [${prjNo}] ${prjName}`;
    if (prjNo) return `Project [${prjNo}]`;
    if (prjName) return `Project ${prjName}`;
    return 'Project';
  }
  return burden;
});

const getClassificationLabel = (c?: string | null) => {
  if (!c) return '';
  if (c === 'Aset') return t('inventory.classificationAsset');
  if (c === 'Inventaris') return t('inventory.classificationInventory');
  return c;
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
                    :src="'/media/inventory/placeholder.jpg'" 
                    class="w-full h-full object-cover opacity-50" 
                  />
                </div>

                <!-- Details Single Column (takes full horizontal space) -->
                <div class="flex-grow flex flex-col gap-1.5 text-foreground">
                  <!-- 1. Nomor Aset -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.assetCode') }}:</span> {{ approval.asset_code }}
                  </p>

                  <!-- 2. Nomor Aset Lama (only shown when legacy_number is different than number) -->
                  <p v-if="approval.unit_details?.legacy_number && String(approval.unit_details.legacy_number).trim() !== String(approval.asset_code).trim()" class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.legacyNumber') }}:</span> {{ approval.unit_details.legacy_number }}
                  </p>

                  <!-- 3. Kategori (shows category + type) -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.category') }}:</span> {{ categoryWithSubcategory }}
                  </p>

                  <!-- 4. Model (shows brand + name + spec) [dynamic, shows asset's spec for COMP, model's spec for anything else] -->
                  <p class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.modelLabel') }}:</span> {{ modelDisplay }}
                  </p>

                  <!-- 5. Nopol (only for vehicle category) -->
                  <p v-if="isVehicle(approval)" class="font-bold text-foreground">
                    <span class="text-foreground">{{ t('inventory.nopol') }}:</span> {{ approval.unit_details?.vehicle_registration || '-' }}
                  </p>

                  <!-- 6. Tanggal Kedaluwarsa Lisensi (only for Software) -->
                  <p v-if="isSoft(approval)" class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.licenseExpDate') }}:</span>
                    {{ approval.unit_details?.license_exp_date ? formatDate(approval.unit_details.license_exp_date) : t('inventory.perpetual') }}
                  </p>

                  <!-- 7. Pengingat Perpanjangan (only for Software) -->
                  <p v-if="isSoft(approval) && approval.unit_details?.license_exp_date" class="text-foreground">
                    <span class="text-foreground">{{ t('inventory.renewalReminder') }}:</span>
                    {{ approval.unit_details?.renewal_reminder === 0 ? t('inventory.renewalReminderDisabled') : (approval.unit_details?.renewal_reminder !== null && approval.unit_details?.renewal_reminder !== undefined ? `${approval.unit_details.renewal_reminder} ${t('inventory.renewalReminderSuffix')}` : '-') }}
                  </p>

                  <!-- 8. Status & Decision flow entries -->
                  <!-- Pending Mode: Status & Kondisi -->
                  <div v-if="mode === 'pending'" class="flex items-center gap-1.5 flex-wrap">
                    <span>{{ hasCondition(approval.unit_details?.condition ?? approval.condition) && !isSoft(approval) ? t('inventory.statusAndCondition') : t('inventory.status') }}:</span>
                    <StatusBadge 
                      :status="approval.unit_details?.status ?? approval.status" 
                      :approval-status="approval.unit_details?.approval_status ?? approval.approval_status ?? 'Pending:DM'" 
                    />
                    <span v-if="hasCondition(approval.unit_details?.condition ?? approval.condition) && !isSoft(approval)" :class="getConditionClass(approval.unit_details?.condition ?? approval.condition)">
                      {{ getConditionLabel(approval.unit_details?.condition ?? approval.condition) }}
                    </span>
                  </div>

                  <!-- Decided Mode: Keputusan, Status & Kondisi, Catatan Manager -->
                  <template v-if="mode === 'decided'">
                    <p class="text-foreground">
                      {{ t('approvals.decisionLabel') }} 
                      <span 
                        :class="[
                          'font-semibold',
                          approval.decision === 'approved' ? 'text-emerald-600' : 'text-rose-600'
                        ]"
                      >
                        {{ approval.decision === 'approved' ? t('approvals.approved') : t('approvals.rejected') }}
                      </span>
                    </p>

                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span>{{ hasCondition(approval.unit_details?.condition ?? approval.condition) && !isSoft(approval) ? t('inventory.statusAndCondition') : t('inventory.status') }}:</span>
                      <StatusBadge 
                        :status="approval.unit_details?.status ?? approval.status" 
                        :approval-status="approval.unit_details?.approval_status ?? approval.approval_status" 
                      />
                      <span v-if="hasCondition(approval.unit_details?.condition ?? approval.condition) && !isSoft(approval)" :class="getConditionClass(approval.unit_details?.condition ?? approval.condition)">
                        {{ getConditionLabel(approval.unit_details?.condition ?? approval.condition) }}
                      </span>
                    </div>

                    <p class="text-foreground" v-if="approval.note">
                      {{ t('approvals.managerNoteLabel') }} <span class="italic text-muted-foreground">"{{ approval.note }}"</span>
                    </p>
                  </template>

                  <!-- 9. Kondisi (when decided or standalone) -->
                  <p v-if="!isSoft(approval) && hasCondition(approval.unit_details?.condition ?? approval.condition) && mode !== 'pending' && mode !== 'decided'" class="text-foreground">
                    {{ t('inventory.condition') }}:
                    <span :class="getConditionClass(approval.unit_details?.condition ?? approval.condition)">
                      {{ getConditionLabel(approval.unit_details?.condition ?? approval.condition) }}
                    </span>
                  </p>

                  <!-- 10. Quality Control -->
                  <p v-if="!isSoft(approval)" class="text-foreground">
                    {{ t('inventory.qualityControl') }}:
                    <span :class="getQcColorClass(approval.unit_details?.qc_pass ?? approval.qc_pass)">
                      {{ formatQcStatus(approval.unit_details?.qc_pass ?? approval.qc_pass, approval.unit_details?.last_qc ?? approval.unit_details?.last_qc_formatted ?? approval.last_qc, t) }}
                    </span>
                  </p>

                  <!-- 11. Lokasi (not "Lokasi Penyimpanan") -->
                  <p v-if="!isSoft(approval)" class="text-foreground">
                    {{ t('inventory.location') }}: {{ formatLocation(approval.unit_details?.location, approval.unit_details?.floor, approval.unit_details?.room) }}
                  </p>

                  <!-- 12. Umur (calculate from lot's date, clamped >= 0) -->
                  <p class="text-foreground">
                    {{ t('inventory.age') }}: {{ (approval.unit_details?.age !== undefined && approval.unit_details?.age !== null) || (approval.age !== undefined && approval.age !== null) ? `${Math.max(0, Number(approval.unit_details?.age ?? approval.age ?? 0))} ${t('inventory.yearUnit')}` : '-' }}
                  </p>

                  <!-- Collapsible Toggle: Info Lebih Lanjut (Option B) -->
                  <div class="pt-1">
                    <button
                      type="button"
                      @click="showMoreInfo = !showMoreInfo"
                      class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 transition-all cursor-pointer focus:outline-none"
                    >
                      <span>{{ t('inventory.moreInfo') }}</span>
                      <ChevronDown 
                        class="w-3.5 h-3.5 transition-transform duration-200 transform" 
                        :class="{ 'rotate-180': showMoreInfo }"
                      />
                    </button>
                  </div>

                  <!-- Collapsible Content with GPU-accelerated animation -->
                  <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-1"
                  >
                    <div v-if="showMoreInfo" class="flex flex-col gap-1.5 pt-0.5">
                      <!-- 13. Nilai -->
                      <p class="text-foreground">
                        {{ t('inventory.value') }}: {{ formatRupiah(approval.unit_details?.price ?? approval.price) }}
                      </p>

                      <!-- 14. Jenis peminjaman (from "Jenis") -->
                      <p v-if="approval.unit_details?.borrowing_type || approval.unit_details?.type || approval.borrowing_type || approval.type" class="text-foreground">
                        {{ t('inventory.borrowingType') }}: {{ approval.unit_details?.borrowing_type || approval.unit_details?.type || approval.borrowing_type || approval.type }}
                      </p>

                      <!-- 15. Klasifikasi -->
                      <p v-if="approval.unit_details?.classification || approval.classification" class="text-foreground">
                        {{ t('inventory.classification') }}: {{ getClassificationLabel(approval.unit_details?.classification || approval.classification) }}
                      </p>

                      <!-- 16. Organizer -->
                      <p class="text-foreground">
                        {{ t('inventory.organizer') }}: {{ approval.unit_details?.organizer || approval.organizer || '-' }}
                      </p>

                      <!-- 17. Pembebanan (also shows the project number if project) -->
                      <p class="text-foreground">
                        {{ t('inventory.burden') }}: {{ burdenDisplay }}
                      </p>

                      <!-- 18. Pembaruan Terakhir / Tanggal Pengajuan -->
                      <p class="text-foreground">
                        {{ t('inventory.lastUpdate') }}: {{ approval.unit_details?.updated_at || approval.updated_at || approval.requested_at || '-' }}
                      </p>
                    </div>
                  </Transition>
                </div>
              </div>

              <!-- ── TAB 2: JEJAK AUDIT ── -->
              <AuditTrailTab 
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
