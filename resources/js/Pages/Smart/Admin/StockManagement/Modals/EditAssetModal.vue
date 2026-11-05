<script setup lang="ts">
/**
 * Edit Asset Modal component supporting single asset updates and bulk edits with status/condition validation rules.
 * Componentized into AssetMainSection and AssetDocumentsSection (<500 LOC).
 */
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { compressImageIfNeeded } from '@/utils/imageCompressor';
import {
  hasAssetStatus,
  isRestrictedAssetStatus,
  formatAssetStatusLabels,
} from '@/lib/assetStatus';
import AssetMainSection from './EditAsset/AssetMainSection.vue';
import AssetDocumentsSection from './EditAsset/AssetDocumentsSection.vue';

interface Props {
  open: boolean;
  /** The asset items to edit (single/bulk) */
  items: any[];
  /** Parent LOT object containing default location details and image_url */
  lot: any;
  /** Parent Barang object to check category */
  barang: any;
  locations: any[];
  floors?: any[];
  rooms?: any[];
  projects?: { id?: number; id_project?: number; no_project: string; project_name: string; client_id?: string }[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t, te } = useI18n();

useModalLock(computed(() => props.open));

// Gated Computeds for Performance Optimization
const isSingle = computed(() => {
  if (!props.open) return true;
  return props.items.length === 1;
});

const selectedItem = computed(() => {
  if (!props.open) return null;
  return isSingle.value && props.items.length > 0 ? props.items[0] : null;
});

const isVehicle = computed(() => {
  if (!props.open) return false;
  return props.barang?.category_code === 'KEND' ||
    props.barang?.category === 'Kendaraan' ||
    props.barang?.category === 'KEND' ||
    props.lot?.barang_category_code === 'KEND';
});

const isComp = computed(() => {
  if (!props.open) return false;
  return props.barang?.category_code === 'COMP' ||
    props.barang?.category === 'Computer' ||
    props.barang?.category === 'COMP' ||
    props.lot?.barang_category_code === 'COMP';
});

const isSoft = computed(() => {
  if (!props.open) return false;
  return props.barang?.category_code === 'SOFT' ||
    props.barang?.category === 'Software' ||
    props.barang?.category === 'SOFT' ||
    props.lot?.barang_category_code === 'SOFT' ||
    props.lot?.model_category_code === 'SOFT' ||
    (isSingle.value && (
      selectedItem.value?.lot?.model?.type?.category?.code === 'SOFT' ||
      selectedItem.value?.model_category_code === 'SOFT'
    ));
});

const arrNeedApproval = ['Rusak Total', 'Hilang'];
const arrInactiveConditions = ['Rusak Total', 'Hilang'];

const getStatusLabel = (s: string) => {
  return formatAssetStatusLabels(s, t, te);
};

const getConditionLabel = (c: string) => {
  if (!c) return '';
  const map: Record<string, string> = {
    'Bagus': t('inventory.conditionGood'),
    'Rusak': t('inventory.conditionDamaged'),
    'Rusak Total': t('inventory.conditionTotalDamage'),
    'Hilang': t('inventory.conditionLost'),
  };
  return map[c] || c;
};

const getClassificationLabel = (c: string) => {
  if (!c) return '';
  if (c === 'Aset') return t('inventory.classificationAsset');
  if (c === 'Inventaris') return t('inventory.classificationInventory');
  return c;
};

const getBurdenLabel = (b: string) => {
  if (!b || b === 'Tidak berubah') return t('inventory.unchanged');
  if (b === 'Project') return t('inventory.project');
  if (b === 'Corporate') return t('inventory.corporate');
  return b;
};

const isRestrictedStatus = (status: string | null | undefined, approvalStatus?: string | null | undefined) => {
  return isRestrictedAssetStatus(status);
};

const isBorrowedUnit = computed(() => {
  if (!props.open) return false;
  return (props.items || []).some(item => String(item?.status).trim().toLowerCase() === 'dipinjam (st)');
});

const isBorrowingTypeDisabled = computed(() => {
  if (!props.open) return false;
  return (props.items || []).some(item => {
    const s = String(item?.status || '').trim().toLowerCase();
    return s === 'dipinjam (st)' || s === 'dipinjam (sewa)' || s === 'di-assign (lt)' || s.startsWith('dipinjam') || s.startsWith('di-assign');
  });
});

const hasRestrictedUnit = computed(() => {
  if (!props.open) return false;
  return (props.items || []).some(item => isRestrictedStatus(item?.status, item?.approval_status) || arrInactiveConditions.includes(item?.condition));
});

const isKondisiDisabled = computed(() => {
  return hasRestrictedUnit.value;
});

const isStatusDisabled = computed(() => true);

const isDocumentDisabled = computed(() => {
  return hasRestrictedUnit.value;
});

const isBodBocFieldVisible = computed(() => {
  if (!props.open) return false;
  if (isSingle.value) {
    return hasAssetStatus(selectedItem.value?.status, 'Pending:BoD/BoC', selectedItem.value?.approval_status);
  }
  return props.items.length > 0 && props.items.every((item: any) => hasAssetStatus(item.status, 'Pending:BoD/BoC', item.approval_status));
});

const canShowAssignToLocation = computed(() => {
  if (isSoft.value) return false;
  if (!props.open || !props.items || props.items.length === 0) return false;
  if (isSingle.value) {
    const s = String(selectedItem.value?.status || '').trim().toLowerCase();
    return s === 'tersedia' || (s === 'di-assign (lt)' && Boolean(selectedItem.value?.is_assigned_to_location));
  }
  return props.items.every(item => {
    const s = String(item?.status || '').trim().toLowerCase();
    return s === 'tersedia';
  });
});

const form = useForm({
  ids: [] as number[],
  number: '',
  legacy_number: '',
  location_id: '' as string | number,
  status: '',
  approval_status: '',
  is_assigned_to_location: null as boolean | null,
  condition: '',
  qc_pass: null as boolean | string | null,
  type: '',
  classification: '',
  price: '' as string | number,
  specification: '',
  image_url: null as File | null,
  image_url_name: '',
  vehicle_registration: '',
  memo_file: null as File | null,
  memo_file_name: '',
  lost_doc_file: null as File | null,
  lost_doc_file_name: '',
  admin_note: '',
  bod_boc_approval_file: null as File | null,
  bod_boc_approval_file_name: '',
  bod_boc_disapproval_file: null as File | null,
  bod_boc_disapproval_file_name: '',
  burden: '',
  project_id: '' as string | number,
  license_exp_date: null as string | null,
  renewal_reminder: null as number | string | null,
  license_type: 'Subscription' as 'Subscription' | 'Perpetual',
});

const bodBocDecisionType = ref<'approval' | 'disapproval' | null>(null);

const errors = ref({
  specification: '',
  location_id: '',
  status: '',
  condition: '',
  type: '',
  classification: '',
  image_url: '',
  vehicle_registration: '',
  memo_file: '',
  lost_doc_file: '',
  admin_note: '',
  bod_boc_approval_file: '',
  bod_boc_disapproval_file: '',
  burden: '',
  project_id: '',
  license_exp_date: '',
  renewal_reminder: '',
});

const resetErrors = () => {
  errors.value = {
    specification: '',
    location_id: '',
    status: '',
    condition: '',
    type: '',
    classification: '',
    image_url: '',
    vehicle_registration: '',
    memo_file: '',
    lost_doc_file: '',
    admin_note: '',
    bod_boc_approval_file: '',
    bod_boc_disapproval_file: '',
    burden: '',
    project_id: '',
    license_exp_date: '',
    renewal_reminder: '',
  };
};

const isSubmitting = ref(false);
const isProcessing = computed(() => form.processing || isSubmitting.value);

const projectOptions = computed(() => {
  if (!props.open) return [];
  return (props.projects || [])
    .filter(p => Boolean(p.no_project?.trim() && p.project_name?.trim()))
    .map(p => ({
      id: (p.id ?? p.id_project) as number,
      name: `[${p.no_project}] ${p.project_name}`,
    }));
});

// Reactive error clearing
watch(() => form.specification, v => { if (v && errors.value.specification) errors.value.specification = ''; });
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.status, v => { if (v && errors.value.status) errors.value.status = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.type, v => { if (v && errors.value.type) errors.value.type = ''; });
watch(() => form.classification, v => { if (v && errors.value.classification) errors.value.classification = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.memo_file, v => { if (v && errors.value.memo_file) errors.value.memo_file = ''; });
watch(() => form.lost_doc_file, v => { if (v && errors.value.lost_doc_file) errors.value.lost_doc_file = ''; });
watch(() => form.bod_boc_approval_file, v => { if (v && errors.value.bod_boc_approval_file) errors.value.bod_boc_approval_file = ''; });
watch(() => form.bod_boc_disapproval_file, v => { if (v && errors.value.bod_boc_disapproval_file) errors.value.bod_boc_disapproval_file = ''; });
watch(() => form.burden, v => {
  if (v && errors.value.burden) errors.value.burden = '';
  if (v !== 'Project') {
    form.project_id = '';
    errors.value.project_id = '';
  }
});
watch(() => form.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });

watch(() => form.condition, (newVal, oldVal) => {
  const isRestricted = isSingle.value 
    ? isRestrictedStatus(selectedItem.value?.status, selectedItem.value?.approval_status)
    : hasRestrictedUnit.value;

  if (!isRestricted) {
    if (arrInactiveConditions.includes(newVal)) {
      form.approval_status = 'Pending:DM';
    } else if (oldVal && arrInactiveConditions.includes(oldVal)) {
      form.approval_status = isSingle.value ? (selectedItem.value?.approval_status || '') : '';
    }
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

const isImageDeleted = ref(false);

const hasImage = computed(() => {
  if (!isSingle.value) return Boolean(form.image_url);
  if (form.image_url) return true;
  if (isImageDeleted.value) return false;
  return Boolean(selectedItem.value?.image_url);
});

const handleAssignToLocationSelect = (value: boolean | null) => {
  form.is_assigned_to_location = value;
  if (value === true) {
    form.status = 'Di-assign (LT)';
    if (isSingle.value && selectedItem.value) {
      form.location_id = selectedItem.value.location_id || form.location_id;
    }
  } else if (value === false) {
    form.status = 'Tersedia';
  } else {
    form.status = '';
  }
};

const resetModalState = () => {
  form.reset();
  form.clearErrors();
  resetErrors();
  isImageDeleted.value = false;
  form.ids = [];
  form.number = '';
  form.legacy_number = '';
  form.location_id = '';
  form.status = '';
  form.approval_status = '';
  form.is_assigned_to_location = null;
  form.condition = '';
  form.type = '';
  form.classification = '';
  form.price = '';
  form.specification = '';
  form.image_url = null;
  form.image_url_name = '';
  form.vehicle_registration = '';
  form.memo_file = null;
  form.memo_file_name = '';
  form.lost_doc_file = null;
  form.lost_doc_file_name = '';
  form.admin_note = '';
  form.bod_boc_approval_file = null;
  form.bod_boc_approval_file_name = '';
  form.bod_boc_disapproval_file = null;
  form.bod_boc_disapproval_file_name = '';
  bodBocDecisionType.value = null;
  form.burden = 'Tidak berubah';
  form.project_id = '';
  form.license_exp_date = null;
  form.renewal_reminder = null;
  form.license_type = 'Subscription';

  const inputIds = [
    'edit-asset-photo-upload',
    'edit-asset-memo-upload',
    'edit-asset-lost-doc-upload',
    'edit-asset-bod-boc-doc-upload',
    'edit-asset-bod-boc-disapproval-doc-upload',
  ];
  inputIds.forEach(id => {
    const el = document.getElementById(id) as HTMLInputElement | null;
    if (el) el.value = '';
  });
};

// Init form when modal opens
watch(() => props.open, (val) => {
  if (!val) {
    resetModalState();
    return;
  }
  resetModalState();
  form.ids = props.items.map(r => r.id);

  if (isSingle.value && selectedItem.value) {
    const item = selectedItem.value;
    form.number = item.number || '';
    form.legacy_number = item.legacy_number || '';
    form.location_id = item.location_id || '';
    form.status = item.status || 'Tersedia';
    form.approval_status = item.approval_status || '';
    form.is_assigned_to_location = Boolean(item.is_assigned_to_location);
    form.condition = item.condition || '';
    form.qc_pass = null;
    form.type = item.type || item.borrowing_type || '';
    form.classification = item.classification || '';
    if (!isRestrictedStatus(item.status, item.approval_status) && arrInactiveConditions.includes(form.condition)) {
      form.approval_status = 'Pending:DM';
    }
    form.price = item.price || '';
    form.image_url_name = item.image_url ? item.image_url.split('/').pop() || '' : '';
    form.vehicle_registration = item.vehicle_registration || '';
    form.memo_file_name = item.memo_file_name || (item.memo_url ? item.memo_url.split('/').pop() || '' : '');
    form.lost_doc_file_name = item.lost_doc_file_name || (item.lost_doc_url ? item.lost_doc_url.split('/').pop() || '' : '');
    form.admin_note = item.admin_note || '';
    form.bod_boc_approval_file_name = item.bod_boc_approval_file_name || (item.bod_boc_approval_url ? item.bod_boc_approval_url.split('/').pop() || '' : '');
    form.bod_boc_disapproval_file_name = item.bod_boc_disapproval_file_name || (item.bod_boc_disapproval_url ? item.bod_boc_disapproval_url.split('/').pop() || '' : '');
    if (form.bod_boc_approval_file_name) {
      bodBocDecisionType.value = 'approval';
    } else if (form.bod_boc_disapproval_file_name) {
      bodBocDecisionType.value = 'disapproval';
    } else {
      bodBocDecisionType.value = null;
    }
    form.burden = item.burden || 'Corporate';
    form.project_id = item.project_id || '';
    form.specification = isComp.value ? (item.comp_specification || item.specification || '') : '';
    const hasExpDate = item.license_exp_date !== null && item.license_exp_date !== undefined && item.license_exp_date !== '';
    form.license_type = hasExpDate ? 'Subscription' : 'Perpetual';
    form.license_exp_date = hasExpDate ? item.license_exp_date : null;
    form.renewal_reminder = item.renewal_reminder !== null && item.renewal_reminder !== undefined ? item.renewal_reminder : null;
  } else {
    form.number = '';
    form.legacy_number = '';
    form.location_id = '';
    form.status = '';
    form.approval_status = '';
    form.is_assigned_to_location = null;
    form.condition = '';
    form.qc_pass = null;
    form.type = '';
    form.classification = '';
    form.price = '';
    form.specification = '';
    form.vehicle_registration = '';
    form.burden = 'Tidak berubah';
    form.project_id = '';
    form.license_exp_date = null;
    form.renewal_reminder = null;
    form.license_type = 'Subscription';
  }
}, { immediate: true });

const closeModal = () => {
  resetModalState();
  emit('update:open', false);
};

const handleFileUpload = async (e: any) => {
  const target = e.target as HTMLInputElement;
  const rawFile = target.files?.[0];
  if (!rawFile) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(rawFile.type) && !rawFile.type.startsWith('image/')) {
    toast.error(t('inventory.invalidFileFormat'));
    target.value = '';
    return;
  }

  try {
    const file = await compressImageIfNeeded(rawFile);
    if (file.size > 1024 * 1024) {
      toast.error(t('inventory.fileTooLarge1Mb'));
      target.value = '';
      return;
    }
    isImageDeleted.value = false;
    form.image_url = file;
    form.image_url_name = file.name;
  } catch (err) {
    console.error('Gagal memproses gambar:', err);
    toast.error(t('inventory.failedProcessImage'));
  } finally {
    target.value = '';
  }
};

const handleDeletePhoto = () => {
  isImageDeleted.value = true;
  form.image_url = null;
  form.image_url_name = '';
  const input = document.getElementById('edit-asset-photo-upload') as HTMLInputElement;
  if (input) input.value = '';
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-asset-photo-upload') as HTMLInputElement;
  input?.click();
};

const triggerMemoFileInput = () => {
  const input = document.getElementById('edit-asset-memo-upload') as HTMLInputElement;
  input?.click();
};

const triggerLostDocFileInput = () => {
  const input = document.getElementById('edit-asset-lost-doc-upload') as HTMLInputElement;
  input?.click();
};

const triggerBodBocFileInput = () => {
  const input = document.getElementById('edit-asset-bod-boc-doc-upload') as HTMLInputElement;
  input?.click();
};

const triggerBodBocDisapprovalFileInput = () => {
  const input = document.getElementById('edit-asset-bod-boc-disapproval-doc-upload') as HTMLInputElement;
  input?.click();
};

const handleMemoUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowed = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowed.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    return;
  }
  form.memo_file = file;
  form.memo_file_name = file.name;
};

const handleLostDocUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowed = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowed.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    return;
  }
  form.lost_doc_file = file;
  form.lost_doc_file_name = file.name;
};

const handleBodBocFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowed = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowed.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    return;
  }
  form.bod_boc_approval_file = file;
  form.bod_boc_approval_file_name = file.name;
};

const handleBodBocDisapprovalFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowed = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowed.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    return;
  }
  form.bod_boc_disapproval_file = file;
  form.bod_boc_disapproval_file_name = file.name;
};

const handleToggleBodBocDecision = (type: 'approval' | 'disapproval', checked: boolean) => {
  if (!checked) {
    if (bodBocDecisionType.value === type) {
      bodBocDecisionType.value = null;
      if (type === 'approval') {
        form.bod_boc_approval_file = null;
        form.bod_boc_approval_file_name = '';
      } else {
        form.bod_boc_disapproval_file = null;
        form.bod_boc_disapproval_file_name = '';
      }
    }
  } else {
    bodBocDecisionType.value = type;
    if (type === 'approval') {
      form.bod_boc_disapproval_file = null;
      form.bod_boc_disapproval_file_name = '';
      const disapprovalInput = document.getElementById('edit-asset-bod-boc-disapproval-doc-upload') as HTMLInputElement | null;
      if (disapprovalInput) disapprovalInput.value = '';
    } else {
      form.bod_boc_approval_file = null;
      form.bod_boc_approval_file_name = '';
      const approvalInput = document.getElementById('edit-asset-bod-boc-doc-upload') as HTMLInputElement | null;
      if (approvalInput) approvalInput.value = '';
    }
  }
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (!isImageDeleted.value && isSingle.value && selectedItem.value?.image_url) {
    window.open('/media/' + selectedItem.value.image_url, '_blank');
  }
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    window.open(URL.createObjectURL(form.memo_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.memo_url) {
    window.open('/media/' + selectedItem.value.memo_url, '_blank');
  }
};

const viewLostDocInNewTab = () => {
  if (form.lost_doc_file) {
    window.open(URL.createObjectURL(form.lost_doc_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.lost_doc_url) {
    window.open('/media/' + selectedItem.value.lost_doc_url, '_blank');
  }
};

const viewBodBocInNewTab = () => {
  if (form.bod_boc_approval_file) {
    window.open(URL.createObjectURL(form.bod_boc_approval_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.bod_boc_approval_url) {
    window.open('/media/' + selectedItem.value.bod_boc_approval_url, '_blank');
  }
};

const viewBodBocDisapprovalInNewTab = () => {
  if (form.bod_boc_disapproval_file) {
    window.open(URL.createObjectURL(form.bod_boc_disapproval_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.bod_boc_disapproval_url) {
    window.open('/media/' + selectedItem.value.bod_boc_disapproval_url, '_blank');
  }
};

const parseCurrencyToNumber = (val: any): number => {
  if (typeof val === 'number') return val;
  if (!val) return 0;
  let clean = val.toString().replace(/[^0-9.,]/g, '');
  if (clean.includes('.') && clean.includes(',')) {
    clean = clean.replace(/\./g, '').replace(',', '.');
  } else if (clean.includes('.')) {
    const parts = clean.split('.');
    if (parts[parts.length - 1].length === 3) {
      clean = clean.replace(/\./g, '');
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
  return isNaN(parsed) ? 0 : parsed;
};

const handleSubmit = () => {
  if (isProcessing.value) return;
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (isComp.value && !form.specification?.trim()) { errors.value.specification = t('inventory.specificationRequired'); isValid = false; }
    if (!isSoft.value) {
      if (!form.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
      if (!form.condition) { errors.value.condition = t('inventory.conditionRequired'); isValid = false; }
    } else {
      form.location_id = '' as any;
      form.condition = '';
    }
    if (!form.type) { errors.value.type = t('inventory.typeRequired'); isValid = false; }
    if (!form.classification) { errors.value.classification = t('inventory.classificationRequired'); isValid = false; }
    if (!form.status) { errors.value.status = t('inventory.statusRequired'); isValid = false; }
    if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = t('inventory.nopolRequired'); isValid = false; }
    if (arrNeedApproval.includes(form.condition) && !isDocumentDisabled.value && !form.memo_file_name) { errors.value.memo_file = t('inventory.memoRequired'); isValid = false; }
    if (form.condition === 'Hilang' && !isDocumentDisabled.value && !form.lost_doc_file_name) { errors.value.lost_doc_file = t('inventory.lostDocRequired'); isValid = false; }
    if (!form.burden) { errors.value.burden = t('inventory.burdenRequired'); isValid = false; }
    if (form.burden === 'Project' && !form.project_id) { errors.value.project_id = t('inventory.projectRequired'); isValid = false; }
    if (isSoft.value) {
      if (form.license_type === 'Subscription') {
        if (!form.license_exp_date) {
          errors.value.license_exp_date = t('inventory.expDateRequiredForSubscription');
          isValid = false;
        }
      } else {
        form.license_exp_date = null;
        form.renewal_reminder = null;
      }
    }
    if (!isValid) {
      toast.error(t('common.modals.formInvalid'));
      return;
    }

    form.transform((data) => {
      const fd: any = {
        _method: 'PUT',
        number: data.number,
        lot_id: props.lot?.id || selectedItem.value?.lot_id,
        location_id: isSoft.value ? null : data.location_id,
        status: data.status,
        condition: isSoft.value ? null : data.condition,
        type: data.type,
        borrowing_type: data.type,
        classification: data.classification,
        price: data.price !== '' && data.price !== null ? parseCurrencyToNumber(data.price) : null,
        burden: data.burden,
        project_id: data.burden === 'Project' ? data.project_id : null,
      };
      if (!isSoft.value && data.qc_pass !== null && data.qc_pass !== 'unchanged') {
        fd.qc_pass = data.qc_pass;
      }
      if (canShowAssignToLocation.value && data.is_assigned_to_location !== null) {
        fd.is_assigned_to_location = data.is_assigned_to_location;
      }
      if (isVehicle.value) fd.vehicle_registration = data.vehicle_registration;
      if (isComp.value) {
        fd.comp_specification = data.specification || null;
        fd.specification = data.specification || null;
      }
      if (isSoft.value) {
        fd.license_exp_date = data.license_type === 'Subscription' ? data.license_exp_date : null;
        fd.renewal_reminder = data.license_type === 'Subscription' ? (data.renewal_reminder !== null && data.renewal_reminder !== '' ? Number(data.renewal_reminder) : null) : null;
      }
      if (data.image_url) {
        fd.image_url = data.image_url;
      } else if (isSingle.value && isImageDeleted.value) {
        fd.delete_image = true;
      }
      if (data.memo_file) fd.memo_file = data.memo_file;
      if (data.lost_doc_file) fd.lost_doc_file = data.lost_doc_file;
      if (arrNeedApproval.includes(form.condition)) fd.admin_note = data.admin_note || null;
      if (bodBocDecisionType.value === 'approval' && data.bod_boc_approval_file) {
        fd.bod_boc_approval_file = data.bod_boc_approval_file;
      } else if (bodBocDecisionType.value === 'disapproval' && data.bod_boc_disapproval_file) {
        fd.bod_boc_disapproval_file = data.bod_boc_disapproval_file;
      }
      return fd;
    }).post(`/smart/stock-management/assets/${form.ids[0]}`, {
      onSuccess: () => {
        resetModalState();
        closeModal();
        emit('success');
      },
      onError: (errs) => {
        const errorKeys = Object.keys(errs || {});
        if (errorKeys.length > 0) {
          errorKeys.forEach((key) => {
            if (key in errors.value) {
              (errors.value as any)[key] = errs[key];
            }
          });
          toast.error(t('common.modals.formInvalid'));
        }
      },
    });
  } else {
    // Bulk edit
    if (form.burden === 'Project' && !form.project_id) {
      errors.value.project_id = t('inventory.projectRequired');
      toast.error(t('common.modals.formInvalid'));
      return;
    }

    const hasField = !!(
      (form.status && !isStatusDisabled.value) ||
      (form.condition && !isKondisiDisabled.value) ||
      (!isSoft.value && form.qc_pass !== 'unchanged' && form.qc_pass !== null) ||
      (canShowAssignToLocation.value && form.is_assigned_to_location !== null) ||
      form.type ||
      form.classification ||
      form.location_id || form.price ||
      (isComp.value && form.specification) ||
      (isSoft.value && (form.license_exp_date || form.renewal_reminder !== null || form.license_type === 'Perpetual')) ||
      form.image_url || form.memo_file || form.lost_doc_file || form.bod_boc_approval_file || form.bod_boc_disapproval_file ||
      (form.burden && form.burden !== 'Tidak berubah')
    );
    if (!hasField) {
      toast.error(t('inventory.atLeastOneField'));
      return;
    }

    if (form.condition && !isKondisiDisabled.value && arrNeedApproval.includes(form.condition) && !isDocumentDisabled.value && !form.memo_file_name) {
      errors.value.memo_file = t('inventory.memoRequired');
      toast.error(t('common.modals.formInvalid'));
      return;
    }
    if (form.condition && !isKondisiDisabled.value && form.condition === 'Hilang' && !isDocumentDisabled.value && !form.lost_doc_file_name) {
      errors.value.lost_doc_file = t('inventory.lostDocRequired');
      toast.error(t('common.modals.formInvalid'));
      return;
    }

    const payload: any = { ids: form.ids };
    if (canShowAssignToLocation.value && form.is_assigned_to_location !== null) {
      payload.is_assigned_to_location = form.is_assigned_to_location;
    }
    if (form.status && !isStatusDisabled.value) payload.status = form.status;
    if (isSoft.value) {
      payload.condition = null;
    } else if (form.condition && !isKondisiDisabled.value) {
      payload.condition = form.condition;
    }
    if (!isSoft.value && form.qc_pass !== 'unchanged' && form.qc_pass !== null) {
      payload.qc_pass = form.qc_pass;
    }
    if (form.type) {
      payload.type = form.type;
      payload.borrowing_type = form.type;
    }
    if (form.classification) payload.classification = form.classification;
    if (isComp.value && form.specification) {
      payload.comp_specification = form.specification;
      payload.specification = form.specification;
    }
    if (isSoft.value) {
      if (form.license_type === 'Subscription') {
        if (form.license_exp_date) payload.license_exp_date = form.license_exp_date;
        if (form.renewal_reminder !== null && form.renewal_reminder !== '') payload.renewal_reminder = Number(form.renewal_reminder);
      } else if (form.license_type === 'Perpetual') {
        payload.license_exp_date = null;
        payload.renewal_reminder = null;
      }
    }
    if (isSoft.value) {
      payload.location_id = null;
    } else if (form.location_id) {
      payload.location_id = form.location_id;
    }
    if (form.price) payload.price = parseCurrencyToNumber(form.price).toString();
    if (form.burden && form.burden !== 'Tidak berubah') {
      payload.burden = form.burden;
      if (form.burden === 'Project') {
        payload.project_id = form.project_id;
      }
    }
    if (form.image_url instanceof File) payload.image_url = form.image_url;
    if (form.memo_file instanceof File) payload.memo_file = form.memo_file;
    if (form.lost_doc_file instanceof File) payload.lost_doc_file = form.lost_doc_file;
    if (arrNeedApproval.includes(form.condition) && form.admin_note) payload.admin_note = form.admin_note;
    if (bodBocDecisionType.value === 'approval' && form.bod_boc_approval_file instanceof File) {
      payload.bod_boc_approval_file = form.bod_boc_approval_file;
    } else if (bodBocDecisionType.value === 'disapproval' && form.bod_boc_disapproval_file instanceof File) {
      payload.bod_boc_disapproval_file = form.bod_boc_disapproval_file;
    }

    isSubmitting.value = true;
    router.post('/smart/stock-management/assets/bulk-update', payload, {
      onFinish: () => {
        isSubmitting.value = false;
      },
      onSuccess: () => {
        resetModalState();
        closeModal();
        emit('success');
      },
      onError: (errs) => {
        isSubmitting.value = false;
        console.error('Bulk update errors:', errs);
        toast.error(t('inventory.bulkAssetFailed') || 'Failed to update assets');
      },
    });
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
        <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
          <div v-if="open" class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? (isVehicle ? t('inventory.editAssetVehicleDetail') : t('inventory.editAssetDetail')) : t('inventory.editAssetSelected') }}
              </h3>
              <button @click="closeModal" :disabled="isProcessing" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6">
              <!-- Main Asset Attributes Section -->
              <AssetMainSection
                :form="form"
                :errors="errors"
                :is-single="isSingle"
                :selected-item="selectedItem"
                :is-comp="isComp"
                :is-vehicle="isVehicle"
                :is-soft="isSoft"
                :can-show-assign-to-location="canShowAssignToLocation"
                :is-kondisi-disabled="isKondisiDisabled"
                :is-borrowed-unit="isBorrowedUnit"
                :is-borrowing-type-disabled="isBorrowingTypeDisabled"
                :locations="locations"
                :project-options="projectOptions"
                :has-image="hasImage"
                :get-status-label="getStatusLabel"
                :get-condition-label="getConditionLabel"
                :get-classification-label="getClassificationLabel"
                :get-burden-label="getBurdenLabel"
                :handle-assign-to-location-select="handleAssignToLocationSelect"
                :handle-file-upload="handleFileUpload"
                :handle-delete-photo="handleDeletePhoto"
                :view-image-in-new-tab="viewImageInNewTab"
                :trigger-file-input="triggerFileInput"
              />

              <!-- Disposal & BoD/BoC Documents Section -->
              <AssetDocumentsSection
                :form="form"
                :errors="errors"
                :is-document-disabled="isDocumentDisabled"
                :is-bod-boc-field-visible="isBodBocFieldVisible"
                :bod-boc-decision-type="bodBocDecisionType"
                :arr-need-approval="arrNeedApproval"
                :trigger-memo-file-input="triggerMemoFileInput"
                :trigger-lost-doc-file-input="triggerLostDocFileInput"
                :trigger-bod-boc-file-input="triggerBodBocFileInput"
                :trigger-bod-boc-disapproval-file-input="triggerBodBocDisapprovalFileInput"
                :handle-memo-upload="handleMemoUpload"
                :handle-lost-doc-upload="handleLostDocUpload"
                :handle-bod-boc-file-upload="handleBodBocFileUpload"
                :handle-bod-boc-disapproval-file-upload="handleBodBocDisapprovalFileUpload"
                :handle-toggle-bod-boc-decision="handleToggleBodBocDecision"
                :view-memo-in-new-tab="viewMemoInNewTab"
                :view-lost-doc-in-new-tab="viewLostDocInNewTab"
                :view-bod-boc-in-new-tab="viewBodBocInNewTab"
                :view-bod-boc-disapproval-in-new-tab="viewBodBocDisapprovalInNewTab"
              />
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between shrink-0">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ isSingle ? t('inventory.requiredMarker') : t('inventory.bulkEmptyMarker') }}
              </p>
              <div class="flex items-center gap-3">
                <Button
                  @click="closeModal"
                  :disabled="isProcessing"
                  variant="white"
                  size="xl"
                >
                  {{ t('common.cancel') }}
                </Button>
                <Button
                  @click="handleSubmit"
                  :disabled="isProcessing"
                  variant="primary"
                  size="xl"
                  class="relative"
                >
                  <Loader2 v-if="isProcessing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': isProcessing }">
                    {{ isSingle ? t('inventory.saveChanges') : t('inventory.saveBulkChanges') }}
                  </span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
