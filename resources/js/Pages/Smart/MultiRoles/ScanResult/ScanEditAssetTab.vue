<script setup lang="ts">
/**
 * Tab component for editing asset details from mobile scan view.
 * Aligns primary inputs and order with desktop conventions, plus a collapsible advanced attributes section.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ChevronDown, Loader2, Camera, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import StatusBadge from '@/Components/StatusBadge.vue';
import { compressImageIfNeeded } from '@/utils/imageCompressor';
import { hasAssetStatus, isRestrictedAssetStatus } from '@/lib/assetStatus';
import { getQcEditTitle } from '@/lib/qualityControl';

interface Props {
  asset: any;
  locations?: any[];
  projects?: any[];
  lot?: any;
  barang?: any;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'cancel'): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

const photoInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
const memoFileInput = ref<HTMLInputElement | null>(null);
const lostDocFileInput = ref<HTMLInputElement | null>(null);
const bodBocFileInput = ref<HTMLInputElement | null>(null);

const isCollapsibleOpen = ref(false);

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

const arrNeedApproval = ['Rusak Total', 'Hilang'];
const arrInactiveConditions = ['Rusak Total', 'Hilang'];

const isRestrictedStatus = (status: string | null | undefined, approvalStatus?: string | null) => {
  return isRestrictedAssetStatus(status) || isRestrictedAssetStatus(approvalStatus);
};

const isRestrictedAsset = computed(() => {
  return isRestrictedStatus(props.asset?.status, props.asset?.approval_status) || arrInactiveConditions.includes(props.asset?.condition);
});

const isKondisiDisabled = computed(() => isRestrictedAsset.value);

const isStatusDisabled = computed(() => {
  if (isRestrictedAsset.value) return true;
  return arrInactiveConditions.includes(form.condition);
});

const isDocumentDisabled = computed(() => isRestrictedAsset.value);

const isBodBocFieldVisible = computed(() => {
  return hasAssetStatus(props.asset?.status, 'Pending:BoD/BoC', props.asset?.approval_status);
});

const isBorrowedUnit = computed(() => {
  const s = (props.asset?.status || '').toLowerCase();
  return s.includes('dipinjam') || s.includes('assign');
});

const isBorrowingTypeDisabled = computed(() => isBorrowedUnit.value || isRestrictedAsset.value);

const qcLabel = computed(() => {
  const lastQc = props.asset?.last_qc ?? props.asset?.lq;
  return getQcEditTitle(lastQc, t);
});

const projectOptions = computed(() => {
  return (props.projects || []).map((p: any) => ({
    id: p.id ?? p.id_project,
    name: p.no_project ? `[${p.no_project}] ${p.project_name}` : (p.name || p.project_name),
  }));
});

// Form setup
const form = useForm({
  number: props.asset?.number || '',
  lot_id: props.lot?.id || props.asset?.lot_id || '',
  location_id: props.asset?.location_id || '',
  status: props.asset?.status || '',
  approval_status: props.asset?.approval_status || '',
  condition: props.asset?.condition || '',
  qc_pass: props.asset?.qc_pass !== undefined && props.asset?.qc_pass !== null ? props.asset.qc_pass : null,
  specification: props.asset?.comp_specification || props.asset?.specification || '',
  type: props.asset?.borrowing_type || props.asset?.type || 'LT',
  borrowing_type: props.asset?.borrowing_type || props.asset?.type || 'LT',
  classification: props.asset?.classification || 'Aset',
  burden: props.asset?.burden || 'Corporate',
  project_id: props.asset?.project_id || '',
  price: props.asset?.price !== null && props.asset?.price !== undefined ? props.asset.price : '',
  image_url: null as File | null,
  image_url_name: props.asset?.image_url ? props.asset.image_url.split('/').pop() || '' : '',
  delete_image: false,
  vehicle_registration: props.asset?.vehicle_registration || '',
  memo_file: null as File | null,
  memo_file_name: props.asset?.memo_file_name || (props.asset?.memo_url ? props.asset.memo_url.split('/').pop() || '' : ''),
  lost_doc_file: null as File | null,
  lost_doc_file_name: props.asset?.lost_doc_file_name || (props.asset?.lost_doc_url ? props.asset.lost_doc_url.split('/').pop() || '' : ''),
  bod_boc_approval_file: null as File | null,
  bod_boc_approval_file_name: props.asset?.bod_boc_approval_file_name || (props.asset?.bod_boc_approval_url ? props.asset.bod_boc_approval_url.split('/').pop() || '' : ''),
});

watch(() => props.asset, (newAsset) => {
  if (!newAsset) return;
  form.number = newAsset.number || '';
  form.location_id = newAsset.location_id || '';
  form.status = newAsset.status || '';
  form.approval_status = newAsset.approval_status || '';
  form.condition = newAsset.condition || '';
  form.qc_pass = newAsset.qc_pass !== undefined && newAsset.qc_pass !== null ? newAsset.qc_pass : null;
  form.specification = newAsset.comp_specification || newAsset.specification || '';
  form.type = newAsset.borrowing_type || newAsset.type || 'LT';
  form.borrowing_type = newAsset.borrowing_type || newAsset.type || 'LT';
  form.classification = newAsset.classification || 'Aset';
  form.burden = newAsset.burden || 'Corporate';
  form.project_id = newAsset.project_id || '';
  form.price = newAsset.price !== null && newAsset.price !== undefined ? newAsset.price : '';
  form.image_url = null;
  form.image_url_name = newAsset.image_url ? newAsset.image_url.split('/').pop() || '' : '';
  form.delete_image = false;
  form.vehicle_registration = newAsset.vehicle_registration || '';
  form.memo_file = null;
  form.memo_file_name = newAsset.memo_file_name || (newAsset.memo_url ? newAsset.memo_url.split('/').pop() || '' : '');
  form.lost_doc_file = null;
  form.lost_doc_file_name = newAsset.lost_doc_file_name || (newAsset.lost_doc_url ? newAsset.lost_doc_url.split('/').pop() || '' : '');
  form.bod_boc_approval_file = null;
  form.bod_boc_approval_file_name = newAsset.bod_boc_approval_file_name || (newAsset.bod_boc_approval_url ? newAsset.bod_boc_approval_url.split('/').pop() || '' : '');

  if (!isRestrictedStatus(newAsset.status, newAsset.approval_status) && arrInactiveConditions.includes(form.condition)) {
    form.approval_status = 'Pending:DM';
  }
}, { deep: true, immediate: true });

const errors = ref({
  location_id: '',
  status: '',
  condition: '',
  image_url: '',
  vehicle_registration: '',
  memo_file: '',
  lost_doc_file: '',
  bod_boc_approval_file: '',
  specification: '',
  type: '',
  classification: '',
  burden: '',
  project_id: '',
  price: '',
});

const resetErrors = () => {
  errors.value = {
    location_id: '',
    status: '',
    condition: '',
    image_url: '',
    vehicle_registration: '',
    memo_file: '',
    lost_doc_file: '',
    bod_boc_approval_file: '',
    specification: '',
    type: '',
    classification: '',
    burden: '',
    project_id: '',
    price: '',
  };
};

// Reactive error clearing
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.status, v => { if (v && errors.value.status) errors.value.status = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.memo_file, v => { if (v && errors.value.memo_file) errors.value.memo_file = ''; });
watch(() => form.lost_doc_file, v => { if (v && errors.value.lost_doc_file) errors.value.lost_doc_file = ''; });
watch(() => form.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });

watch(() => form.condition, (newVal, oldVal) => {
  if (arrInactiveConditions.includes(newVal)) {
    form.approval_status = 'Pending:DM';
  } else if (oldVal && arrInactiveConditions.includes(oldVal)) {
    form.approval_status = props.asset?.approval_status || '';
  }

  if (!arrNeedApproval.includes(newVal)) {
    form.memo_file = null;
    form.memo_file_name = '';
  }
  if (newVal !== 'Hilang') {
    form.lost_doc_file = null;
    form.lost_doc_file_name = '';
  }
});

watch(() => form.type, (val) => {
  form.borrowing_type = val;
});

const hasImage = computed(() => {
  return Boolean(form.image_url || (form.image_url_name && !form.delete_image));
});

const handleFileUpload = async (e: any) => {
  const target = e.target as HTMLInputElement;
  const rawFile = target.files?.[0];
  if (!rawFile) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(rawFile.type) && !rawFile.type.startsWith('image/')) {
    toast.error(t('scanner.invalidImageFormat'));
    target.value = '';
    return;
  }

  try {
    const file = await compressImageIfNeeded(rawFile);
    if (file.size > 1024 * 1024) {
      toast.error(t('scanner.photoMaxSize1MB'));
      target.value = '';
      return;
    }
    form.image_url = file;
    form.image_url_name = file.name;
    form.delete_image = false;
  } catch (err) {
    console.error('Failed to process image:', err);
    toast.error(t('scanner.processImageFailed'));
  } finally {
    target.value = '';
  }
};

const triggerFileInput = () => {
  photoInput.value?.click();
};

const triggerCameraInput = () => {
  cameraInput.value?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    const objectUrl = URL.createObjectURL(form.image_url);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.image_url && !form.delete_image) {
    window.open('/media/' + props.asset.image_url, '_blank');
  }
};

const handleDeletePhoto = () => {
  form.image_url = null;
  form.image_url_name = '';
  form.delete_image = true;
};

const handleMemoUpload = (e: any) => {
  if (isDocumentDisabled.value) return;
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('scanner.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('scanner.docMaxSize2MB')); return; }
  form.memo_file = file;
  form.memo_file_name = file.name;
};

const triggerMemoFileInput = () => {
  if (isDocumentDisabled.value) return;
  memoFileInput.value?.click();
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    const objectUrl = URL.createObjectURL(form.memo_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.memo_url) {
    window.open('/media/' + props.asset.memo_url, '_blank');
  }
};

const handleLostDocUpload = (e: any) => {
  if (isDocumentDisabled.value) return;
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('scanner.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('scanner.docMaxSize2MB')); return; }
  form.lost_doc_file = file;
  form.lost_doc_file_name = file.name;
};

const triggerLostDocFileInput = () => {
  if (isDocumentDisabled.value) return;
  lostDocFileInput.value?.click();
};

const viewLostDocInNewTab = () => {
  if (form.lost_doc_file) {
    const objectUrl = URL.createObjectURL(form.lost_doc_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.lost_doc_url) {
    window.open('/media/' + props.asset.lost_doc_url, '_blank');
  }
};

const handleBodBocDocUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('scanner.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('scanner.docMaxSize2MB')); return; }
  form.bod_boc_approval_file = file;
  form.bod_boc_approval_file_name = file.name;
};

const triggerBodBocDocFileInput = () => {
  bodBocFileInput.value?.click();
};

const viewBodBocDocInNewTab = () => {
  if (form.bod_boc_approval_file) {
    const objectUrl = URL.createObjectURL(form.bod_boc_approval_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.bod_boc_approval_url) {
    window.open('/media/' + props.asset.bod_boc_approval_url, '_blank');
  }
};

const parseCurrencyToNumber = (val: string | number) => {
  if (typeof val === 'number') return Math.max(0, val);
  if (!val) return 0;
  let clean = val.toString().trim();
  if (clean.includes('.') && clean.includes(',')) {
    if (clean.lastIndexOf('.') > clean.lastIndexOf(',')) {
      clean = clean.replace(/,/g, '');
    } else {
      clean = clean.replace(/\./g, '').replace(/,/g, '.');
    }
  } else if (clean.includes(',')) {
    const parts = clean.split(',');
    if (parts[parts.length - 1].length === 3) {
      clean = clean.replace(/,/g, '');
    } else {
      clean = clean.replace(/,/g, '.');
    }
  }
  const parsed = parseFloat(clean);
  return isNaN(parsed) ? 0 : Math.max(0, parsed);
};

const handleSubmit = () => {
  resetErrors();

  let isValid = true;
  if (!isSoft.value) {
    if (!form.condition) { errors.value.condition = t('scanner.conditionRequired'); isValid = false; }
    if (!form.location_id) { errors.value.location_id = t('scanner.locationRequired'); isValid = false; }
  }
  if (!form.status) { errors.value.status = t('scanner.statusRequired'); isValid = false; }
  if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = t('scanner.tnkbRequired'); isValid = false; }
  if (!isSoft.value && arrNeedApproval.includes(form.condition) && !isDocumentDisabled.value && !form.memo_file_name) { errors.value.memo_file = t('scanner.memoRequired'); isValid = false; }
  if (!isSoft.value && form.condition === 'Hilang' && !isDocumentDisabled.value && !form.lost_doc_file_name) { errors.value.lost_doc_file = t('scanner.lostDocRequired'); isValid = false; }
  if (form.burden === 'Project' && !form.project_id) {
    errors.value.project_id = t('inventory.projectRequired') || 'Proyek belum dipilih';
    isValid = false;
  }

  if (!isValid) {
    toast.error(t('scanner.fillRequiredFields'));
    return;
  }

  form.transform((data) => {
    const fd: any = {
      _method: 'PUT',
      number: data.number,
      lot_id: props.lot?.id || props.asset?.lot_id,
      location_id: isSoft.value ? null : data.location_id,
      status: data.status,
      condition: isSoft.value ? null : data.condition,
      borrowing_type: data.type || data.borrowing_type,
      classification: data.classification,
      burden: data.burden,
      project_id: data.burden === 'Project' ? data.project_id : null,
      price: data.price !== '' && data.price !== null ? parseCurrencyToNumber(data.price) : null,
    };
    if (data.qc_pass !== null && data.qc_pass !== undefined && data.qc_pass !== '') {
      fd.qc_pass = data.qc_pass;
    }
    if (isComp.value) {
      fd.comp_specification = data.specification;
      fd.specification = data.specification;
    }
    if (isVehicle.value) {
      fd.vehicle_registration = data.vehicle_registration;
    }
    if (data.image_url) fd.image_url = data.image_url;
    if (data.delete_image) fd.delete_image = data.delete_image;
    if (data.memo_file) fd.memo_file = data.memo_file;
    if (data.lost_doc_file) fd.lost_doc_file = data.lost_doc_file;
    if (data.bod_boc_approval_file) fd.bod_boc_approval_file = data.bod_boc_approval_file;
    return fd;
  }).post(`/smart/stock-management/assets/${props.asset.id}`, {
    onSuccess: () => {
      toast.success(t('scanner.assetUpdatedSuccess'));
      emit('success');
    },
    onError: (err) => {
      if (err.location_id) errors.value.location_id = err.location_id;
      if (err.status) errors.value.status = err.status;
      if (err.condition) errors.value.condition = err.condition;
      if (err.image_url) errors.value.image_url = err.image_url;
      if (err.vehicle_registration) errors.value.vehicle_registration = err.vehicle_registration;
      if (err.memo_file) errors.value.memo_file = err.memo_file;
      if (err.lost_doc_file) errors.value.lost_doc_file = err.lost_doc_file;
      if (err.bod_boc_approval_file) errors.value.bod_boc_approval_file = err.bod_boc_approval_file;
      if (err.project_id) errors.value.project_id = err.project_id;
      if (err.price) errors.value.price = err.price;
      toast.error(t('scanner.assetUpdateError'));
    }
  });
};
</script>

<template>
  <div class="space-y-6">
    <div class="bg-card rounded-2xl p-5 border border-border shadow-sm space-y-5">
      <div class="space-y-4">
        <!-- 1. Condition (Kondisi) -->
        <Field v-if="!isSoft" :data-invalid="!!errors.condition || undefined" :data-disabled="isKondisiDisabled || undefined">
          <FieldLabel>
            <span>{{ t('scanner.condition') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div v-if="isKondisiDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-xl bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
              <span>{{ form.condition || t('scanner.selectCondition') }}</span>
              <ChevronDown class="w-4 h-4 opacity-50" />
            </div>
            <DropdownMenu v-else>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-full justify-between rounded-xl font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground']">
                  {{ form.condition || t('scanner.selectCondition') }}
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
                <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
                <template v-if="!isBorrowedUnit">
                  <DropdownMenuItem @select="form.condition = 'Rusak Total'">{{ t('inventory.conditionTotalDamage') }}</DropdownMenuItem>
                  <DropdownMenuItem @select="form.condition = 'Hilang'">{{ t('inventory.conditionLost') }}</DropdownMenuItem>
                </template>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
          <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
        </Field>

        <!-- 2. Quality Control (non-software) -->
        <Field v-if="!isSoft">
          <FieldLabel>
            <span>{{ qcLabel }}</span>
          </FieldLabel>
          <FieldContent>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  :class="[
                    'w-full justify-between rounded-xl font-normal h-10 px-4',
                    form.qc_pass === null
                      ? 'text-muted-foreground'
                      : (form.qc_pass ? 'text-emerald-600 font-semibold' : 'text-rose-600 font-semibold')
                  ]"
                >
                  {{
                    form.qc_pass === null
                      ? t('inventory.unchanged')
                      : (form.qc_pass ? t('inventory.qcPass') : t('inventory.qcFail'))
                  }}
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                <DropdownMenuItem @select="form.qc_pass = null">{{ t('inventory.unchanged') }}</DropdownMenuItem>
                <DropdownMenuItem class="text-emerald-600 font-semibold" @select="form.qc_pass = true">{{ t('inventory.qcPass') }}</DropdownMenuItem>
                <DropdownMenuItem class="text-rose-600 font-semibold" @select="form.qc_pass = false">{{ t('inventory.qcFail') }}</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
        </Field>

        <!-- 3. Location (non-software) -->
        <Field v-if="!isSoft" :data-invalid="!!errors.location_id || undefined">
          <FieldLabel>
            <span>{{ t('scanner.location') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <LocationCombobox
              v-model="form.location_id"
              :locations="locations || []"
              :placeholder="t('scanner.selectLocation')"
              :error="!!errors.location_id"
              :active-only="true"
            />
          </FieldContent>
          <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
        </Field>

        <!-- 4. Photo -->
        <Field :data-invalid="!!errors.image_url || undefined">
          <FieldLabel>
            <span>{{ t('scanner.photo') }}</span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-2">
              <div class="flex items-center gap-2">
                <div
                  class="flex-1 min-w-0 px-4 py-2 text-sm border rounded-xl bg-muted/10 truncate flex items-center h-10"
                  :class="[hasImage ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.image_url ? 'border-destructive' : 'border-input']"
                  @click="hasImage && viewImageInNewTab()"
                >
                  {{ form.image_url_name || t('scanner.noPhotoSelected') }}
                </div>
                <Button
                  type="button"
                  variant="destructive"
                  size="icon"
                  class="w-10 h-10 rounded-xl shrink-0 cursor-pointer"
                  :disabled="!hasImage"
                  @click="handleDeletePhoto"
                  :title="t('inventory.deletePhoto')"
                >
                  <Trash2 class="w-4 h-4" />
                </Button>
              </div>
              <div class="flex gap-2 w-full">
                <input ref="cameraInput" type="file" id="edit-asset-camera-upload" class="hidden" accept="image/*" capture="environment" @change="handleFileUpload" />
                <input ref="photoInput" type="file" id="edit-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png,.webp" @change="handleFileUpload" />
                <Button type="button" @click="triggerFileInput" size="lg" class="flex-1 rounded-xl h-10 cursor-pointer font-semibold">{{ t('scanner.upload') }}</Button>
                <Button type="button" @click="triggerCameraInput" variant="success" size="lg" class="flex-1 rounded-xl h-10 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer font-semibold flex items-center justify-center gap-2" :title="t('scanner.takePhoto')">
                  <Camera class="w-4 h-4" />
                  <span>{{ t('scanner.takePhoto') }}</span>
                </Button>
              </div>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('scanner.maxPhotoSize') }}</p>
          </FieldContent>
          <FieldError v-if="errors.image_url">{{ errors.image_url }}</FieldError>
        </Field>

        <!-- 5. Status -->
        <Field :data-invalid="!!errors.status || undefined" :data-disabled="isStatusDisabled || undefined">
          <FieldLabel>
            <span>{{ t('scanner.status') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div v-if="isStatusDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-xl bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
              <StatusBadge v-if="form.status" :status="form.status" :approval-status="form.approval_status" class="rounded-sm" />
              <span v-else>{{ t('scanner.selectStatus') }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 ml-auto shrink-0" />
            </div>
            <DropdownMenu v-else>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-full justify-between rounded-xl font-normal h-10 px-4', !form.status ? 'text-muted-foreground' : 'text-foreground']">
                  <span v-if="form.status">{{ form.status }}</span>
                  <span v-else>{{ t('scanner.selectStatus') }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                <DropdownMenuItem @select="form.status = 'Tersedia'">Tersedia</DropdownMenuItem>
                <DropdownMenuItem @select="form.status = 'Dipinjam (ST)'">Dipinjam (ST)</DropdownMenuItem>
                <DropdownMenuItem @select="form.status = 'Di-assign (LT)'">Di-assign (LT)</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
          <FieldError v-if="errors.status">{{ errors.status }}</FieldError>
        </Field>

        <!-- TNKB (Only for Vehicles) -->
        <Field v-if="isVehicle" :data-invalid="!!errors.vehicle_registration || undefined">
          <FieldLabel><span>{{ t('scanner.tnkb') }}<span class="text-rose-500">*</span></span></FieldLabel>
          <FieldContent>
            <input
              type="text"
              v-model="form.vehicle_registration"
              :placeholder="t('scanner.tnkbPlaceholder')"
              class="w-full px-4 py-2 text-sm border border-input rounded-xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
            />
          </FieldContent>
          <FieldError v-if="errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
        </Field>

        <!-- 6. *Official Report/Memo (Dynamic based on condition: Rusak Total / Hilang) -->
        <Field v-if="arrNeedApproval.includes(form.condition)" :data-invalid="!!errors.memo_file || undefined" :data-disabled="isDocumentDisabled || undefined">
          <FieldLabel>
            <span>{{ t('inventory.memoDocument') || t('scanner.memoOrMinutes') }}<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="flex gap-2 flex-col xs:flex-row">
              <div
                class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl truncate flex items-center h-10"
                :class="[
                  form.memo_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                  errors.memo_file ? 'border-destructive' : 'border-input',
                  isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/10'
                ]"
                @click="form.memo_file_name && viewMemoInNewTab()"
              >
                {{ form.memo_file_name || t('scanner.noFileSelected') }}
              </div>
              <div class="flex gap-2 shrink-0">
                <input ref="memoFileInput" type="file" id="edit-asset-memo-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleMemoUpload" :disabled="isDocumentDisabled" />
                <Button type="button" @click="triggerMemoFileInput" size="lg" class="rounded-xl h-10 w-full xs:w-auto" :disabled="isDocumentDisabled">{{ t('scanner.chooseDocument') }}</Button>
              </div>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('scanner.maxFileSize') }}</p>
          </FieldContent>
          <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
        </Field>

        <!-- 7. *Lost Document Report (Dynamic based on condition: Hilang, formatted as "SK Kehilangan") -->
        <Field v-if="form.condition === 'Hilang'" :data-invalid="!!errors.lost_doc_file || undefined" :data-disabled="isDocumentDisabled || undefined">
          <FieldLabel>
            <span>{{ t('inventory.lostDocFile') }}<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="flex gap-2 flex-col xs:flex-row">
              <div
                class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl truncate flex items-center h-10"
                :class="[
                  form.lost_doc_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                  errors.lost_doc_file ? 'border-destructive' : 'border-input',
                  isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/10'
                ]"
                @click="form.lost_doc_file_name && viewLostDocInNewTab()"
              >
                {{ form.lost_doc_file_name || t('scanner.noFileSelected') }}
              </div>
              <div class="flex gap-2 shrink-0">
                <input ref="lostDocFileInput" type="file" id="edit-asset-lost-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleLostDocUpload" :disabled="isDocumentDisabled" />
                <Button type="button" @click="triggerLostDocFileInput" size="lg" class="rounded-xl h-10 w-full xs:w-auto" :disabled="isDocumentDisabled">{{ t('scanner.chooseDocument') }}</Button>
              </div>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('scanner.maxFileSize') }}</p>
          </FieldContent>
          <FieldError v-if="errors.lost_doc_file">{{ errors.lost_doc_file }}</FieldError>
        </Field>

        <!-- BoD/BoC Approval Document (when pending BoD/BoC) -->
        <Field v-if="isBodBocFieldVisible" :data-invalid="!!errors.bod_boc_approval_file || undefined">
          <FieldLabel><span>{{ t('scanner.bodBocApprovalForm') }}</span></FieldLabel>
          <FieldContent>
            <div class="flex gap-2 flex-col xs:flex-row">
              <div
                class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl bg-muted/10 truncate flex items-center h-10"
                :class="[
                  form.bod_boc_approval_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                  errors.bod_boc_approval_file ? 'border-destructive' : 'border-input'
                ]"
                @click="form.bod_boc_approval_file_name && viewBodBocDocInNewTab()"
              >
                {{ form.bod_boc_approval_file_name || t('scanner.noFileSelected') }}
              </div>
              <div class="flex gap-2 shrink-0">
                <input ref="bodBocFileInput" type="file" id="edit-asset-bod-boc-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleBodBocDocUpload" />
                <Button type="button" @click="triggerBodBocDocFileInput" size="lg" class="rounded-xl h-10 w-full xs:w-auto">{{ t('scanner.chooseDocument') }}</Button>
              </div>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('scanner.maxFileSize') }}</p>
          </FieldContent>
          <FieldError v-if="errors.bod_boc_approval_file">{{ errors.bod_boc_approval_file }}</FieldError>
        </Field>

        <!-- ── Collapsible Section: Specification, Borrowing Type, Unit Price, Classification, Burden, Project ── -->
        <div class="pt-2 border-t border-border">
          <button
            type="button"
            @click="isCollapsibleOpen = !isCollapsibleOpen"
            class="w-full flex items-center justify-between py-2 text-xs font-bold text-foreground uppercase tracking-wider transition-colors hover:text-primary cursor-pointer focus:outline-none"
            :aria-expanded="isCollapsibleOpen"
          >
            <span>{{ t('scanner.additionalDetails') || t('inventory.moreInfo') }}</span>
            <ChevronDown
              class="w-4 h-4 transition-transform duration-200 transform"
              :class="{ 'rotate-180': isCollapsibleOpen }"
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
            <div v-if="isCollapsibleOpen" class="space-y-4 pt-3">
              <!-- Specification -->
              <Field>
                <FieldLabel><span>{{ t('inventory.specification') }}</span></FieldLabel>
                <FieldContent>
                  <textarea
                    v-model="form.specification"
                    rows="2"
                    :placeholder="t('inventory.specificationPlaceholder')"
                    class="w-full px-4 py-2 text-sm border border-input rounded-xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
                  />
                </FieldContent>
              </Field>

              <!-- Borrowing Type (LT / ST) -->
              <Field :data-disabled="isBorrowingTypeDisabled || undefined">
                <FieldLabel>
                  <span>{{ t('inventory.borrowingType') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div v-if="isBorrowingTypeDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-xl bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
                    <span>{{ form.type || form.borrowing_type }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </div>
                  <DropdownMenu v-else>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" class="w-full justify-between rounded-xl font-normal h-10 px-4 text-foreground">
                        {{ form.type || form.borrowing_type }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                      <DropdownMenuItem @select="form.type = 'LT'">LT</DropdownMenuItem>
                      <DropdownMenuItem @select="form.type = 'ST'">ST</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </FieldContent>
              </Field>

              <!-- Unit Price -->
              <Field>
                <FieldLabel>
                  <span>{{ t('scanner.unitPrice') }}</span>
                </FieldLabel>
                <FieldContent>
                  <div class="flex rounded-xl border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden w-full">
                    <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                    <input
                      type="number"
                      v-model="form.price"
                      :placeholder="t('scanner.unitPricePlaceholder')"
                      min="0"
                      class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                    />
                  </div>
                </FieldContent>
              </Field>

              <!-- Classification (Aset / Inventaris) -->
              <Field>
                <FieldLabel>
                  <span>{{ t('inventory.classification') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" class="w-full justify-between rounded-xl font-normal h-10 px-4 text-foreground">
                        {{ form.classification === 'Aset' ? t('inventory.classificationAsset') : t('inventory.classificationInventory') }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                      <DropdownMenuItem @select="form.classification = 'Aset'">{{ t('inventory.classificationAsset') }}</DropdownMenuItem>
                      <DropdownMenuItem @select="form.classification = 'Inventaris'">{{ t('inventory.classificationInventory') }}</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </FieldContent>
              </Field>

              <!-- Burden (Corporate / Project) -->
              <Field>
                <FieldLabel>
                  <span>{{ t('inventory.burden') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" class="w-full justify-between rounded-xl font-normal h-10 px-4 text-foreground">
                        {{ form.burden === 'Project' ? t('inventory.project') : t('inventory.corporate') }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                      <DropdownMenuItem @select="form.burden = 'Corporate'">{{ t('inventory.corporate') }}</DropdownMenuItem>
                      <DropdownMenuItem @select="form.burden = 'Project'">{{ t('inventory.project') }}</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </FieldContent>
              </Field>

              <!-- *Project selector (dynamic based on Burden: Project) -->
              <Field v-if="form.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
                <FieldLabel>
                  <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <Combobox
                    v-model="form.project_id"
                    :options="projectOptions"
                    :search-placeholder="t('inventory.searchProjectPlaceholder')"
                    :default-label="t('inventory.selectProject')"
                    width-class="w-full h-10 px-4 rounded-xl"
                    :error="!!errors.project_id"
                  />
                </FieldContent>
                <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
              </Field>
            </div>
          </Transition>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="border-t border-border pt-4 flex gap-3">
        <Button
          @click="emit('cancel')"
          variant="white"
          size="lg"
          class="flex-1 rounded-xl h-11 text-sm font-semibold cursor-pointer"
        >
          {{ t('common.cancel') }}
        </Button>
        <Button
          @click="handleSubmit"
          :disabled="form.processing"
          variant="primary"
          size="lg"
          class="flex-1 rounded-xl h-11 text-sm font-semibold relative cursor-pointer"
        >
          <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
          <span :class="{ 'opacity-0': form.processing }" class="flex items-center justify-center">
            {{ t('common.save') }}
          </span>
        </Button>
      </div>
    </div>
  </div>
</template>
