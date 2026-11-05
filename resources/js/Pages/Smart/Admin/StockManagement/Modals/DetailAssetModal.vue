<script setup lang="ts">
/**
 * Detail Asset Modal component displaying asset unit specifications, borrow assignments, QR code generation, and audit trail tabs.
 */
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, FileText, Upload, Loader2, ExternalLink, ChevronDown } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatDate, formatLocation } from '@/lib/utils';
import { formatQcStatus, getQcColorClass } from '@/lib/qualityControl';
import { hasAssetStatus, isMaintenanceAllowed } from '@/lib/assetStatus';
import StatusBadge from '@/Components/StatusBadge.vue';
import Tabs from '@/Components/Tabs.vue';
import AuditTrailTab from '@/Pages/Smart/MultiRoles/Tabs/AuditTrailTab.vue';
import MaintenanceTab from './MaintenanceTab.vue';

interface Props {
  open: boolean;
  asset: any;
  lot?: any;
  users?: { id: number; name: string }[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'edit', asset: any): void;
}>();

import { usePermissions } from '@/composables/usePermissions';

const { t, locale } = useI18n();
const { can } = usePermissions();

useModalLock(computed(() => props.open));

const detailActiveTab = ref('Detail Aset');
const fileInputRef = ref<HTMLInputElement | null>(null);
const disapprovalFileInputRef = ref<HTMLInputElement | null>(null);
const isUploading = ref(false);
const fetchedLifecycles = ref<any[]>([]);
const showMoreInfo = ref(false);

const effectiveLifecycles = computed(() => {
  if (Array.isArray(props.asset?.lifecycles)) {
    return props.asset.lifecycles;
  }
  return fetchedLifecycles.value;
});

const isBorrowing = computed(() => {
  const s = props.asset?.status || '';
  return s === 'Dipinjam (ST)' || s === 'Dipinjam (sewa)' || s.toLowerCase().startsWith('dipinjam');
});

const isAssignment = computed(() => {
  const s = props.asset?.status || '';
  return s === 'Di-assign (LT)' || s.toLowerCase().startsWith('di-assign');
});

const isAssignmentOrBorrowing = computed(() => isBorrowing.value || isAssignment.value);

const activeRequestId = computed<number | null>(() => {
  if (props.asset?.active_borrowing?.request_id) {
    return Number(props.asset.active_borrowing.request_id);
  }
  if (props.asset?.request_id) {
    return Number(props.asset.request_id);
  }
  const lifecycleWithReq = effectiveLifecycles.value?.find((l: any) => l.request_id);
  if (lifecycleWithReq?.request_id) {
    return Number(lifecycleWithReq.request_id);
  }
  return null;
});

const assignmentOrBorrowingUrl = computed<string | null>(() => {
  if (!isAssignmentOrBorrowing.value) return null;
  const reqId = activeRequestId.value;
  if (!reqId) return null;
  return `/smart/borrowed/${reqId}`;
});

const fetchAssetDetailsIfNeeded = async () => {
  if (!props.open || !props.asset?.id) return;
  // If Jejak Audit is open and lifecycles already exists, no need to fetch
  const needsLifecycles = detailActiveTab.value === 'Jejak Audit' && !Array.isArray(props.asset.lifecycles);
  // If Perawatan is open or asset is borrowed/assigned, and active_borrowing is not yet loaded on the asset
  const needsBorrowing = (detailActiveTab.value === 'Perawatan' || isAssignmentOrBorrowing.value) && props.asset.active_borrowing === undefined;

  if (!needsLifecycles && !needsBorrowing) {
    if (detailActiveTab.value === 'Jejak Audit' && Array.isArray(props.asset.lifecycles)) {
      fetchedLifecycles.value = props.asset.lifecycles;
    }
    return;
  }

  if (typeof fetch !== 'function') return;

  try {
    const res = await fetch(`/smart/stock-management/assets/${props.asset.id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res && res.ok) {
      const data = await res.json();
      if (data && Array.isArray(data.lifecycles)) {
        fetchedLifecycles.value = data.lifecycles;
        if (props.asset) {
          props.asset.lifecycles = data.lifecycles;
        }
      }
      if (data && data.active_borrowing !== undefined && props.asset) {
        props.asset.active_borrowing = data.active_borrowing;
      }
    }
  } catch {
    // Gracefully ignore background detail fetch errors in tests/offline
  }
};

const tabs = computed(() => {
  const list = [
    { id: 'Detail Aset', label: t('inventory.assetDetail') },
  ];

  if (can('inventory.maintenance') && isMaintenanceAllowed(props.asset?.status, props.asset?.approval_status)) {
    list.push({ id: 'Perawatan', label: t('inventory.maintenanceTab') });
  }

  list.push({ id: 'Jejak Audit', label: t('inventory.auditTrail') });

  return list;
});

watch(
  () => [props.open, props.asset?.id] as const,
  ([newOpen]) => {
    if (newOpen) {
      detailActiveTab.value = 'Detail Aset';
      showMoreInfo.value = false;
      isUploading.value = false;
      fetchedLifecycles.value = Array.isArray(props.asset?.lifecycles) ? props.asset.lifecycles : [];
      if (fileInputRef.value) {
        fileInputRef.value.value = '';
      }
      if (disapprovalFileInputRef.value) {
        disapprovalFileInputRef.value.value = '';
      }
      if (isAssignmentOrBorrowing.value && props.asset?.active_borrowing === undefined) {
        void fetchAssetDetailsIfNeeded();
      }
    }
  },
  { immediate: true }
);

watch(detailActiveTab, (tab) => {
  if (tab === 'Jejak Audit' || tab === 'Perawatan') {
    void fetchAssetDetailsIfNeeded();
  }
});

const isVehicle = computed(() => {
  if (!props.asset) return false;
  const category = (finalBarangCategory.value || '').toLowerCase();
  const subcategory = (finalBarangSubcategory.value || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

// Helper Functions
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

const getAge = (dateStr: string | null) => {
  if (!dateStr || dateStr === '-') return null;
  const receipt = new Date(dateStr);
  if (isNaN(receipt.getTime())) return null;
  const diffTime = new Date().getTime() - receipt.getTime();
  const years = Math.floor(diffTime / (1000 * 60 * 60 * 24 * 365));
  return years >= 0 ? years : 0;
};

const openMemoFile = (path?: string | null) => {
  if (!path) return;
  window.open('/media/' + path, '_blank');
};

const triggerBodBocUpload = () => {
  if (isUploading.value) return;
  fileInputRef.value?.click();
};

const triggerBodBocDisapprovalUpload = () => {
  if (isUploading.value) return;
  disapprovalFileInputRef.value?.click();
};

const handleBodBocUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file || !props.asset) return;

  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    target.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    target.value = '';
    return;
  }

  isUploading.value = true;
  router.post(
    `/smart/stock-management/assets/${props.asset.id}`,
    {
      _method: 'PUT',
      bod_boc_approval_file: file,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isUploading.value = false;
        target.value = '';
        if (props.asset) {
          props.asset.status = 'Dihapus';
          props.asset.approval_status = null;
        }
        emit('update:open', false);
      },
      onError: (errors: any) => {
        isUploading.value = false;
        target.value = '';
        if (errors.bod_boc_approval_file) {
          toast.error(errors.bod_boc_approval_file);
        } else {
          toast.error(t('inventory.uploadBodBocFailed'));
        }
      },
      onFinish: () => {
        isUploading.value = false;
        target.value = '';
      },
    }
  );
};

const handleBodBocDisapprovalUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file || !props.asset) return;

  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    target.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    target.value = '';
    return;
  }

  isUploading.value = true;
  router.post(
    `/smart/stock-management/assets/${props.asset.id}`,
    {
      _method: 'PUT',
      bod_boc_disapproval_file: file,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isUploading.value = false;
        target.value = '';
        if (props.asset) {
          props.asset.status = 'Tersedia';
          props.asset.approval_status = null;
        }
        emit('update:open', false);
      },
      onError: (errors: any) => {
        isUploading.value = false;
        target.value = '';
        if (errors.bod_boc_disapproval_file) {
          toast.error(errors.bod_boc_disapproval_file);
        } else {
          toast.error(t('inventory.uploadBodBocFailed'));
        }
      },
      onFinish: () => {
        isUploading.value = false;
        target.value = '';
      },
    }
  );
};

const maintenanceTabRef = ref<any>(null);
const isMaintenanceSubmitting = ref(false);

const handleMaintenanceSaved = (payload: { maintenance: any; assetCondition: string }) => {
  if (props.asset && payload.assetCondition) {
    props.asset.condition = payload.assetCondition;
  }
  // Re-fetch lifecycles so that Jejak Audit is up to date
  void fetchAssetDetailsIfNeeded();
  // Close modal immediately after successful maintenance submission
  emit('update:open', false);
};

const triggerMaintenanceSubmit = async () => {
  if (maintenanceTabRef.value?.submitForm) {
    await maintenanceTabRef.value.submitForm();
  }
};

// Dynamic attribute resolution
const finalLotAge = computed(() => {
  if (props.lot?.age !== undefined && props.lot?.age !== null) return props.lot.age;
  if (props.asset?.lot_age !== undefined && props.asset?.lot_age !== null) return props.asset.lot_age;
  return getAge(finalLotDateOfReceipt.value);
});

const finalLotOrganizer = computed(() => props.lot?.organizer || props.asset?.lot_organizer || '');
const finalLotDateOfReceipt = computed(() => props.lot?.date_of_receipt || props.asset?.lot_date_of_receipt || '');
const isComp = computed(() => {
  const catCode = props.asset?.barang_category_code || props.lot?.barang_category_code || '';
  const cat = (finalBarangCategory.value || '').toLowerCase();
  return catCode === 'COMP' || cat === 'computer' || cat === 'comp';
});

const isSoft = computed(() => {
  const catCode = props.asset?.barang_category_code || props.lot?.barang_category_code || props.asset?.model_category_code || props.lot?.model_category_code || '';
  const cat = (finalBarangCategory.value || '').toLowerCase();
  return catCode === 'SOFT' || cat === 'software' || cat === 'soft';
});

const finalLotVendor = computed(() => {
  const vId = props.lot?.vendor_id ?? props.asset?.lot_vendor_id;
  const vName = props.lot?.vendor || props.asset?.lot_vendor;
  if (!vId || vName === '-' || !vName || String(vName).trim().toLowerCase() === 'beragam') {
    return t('inventory.vendorMultiple');
  }
  return vName;
});
const isLotVendorMultiple = computed(() => {
  const vName = props.lot?.vendor || props.asset?.lot_vendor;
  return !props.lot?.vendor_id && !props.asset?.lot_vendor_id || String(vName).trim().toLowerCase() === 'beragam' || finalLotVendor.value === t('inventory.vendorMultiple');
});
const finalLotPoNumber = computed(() => props.lot?.po_number || props.asset?.lot_po_number || '');

const finalBarangBrand = computed(() => props.lot?.barang_brand || props.asset?.barang_brand || '');
const finalBarangNama = computed(() => props.lot?.barang_nama || props.asset?.barang_nama || '');
const finalBarangSpecification = computed(() => props.lot?.barang_specification || props.asset?.barang_specification || '');
const finalBarangCategory = computed(() => props.lot?.barang_category || props.asset?.barang_category || '');
const finalBarangSubcategory = computed(() => props.lot?.barang_subcategory || props.asset?.barang_subcategory || '');
const finalBarangUom = computed(() => props.lot?.barang_uom || props.asset?.barang_uom || '');

const modelDisplay = computed(() => {
  const brand = finalBarangBrand.value && finalBarangBrand.value !== '-' ? finalBarangBrand.value : '';
  const nama = finalBarangNama.value && finalBarangNama.value !== '-' ? finalBarangNama.value : '';
  const spec = isComp.value
    ? (props.asset?.comp_specification || props.asset?.specification || '')
    : (finalBarangSpecification.value && finalBarangSpecification.value !== '-' ? finalBarangSpecification.value : '');
  
  return [brand, nama, spec].filter(s => Boolean(s && String(s).trim() !== '' && String(s).trim() !== '-')).join(' ') || '-';
});

const categoryWithSubcategory = computed(() => {
  const cat = finalBarangCategory.value;
  const sub = finalBarangSubcategory.value;
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
      <div v-if="open" @click="emit('update:open', false)" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open" 
            class="bg-card w-full md:max-w-[90%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-2 px-4 border-b border-border">
              <Tabs v-model="detailActiveTab" :tabs="tabs" />
              <button @click="emit('update:open', false)" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] space-y-4 overscroll-contain">
              <!-- ── TAB 1: DETAIL ASET ── -->
              <div v-if="detailActiveTab === 'Detail Aset'" class="space-y-6">
                <div class="flex flex-col md:flex-row gap-6">
                  <!-- Image Column -->
                  <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                    <img 
                      v-if="asset && asset.image_url" 
                      :src="'/media/' + asset.image_url" 
                      class="w-full h-full object-cover" 
                    />
                    <img 
                      v-else 
                      :src="'/media/inventory/placeholder.jpg'" 
                      class="w-full h-full object-cover opacity-50" 
                    />
                  </div>

                  <!-- Details Single Column (takes full horizontal space) -->
                  <div v-if="asset" class="flex-grow flex flex-col gap-1.5 text-foreground">
                    <!-- 1. Nomor Aset -->
                    <p class="font-bold text-foreground">
                      <span class="text-foreground">{{ t('inventory.assetCode') }}:</span> {{ asset.number }}
                    </p>

                    <!-- 2. Nomor Aset Lama (only shown when legacy_number is different than number) -->
                    <p v-if="asset.legacy_number && asset.legacy_number !== asset.number" class="text-foreground">
                      <span class="text-foreground">{{ t('inventory.legacyNumber') }}:</span> {{ asset.legacy_number }}
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
                    <p v-if="isVehicle" class="font-bold text-foreground">
                      <span class="text-foreground">{{ t('inventory.nopol') }}:</span> {{ asset.vehicle_registration || '-' }}
                    </p>

                    <!-- 6. Tanggal Kedaluwarsa Lisensi (only for Software) -->
                    <p v-if="isSoft" class="text-foreground">
                      <span class="text-foreground">{{ t('inventory.licenseExpDate') }}:</span>
                      {{ asset.license_exp_date ? formatDate(asset.license_exp_date) : t('inventory.perpetual') }}
                    </p>

                    <!-- 7. Pengingat Perpanjangan (only for Software) -->
                    <p v-if="isSoft && asset.license_exp_date" class="text-foreground">
                      <span class="text-foreground">{{ t('inventory.renewalReminder') }}:</span>
                      {{ asset.renewal_reminder === 0 ? t('inventory.renewalReminderDisabled') : (asset.renewal_reminder !== null && asset.renewal_reminder !== undefined ? `${asset.renewal_reminder} ${t('inventory.renewalReminderSuffix')}` : '-') }}
                    </p>

                    <!-- 8. Status (if assignment or borrowing add a primary new tab icon on the right of the status badge) -->
                    <div class="text-foreground flex items-center gap-1.5 flex-wrap">
                      <span class="text-foreground">{{ t('inventory.status') }}:</span> 
                      <StatusBadge 
                        :status="asset.status" 
                        :approval-status="asset.approval_status"
                        :proposed-status="asset.proposed_status" 
                      />
                      <a 
                        v-if="isAssignmentOrBorrowing && assignmentOrBorrowingUrl"
                        :href="assignmentOrBorrowingUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center text-primary hover:text-primary/80 transition-colors p-0.5 rounded cursor-pointer"
                        :title="t('inventory.openAssignmentOrBorrowing')"
                      >
                        <ExternalLink class="w-4 h-4" />
                      </a>
                    </div>

                    <!-- 9. Kondisi -->
                    <p v-if="!isSoft" class="text-foreground">
                      {{ t('inventory.condition') }}: 
                      <span 
                        :class="[
                          'font-semibold',
                          asset.condition === 'Bagus' ? 'text-emerald-600' : 'text-rose-600'
                        ]"
                      >
                        {{ getConditionLabel(asset.condition) }}
                      </span>
                    </p>

                    <!-- 10. Quality Control -->
                    <p v-if="!isSoft" class="text-foreground">
                      {{ t('inventory.qualityControl') }}: 
                      <span :class="getQcColorClass(asset.qc_pass)">
                        {{ formatQcStatus(asset.qc_pass, asset.last_qc ?? asset.lq, t) }}
                      </span>
                    </p>

                    <!-- Admin & DM Notes (kept between Condition/QC and Value for test contracts) -->
                    <p v-if="asset.admin_note" class="text-foreground">
                      {{ t('inventory.adminNote') }}: <span class="italic text-muted-foreground font-normal">"{{ asset.admin_note }}"</span>
                    </p>
                    <p v-if="asset.dm_note" class="text-foreground">
                      {{ t('inventory.dmNote') }}: <span class="italic text-muted-foreground font-normal">"{{ asset.dm_note }}"</span>
                    </p>

                    <!-- 11. Lokasi (not "Lokasi Penyimpanan") -->
                    <p v-if="!isSoft" class="text-foreground">
                      {{ t('inventory.location') }}: {{ formatLocation(asset.location, asset.floor, asset.room) }}
                    </p>

                    <!-- 12. Umur (calculate from lot's date) -->
                    <p class="text-foreground">
                      {{ t('inventory.age') }}: {{ finalLotAge !== null && finalLotAge !== undefined ? `${finalLotAge} ${t('inventory.yearUnit')}` : '-' }}
                    </p>

                    <!-- Collapsible Toggle: Info Lebih Lanjut (Option B: Static label + GPU-accelerated rotating chevron) -->
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
                          {{ t('inventory.value') }}: {{ formatRupiah(asset.price) }}
                        </p>

                        <!-- 14. Jenis peminjaman (from "Jenis") -->
                        <p v-if="asset.borrowing_type || asset.type" class="text-foreground">
                          {{ t('inventory.borrowingType') }}: {{ asset.borrowing_type || asset.type }}
                        </p>

                        <!-- 15. Klasifikasi -->
                        <p v-if="asset.classification" class="text-foreground">
                          {{ t('inventory.classification') }}: {{ getClassificationLabel(asset.classification) }}
                        </p>

                        <!-- 16. Organizer -->
                        <p class="text-foreground">
                          {{ t('inventory.organizer') }}: {{ finalLotOrganizer || '-' }}
                        </p>

                        <!-- 17. Pembebanan (also shows the project number if project) -->
                        <p class="text-foreground">
                          {{ t('inventory.burden') }}: {{ burdenDisplay }}
                        </p>

                        <!-- 18. Pembaruan Terakhir -->
                        <p class="text-foreground">
                          {{ t('inventory.lastUpdate') }}: {{ asset.updated_at || '-' }}
                        </p>
                      </div>
                    </Transition>
                  </div>
                </div>
              </div>

              <!-- ── TAB 2: PERAWATAN / MAINTENANCE ── -->
              <MaintenanceTab
                ref="maintenanceTabRef"
                v-if="detailActiveTab === 'Perawatan'"
                :asset="props.asset"
                :users="props.users"
                @saved="handleMaintenanceSaved"
                @submitting="isMaintenanceSubmitting = $event"
              />

              <!-- ── TAB 3: JEJAK AUDIT ── -->
              <AuditTrailTab 
                v-if="detailActiveTab === 'Jejak Audit'"
                :lifecycles="effectiveLifecycles" 
              />
            </div>

            <!-- Modal Footer -->
            <div 
              class="py-3 px-4 border-t border-border flex items-center shrink-0 bg-muted/10"
              :class="detailActiveTab === 'Perawatan' ? 'justify-between' : 'justify-end gap-3'"
            >
              <!-- Left side required marker on Perawatan tab -->
              <p v-if="detailActiveTab === 'Perawatan'" class="text-sm text-rose-500 italic font-medium">
                {{ t('inventory.requiredMarker') }}
              </p>

              <!-- Perawatan Tab Footer Buttons -->
              <div v-if="detailActiveTab === 'Perawatan'" class="flex items-center gap-3">
                <Button 
                  type="button"
                  @click="emit('update:open', false)"
                  :disabled="isMaintenanceSubmitting"
                  variant="white"
                  size="xl"
                >
                  {{ t('common.cancel') }}
                </Button>
                <Button 
                  type="button"
                  @click="triggerMaintenanceSubmit"
                  :disabled="isMaintenanceSubmitting"
                  variant="primary"
                  size="xl"
                  class="relative inline-flex items-center gap-2"
                >
                  <Loader2 v-if="isMaintenanceSubmitting" class="h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': isMaintenanceSubmitting }">
                    {{ t('inventory.saveMaintenance') }}
                  </span>
                </Button>
              </div>

              <!-- Other Tabs Footer Buttons -->
              <template v-else>
                <template v-if="detailActiveTab === 'Detail Aset'">
                <Button 
                  v-if="can('inventory.view') && asset"
                  as="a"
                  :href="`/smart/stock-management/assets/${asset.id}/qr-code`" 
                  download 
                  target="_blank"
                  variant="warning"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  {{ t('inventory.downloadQr') }}
                </Button>

                <Button 
                  v-if="asset && asset.memo_url"
                  @click="openMemoFile(asset.memo_url)"
                  variant="warning"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <FileText class="w-4 h-4" />
                  {{ t('inventory.openMemo') }}
                </Button>

                <Button 
                  v-if="asset && asset.lost_doc_url && (asset.proposed_status === 'Hilang' || asset.condition === 'Hilang')"
                  @click="openMemoFile(asset.lost_doc_url)"
                  variant="warning"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <FileText class="w-4 h-4" />
                  {{ t('inventory.openLostDoc') }}
                </Button>

                <!-- Hidden BoD/BoC File Inputs -->
                <input 
                  ref="fileInputRef"
                  type="file"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleBodBocUpload"
                />
                <input 
                  ref="disapprovalFileInputRef"
                  type="file"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleBodBocDisapprovalUpload"
                />

                <!-- BoD/BoC Approval Document Button -->
                <Button 
                  v-if="asset && asset.bod_boc_approval_url"
                  @click="openMemoFile(asset.bod_boc_approval_url)"
                  variant="success"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <FileText class="w-4 h-4" />
                  {{ t('inventory.openBodBocDoc') }}
                </Button>

                <Button 
                  v-if="(can('inventory.status_approval.request') || can('inventory.manage')) && asset && hasAssetStatus(asset.status, 'Pending:BoD/BoC', asset.approval_status)"
                  @click="triggerBodBocUpload"
                  :disabled="isUploading"
                  variant="success"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <Upload class="w-4 h-4" />
                  {{ isUploading ? t('inventory.uploading') : t('inventory.uploadBodBocDoc') }}
                </Button>

                <!-- BoD/BoC Disapproval Document Button -->
                <Button 
                  v-if="asset && asset.bod_boc_disapproval_url"
                  @click="openMemoFile(asset.bod_boc_disapproval_url)"
                  variant="destructive"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <FileText class="w-4 h-4" />
                  {{ t('inventory.openBodBocDisapprovalDoc') }}
                </Button>

                <Button 
                  v-if="(can('inventory.status_approval.request') || can('inventory.manage')) && asset && hasAssetStatus(asset.status, 'Pending:BoD/BoC', asset.approval_status)"
                  @click="triggerBodBocDisapprovalUpload"
                  :disabled="isUploading"
                  variant="destructive"
                  size="lg"
                  class="inline-flex items-center gap-2"
                >
                  <Upload class="w-4 h-4" />
                  {{ isUploading ? t('inventory.uploading') : t('inventory.uploadBodBocDisapprovalDoc') }}
                </Button>

                <Button 
                  v-if="can('inventory.manage')"
                  @click="
                    emit('update:open', false);
                    emit('edit', props.asset);
                  "
                  variant="primary"
                  size="lg"
                >
                  {{ t('inventory.editAssetDetail') }}
                </Button>
              </template>

              <Button 
                @click="emit('update:open', false)"
                variant="white"
                size="lg"
              >
                {{ t('common.back') }}
              </Button>
              </template>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
