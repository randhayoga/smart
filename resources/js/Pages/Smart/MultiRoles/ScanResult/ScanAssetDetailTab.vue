<script setup lang="ts">
/**
 * Tab component for displaying detailed asset information in the mobile scan result view.
 * Features optimized typography hierarchy, primary asset attributes, and a collapsible more-info card.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, ExternalLink } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatDate, formatLocation } from '@/lib/utils';
import { formatQcStatus, getQcColorClass } from '@/lib/qualityControl';

interface Props {
  asset: any;
  locations?: any[];
  lot?: any;
  barang?: any;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'edit'): void;
}>();

const { t, locale } = useI18n();

const isMoreInfoOpen = ref(false);

const isVehicle = computed(() => {
  const category = (props.asset?.barang_category || props.barang?.category || props.asset?.model_category || '').toLowerCase();
  const subcategory = (props.asset?.barang_subcategory || props.asset?.model_type || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

const isComp = computed(() => {
  const catCode = props.asset?.barang_category_code || props.barang?.category_code || props.asset?.model_category_code || '';
  const cat = (props.asset?.barang_category || props.barang?.category || props.asset?.model_category || '').toLowerCase();
  return catCode === 'COMP' || cat === 'computer' || cat === 'comp';
});

const isSoft = computed(() => {
  const catCode = props.asset?.barang_category_code || props.barang?.category_code || props.asset?.model_category_code || props.asset?.category_code || '';
  const cat = (props.asset?.barang_category || props.barang?.category || props.asset?.category || props.asset?.model_category || '').toLowerCase();
  return catCode === 'SOFT' || cat === 'software' || cat === 'soft';
});

const brandAndName = computed(() => {
  const brand = props.asset?.barang_brand || props.asset?.model_brand || '';
  const name = props.asset?.barang_nama || props.asset?.model_name || '';
  const cleanBrand = brand && brand !== '-' ? brand : '';
  const cleanName = name && name !== '-' ? name : '';
  const parts = [cleanBrand, cleanName].filter(Boolean);
  return parts.length > 0 ? parts.join(' ') : (props.asset?.number || '-');
});

const specDisplay = computed(() => {
  if (isComp.value) {
    return props.asset?.comp_specification || props.asset?.specification || '-';
  }
  return props.asset?.barang_specification || props.asset?.model_specification || props.asset?.specification || '-';
});

const categoryWithSubcategory = computed(() => {
  const cat = props.asset?.barang_category || props.asset?.model_category || '';
  const sub = props.asset?.barang_subcategory || props.asset?.model_type || props.asset?.type_name || '';
  if (cat && sub && cat !== '-' && sub !== '-') {
    return `${cat} (${sub})`;
  }
  return cat || sub || '-';
});

const burdenDisplay = computed(() => {
  if (!props.asset?.burden) return '-';
  if (props.asset.burden === 'Project') {
    const prjNo = props.asset.project_no;
    const prjName = props.asset.project_name;
    if (prjNo && prjName) {
      return `Project [${prjNo}] ${prjName}`;
    }
    if (prjNo) {
      return `Project [${prjNo}]`;
    }
    if (prjName) {
      return `Project ${prjName}`;
    }
    return 'Project';
  }
  return props.asset.burden;
});

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
  const formatted = Math.floor(num).toLocaleString(loc);
  return `Rp${formatted}`;
};

const getConditionLabel = (cond: string) => {
  if (!cond) return '';
  const map: Record<string, string> = {
    'Bagus': t('inventory.conditionGood'),
    'Rusak': t('inventory.conditionDamaged'),
    'Rusak Total': t('inventory.conditionTotalDamage'),
    'Hilang': t('inventory.conditionLost'),
  };
  return map[cond] || cond;
};

const getClassificationLabel = (c: string) => {
  if (!c) return '';
  if (c === 'Aset') return t('inventory.classificationAsset');
  if (c === 'Inventaris') return t('inventory.classificationInventory');
  return c;
};

const qcFormattedDate = computed(() => {
  const lastQc = props.asset?.last_qc ?? props.asset?.lq;
  if (!lastQc) return null;
  const formatted = formatDate(lastQc);
  return formatted && formatted !== '-' ? formatted : null;
});
</script>

<template>
  <div class="space-y-4">
    <!-- Card 1: Image & Summary (Brand+Name, Spec, Numbers) -->
    <div class="bg-card rounded-2xl p-4 border border-border shadow-sm flex flex-col items-center">
      <div class="w-full aspect-square rounded-xl bg-muted flex items-center justify-center overflow-hidden border border-border shadow-inner">
        <img
          v-if="props.asset.image_url"
          :src="'/media/' + props.asset.image_url"
          class="w-full h-full object-cover"
        />
        <img
          v-else
          :src="'/media/inventory/placeholder.jpg'"
          class="w-full h-full object-cover opacity-60"
        />
      </div>
      <div class="mt-4 text-center w-full px-2">
        <!-- Legacy number: kept with legacy styling -->
        <p v-if="props.asset.legacy_number" class="text-xs text-muted-foreground font-mono">
          <span class="font-medium">{{ t('inventory.legacyNumber') }}:</span> {{ props.asset.legacy_number }}
        </p>
        <!-- Asset number: smaller but distinct uppercase -->
        <h1 class="text-sm font-semibold tracking-wide text-foreground uppercase mt-0.5">
          {{ props.asset.number }}
        </h1>
        <!-- Brand + Name: combined format "<brand> <name>" -->
        <h2 class="text-base font-bold text-foreground leading-snug mt-1">
          {{ brandAndName }}
        </h2>
        <!-- Specification: purely spec below brand+name -->
        <p v-if="specDisplay && specDisplay !== '-'" class="text-xs text-muted-foreground font-normal mt-1 break-words">
          {{ specDisplay }}
        </p>
      </div>
    </div>

    <!-- Card 2: Primary Information (Category, Status, Condition, Quality Control, Location) -->
    <div class="bg-card rounded-2xl border border-border overflow-hidden shadow-sm">
      <div class="px-4 py-3 border-b border-border bg-muted/20 flex items-center justify-between">
        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">
          {{ isSoft ? t('scanner.status') : (t('inventory.primaryInfo') || t('scanner.statusAndLocation')) }}
        </h3>
      </div>
      <div class="p-4 space-y-3.5 text-sm">
        <!-- 1. Category -->
        <div class="flex justify-between items-start gap-4">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.category') }}</span>
          <span class="text-foreground font-semibold text-right">{{ categoryWithSubcategory }}</span>
        </div>

        <!-- 2. Status (+ Software/Vehicle details) -->
        <div class="flex justify-between items-start gap-4">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('scanner.status') }}</span>
          <StatusBadge :status="props.asset.status" :proposed-status="props.asset.proposed_status" :approval-status="props.asset.approval_status" />
        </div>

        <!-- Software License Information if Software -->
        <template v-if="isSoft">
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.licenseExpDate') }}</span>
            <span class="text-foreground font-semibold">
              {{ props.asset.license_exp_date ? formatDate(props.asset.license_exp_date) : t('inventory.perpetual') }}
            </span>
          </div>
          <div v-if="props.asset.license_exp_date" class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.renewalReminder') }}</span>
            <span class="text-foreground font-semibold">
              {{ props.asset.renewal_reminder === 0 ? t('inventory.renewalReminderDisabled') : (props.asset.renewal_reminder !== null && props.asset.renewal_reminder !== undefined ? `${props.asset.renewal_reminder} ${t('inventory.renewalReminderSuffix')}` : '-') }}
            </span>
          </div>
        </template>

        <!-- Vehicle TNKB if Vehicle -->
        <div v-if="isVehicle" class="flex justify-between items-start gap-4">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('scanner.licensePlate') }}</span>
          <span class="text-foreground font-semibold">{{ props.asset.vehicle_registration || '-' }}</span>
        </div>

        <!-- 3. Condition (non-software) -->
        <div v-if="!isSoft && props.asset.condition" class="flex justify-between items-start gap-4">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('scanner.condition') }}</span>
          <span
            :class="[
              'font-semibold',
              props.asset.condition === 'Bagus' ? 'text-emerald-600' :
              props.asset.condition === 'Lelang/Hibah' ? 'text-purple-600' :
              'text-rose-600'
            ]"
          >
            {{ getConditionLabel(props.asset.condition) }}
          </span>
        </div>

        <!-- 4. Quality Control (non-software) -->
        <div v-if="!isSoft" class="flex justify-between items-start gap-4">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.qualityControl') }}</span>
          <span v-if="props.asset.qc_pass === null || props.asset.qc_pass === undefined" class="text-muted-foreground">
            -
          </span>
          <div v-else class="text-right leading-tight space-y-0.5">
            <div :class="['font-semibold', getQcColorClass(props.asset.qc_pass)]">
              {{ props.asset.qc_pass ? t('inventory.qcPass') : t('inventory.qcFail') }}
            </div>
            <div v-if="qcFormattedDate" class="text-xs text-muted-foreground font-normal">
              {{ qcFormattedDate }}
            </div>
          </div>
        </div>

        <!-- 5. Location (non-software) -->
        <div v-if="!isSoft" class="flex justify-between items-start gap-4">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('scanner.location') }}</span>
          <span class="text-foreground font-semibold text-right max-w-[200px]">
            {{ formatLocation(props.asset.location, props.asset.floor, props.asset.room) }}
          </span>
        </div>

        <!-- Notes if present -->
        <div v-if="props.asset.admin_note" class="flex justify-between items-start gap-4 text-xs pt-1 border-t border-border/60">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.adminNote') }}</span>
          <span class="italic text-muted-foreground text-right">"{{ props.asset.admin_note }}"</span>
        </div>
        <div v-if="props.asset.dm_note" class="flex justify-between items-start gap-4 text-xs">
          <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.dmNote') }}</span>
          <span class="italic text-muted-foreground text-right">"{{ props.asset.dm_note }}"</span>
        </div>
      </div>
    </div>

    <!-- Card 3: Collapsible More Information (Value, Borrowing Type, Classification, Organizer, Burden, Last Update) -->
    <div class="bg-card rounded-2xl border border-border overflow-hidden shadow-sm">
      <button
        type="button"
        @click="isMoreInfoOpen = !isMoreInfoOpen"
        class="w-full px-4 py-3 border-b border-border bg-muted/20 flex items-center justify-between transition-colors hover:bg-muted/30 focus:outline-none cursor-pointer"
        :aria-expanded="isMoreInfoOpen"
      >
        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">
          {{ t('inventory.moreInfo') }}
        </h3>
        <ChevronDown
          class="w-4 h-4 text-foreground/70 transition-transform duration-200 transform"
          :class="{ 'rotate-180': isMoreInfoOpen }"
        />
      </button>

      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div v-if="isMoreInfoOpen" class="p-4 space-y-3.5 text-sm">
          <!-- 1. Value -->
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.value') }}</span>
            <span class="text-foreground font-semibold">{{ formatRupiah(props.asset.price) }}</span>
          </div>

          <!-- 2. Borrowing Type -->
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.borrowingType') }}</span>
            <span class="text-foreground font-semibold">{{ props.asset.borrowing_type || props.asset.type || '-' }}</span>
          </div>

          <!-- 3. Classification -->
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.classification') }}</span>
            <span class="text-foreground font-semibold">{{ getClassificationLabel(props.asset.classification) || '-' }}</span>
          </div>

          <!-- 4. Organizer -->
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.organizer') }}</span>
            <span class="text-foreground font-semibold text-right">{{ props.asset.lot_organizer || props.asset.organizer || '-' }}</span>
          </div>

          <!-- 5. Burden -->
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.burden') }}</span>
            <span class="text-foreground font-semibold text-right max-w-[220px]">{{ burdenDisplay }}</span>
          </div>

          <!-- 6. Last Update -->
          <div class="flex justify-between items-start gap-4">
            <span class="text-muted-foreground font-medium shrink-0">{{ t('inventory.lastUpdate') }}</span>
            <span class="text-foreground font-semibold">{{ props.asset.updated_at || '-' }}</span>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Edit Button Trigger -->
    <Button
      @click="emit('edit')"
      variant="primary"
      size="lg"
      class="w-full h-12 rounded-xl flex items-center justify-center gap-2 text-sm font-bold shadow-md cursor-pointer"
    >
      {{ t('scanner.editLocationAndDetail') }}
    </Button>
  </div>
</template>
