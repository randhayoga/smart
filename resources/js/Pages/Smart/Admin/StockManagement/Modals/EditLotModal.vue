<script setup lang="ts">
/**
 * Edit LOT Modal component supporting single LOT updates ("Edit LOT Details")
 * and bulk LOT updates ("Edit Selected LOTs").
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

interface Props {
  open: boolean;
  /** The LOT item to edit (single), or array of LOT items for bulk edit */
  items: any[];
  /** Whether the parent barang is consumable */
  isConsumable: boolean;
  /** Parent barang image URL */
  parentImageUrl?: string | null;
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  projects: any[];
  uom?: string | null;
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
const lotUom = computed(() => {
  return props.uom || selectedItem.value?.barang_uom || selectedItem.value?.uom || '';
});

const memoFileInputRef = ref<HTMLInputElement | null>(null);

const form = useForm({
  ids: [] as number[],
  number: '',
  barang_id: '' as string | number,
  model_id: '' as string | number,
  entry_type: 'Procurement' as 'Procurement' | 'Hibah',
  po_number: '',
  purchase_date: '',
  date_of_grant: '',
  memo_number: '',
  memo_file: null as File | null,
  memo_file_name: '',
  delete_memo: false,
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  // Consumable fields
  location_id: '' as string | number,
  unit_price: '' as string | number,
  burden: 'Corporate' as string,
  project_id: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  delete_image: false,
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
  burden: '',
  project_id: '',
  image_url: '',
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
    burden: '',
    project_id: '',
    image_url: '',
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

const isBeragamLot = computed(() => {
  if (!isSingle.value || !selectedItem.value) return false;
  const item = selectedItem.value;
  const vendorName = String(item.vendor || item.vendor_name || '').trim().toLowerCase();
  if (vendorName === 'beragam') return true;

  const currentVendor = (props.vendors || []).find(v => v.id == item.vendor_id || v.id == item.legacy_vendor_id);
  if (currentVendor && currentVendor.name?.trim().toLowerCase() === 'beragam') {
    return true;
  }
  return false;
});

// Reactive error clearing
watch(() => form.organizer_id, v => { if (v && errors.value.organizer_id) errors.value.organizer_id = ''; });
watch(() => form.vendor_id, v => { if (v && errors.value.vendor_id) errors.value.vendor_id = ''; });
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.po_number, v => { if (v && errors.value.po_number) errors.value.po_number = ''; });
watch(() => form.purchase_date, v => { if (v && errors.value.purchase_date) errors.value.purchase_date = ''; });
watch(() => form.memo_number, v => { if (v && errors.value.memo_number) errors.value.memo_number = ''; });
watch(() => form.date_of_grant, v => { if (v && errors.value.date_of_grant) errors.value.date_of_grant = ''; });
watch(() => form.burden, v => {
  if (v && errors.value.burden) errors.value.burden = '';
  if (v !== 'Project') {
    form.project_id = '';
    errors.value.project_id = '';
  }
});
watch(() => form.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });

const isImageDeleted = ref(false);

const hasImage = computed(() => {
  if (!isSingle.value) return Boolean(form.image_url);
  if (form.image_url) return true;
  if (isImageDeleted.value) return false;
  return Boolean(selectedItem.value?.imageUrl || selectedItem.value?.image_url);
});

const hasMemoFile = computed(() => {
  if (form.delete_memo) return false;
  return Boolean(form.memo_file || form.memo_file_name);
});

const onSelectEntryType = (type: 'Procurement' | 'Hibah') => {
  form.entry_type = type;
  if (type === 'Procurement') {
    form.memo_number = '';
    form.date_of_grant = '';
    form.memo_file = null;
    form.memo_file_name = '';
    form.delete_memo = false;
  } else {
    form.vendor_id = '';
    form.po_number = '';
    form.purchase_date = '';
  }
};

const onSelectBurden = (burden: string) => {
  form.burden = burden;
  if (burden !== 'Project') {
    form.project_id = '';
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

  form.memo_file = file;
  form.memo_file_name = file.name;
  form.delete_memo = false;
};

const handleDeleteMemoFile = () => {
  form.memo_file = null;
  form.memo_file_name = '';
  form.delete_memo = true;
  if (memoFileInputRef.value) {
    memoFileInputRef.value.value = '';
  }
};

const viewMemoFileInNewTab = () => {
  if (form.memo_file) {
    window.open(URL.createObjectURL(form.memo_file), '_blank');
  } else if (selectedItem.value?.memo_url && !form.delete_memo) {
    window.open('/media/' + selectedItem.value.memo_url, '_blank');
  }
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-lot-photo-upload') as HTMLInputElement;
  input?.click();
};

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidFileFormat'));
    return;
  }
  if (file.size > 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge1Mb'));
    return;
  }
  isImageDeleted.value = false;
  form.image_url = file;
  form.image_url_name = file.name;
  form.delete_image = false;
};

const handleDeletePhoto = () => {
  isImageDeleted.value = true;
  form.image_url = null;
  form.image_url_name = '';
  form.delete_image = true;
  const input = document.getElementById('edit-lot-photo-upload') as HTMLInputElement;
  if (input) input.value = '';
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (!isImageDeleted.value && isSingle.value && selectedItem.value) {
    const imageUrl = selectedItem.value.imageUrl || selectedItem.value.image_url;
    if (imageUrl) window.open('/media/' + imageUrl, '_blank');
  }
};

const resetModalState = () => {
  form.reset();
  form.clearErrors();
  resetErrors();
  isImageDeleted.value = false;
  form.ids = [];
  form.number = '';
  form.barang_id = '';
  form.model_id = '';
  form.entry_type = 'Procurement';
  form.po_number = '';
  form.purchase_date = '';
  form.date_of_grant = '';
  form.memo_number = '';
  form.memo_file = null;
  form.memo_file_name = '';
  form.delete_memo = false;
  form.organizer_id = '';
  form.vendor_id = '';
  form.location_id = '';
  form.unit_price = '';
  form.burden = props.isConsumable ? 'Tidak berubah' : '';
  form.project_id = '';
  form.image_url = null;
  form.image_url_name = '';
  form.delete_image = false;

  if (memoFileInputRef.value) {
    memoFileInputRef.value.value = '';
  }
  const photoInput = document.getElementById('edit-lot-photo-upload') as HTMLInputElement | null;
  if (photoInput) {
    photoInput.value = '';
  }
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
    form.barang_id = item.barang_id || item.model_id || '';
    form.model_id = item.model_id || item.barang_id || '';
    form.entry_type = (item.entry_type === 'Hibah') ? 'Hibah' : 'Procurement';
    form.po_number = item.po_number || '';
    form.organizer_id = item.organizer_id || '';
    form.vendor_id = item.vendor_id || item.legacy_vendor_id || '';

    let receiptDate = item.purchase_grant_date || item.date_of_receipt || '';
    if (!receiptDate && item.entryDate && item.entryDate !== '-') {
      const parts = item.entryDate.split('/');
      if (parts.length === 3) {
        receiptDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
      }
    }
    if (form.entry_type === 'Hibah') {
      form.date_of_grant = receiptDate;
      form.purchase_date = '';
    } else {
      form.purchase_date = receiptDate;
      form.date_of_grant = '';
    }

    form.memo_number = item.memo_number || '';
    form.memo_file = null;
    form.memo_file_name = (item.memo_url || '').split('/').pop() || '';
    form.delete_memo = false;

    if (props.isConsumable) {
      form.location_id = item.location_id || '';
      form.unit_price = item.unitPrice || item.unit_price || '';
      form.burden = item.burden || 'Corporate';
      form.project_id = item.project_id || '';
      form.image_url_name = (item.imageUrl || item.image_url || '').split('/').pop() || '';
    } else {
      form.location_id = '';
      form.unit_price = '';
      form.burden = '';
      form.project_id = '';
      form.image_url_name = '';
    }
  } else {
    // Mass edit
    form.number = '';
    form.barang_id = '';
    form.model_id = '';
    form.entry_type = 'Procurement';
    form.po_number = '';
    form.purchase_date = '';
    form.date_of_grant = '';
    form.memo_number = '';
    form.memo_file = null;
    form.memo_file_name = '';
    form.delete_memo = false;
    form.organizer_id = '';
    form.vendor_id = '';
    form.location_id = '';
    form.unit_price = '';
    form.burden = props.isConsumable ? 'Tidak berubah' : '';
    form.project_id = '';
    form.image_url_name = '';
  }
}, { immediate: true });

const closeModal = () => {
  if (form.processing) return;
  resetModalState();
  emit('update:open', false);
};

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) {
    if (form.processing) {
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
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown, true);
});

const handleSubmit = () => {
  if (form.processing) return;
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (form.entry_type === 'Procurement') {
      if (!form.po_number || !String(form.po_number).trim()) {
        errors.value.po_number = t('inventory.poNumberRequired');
        isValid = false;
      }
      if (!form.purchase_date) {
        errors.value.purchase_date = t('inventory.purchaseDateRequired');
        isValid = false;
      }
      if (!isBeragamLot.value && !form.vendor_id) {
        errors.value.vendor_id = t('inventory.vendorRequired');
        isValid = false;
      }
    } else {
      // Hibah
      if (!form.memo_number || !String(form.memo_number).trim()) {
        errors.value.memo_number = t('inventory.memoNumberRequired');
        isValid = false;
      }
      if (!form.date_of_grant) {
        errors.value.date_of_grant = t('inventory.dateOfGrantRequired');
        isValid = false;
      }
    }

    if (!form.organizer_id) {
      errors.value.organizer_id = t('inventory.organizerRequired');
      isValid = false;
    }

    if (props.isConsumable) {
      if (!form.burden) {
        errors.value.burden = t('inventory.burdenRequired');
        isValid = false;
      } else if (form.burden === 'Project' && !form.project_id) {
        errors.value.project_id = t('inventory.projectRequired');
        isValid = false;
      }
      if (!form.location_id) {
        errors.value.location_id = t('inventory.locationRequired');
        isValid = false;
      }
    }

    if (!isValid) return;

    // Single edit - direct endpoint
    form.transform((data) => {
      const fd: any = {
        _method: 'PUT',
        number: data.number,
        barang_id: data.barang_id,
        model_id: data.model_id || data.barang_id,
        entry_type: data.entry_type,
        organizer_id: data.organizer_id,
        purchase_grant_date: data.entry_type === 'Procurement' ? data.purchase_date : data.date_of_grant,
        purchase_date: data.purchase_date,
        date_of_grant: data.date_of_grant,
        po_number: data.entry_type === 'Procurement' ? data.po_number : null,
        vendor_id: data.entry_type === 'Procurement' ? (data.vendor_id ? Number(data.vendor_id) : null) : null,
        memo_number: data.entry_type === 'Hibah' ? data.memo_number : null,
      };

      if (data.entry_type === 'Hibah') {
        if (data.memo_file) {
          fd.memo_file = data.memo_file;
        } else if (data.delete_memo) {
          fd.delete_memo = true;
        }
      }

      if (props.isConsumable) {
        fd.burden = data.burden;
        fd.project_id = data.burden === 'Project' ? data.project_id : null;
        fd.location_id = data.location_id ? Number(data.location_id) : null;
        fd.unit_price = data.unit_price || null;
        if (data.image_url) {
          fd.image_url = data.image_url;
        } else if (isImageDeleted.value) {
          fd.delete_image = true;
        }
      }

      return fd;
    }).post(`/smart/stock-management/lots/${form.ids[0]}`, {
      onSuccess: () => {
        resetModalState();
        closeModal();
        emit('success');
      },
      onError: (errs) => {
        Object.keys(errs).forEach((key) => {
          if (key in errors.value) {
            (errors.value as any)[key] = errs[key];
          }
        });
        toast.error(t('inventory.editLotFailed') || 'Failed to update lot');
      }
    });
  } else {
    // Bulk edit
    let isValid = true;
    if (props.isConsumable && form.burden === 'Project' && !form.project_id) {
      errors.value.project_id = t('inventory.projectRequired');
      isValid = false;
    }
    if (!isValid) return;

    const hasField = Boolean(
      form.organizer_id || form.vendor_id || form.purchase_date ||
      (props.isConsumable && (
        form.location_id || form.unit_price || form.image_url ||
        (form.burden && form.burden !== 'Tidak berubah')
      ))
    );
    if (!hasField) {
      toast.error(t('inventory.atLeastOneField'));
      return;
    }

    form.transform((data) => {
      const fd: any = { _method: 'PUT', ids: data.ids };
      if (data.organizer_id) fd.organizer_id = data.organizer_id;
      if (data.vendor_id) fd.vendor_id = data.vendor_id;
      if (data.purchase_date) {
        fd.purchase_grant_date = data.purchase_date;
        fd.purchase_date = data.purchase_date;
      }
      if (props.isConsumable) {
        if (data.location_id) fd.location_id = data.location_id;
        if (data.unit_price) fd.unit_price = data.unit_price;
        if (data.image_url) fd.image_url = data.image_url;
        if (data.burden && data.burden !== 'Tidak berubah') {
          fd.burden = data.burden;
          if (data.burden === 'Project') {
            fd.project_id = data.project_id;
          }
        }
      }
      return fd;
    }).post('/smart/stock-management/lots/bulk', {
      onSuccess: () => {
        resetModalState();
        closeModal();
        emit('success');
      },
      onError: () => {
        toast.error(t('inventory.bulkLotFailed') || 'Failed to bulk update lots');
      }
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
                {{ isSingle ? t('inventory.editLotDetail') : t('inventory.editLotSelected') }}
              </h3>
              <button @click="closeModal" :disabled="form.processing" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[80vh] overscroll-contain">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <!-- LOT Code Field (disabled in single, clear placeholder in bulk) -->
                  <Field>
                    <FieldLabel>{{ t('inventory.lotCode') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="text"
                        :value="isSingle && selectedItem ? (selectedItem.number || form.number) : t('inventory.cannotBeChangedBulk')"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <!-- Entry Type Selection (Single Edit only) -->
                  <Field v-if="isSingle" :data-invalid="!!errors.entry_type || undefined">
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
                            {{ form.entry_type === 'Procurement' ? t('inventory.procurement') : t('inventory.grant') }}
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

                  <!-- Procurement: PO Number (Single Edit only) -->
                  <Field v-if="isSingle && form.entry_type === 'Procurement'" :data-invalid="!!errors.po_number || undefined">
                    <FieldLabel for="edit-lot-po-number">
                      <span>{{ t('inventory.poNumber') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <input
                        id="edit-lot-po-number"
                        v-model="form.po_number"
                        type="text"
                        maxlength="255"
                        :placeholder="t('inventory.poNumberPlaceholder')"
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                        :class="[errors.po_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.po_number">{{ errors.po_number }}</FieldError>
                  </Field>

                  <!-- Date Input: Purchase Date in Procurement or in Mass Edit; Date of Grant in Hibah -->
                  <Field
                    v-if="!isSingle || form.entry_type === 'Procurement'"
                    :data-invalid="(isSingle && !!errors.purchase_date) || undefined"
                  >
                    <FieldLabel for="edit-lot-purchase-date">
                      <span>{{ t('inventory.purchaseDate') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <input
                        id="edit-lot-purchase-date"
                        v-model="form.purchase_date"
                        type="date"
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                        :class="[(isSingle && errors.purchase_date) ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.purchase_date">{{ errors.purchase_date }}</FieldError>
                  </Field>

                  <!-- Hibah: Memo Number, Date of Grant, Memo Document (Single Edit only) -->
                  <template v-else-if="isSingle && form.entry_type === 'Hibah'">
                    <Field :data-invalid="!!errors.memo_number || undefined">
                      <FieldLabel for="edit-lot-memo-number">
                        <span>{{ t('inventory.memoNumber') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <input
                          id="edit-lot-memo-number"
                          v-model="form.memo_number"
                          type="text"
                          maxlength="255"
                          placeholder="MEMO-001"
                          class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                          :class="[errors.memo_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.memo_number">{{ errors.memo_number }}</FieldError>
                    </Field>

                    <Field :data-invalid="!!errors.date_of_grant || undefined">
                      <FieldLabel for="edit-lot-date-of-grant">
                        <span>{{ t('inventory.dateOfGrant') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <input
                          id="edit-lot-date-of-grant"
                          v-model="form.date_of_grant"
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
                            <div
                              class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 truncate h-10 flex items-center"
                              :class="[
                                hasMemoFile
                                  ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                                  : 'text-muted-foreground cursor-default'
                              ]"
                              @click="hasMemoFile && viewMemoFileInNewTab()"
                            >
                              {{ form.memo_file_name || t('inventory.noFileSelected') }}
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
                              :disabled="!hasMemoFile"
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

                  <!-- Consumable (Single Edit): Total Stock & Unit Price -->
                  <template v-if="props.isConsumable && isSingle">
                    <Field>
                      <FieldLabel for="edit-lot-total-stock">
                        <span>{{ t('inventory.totalStock') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="flex w-full rounded-[14px] border border-input bg-muted/30 text-muted-foreground h-10 overflow-hidden">
                          <input
                            id="edit-lot-total-stock"
                            type="text"
                            :value="selectedItem?.initial_quantity ?? selectedItem?.initialQuantity ?? t('inventory.cannotBeChanged')"
                            disabled
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 cursor-not-allowed h-full text-muted-foreground"
                          />
                          <span v-if="lotUom" class="inline-flex items-center px-3 bg-muted/20 text-muted-foreground text-sm border-l border-input select-none font-medium">
                            {{ lotUom }}
                          </span>
                        </div>
                      </FieldContent>
                    </Field>

                    <Field>
                      <FieldLabel for="edit-lot-unit-price">
                        <span>{{ t('inventory.unitPrice') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input
                            id="edit-lot-unit-price"
                            type="number"
                            v-model="form.unit_price"
                            :placeholder="t('inventory.unitPricePlaceholder')"
                            min="0"
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          />
                        </div>
                      </FieldContent>
                    </Field>
                  </template>

                  <!-- Consumable (Mass Edit): Location & Unit Price to keep Left Column balanced (4 fields: LOT Code, Purchase Date, Location, Unit Price) -->
                  <template v-if="props.isConsumable && !isSingle">
                    <!-- Location -->
                    <Field :data-invalid="!!errors.location_id || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.location') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <LocationCombobox
                          v-model="form.location_id"
                          :locations="locations"
                          :placeholder="t('inventory.unchanged')"
                          :error="!!errors.location_id"
                          :active-only="true"
                          :clearable="true"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
                    </Field>

                    <!-- Unit Price -->
                    <Field>
                      <FieldLabel for="edit-lot-unit-price-bulk">
                        <span>{{ t('inventory.unitPrice') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input
                            id="edit-lot-unit-price-bulk"
                            type="number"
                            v-model="form.unit_price"
                            :placeholder="t('inventory.unchanged')"
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
                  <!-- Vendor (Only if Procurement or mass edit) -->
                  <Field
                    v-if="!isSingle || form.entry_type === 'Procurement'"
                    :data-invalid="(isSingle && !!errors.vendor_id) || undefined"
                  >
                    <FieldLabel for="edit-lot-vendor">
                      <span>{{ t('inventory.vendor') }}<span v-if="isSingle && !isBeragamLot" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox
                        id="edit-lot-vendor"
                        v-model="form.vendor_id"
                        :options="filteredVendors"
                        :disabled="isBeragamLot"
                        :search-placeholder="t('inventory.searchVendorPlaceholder')"
                        :default-label="isBeragamLot ? (t('inventory.vendorMultiple') || 'Beragam') : (isSingle ? t('inventory.selectVendor') : t('inventory.unchanged'))"
                        width-class="w-full h-10 px-4"
                        :error="isSingle && !!errors.vendor_id"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.vendor_id">{{ errors.vendor_id }}</FieldError>
                  </Field>

                  <!-- Organizer -->
                  <Field :data-invalid="(isSingle && !!errors.organizer_id) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.organizer') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant="outline"
                            :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.organizer_id ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.organizer_id) ? 'border-destructive' : '']"
                          >
                            {{ organizers.find(o => o.id == form.organizer_id)?.name || (isSingle ? t('inventory.selectOrganizer') : t('inventory.unchanged')) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-if="!isSingle" @select="form.organizer_id = ''">{{ t('inventory.unchanged') }}</DropdownMenuItem>
                          <DropdownMenuItem v-for="org in organizers" :key="org.id" @select="form.organizer_id = org.id">{{ org.name }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.organizer_id">{{ errors.organizer_id }}</FieldError>
                  </Field>

                  <!-- Consumable: Burden, Project, Location (Single only), Photo -->
                  <template v-if="props.isConsumable">
                    <!-- Burden (DropdownMenu) -->
                    <Field :data-invalid="(isSingle && !!errors.burden) || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.burden') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button
                              variant="outline"
                              class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                              :class="[(isSingle && errors.burden) ? 'border-destructive' : '']"
                            >
                              {{ form.burden === 'Project' ? t('inventory.project') : (form.burden === 'Corporate' ? t('inventory.corporate') : (!isSingle ? t('inventory.unchanged') : t('inventory.corporate'))) }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem v-if="!isSingle" @select="onSelectBurden('Tidak berubah')">{{ t('inventory.unchanged') }}</DropdownMenuItem>
                            <DropdownMenuItem @select="onSelectBurden('Corporate')">{{ t('inventory.corporate') }}</DropdownMenuItem>
                            <DropdownMenuItem @select="onSelectBurden('Project')">{{ t('inventory.project') }}</DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </FieldContent>
                      <FieldError v-if="isSingle && errors.burden">{{ errors.burden }}</FieldError>
                    </Field>

                    <!-- Project Combobox (if Burden = Project) -->
                    <Field v-if="form.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
                      <FieldLabel for="edit-lot-project">
                        <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
                      </FieldLabel>
                      <FieldContent>
                        <Combobox
                          id="edit-lot-project"
                          v-model="form.project_id"
                          :options="projectOptions"
                          :placeholder="t('inventory.selectProject')"
                          :search-placeholder="t('inventory.searchProjectPlaceholder')"
                          :default-label="t('inventory.selectProject')"
                          width-class="w-full h-10 px-4"
                          :error="!!errors.project_id"
                        />
                      </FieldContent>
                      <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
                    </Field>

                    <!-- Location (LocationCombobox with label "Location", Single Edit only) -->
                    <Field v-if="isSingle" :data-invalid="!!errors.location_id || undefined">
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

                    <!-- Photo (Label "Photo", NO Match Parent button) -->
                    <Field :data-invalid="(isSingle && !!errors.image_url) || undefined">
                      <FieldLabel>
                        <span>{{ t('inventory.photo') }}</span>
                      </FieldLabel>
                      <FieldContent>
                        <div class="space-y-1">
                          <div class="flex gap-2">
                            <div
                              class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                              :class="[
                                hasImage
                                  ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                                  : 'text-muted-foreground cursor-default',
                                (isSingle && errors.image_url) ? 'border-destructive' : 'border-input'
                              ]"
                              @click="hasImage && viewImageInNewTab()"
                            >
                              {{ form.image_url_name || (isSingle ? t('inventory.noPhotoSelected') : t('inventory.unchanged')) }}
                            </div>
                            <input
                              type="file"
                              id="edit-lot-photo-upload"
                              class="hidden"
                              accept=".jpg,.jpeg,.png,.webp"
                              @change="handleFileUpload"
                            />
                            <Button
                              type="button"
                              size="lg"
                              class="shrink-0"
                              @click="triggerFileInput"
                            >
                              {{ t('inventory.chooseFile') }}
                            </Button>
                            <Button
                              v-if="isSingle"
                              type="button"
                              variant="destructive"
                              size="icon"
                              class="w-9 shrink-0"
                              :disabled="!hasImage"
                              :title="t('inventory.deletePhoto')"
                              @click="handleDeletePhoto"
                            >
                              <Trash2 class="w-4 h-4" />
                            </Button>
                          </div>
                          <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize1Mb') }}</p>
                        </div>
                      </FieldContent>
                      <FieldError v-if="isSingle && errors.image_url">{{ errors.image_url }}</FieldError>
                    </Field>
                  </template>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ isSingle ? t('inventory.requiredMarker') : t('inventory.bulkEmptyMarker') }}
              </p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" :disabled="form.processing" variant="white" size="xl">{{ t('common.cancel') }}</Button>
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
