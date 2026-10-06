<script setup lang="ts">
/**
 * Edit Asset Modal component supporting single asset updates and bulk edits with status/condition validation rules.
 */
import { ref, watch, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, ChevronDown, Loader2, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { compressImageIfNeeded } from '@/utils/imageCompressor';

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
  projects?: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const isSingle = computed(() => props.items.length === 1);
const selectedItem = computed(() => isSingle.value ? props.items[0] : null);
const isVehicle = computed(() => props.barang?.category === 'Kendaraan');

const arrNeedApproval = ['Rusak Total', 'Hilang'];
const arrInactiveConditions = ['Rusak Total', 'Hilang', 'Lelang/Hibah'];

const getStatusLabel = (s: string) => {
  if (!s) return '';
  const map: Record<string, string> = {
    'tersedia': t('status.tersedia'),
    'dipinjam': t('status.dipinjam'),
    'standby': t('status.standby'),
    'tidak aktif': t('status.tidakAktif'),
    'pending': t('status.pending'),
    'pending:dm': t('status.pendingDm'),
    'pending: dm': t('status.pendingDm'),
    'pending:bod/boc': t('status.pendingBodBoc'),
    'pending: bod/boc': t('status.pendingBodBoc'),
    'scrapped': t('status.scrapped'),
    'lost': t('status.lost'),
    'belum diverifikasi': t('status.belumDiverifikasi'),
  };
  return map[s.toLowerCase()] || s;
};

const getConditionLabel = (c: string) => {
  if (!c) return '';
  const map: Record<string, string> = {
    'Bagus': t('inventory.conditionGood'),
    'Rusak': t('inventory.conditionDamaged'),
    'QC Passed': t('inventory.conditionQcPassed'),
    'Lelang/Hibah': t('inventory.conditionAuctionGrant'),
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

const allowedStatuses = ['tersedia', 'standby'];

const hasDisallowedStatus = computed(() => {
  if (!props.items || props.items.length === 0) return true;
  return props.items.some(item => {
    const s = String(item?.status || '').trim().toLowerCase();
    return !allowedStatuses.includes(s);
  });
});

const isBorrowedUnit = computed(() => {
  return (props.items || []).some(item => String(item?.status).trim().toLowerCase() === 'dipinjam');
});

const isRestrictedStatus = (status: string | null | undefined) => {
  if (!status) return false;
  const s = String(status).trim().toLowerCase();
  return s === 'tidak aktif' || s === 'pending' || s.startsWith('pending') || s === 'belum diverifikasi' || s === 'verifikasi ditolak';
};

const hasRestrictedUnit = computed(() => {
  return (props.items || []).some(item => isRestrictedStatus(item?.status) || arrInactiveConditions.includes(item?.condition));
});

const isKondisiDisabled = computed(() => {
  return hasRestrictedUnit.value;
});

const isStatusDisabled = computed(() => {
  if (hasDisallowedStatus.value) return true;
  if (hasRestrictedUnit.value || isBorrowedUnit.value) return true;
  return arrInactiveConditions.includes(form.condition);
});

const isDocumentDisabled = computed(() => {
  return hasRestrictedUnit.value;
});

const isBodBocFieldVisible = computed(() => {
  if (isSingle.value) {
    return selectedItem.value?.status === 'Pending:BoD/BoC';
  }
  return props.items.length > 0 && props.items.every((item: any) => item.status === 'Pending:BoD/BoC');
});

const form = useForm({
  ids: [] as number[],
  number: '',
  legacy_number: '',
  location_id: '' as string | number,
  status: '',
  condition: '',
  type: '',
  classification: '',
  price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  vehicle_registration: '',
  memo_file: null as File | null,
  memo_file_name: '',
  lost_doc_file: null as File | null,
  lost_doc_file_name: '',
  bod_boc_approval_file: null as File | null,
  bod_boc_approval_file_name: '',
  burden: '',
  project_id: '' as string | number,
});

const errors = ref({
  location_id: '',
  status: '',
  condition: '',
  type: '',
  classification: '',
  image_url: '',
  vehicle_registration: '',
  memo_file: '',
  lost_doc_file: '',
  bod_boc_approval_file: '',
  burden: '',
  project_id: '',
});

const resetErrors = () => {
  errors.value = {
    location_id: '',
    status: '',
    condition: '',
    type: '',
    classification: '',
    image_url: '',
    vehicle_registration: '',
    memo_file: '',
    lost_doc_file: '',
    bod_boc_approval_file: '',
    burden: '',
    project_id: '',
  };
};

const projectOptions = computed(() => {
  return (props.projects || []).map(p => ({
    id: p.id,
    name: `[${p.no_project}] ${p.project_name}`
  }));
});

// Reactive error clearing
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
    ? isRestrictedStatus(selectedItem.value?.status)
    : hasRestrictedUnit.value;

  if (!isRestricted) {
    if (arrInactiveConditions.includes(newVal)) {
      form.status = 'Pending:BoD/BoC';
    } else if (oldVal && arrInactiveConditions.includes(oldVal) && (form.status === 'Pending' || form.status === 'Pending:BoD/BoC')) {
      if (isSingle.value && selectedItem.value?.status && !isRestrictedStatus(selectedItem.value.status)) {
        form.status = selectedItem.value.status;
      } else {
        form.status = '';
      }
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
  if (!isSingle.value) return false;
  if (form.image_url) return true;
  if (form.use_lot_image) return true;
  if (isImageDeleted.value) return false;
  return Boolean(selectedItem.value?.image_url);
});

// Init form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  isImageDeleted.value = false;
  form.ids = props.items.map(r => r.id);
  form.image_url = null;
  form.image_url_name = '';
  form.use_lot_image = false;
  form.memo_file = null;
  form.memo_file_name = '';
  form.lost_doc_file = null;
  form.lost_doc_file_name = '';
  form.bod_boc_approval_file = null;
  form.bod_boc_approval_file_name = '';

  if (isSingle.value && selectedItem.value) {
    const item = selectedItem.value;
    form.number = item.number || '';
    form.legacy_number = item.legacy_number || '';
    form.location_id = item.location_id || '';
    form.status = item.status || '';
    form.condition = item.condition || '';
    form.type = item.type || '';
    form.classification = item.classification || '';
    if (!isRestrictedStatus(item.status) && arrInactiveConditions.includes(form.condition)) {
      form.status = 'Pending:BoD/BoC';
    }
    form.price = item.price || '';
    form.image_url_name = item.image_url ? item.image_url.split('/').pop() || '' : '';
    form.vehicle_registration = item.vehicle_registration || '';
    form.memo_file_name = item.memo_file_name || (item.memo_url ? item.memo_url.split('/').pop() || '' : '');
    form.lost_doc_file_name = item.lost_doc_file_name || (item.lost_doc_url ? item.lost_doc_url.split('/').pop() || '' : '');
    form.bod_boc_approval_file_name = item.bod_boc_approval_file_name || (item.bod_boc_approval_url ? item.bod_boc_approval_url.split('/').pop() || '' : '');
    form.burden = item.burden || 'Corporate';
    form.project_id = item.project_id || '';
  } else {
    form.number = '';
    form.legacy_number = '';
    const firstLoc = props.items[0]?.location_id;
    const sameLoc = props.items.every(i => i.location_id === firstLoc);
    form.location_id = sameLoc ? (firstLoc || '') : '';
    form.status = '';
    form.condition = '';
    form.type = '';
    form.classification = '';
    form.price = '';
    form.vehicle_registration = '';
    form.burden = 'Tidak berubah';
    form.project_id = '';
  }
}, { immediate: true });

const closeModal = () => {
  isImageDeleted.value = false;
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
    form.use_lot_image = false;
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
  form.use_lot_image = false;
  const input = document.getElementById('edit-asset-photo-upload') as HTMLInputElement;
  if (input) input.value = '';
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-asset-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (form.use_lot_image && props.lot?.imageUrl) {
    window.open('/media/' + props.lot.imageUrl, '_blank');
  } else if (!isImageDeleted.value && isSingle.value && selectedItem.value?.image_url) {
    window.open('/media/' + selectedItem.value.image_url, '_blank');
  }
};

const handleSamakanPhoto = () => {
  if (props.lot?.imageUrl) {
    isImageDeleted.value = false;
    form.use_lot_image = true;
    form.image_url = null;
    form.image_url_name = props.lot.imageUrl.split('/').pop() || '';
    const input = document.getElementById('edit-asset-photo-upload') as HTMLInputElement;
    if (input) input.value = '';
  } else {
    toast.error(t('inventory.lotNoPhoto'));
  }
};

const handleMemoUpload = (e: any) => {
  if (isDocumentDisabled.value) return;
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('inventory.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('inventory.fileTooLarge2Mb')); return; }
  form.memo_file = file;
  form.memo_file_name = file.name;
};

const triggerMemoFileInput = () => {
  if (isDocumentDisabled.value) return;
  const input = document.getElementById('edit-asset-memo-upload') as HTMLInputElement;
  input?.click();
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    window.open(URL.createObjectURL(form.memo_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.memo_url) {
    window.open('/media/' + selectedItem.value.memo_url, '_blank');
  }
};

const handleLostDocUpload = (e: any) => {
  if (isDocumentDisabled.value) return;
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('inventory.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('inventory.fileTooLarge2Mb')); return; }
  form.lost_doc_file = file;
  form.lost_doc_file_name = file.name;
};

const triggerLostDocFileInput = () => {
  if (isDocumentDisabled.value) return;
  const input = document.getElementById('edit-asset-lost-doc-upload') as HTMLInputElement;
  input?.click();
};

const viewLostDocInNewTab = () => {
  if (form.lost_doc_file) {
    window.open(URL.createObjectURL(form.lost_doc_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.lost_doc_url) {
    window.open('/media/' + selectedItem.value.lost_doc_url, '_blank');
  }
};

const handleBodBocDocUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('inventory.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('inventory.fileTooLarge2Mb')); return; }
  form.bod_boc_approval_file = file;
  form.bod_boc_approval_file_name = file.name;
};

const triggerBodBocDocFileInput = () => {
  const input = document.getElementById('edit-asset-bod-boc-doc-upload') as HTMLInputElement;
  input?.click();
};

const viewBodBocDocInNewTab = () => {
  if (form.bod_boc_approval_file) {
    window.open(URL.createObjectURL(form.bod_boc_approval_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.bod_boc_approval_url) {
    window.open('/media/' + selectedItem.value.bod_boc_approval_url, '_blank');
  }
};

const handleSamakanPrice = () => {
  if (props.lot?.unitPrice) {
    form.price = props.lot.unitPrice;
  } else if (props.lot?.unit_price) {
    form.price = props.lot.unit_price;
  } else {
    toast.error(t('inventory.lotNoDefaultPrice'));
  }
};

const parseCurrencyToNumber = (val: string | number) => {
  if (typeof val === 'number') return val;
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
  return isNaN(parsed) ? 0 : parsed;
};

const handleDecideClassification = () => {
  if (form.price === '' || form.price === null || form.price === undefined) {
    return;
  }
  if (typeof form.price === 'string' && form.price.trim() === '') {
    return;
  }
  const currentPrice = parseCurrencyToNumber(form.price);
  if (isNaN(currentPrice)) {
    return;
  }

  const threshold = Number(import.meta.env.VITE_ASSET_CLASSIFICATION_THRESHOLD) || 5000000;
  if (currentPrice > threshold) {
    form.classification = 'Aset';
  } else {
    form.classification = 'Inventaris';
  }
};

const handleSubmit = () => {
  resetErrors();

  if (isSingle.value) {
      let isValid = true;
      if (!form.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
      if (!form.status) { errors.value.status = t('inventory.statusRequired'); isValid = false; }
      if (!form.condition) { errors.value.condition = t('inventory.conditionRequired'); isValid = false; }
      if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = t('inventory.nopolRequired'); isValid = false; }
      if (arrNeedApproval.includes(form.condition) && !isDocumentDisabled.value && !form.memo_file_name) { errors.value.memo_file = t('inventory.memoRequired'); isValid = false; }
      if (form.condition === 'Hilang' && !isDocumentDisabled.value && !form.lost_doc_file_name) { errors.value.lost_doc_file = t('inventory.lostDocRequired'); isValid = false; }
      if (!form.burden) { errors.value.burden = t('inventory.burdenRequired'); isValid = false; }
      if (form.burden === 'Project' && !form.project_id) { errors.value.project_id = t('inventory.projectRequired'); isValid = false; }
      if (!isValid) return;

      form.transform((data) => {
        const fd: any = {
          _method: 'PUT',
          number: data.number,
          lot_id: props.lot.id,
          location_id: data.location_id,
          status: data.status,
          condition: data.condition,
          type: data.type || null,
          classification: data.classification || null,
          price: data.price !== '' && data.price !== null ? parseCurrencyToNumber(data.price) : null,
          burden: data.burden,
          project_id: data.burden === 'Project' ? data.project_id : null,
        };
        if (isVehicle.value) fd.vehicle_registration = data.vehicle_registration;
        if (data.image_url) {
          fd.image_url = data.image_url;
        } else if (data.use_lot_image) {
          fd.use_lot_image = data.use_lot_image;
        } else if (isSingle.value && isImageDeleted.value) {
          fd.delete_image = true;
        }
        if (data.memo_file) fd.memo_file = data.memo_file;
        if (data.lost_doc_file) fd.lost_doc_file = data.lost_doc_file;
        if (data.bod_boc_approval_file) fd.bod_boc_approval_file = data.bod_boc_approval_file;
        return fd;
      }).post(`/smart/inventory/units/${form.ids[0]}`, {
        onSuccess: () => { closeModal(); emit('success'); },
      });
    } else {
      // Bulk edit
      if (form.burden === 'Project' && !form.project_id) {
        errors.value.project_id = t('inventory.projectRequired');
        return;
      }

      const hasField = !!(
        (form.status && !isStatusDisabled.value) ||
        (form.condition && !isKondisiDisabled.value) ||
        form.type ||
        form.classification ||
        form.location_id || form.price ||
        form.image_url || form.use_lot_image || form.memo_file || form.lost_doc_file || form.bod_boc_approval_file ||
        (form.burden && form.burden !== 'Tidak berubah')
      );
      if (!hasField) {
        toast.error(t('inventory.atLeastOneField'));
        return;
      }

      if (form.condition && !isKondisiDisabled.value && arrNeedApproval.includes(form.condition) && !isDocumentDisabled.value && !form.memo_file_name) {
        errors.value.memo_file = t('inventory.memoRequired');
        return;
      }
      if (form.condition && !isKondisiDisabled.value && form.condition === 'Hilang' && !isDocumentDisabled.value && !form.lost_doc_file_name) {
        errors.value.lost_doc_file = t('inventory.lostDocRequired');
        return;
      }

      const payload: any = { ids: form.ids };
      if (form.status && !isStatusDisabled.value) payload.status = form.status;
      if (form.condition && !isKondisiDisabled.value) payload.condition = form.condition;
      if (form.type) payload.type = form.type;
      if (form.classification) payload.classification = form.classification;
      
      if (form.location_id) {
        payload.location_id = form.location_id;
      }
      
      if (form.price) payload.price = parseCurrencyToNumber(form.price).toString();
      if (form.burden && form.burden !== 'Tidak berubah') {
        payload.burden = form.burden;
        if (form.burden === 'Project') {
          payload.project_id = form.project_id;
        }
      }
      if (form.use_lot_image) payload.use_lot_image = true;
      if (form.image_url instanceof File) payload.image_url = form.image_url;
      if (form.memo_file instanceof File) payload.memo_file = form.memo_file;
      if (form.lost_doc_file instanceof File) payload.lost_doc_file = form.lost_doc_file;
      if (form.bod_boc_approval_file instanceof File) payload.bod_boc_approval_file = form.bod_boc_approval_file;

    router.post('/smart/inventory/units/bulk-update', payload, {
      onSuccess: () => { closeModal(); emit('success'); },
    });
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
        <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
          <div v-if="open" class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? (isVehicle ? t('inventory.editAssetVehicleDetail') : t('inventory.editAssetDetail')) : t('inventory.editAssetSelected') }}
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] overscroll-contain">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <Field>
                    <FieldLabel>{{ t('inventory.assetCode') }}</FieldLabel>
                    <FieldContent>
                      <input type="text" :value="isSingle ? form.number : t('inventory.cannotBeChangedBulk')" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field v-if="isSingle && form.legacy_number">
                    <FieldLabel>{{ t('inventory.legacyNumber') }}</FieldLabel>
                    <FieldContent>
                      <input type="text" :value="form.legacy_number" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.location_id) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.location') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <LocationCombobox
                        v-model="form.location_id"
                        :locations="locations"
                        :placeholder="isSingle ? t('inventory.selectLocation') : t('inventory.unchanged')"
                        :error="isSingle && !!errors.location_id"
                        :active-only="true"
                        :clearable="!isSingle"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.location_id">{{ errors.location_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.type) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.type') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.type ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.type) ? 'border-destructive' : '']">
                            {{ form.type || (isSingle ? t('inventory.selectType') : t('inventory.unchanged')) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.type = 'LT'">LT</DropdownMenuItem>
                          <DropdownMenuItem @select="form.type = 'ST'">ST</DropdownMenuItem>
                          <DropdownMenuItem v-if="isSingle && form.type" @select="form.type = ''">{{ t('inventory.selectType') }}</DropdownMenuItem>
                          <DropdownMenuItem v-if="!isSingle" @select="form.type = ''">{{ t('inventory.unchanged') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.type">{{ errors.type }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.status) || undefined" :data-disabled="isStatusDisabled || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.status') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div v-if="isStatusDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
                        <span>{{ getStatusLabel(form.status) || (isSingle ? t('inventory.selectStatus') : t('inventory.unchanged')) }}</span>
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </div>
                      <DropdownMenu v-else>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.status ? 'text-muted-foreground' : 'text-foreground']">
                            {{ getStatusLabel(form.status) || (isSingle ? t('inventory.selectStatus') : t('inventory.unchanged')) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.status = 'Tersedia'">{{ t('status.tersedia') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Standby'">{{ t('status.standby') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.status">{{ errors.status }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.burden) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.burden') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <RadioGroup v-model="form.burden" class="flex items-center gap-6 h-10">
                        <div v-if="!isSingle" class="flex items-center space-x-2">
                          <RadioGroupItem id="edit-unit-burden-none" value="Tidak berubah" />
                          <label for="edit-unit-burden-none" class="text-sm font-medium text-foreground cursor-pointer select-none">{{ t('inventory.unchanged') }}</label>
                        </div>
                        <div class="flex items-center space-x-2">
                          <RadioGroupItem id="edit-unit-burden-corporate" value="Corporate" />
                          <label for="edit-unit-burden-corporate" class="text-sm font-medium text-foreground cursor-pointer select-none">Corporate</label>
                        </div>
                        <div class="flex items-center space-x-2">
                          <RadioGroupItem id="edit-unit-burden-project" value="Project" />
                          <label for="edit-unit-burden-project" class="text-sm font-medium text-foreground cursor-pointer select-none">Project</label>
                        </div>
                      </RadioGroup>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.burden">{{ errors.burden }}</FieldError>
                  </Field>

                  <Field v-if="form.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
                    <FieldLabel><span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.project_id" :options="projectOptions" :search-placeholder="t('inventory.searchProjectPlaceholder')" :default-label="t('inventory.selectProject')" width-class="w-full h-10 px-4" :error="!!errors.project_id" />
                    </FieldContent>
                    <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="(isSingle && !!errors.condition) || undefined" :data-disabled="isKondisiDisabled || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.condition') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div v-if="isKondisiDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
                        <span>{{ getConditionLabel(form.condition) || (isSingle ? t('inventory.selectCondition') : t('inventory.unchanged')) }}</span>
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </div>
                      <DropdownMenu v-else>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground']">
                            {{ getConditionLabel(form.condition) || (isSingle ? t('inventory.selectCondition') : t('inventory.unchanged')) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'QC Passed'">{{ t('inventory.conditionQcPassed') }}</DropdownMenuItem>
                          <template v-if="!isBorrowedUnit">
                            <DropdownMenuItem @select="form.condition = 'Lelang/Hibah'">{{ t('inventory.conditionAuctionGrant') }}</DropdownMenuItem>
                            <DropdownMenuItem @select="form.condition = 'Rusak Total'">{{ t('inventory.conditionTotalDamage') }}</DropdownMenuItem>
                            <DropdownMenuItem @select="form.condition = 'Hilang'">{{ t('inventory.conditionLost') }}</DropdownMenuItem>
                          </template>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.condition">{{ errors.condition }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>
                      <span>{{ t('inventory.unitPrice') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input type="number" v-model="form.price" :placeholder="isSingle ? t('inventory.unitPricePlaceholder') : t('inventory.unchanged')" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                        </div>
                        <Button type="button" @click="handleSamakanPrice" variant="warning" size="lg">{{ t('inventory.sameAsParent') }}</Button>
                      </div>
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.classification) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.classification') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <div class="flex-grow min-w-0">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.classification ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.classification) ? 'border-destructive' : '']">
                                {{ getClassificationLabel(form.classification) || (isSingle ? t('inventory.selectClassification') : t('inventory.unchanged')) }}
                                <ChevronDown class="w-4 h-4 opacity-50" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                              <DropdownMenuItem @select="form.classification = 'Aset'">{{ t('inventory.classificationAsset') }}</DropdownMenuItem>
                              <DropdownMenuItem @select="form.classification = 'Inventaris'">{{ t('inventory.classificationInventory') }}</DropdownMenuItem>
                              <DropdownMenuItem v-if="isSingle && form.classification" @select="form.classification = ''">{{ t('inventory.selectClassification') }}</DropdownMenuItem>
                              <DropdownMenuItem v-if="!isSingle" @select="form.classification = ''">{{ t('inventory.unchanged') }}</DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                        <Button type="button" @click="handleDecideClassification" variant="warning" size="lg">{{ t('inventory.decide') }}</Button>
                      </div>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.classification">{{ errors.classification }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.image_url) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.photo') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[hasImage ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', (isSingle && errors.image_url) ? 'border-destructive' : 'border-input']"
                          @click="hasImage && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || (isSingle ? t('inventory.noPhotoSelected') : t('inventory.unchanged')) }}
                        </div>
                        <input type="file" id="edit-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg" class="shrink-0">{{ t('inventory.sameAsParent') }}</Button>
                        <Button type="button" @click="triggerFileInput" size="lg" class="shrink-0">{{ t('inventory.chooseFile') }}</Button>
                        <Button
                          v-if="isSingle"
                          type="button"
                          variant="destructive"
                          size="icon"
                          class="w-9 shrink-0"
                          :disabled="!hasImage"
                          @click="handleDeletePhoto"
                          :title="t('inventory.deletePhoto')"
                        >
                          <Trash2 class="w-4 h-4" />
                        </Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize1Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>

                  <!-- TNKB (Only for Vehicles) -->
                  <Field v-if="isVehicle" :data-invalid="(isSingle && !!errors.vehicle_registration) || undefined" :data-disabled="(!isSingle) || undefined">
                    <FieldLabel><span>{{ t('inventory.tnkb') }}<span v-if="isSingle" class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.vehicle_registration" :disabled="!isSingle" :placeholder="!isSingle ? t('inventory.cannotBeChangedBulk') : t('inventory.nopolPlaceholder')"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
                  </Field>

                  <!-- Document Upload (Required for approval conditions) -->
                  <Field v-if="arrNeedApproval.includes(form.condition)" :data-invalid="!!errors.memo_file || undefined" :data-disabled="isDocumentDisabled || undefined">
                    <FieldLabel><span>{{ t('inventory.memoDocument') }}<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] truncate flex items-center h-10"
                          :class="[
                            form.memo_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                            errors.memo_file ? 'border-destructive' : 'border-input',
                            isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/10'
                          ]"
                          @click="form.memo_file_name && viewMemoInNewTab()"
                        >
                          {{ form.memo_file_name || t('inventory.noFileSelected') }}
                        </div>
                        <input type="file" id="edit-asset-memo-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleMemoUpload" :disabled="isDocumentDisabled" />
                        <Button type="button" @click="triggerMemoFileInput" size="lg" :disabled="isDocumentDisabled">{{ t('inventory.chooseDoc') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
                  </Field>

                  <!-- Lost Document Upload (Required only if condition is Hilang) -->
                  <Field v-if="form.condition === 'Hilang'" :data-invalid="!!errors.lost_doc_file || undefined" :data-disabled="isDocumentDisabled || undefined">
                    <FieldLabel><span>{{ t('inventory.lostDocument') }}<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] truncate flex items-center h-10"
                          :class="[
                            form.lost_doc_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                            errors.lost_doc_file ? 'border-destructive' : 'border-input',
                            isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/10'
                          ]"
                          @click="form.lost_doc_file_name && viewLostDocInNewTab()"
                        >
                          {{ form.lost_doc_file_name || t('inventory.noFileSelected') }}
                        </div>
                        <input type="file" id="edit-asset-lost-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleLostDocUpload" :disabled="isDocumentDisabled" />
                        <Button type="button" @click="triggerLostDocFileInput" size="lg" :disabled="isDocumentDisabled">{{ t('inventory.chooseDoc') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="errors.lost_doc_file">{{ errors.lost_doc_file }}</FieldError>
                  </Field>

                  <!-- Formulir Approval BoD/BoC (Only visible when existing status is Pending:BoD/BoC) -->
                  <Field v-if="isBodBocFieldVisible" :data-invalid="!!errors.bod_boc_approval_file || undefined">
                    <FieldLabel><span>{{ t('inventory.bodBocForm') }}</span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] truncate flex items-center h-10 bg-muted/10"
                          :class="[
                            form.bod_boc_approval_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                            errors.bod_boc_approval_file ? 'border-destructive' : 'border-input'
                          ]"
                          @click="form.bod_boc_approval_file_name && viewBodBocDocInNewTab()"
                        >
                          {{ form.bod_boc_approval_file_name || t('inventory.noFileSelected') }}
                        </div>
                        <input type="file" id="edit-asset-bod-boc-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleBodBocDocUpload" />
                        <Button type="button" @click="triggerBodBocDocFileInput" size="lg">{{ t('inventory.chooseDoc') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="errors.bod_boc_approval_file">{{ errors.bod_boc_approval_file }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ isSingle ? t('inventory.requiredMarker') : t('inventory.bulkEmptyMarker') }}
              </p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" variant="white" size="xl">{{ t('common.cancel') }}</Button>
                <Button @click="handleSubmit" :disabled="form.processing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': form.processing }">
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
