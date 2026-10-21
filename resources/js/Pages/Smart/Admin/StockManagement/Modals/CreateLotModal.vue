<script setup lang="ts">
/**
 * Create LOT Modal component ("Input New LOT" / "Input LOT Baru")
 * Matches InputNewStock LotSection.vue and embeds AssetSection.vue for non-consumable asset creation.
 */
import { ref, watch, computed, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, ChevronDown, Loader2, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import AssetSection from './InputNewStock/AssetSection.vue';

interface Props {
  open: boolean;
  barang: {
    id: number;
    code: string;
    category: string;
    category_code?: string;
    subcategory: string;
    brand: string;
    name: string;
    specification: string;
    image_url: string | null;
    uom: string;
    is_consumable: boolean;
  };
  lots: any[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  projects: any[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const memoFileInputRef = ref<HTMLInputElement | null>(null);
const lotPhotoInputRef = ref<HTMLInputElement | null>(null);

const isComp = computed(() => {
  const cat = props.barang.category || '';
  const catCode = props.barang.category_code || '';
  return catCode === 'COMP' || cat.toLowerCase().includes('computer') || cat.toLowerCase().includes('komputer');
});

const isKend = computed(() => {
  const cat = props.barang.category || '';
  const catCode = props.barang.category_code || '';
  return catCode === 'KEND' || cat.toLowerCase().includes('kendaraan') || cat.toLowerCase().includes('vehicle');
});

const lotForm = useForm({
  _method: 'POST',
  barang_id: props.barang.id,
  model_id: props.barang.id,
  entry_type: 'Procurement' as 'Procurement' | 'Hibah',
  po_number: '',
  purchase_date: '',
  memo_number: '',
  date_of_grant: '',
  memo_file: null as File | null,
  memo_file_name: '',
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  // Consumable fields
  location_id: '' as string | number,
  initial_quantity: '' as string | number,
  current_quantity: '' as string | number,
  unit_price: '' as string | number,
  burden: 'Corporate' as 'Corporate' | 'Project',
  project_id: '' as string | number,
  lot_photo: null as File | null,
  lot_photo_name: '',
  use_parent_image: false,
  // Asset fields (for non-consumables)
  input_asset: false,
  asset_specification: '',
  borrowing_type: 'LT' as 'LT' | 'ST',
  condition: 'Bagus' as 'Bagus' | 'Rusak',
  price: '' as string | number,
  classification: 'Aset' as 'Aset' | 'Inventaris',
  photo: null as File | null,
  photo_name: '',
  plate_number: '',
  is_bulk: false,
  bulk_quantity: '' as string | number,
});

const errors = ref<Record<string, string>>({
  entry_type: '',
  organizer_id: '',
  vendor_id: '',
  po_number: '',
  purchase_date: '',
  memo_number: '',
  date_of_grant: '',
  memo_file: '',
  location_id: '',
  initial_quantity: '',
  burden: '',
  project_id: '',
  lot_photo: '',
  asset_specification: '',
  borrowing_type: '',
  condition: '',
  classification: '',
  bulk_quantity: '',
  plate_number: '',
});

const resetErrors = () => {
  errors.value = {
    entry_type: '',
    organizer_id: '',
    vendor_id: '',
    po_number: '',
    purchase_date: '',
    memo_number: '',
    date_of_grant: '',
    memo_file: '',
    location_id: '',
    initial_quantity: '',
    burden: '',
    project_id: '',
    lot_photo: '',
    asset_specification: '',
    borrowing_type: '',
    condition: '',
    classification: '',
    bulk_quantity: '',
    plate_number: '',
  };
};

const projectOptions = computed(() => {
  return (props.projects || [])
    .filter(p => Boolean((p.no_project || p.project_no)?.trim() && p.project_name?.trim()))
    .map(p => ({
      id: p.id_project || p.id,
      name: `[${p.no_project || p.project_no}] ${p.project_name}`
    }));
});

const filteredVendors = computed(() => {
  return (props.vendors || []).filter(v => v.name?.trim().toLowerCase() !== 'beragam');
});

// Reactive error clearing
watch(() => lotForm.organizer_id, v => { if (v && errors.value.organizer_id) errors.value.organizer_id = ''; });
watch(() => lotForm.vendor_id, v => { if (v && errors.value.vendor_id) errors.value.vendor_id = ''; });
watch(() => lotForm.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => lotForm.po_number, v => { if (v && errors.value.po_number) errors.value.po_number = ''; });
watch(() => lotForm.purchase_date, v => { if (v && errors.value.purchase_date) errors.value.purchase_date = ''; });
watch(() => lotForm.memo_number, v => { if (v && errors.value.memo_number) errors.value.memo_number = ''; });
watch(() => lotForm.date_of_grant, v => { if (v && errors.value.date_of_grant) errors.value.date_of_grant = ''; });
watch(() => lotForm.initial_quantity, v => { if ((v !== '' && v !== null) && errors.value.initial_quantity) errors.value.initial_quantity = ''; });
watch(() => lotForm.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });
watch(() => lotForm.burden, v => {
  if (v !== 'Project') {
    lotForm.project_id = '';
    errors.value.project_id = '';
  }
});

const onSelectEntryType = (type: 'Procurement' | 'Hibah') => {
  lotForm.entry_type = type;
  if (type === 'Procurement') {
    lotForm.memo_number = '';
    lotForm.date_of_grant = '';
    lotForm.memo_file = null;
    lotForm.memo_file_name = '';
  } else {
    lotForm.vendor_id = '';
    lotForm.po_number = '';
    lotForm.purchase_date = '';
  }
};

const onSelectBurden = (burden: 'Corporate' | 'Project') => {
  lotForm.burden = burden;
  if (burden !== 'Project') {
    lotForm.project_id = '';
  }
};

const triggerMemoFileInput = () => {
  memoFileInputRef.value?.click();
};

const handleMemoFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

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

  lotForm.memo_file = file;
  lotForm.memo_file_name = file.name;
};

const handleDeleteMemoFile = () => {
  lotForm.memo_file = null;
  lotForm.memo_file_name = '';
  if (memoFileInputRef.value) {
    memoFileInputRef.value.value = '';
  }
};

const triggerLotPhotoInput = () => {
  lotPhotoInputRef.value?.click();
};

const handleLotPhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidFileFormat'));
    target.value = '';
    return;
  }
  if (file.size > 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge1Mb'));
    target.value = '';
    return;
  }

  lotForm.lot_photo = file;
  lotForm.lot_photo_name = file.name;
  lotForm.use_parent_image = false;
};

const handleDeleteLotPhoto = () => {
  lotForm.lot_photo = null;
  lotForm.lot_photo_name = '';
  lotForm.use_parent_image = false;
  if (lotPhotoInputRef.value) {
    lotPhotoInputRef.value.value = '';
  }
};

const hasLotPhoto = computed(() => {
  return Boolean(lotForm.lot_photo || lotForm.lot_photo_name);
});

const viewLotPhotoInNewTab = () => {
  if (lotForm.lot_photo) {
    window.open(URL.createObjectURL(lotForm.lot_photo), '_blank');
  }
};

const isProcessing = computed(() => lotForm.processing);

const closeModal = () => {
  if (isProcessing.value) return;
  emit('update:open', false);
};

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) {
    if (isProcessing.value) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      return;
    }
    closeModal();
  }
};

watch(() => props.open, (val) => {
  if (!val) {
    window.removeEventListener('keydown', handleKeydown, true);
    return;
  }
  window.addEventListener('keydown', handleKeydown, true);
  lotForm.reset();
  lotForm.barang_id = props.barang.id;
  lotForm.model_id = props.barang.id;
  lotForm.entry_type = 'Procurement';
  lotForm.po_number = '';
  lotForm.purchase_date = '';
  lotForm.memo_number = '';
  lotForm.date_of_grant = '';
  lotForm.memo_file = null;
  lotForm.memo_file_name = '';
  lotForm.organizer_id = '';
  lotForm.vendor_id = '';
  lotForm.location_id = '';
  lotForm.initial_quantity = '';
  lotForm.current_quantity = '';
  lotForm.unit_price = '';
  lotForm.burden = 'Corporate';
  lotForm.project_id = '';
  lotForm.lot_photo = null;
  lotForm.lot_photo_name = '';
  lotForm.use_parent_image = false;
  lotForm.input_asset = false;
  lotForm.asset_specification = '';
  lotForm.borrowing_type = 'LT';
  lotForm.condition = 'Bagus';
  lotForm.price = '';
  lotForm.classification = 'Aset';
  lotForm.photo = null;
  lotForm.photo_name = '';
  lotForm.plate_number = '';
  lotForm.is_bulk = false;
  lotForm.bulk_quantity = '';
  lotForm.clearErrors();
  resetErrors();
}, { immediate: true });

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown, true);
});

const handleSubmit = () => {
  if (isProcessing.value) return;
  resetErrors();
  let isValid = true;

  if (lotForm.entry_type === 'Procurement') {
    if (!lotForm.po_number || !String(lotForm.po_number).trim()) {
      errors.value.po_number = t('inventory.poNumberRequired');
      isValid = false;
    }
    if (!lotForm.purchase_date) {
      errors.value.purchase_date = t('inventory.purchaseDateRequired');
      isValid = false;
    }
    if (!lotForm.vendor_id) {
      errors.value.vendor_id = t('inventory.vendorRequired');
      isValid = false;
    }
  } else {
    if (!lotForm.memo_number || !String(lotForm.memo_number).trim()) {
      errors.value.memo_number = t('inventory.memoNumberRequired');
      isValid = false;
    }
    if (!lotForm.date_of_grant) {
      errors.value.date_of_grant = t('inventory.dateOfGrantRequired');
      isValid = false;
    }
  }

  if (!lotForm.organizer_id) {
    errors.value.organizer_id = t('inventory.organizerRequired');
    isValid = false;
  }

  if (props.barang.is_consumable) {
    if (lotForm.initial_quantity === '' || lotForm.initial_quantity === null) {
      errors.value.initial_quantity = t('inventory.stockQtyRequired');
      isValid = false;
    }
    if (!lotForm.burden) {
      errors.value.burden = t('inventory.burdenRequired');
      isValid = false;
    } else if (lotForm.burden === 'Project' && !lotForm.project_id) {
      errors.value.project_id = t('inventory.projectRequired');
      isValid = false;
    }
    if (!lotForm.location_id) {
      errors.value.location_id = t('inventory.locationRequired');
      isValid = false;
    }
  } else if (lotForm.input_asset) {
    if (isComp.value && !String(lotForm.asset_specification || '').trim()) {
      errors.value.asset_specification = t('inventory.specificationRequired');
      isValid = false;
    }
    if (!lotForm.borrowing_type) {
      errors.value.borrowing_type = t('inventory.borrowingTypeRequired');
      isValid = false;
    }
    if (!lotForm.condition) {
      errors.value.condition = t('inventory.conditionRequired');
      isValid = false;
    }
    if (!lotForm.location_id) {
      errors.value.location_id = t('inventory.locationRequired');
      isValid = false;
    }
    if (!lotForm.classification) {
      errors.value.classification = t('inventory.classificationRequired');
      isValid = false;
    }
    if (!lotForm.burden) {
      errors.value.burden = t('inventory.burdenRequired');
      isValid = false;
    } else if (lotForm.burden === 'Project' && !lotForm.project_id) {
      errors.value.project_id = t('inventory.projectRequired');
      isValid = false;
    }
    if (isKend.value && !String(lotForm.plate_number || '').trim()) {
      errors.value.plate_number = t('inventory.plateNumberRequired');
      isValid = false;
    }
    if (!isKend.value && lotForm.is_bulk) {
      if (!lotForm.bulk_quantity || Number(lotForm.bulk_quantity) < 1) {
        errors.value.bulk_quantity = t('inventory.bulkQuantityRequired');
        isValid = false;
      }
    }
  }

  if (!isValid) return;

  lotForm.transform((data) => {
    const fd: any = {
      _method: data._method,
      barang_id: data.barang_id,
      model_id: data.model_id,
      entry_type: data.entry_type,
      organizer_id: data.organizer_id,
      po_number: data.entry_type === 'Procurement' ? data.po_number : null,
      purchase_grant_date: data.entry_type === 'Procurement' ? data.purchase_date : data.date_of_grant,
      purchase_date: data.purchase_date,
      date_of_grant: data.date_of_grant,
      vendor_id: data.entry_type === 'Procurement' ? (data.vendor_id ? Number(data.vendor_id) : null) : null,
      memo_number: data.entry_type === 'Hibah' ? data.memo_number : null,
    };

    if (data.entry_type === 'Hibah' && data.memo_file) {
      fd.memo_file = data.memo_file;
    }

    if (props.barang.is_consumable) {
      fd.initial_quantity = data.initial_quantity;
      fd.current_quantity = data.initial_quantity;
      fd.unit_price = data.unit_price || null;
      fd.burden = data.burden;
      fd.project_id = data.burden === 'Project' ? data.project_id : null;
      fd.location_id = data.location_id ? Number(data.location_id) : null;
      if (data.lot_photo) fd.image_url = data.lot_photo;
      if (data.use_parent_image) fd.use_parent_image = data.use_parent_image;
    } else {
      fd.input_asset = data.input_asset;
      if (data.input_asset) {
        fd.burden = data.burden;
        fd.project_id = data.burden === 'Project' ? data.project_id : null;
        fd.location_id = data.location_id ? Number(data.location_id) : null;
        fd.borrowing_type = data.borrowing_type;
        fd.condition = data.condition;
        fd.classification = data.classification;
        fd.price = data.price || null;
        if (data.asset_specification) fd.asset_specification = data.asset_specification;
        if (data.plate_number) fd.plate_number = data.plate_number;
        fd.is_bulk = data.is_bulk;
        if (data.is_bulk) fd.bulk_quantity = data.bulk_quantity;
        if (data.photo) fd.photo = data.photo;
      }
    }

    return fd;
  });

  lotForm.post('/smart/stock-management/lots', {
    onSuccess: () => {
      closeModal();
      emit('success');
    },
    onError: (errs) => {
      Object.keys(errs).forEach((k) => {
        if (k in errors.value) {
          (errors.value as any)[k] = errs[k];
        }
      });
      toast.error(t('inventory.createLotFailed') || 'Failed to create lot');
    }
  });
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
              <h3 class="text-lg font-bold text-foreground">{{ t('inventory.createNewLot') }}</h3>
              <button @click="closeModal" :disabled="isProcessing" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[85vh] overscroll-contain space-y-6">
              <!-- Lot Section Form -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <!-- Entry Type Selection -->
                  <Field :data-invalid="!!errors.entry_type || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.entryType') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant="outline"
                            class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                            :class="[errors.entry_type ? 'border-destructive' : '']"
                          >
                            {{ lotForm.entry_type === 'Procurement' ? t('inventory.procurement') : t('inventory.grant') }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="onSelectEntryType('Procurement')">{{ t('inventory.procurement') }}</DropdownMenuItem>
                          <DropdownMenuItem @select="onSelectEntryType('Hibah')">{{ t('inventory.grant') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.entry_type">{{ errors.entry_type }}</FieldError>
                  </Field>

                  <!-- Procurement Fields -->
                  <template v-if="lotForm.entry_type === 'Procurement'">
                    <!-- PO Number -->
                    <Field :data-invalid="!!errors.po_number || undefined">
                      <FieldLabel for="new-lot-po-number">
                        <span>{{ t('inventory.poNumber') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <input
                          id="new-lot-po-number"
                          v-model="lotForm.po_number"
                          type="text"
                          maxlength="255"
                          placeholder="PO-001"
                          class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                          :class="[errors.po_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.po_number">{{ errors.po_number }}</FieldError>
                    </Field>

                    <!-- Purchase Date -->
                    <Field :data-invalid="!!errors.purchase_date || undefined">
                      <FieldLabel for="new-lot-purchase-date">
                        <span>{{ t('inventory.purchaseDate') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <input
                          id="new-lot-purchase-date"
                          v-model="lotForm.purchase_date"
                          type="date"
                          class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                          :class="[errors.purchase_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.purchase_date">{{ errors.purchase_date }}</FieldError>
                    </Field>
                  </template>

                  <!-- Hibah Fields -->
                  <template v-else-if="lotForm.entry_type === 'Hibah'">
                    <!-- Memo Number -->
                    <Field :data-invalid="!!errors.memo_number || undefined">
                      <FieldLabel for="new-lot-memo-number">
                        <span>{{ t('inventory.memoNumber') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <input
                          id="new-lot-memo-number"
                          v-model="lotForm.memo_number"
                          type="text"
                          maxlength="255"
                          placeholder="MEMO-001"
                          class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                          :class="[errors.memo_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.memo_number">{{ errors.memo_number }}</FieldError>
                    </Field>

                    <!-- Date of Grant -->
                    <Field :data-invalid="!!errors.date_of_grant || undefined">
                      <FieldLabel for="new-lot-date-of-grant">
                        <span>{{ t('inventory.dateOfGrant') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <input
                          id="new-lot-date-of-grant"
                          v-model="lotForm.date_of_grant"
                          type="date"
                          class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                          :class="[errors.date_of_grant ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.date_of_grant">{{ errors.date_of_grant }}</FieldError>
                    </Field>

                    <!-- Memo Document File Upload -->
                    <Field :data-invalid="!!errors.memo_file || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.memoDocument') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="space-y-1">
                          <div class="flex items-center gap-2">
                            <div class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 text-muted-foreground truncate h-10 flex items-center">
                              {{ lotForm.memo_file_name || t('inventory.noFileSelected') }}
                            </div>
                            <input
                              ref="memoFileInputRef"
                              type="file"
                              class="hidden"
                              accept=".pdf,.jpg,.jpeg,.png,.webp"
                              @change="handleMemoFileUpload"
                            />
                            <Button
                              type="button"
                              size="lg"
                              class="shrink-0"
                              @click="triggerMemoFileInput"
                            >
                              {{ t('inventory.chooseFile') }}
                            </Button>
                            <Button
                              type="button"
                              variant="destructive"
                              size="icon"
                              class="w-9 shrink-0"
                              :disabled="!lotForm.memo_file"
                              @click="handleDeleteMemoFile"
                            >
                              <Trash2 class="w-4 h-4" />
                            </Button>
                          </div>
                          <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                        </div>
                      </FieldContent>
                      <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
                    </Field>
                  </template>

                  <!-- Consumable: Total Stock & Unit Price -->
                  <template v-if="props.barang.is_consumable">
                    <Field :data-invalid="!!errors.initial_quantity || undefined">
                      <FieldLabel for="new-lot-total-stock">
                        <span>{{ t('inventory.totalStock') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <div
                          class="flex w-full rounded-[14px] border bg-background focus-within:ring-2 transition-colors h-10 overflow-hidden"
                          :class="[errors.initial_quantity ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive' : 'border-input focus-within:ring-primary/20 focus-within:border-primary']"
                        >
                          <input
                            id="new-lot-total-stock"
                            v-model="lotForm.initial_quantity"
                            type="number"
                            min="0"
                            :placeholder="t('inventory.stockCountPlaceholder')"
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          />
                          <span v-if="barang.uom" class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-l border-input select-none font-medium">
                            {{ barang.uom }}
                          </span>
                        </div>
                      </FieldContent>
                      <FieldError v-if="errors.initial_quantity">{{ errors.initial_quantity }}</FieldError>
                    </Field>

                    <Field>
                      <FieldLabel>
                        <span>{{ t('inventory.unitPrice') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input
                            type="number"
                            v-model="lotForm.unit_price"
                            :placeholder="t('inventory.unitPricePlaceholder')"
                            min="0"
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          />
                        </div>
                      </FieldContent>
                    </Field>
                  </template>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <!-- Vendor (Procurement only) -->
                  <Field v-if="lotForm.entry_type === 'Procurement'" :data-invalid="!!errors.vendor_id || undefined">
                    <FieldLabel for="new-lot-vendor">
                      <span>{{ t('inventory.vendor') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox
                        id="new-lot-vendor"
                        :model-value="lotForm.vendor_id"
                        :options="filteredVendors"
                        :placeholder="t('inventory.selectVendor')"
                        :search-placeholder="t('inventory.searchVendorPlaceholder')"
                        :error="!!errors.vendor_id"
                        width-class="w-full"
                        @update:model-value="(val) => { lotForm.vendor_id = val ? Number(val) : ''; }"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.vendor_id">{{ errors.vendor_id }}</FieldError>
                  </Field>

                  <!-- Organizer -->
                  <Field :data-invalid="!!errors.organizer_id || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.organizer') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant="outline"
                            class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                            :class="[!lotForm.organizer_id ? 'text-muted-foreground' : 'text-foreground', errors.organizer_id ? 'border-destructive' : '']"
                          >
                            {{ organizers.find(o => o.id == lotForm.organizer_id)?.name || t('inventory.selectOrganizer') }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem
                            v-for="org in organizers"
                            :key="org.id"
                            @select="lotForm.organizer_id = org.id"
                          >
                            {{ org.name }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.organizer_id">{{ errors.organizer_id }}</FieldError>
                  </Field>

                  <!-- Consumable: Burden, Project, Location, Photo -->
                  <template v-if="props.barang.is_consumable">
                    <!-- Burden -->
                    <Field :data-invalid="!!errors.burden || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.burden') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button
                              variant="outline"
                              class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                              :class="[errors.burden ? 'border-destructive' : '']"
                            >
                              {{ lotForm.burden === 'Project' ? t('inventory.project') : t('inventory.corporate') }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem @select="onSelectBurden('Corporate')">{{ t('inventory.corporate') }}</DropdownMenuItem>
                            <DropdownMenuItem @select="onSelectBurden('Project')">{{ t('inventory.project') }}</DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </FieldContent>
                      <FieldError v-if="errors.burden">{{ errors.burden }}</FieldError>
                    </Field>

                    <!-- Project Combobox (if Burden = Project) -->
                    <Field v-if="lotForm.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
                      <FieldLabel for="new-lot-project">
                        <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <Combobox
                          id="new-lot-project"
                          :model-value="lotForm.project_id"
                          :options="projectOptions"
                          :placeholder="t('inventory.selectProject')"
                          :error="!!errors.project_id"
                          width-class="w-full"
                          @update:model-value="(val) => { lotForm.project_id = val ? Number(val) : ''; }"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
                    </Field>

                    <!-- Location Selection -->
                    <Field :data-invalid="!!errors.location_id || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.location') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <LocationCombobox
                          v-model="lotForm.location_id"
                          :locations="locations || []"
                          :placeholder="t('inventory.selectLocation')"
                          :error="!!errors.location_id"
                          :active-only="true"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
                    </Field>

                    <!-- Photo Selection -->
                    <Field :data-invalid="!!errors.lot_photo || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.photo') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="space-y-1">
                          <div class="flex gap-2">
                            <div
                              class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                              :class="[
                                hasLotPhoto
                                  ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                                  : 'text-muted-foreground cursor-default',
                                errors.lot_photo ? 'border-destructive' : 'border-input',
                              ]"
                              @click="hasLotPhoto && viewLotPhotoInNewTab()"
                            >
                              {{ lotForm.lot_photo_name || t('inventory.noPhotoSelected') }}
                            </div>
                            <input
                              ref="lotPhotoInputRef"
                              type="file"
                              class="hidden"
                              accept=".jpg,.jpeg,.png,.webp"
                              @change="handleLotPhotoUpload"
                            />
                            <Button
                              type="button"
                              size="lg"
                              class="shrink-0"
                              @click="triggerLotPhotoInput"
                            >
                              {{ t('inventory.chooseFile') }}
                            </Button>
                            <Button
                              type="button"
                              variant="destructive"
                              size="icon"
                              class="w-9 shrink-0"
                              :disabled="!hasLotPhoto"
                              :title="t('inventory.deletePhoto')"
                              @click="handleDeleteLotPhoto"
                            >
                              <Trash2 class="w-4 h-4" />
                            </Button>
                          </div>
                          <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize1Mb') }}</p>
                        </div>
                      </FieldContent>
                      <FieldError v-if="errors.lot_photo">{{ errors.lot_photo }}</FieldError>
                    </Field>
                  </template>
                </div>
              </div>

              <!-- Asset Section (for non-consumables) -->
              <AssetSection
                v-if="!props.barang.is_consumable"
                :form="lotForm"
                :errors="errors"
                :locations="locations || []"
                :projects="projects || []"
                :is-comp="isComp"
                :is-kend="isKend"
                :lot-ready="true"
                :is-consumable="props.barang.is_consumable"
              />
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('inventory.requiredMarker') }}</p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" :disabled="isProcessing" variant="white" size="xl">{{ t('common.cancel') }}</Button>
                <Button @click="handleSubmit" :disabled="isProcessing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="isProcessing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': isProcessing }">{{ t('inventory.createLotBtn') }}</span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
