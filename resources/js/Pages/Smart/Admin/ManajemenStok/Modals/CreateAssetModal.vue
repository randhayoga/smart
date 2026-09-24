<script setup lang="ts">
/**
 * Create Asset Modal component for registering new asset units (single or bulk batch creation) with photo compression.
 */
import { ref, watch, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, ChevronDown, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import { compressImageIfNeeded } from '@/utils/imageCompressor';

interface Props {
  open: boolean;
  lot: any;
  units: any[];
  barang: any;
  locations: any[];
  floors?: any[];
  rooms?: any[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const isVehicle = computed(() => props.barang?.category === 'Kendaraan');
const arrNeedApproval = ['Rusak Total', 'Hilang'];
const arrInactiveConditions = ['Rusak Total', 'Hilang', 'Lelang/Hibah'];

const getStatusLabel = (s: string) => {
  if (!s) return t('inventory.selectStatus');
  const map: Record<string, string> = {
    'Tersedia': t('status.tersedia'),
    'Dipinjam': t('status.dipinjam'),
    'Standby': t('status.standby'),
    'Pending': t('status.pending'),
    'Pending:BoD/BoC': t('status.pending'),
    'Scrapped': t('status.scrapped'),
    'Lost': t('status.lost'),
  };
  return map[s] || s;
};

const getConditionLabel = (c: string) => {
  if (!c) return t('inventory.selectCondition');
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

const isStatusDisabled = computed(() => {
  return arrInactiveConditions.includes(form.condition);
});

const form = useForm({
  _method: 'POST',
  number: '',
  lot_id: props.lot?.id,
  location_id: '' as string | number,
  status: '',
  condition: '',
  type: '',
  classification: '',
  price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  is_bulk: false,
  bulk_quantity: '' as string | number,
  vehicle_registration: '',
  memo_file: null as File | null,
  memo_file_name: '',
  lost_doc_file: null as File | null,
  lost_doc_file_name: '',
});

const errors = ref({
  location_id: '',
  status: '',
  condition: '',
  type: '',
  classification: '',
  image_url: '',
  vehicle_registration: '',
  bulk_quantity: '',
  memo_file: '',
  lost_doc_file: '',
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
    bulk_quantity: '',
    memo_file: '',
    lost_doc_file: '',
  };
};

// Reactive error clearing
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.status, v => { if (v && errors.value.status) errors.value.status = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.type, v => { if (v && errors.value.type) errors.value.type = ''; });
watch(() => form.classification, v => { if (v && errors.value.classification) errors.value.classification = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.bulk_quantity, v => { if (v !== '' && errors.value.bulk_quantity) errors.value.bulk_quantity = ''; });
watch(() => form.memo_file, v => { if (v && errors.value.memo_file) errors.value.memo_file = ''; });
watch(() => form.lost_doc_file, v => { if (v && errors.value.lost_doc_file) errors.value.lost_doc_file = ''; });

watch(() => form.condition, (newVal, oldVal) => {
  if (arrInactiveConditions.includes(newVal)) {
    form.status = 'Pending:BoD/BoC';
  } else if (oldVal && arrInactiveConditions.includes(oldVal) && (form.status === 'Pending' || form.status === 'Pending:BoD/BoC')) {
    form.status = '';
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

const generateAssetCode = () => {
  if (props.lot?.next_asset_code) {
    return props.lot.next_asset_code;
  }
  const tipeCode = props.barang?.subcategory_code || '';
  const organizerCode = props.lot?.organizer || '';
  const combination = `${tipeCode}-${organizerCode}-PTRE`;
  
  let yy = String(new Date().getFullYear()).slice(-2);
  if (props.lot?.date_of_receipt) {
    const dateObj = new Date(props.lot.date_of_receipt);
    if (!isNaN(dateObj.getTime())) {
      yy = String(dateObj.getFullYear()).slice(-2);
    }
  }

  const pattern = `-${organizerCode}-PTRE`;
  const matchingUnits = (props.units || []).filter(unit => {
    return unit.number?.includes(pattern);
  });

  let nextNum = 1;
  if (matchingUnits.length > 0) {
    const numbers = matchingUnits.map(unit => {
      const firstPart = unit.number.split('-')[0];
      return parseInt(firstPart, 10) || 0;
    });
    nextNum = Math.max(...numbers) + 1;
  }
  const paddedNum = String(nextNum).padStart(5, '0');
  return `${paddedNum}-${combination}-${yy}`;
};

// Init form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  form.lot_id = props.lot?.id;
  form.number = generateAssetCode();
  form.image_url = null;
  form.image_url_name = '';
  form.use_lot_image = false;
  form.memo_file = null;
  form.memo_file_name = '';
  form.lost_doc_file = null;
  form.lost_doc_file_name = '';
});

const closeModal = () => { emit('update:open', false); };

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

const triggerFileInput = () => {
  const input = document.getElementById('create-asset-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (form.use_lot_image && props.lot?.imageUrl) {
    window.open('/media/' + props.lot.imageUrl, '_blank');
  }
};

const handleSamakanPhoto = () => {
  if (props.lot?.imageUrl) {
    form.use_lot_image = true;
    form.image_url = null;
    form.image_url_name = props.lot.imageUrl.split('/').pop() || '';
  } else {
    toast.error(t('inventory.lotNoPhoto'));
  }
};

const handleMemoUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('inventory.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('inventory.fileTooLarge2Mb')); return; }
  form.memo_file = file;
  form.memo_file_name = file.name;
};

const triggerMemoFileInput = () => {
  const input = document.getElementById('create-asset-memo-upload') as HTMLInputElement;
  input?.click();
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    window.open(URL.createObjectURL(form.memo_file), '_blank');
  }
};

const handleLostDocUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error(t('inventory.invalidDocFormat')); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error(t('inventory.fileTooLarge2Mb')); return; }
  form.lost_doc_file = file;
  form.lost_doc_file_name = file.name;
};

const triggerLostDocFileInput = () => {
  const input = document.getElementById('create-asset-lost-doc-upload') as HTMLInputElement;
  input?.click();
};

const viewLostDocInNewTab = () => {
  if (form.lost_doc_file) {
    window.open(URL.createObjectURL(form.lost_doc_file), '_blank');
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

  let isValid = true;
  if (!form.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
  if (!form.type) { errors.value.type = t('inventory.typeRequired'); isValid = false; }
  if (!form.status) { errors.value.status = t('inventory.statusRequired'); isValid = false; }
  if (!form.condition) { errors.value.condition = t('inventory.conditionRequired'); isValid = false; }
  if (!form.classification) { errors.value.classification = t('inventory.classificationRequired'); isValid = false; }
  if (!form.image_url && !form.image_url_name) { errors.value.image_url = t('inventory.assetPhotoRequired'); isValid = false; }
  if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = t('inventory.nopolRequired'); isValid = false; }
  if (arrNeedApproval.includes(form.status) && !form.memo_file_name) { errors.value.memo_file = t('inventory.memoRequired'); isValid = false; }
  if (form.status === 'Hilang' && !form.lost_doc_file_name) { errors.value.lost_doc_file = t('inventory.lostDocRequired'); isValid = false; }
  if (form.is_bulk && (form.bulk_quantity === '' || form.bulk_quantity === null)) { errors.value.bulk_quantity = t('inventory.assetCountRequired'); isValid = false; }
  if (!isValid) return;

  form.transform((data) => {
    const fd: any = {
      _method: 'POST',
      number: data.number,
      lot_id: data.lot_id,
      location_id: data.location_id,
      status: data.status,
      condition: data.condition,
      type: data.type,
      classification: data.classification,
      price: data.price !== '' && data.price !== null ? parseCurrencyToNumber(data.price) : null,
    };
    if (isVehicle.value) fd.vehicle_registration = data.vehicle_registration;
    if (data.image_url) fd.image_url = data.image_url;
    if (data.use_lot_image) fd.use_lot_image = data.use_lot_image;
    if (data.memo_file) fd.memo_file = data.memo_file;
    if (data.lost_doc_file) fd.lost_doc_file = data.lost_doc_file;
    if (data.is_bulk) {
      fd.is_bulk = data.is_bulk;
      fd.bulk_quantity = data.bulk_quantity;
    }
    return fd;
  });

  const url = form.is_bulk ? '/smart/inventory/units/bulk' : '/smart/inventory/units';
  form.post(url, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      emit('success');
    },
    onError: (errs) => {
      console.error('Validation errors:', errs);
      toast.error(t('inventory.createAssetFailed'));
    },
  });
};
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
        <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
          <div v-if="open" class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-6 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">{{ t('inventory.createNewAsset') }}</h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors cursor-pointer">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] overscroll-contain">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column: Form Fields -->
                <div class="space-y-6">
                  <Field>
                    <FieldLabel>
                      <span>{{ t('inventory.assetCode') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <input type="text" :value="form.number" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.location_id || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.location') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <LocationCombobox
                        v-model="form.location_id"
                        :locations="locations"
                        :placeholder="t('inventory.selectLocation')"
                        :error="!!errors.location_id"
                        :active-only="true"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.type || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.type') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.type ? 'text-muted-foreground' : 'text-foreground', errors.type ? 'border-destructive' : '']">
                            {{ form.type || t('inventory.selectType') }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.type = 'LT'">LT</DropdownMenuItem>
                          <DropdownMenuItem @select="form.type = 'ST'">ST</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.type">{{ errors.type }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.status || undefined" :data-disabled="isStatusDisabled || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.status') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div v-if="isStatusDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
                        <span>{{ getStatusLabel(form.status) }}</span>
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </div>
                      <DropdownMenu v-else>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.status ? 'text-muted-foreground' : 'text-foreground', errors.status ? 'border-destructive' : '']">
                            {{ getStatusLabel(form.status) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.status = 'Tersedia'">{{ t('status.tersedia') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Standby'">{{ t('status.standby') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.status">{{ errors.status }}</FieldError>
                  </Field>

                  <!-- Bulk Creation (Only when not vehicle category) -->
                  <Field v-if="!isVehicle" :data-invalid="!!errors.bulk_quantity || undefined">
                    <FieldContent>
                      <div class="flex items-center gap-2 w-full pt-2">
                        <Checkbox id="auto-create-checkbox" v-model="form.is_bulk" />
                        <label for="auto-create-checkbox" class="cursor-pointer select-none text-sm font-medium text-foreground">
                          {{ t('inventory.autoCreateLabelPrefix') }}
                        </label>
                        <input type="number" v-model="form.bulk_quantity" placeholder="..." min="1" :disabled="!form.is_bulk"
                          class="w-16 px-2 py-1 text-sm border rounded-[10px] bg-background focus:outline-none focus:ring-2 transition-colors h-8 disabled:opacity-50 disabled:cursor-not-allowed mx-1"
                          :class="[errors.bulk_quantity ? 'border-destructive' : 'border-input']"
                        />
                        <span class="text-sm font-medium text-foreground">{{ t('inventory.autoCreateLabelSuffix') }}</span>
                      </div>
                    </FieldContent>
                    <FieldError v-if="errors.bulk_quantity" class="pl-6">{{ errors.bulk_quantity }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="!!errors.condition || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.condition') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground', errors.condition ? 'border-destructive' : '']">
                            {{ getConditionLabel(form.condition) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'QC Passed'">{{ t('inventory.conditionQcPassed') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Lelang/Hibah'">{{ t('inventory.conditionAuctionGrant') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Rusak Total'">{{ t('inventory.conditionTotalDamage') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Hilang'">{{ t('inventory.conditionLost') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>
                      <span>{{ t('inventory.unitPrice') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input type="number" v-model="form.price" :placeholder="t('inventory.unitPricePlaceholder')" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                        </div>
                        <Button type="button" @click="handleSamakanPrice" variant="warning" size="lg">{{ t('inventory.sameAsParent') }}</Button>
                      </div>
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.classification || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.classification') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <div class="flex-grow min-w-0">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.classification ? 'text-muted-foreground' : 'text-foreground', errors.classification ? 'border-destructive' : '']">
                                {{ getClassificationLabel(form.classification) || t('inventory.selectClassification') }}
                                <ChevronDown class="w-4 h-4 opacity-50" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                              <DropdownMenuItem @select="form.classification = 'Aset'">{{ t('inventory.classificationAsset') }}</DropdownMenuItem>
                              <DropdownMenuItem @select="form.classification = 'Inventaris'">{{ t('inventory.classificationInventory') }}</DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                        <Button type="button" @click="handleDecideClassification" variant="warning" size="lg">{{ t('inventory.decide') }}</Button>
                      </div>
                    </FieldContent>
                    <FieldError v-if="errors.classification">{{ errors.classification }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.image_url || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.photo') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.image_url ? 'border-destructive' : 'border-input']"
                          @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || t('inventory.noPhotoSelected') }}
                        </div>
                        <input type="file" id="create-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg">{{ t('inventory.sameAsParent') }}</Button>
                        <Button type="button" @click="triggerFileInput" size="lg">{{ t('inventory.chooseFile') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize1Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>

                  <!-- TNKB (Only for Vehicles) -->
                  <Field v-if="isVehicle" :data-invalid="!!errors.vehicle_registration || undefined">
                    <FieldLabel><span>{{ t('inventory.tnkb') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.vehicle_registration" :placeholder="t('inventory.nopolPlaceholder')"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                        :class="[errors.vehicle_registration ? 'border-destructive' : '']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
                  </Field>

                  <!-- Document Upload (Required for approval conditions) -->
                  <Field v-if="arrNeedApproval.includes(form.condition)" :data-invalid="!!errors.memo_file || undefined">
                    <FieldLabel><span>{{ t('inventory.memoDocument') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[form.memo_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.memo_file ? 'border-destructive' : 'border-input']"
                          @click="form.memo_file_name && viewMemoInNewTab()"
                        >
                          {{ form.memo_file_name || t('inventory.noFileSelected') }}
                        </div>
                        <input type="file" id="create-asset-memo-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleMemoUpload" />
                        <Button type="button" @click="triggerMemoFileInput" size="lg">{{ t('inventory.chooseDoc') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
                  </Field>

                  <!-- Lost Document Upload (Required only if condition is Hilang) -->
                  <Field v-if="form.condition === 'Hilang'" :data-invalid="!!errors.lost_doc_file || undefined">
                    <FieldLabel><span>{{ t('inventory.lostDocument') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[form.lost_doc_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.lost_doc_file ? 'border-destructive' : 'border-input']"
                          @click="form.lost_doc_file_name && viewLostDocInNewTab()"
                        >
                          {{ form.lost_doc_file_name || t('inventory.noFileSelected') }}
                        </div>
                        <input type="file" id="create-asset-lost-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleLostDocUpload" />
                        <Button type="button" @click="triggerLostDocFileInput" size="lg">{{ t('inventory.chooseDoc') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="errors.lost_doc_file">{{ errors.lost_doc_file }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('inventory.requiredMarker') }}</p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" variant="white" size="xl">{{ t('common.cancel') }}</Button>
                <Button @click="handleSubmit" :disabled="form.processing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': form.processing }">{{ t('inventory.createAssetBtn') }}</span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
