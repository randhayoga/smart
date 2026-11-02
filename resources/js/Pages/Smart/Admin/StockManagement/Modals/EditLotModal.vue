<script setup lang="ts">
/**
 * Edit LOT Modal component supporting single LOT updates ("Edit LOT Details")
 * and bulk LOT updates ("Edit Selected LOTs").
 * Componentized into LotGeneralSection and LotConsumableSection (<500 LOC).
 */
import { ref, watch, computed, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import LotGeneralSection from './EditLot/LotGeneralSection.vue';
import LotConsumableSection from './EditLot/LotConsumableSection.vue';

interface Props {
  open: boolean;
  items: any[];
  isConsumable: boolean;
  parentImageUrl?: string | null;
  organizers: { id: number; name: string }[];
  vendors: { id: number; name: string }[];
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

// Gated computeds for performance optimization
const isSingle = computed(() => {
  if (!props.open) return true;
  return props.items.length === 1;
});

const selectedItem = computed(() => {
  if (!props.open) return null;
  return isSingle.value && props.items.length > 0 ? props.items[0] : null;
});

const lotUom = computed(() => {
  if (!props.open) return '';
  return props.uom || selectedItem.value?.barang_uom || selectedItem.value?.uom || '';
});

const isBeragamLot = computed(() => {
  if (!props.open || !isSingle.value || !selectedItem.value) return false;
  const item = selectedItem.value;
  const vendorName = String(item.vendor || item.vendor_name || '').trim().toLowerCase();
  if (vendorName === 'beragam') return true;

  const currentVendor = (props.vendors || []).find(v => v.id == item.vendor_id || v.id == item.legacy_vendor_id);
  if (currentVendor && currentVendor.name?.trim().toLowerCase() === 'beragam') {
    return true;
  }
  return false;
});

const isImageDeleted = ref(false);

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

    // Single edit
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
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
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
            class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? t('inventory.editLotDetail') : t('inventory.editLotSelected') }}
              </h3>
              <button @click="closeModal" :disabled="form.processing" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6">
              <!-- General LOT Section -->
              <LotGeneralSection
                :form="form"
                :errors="errors"
                :is-single="isSingle"
                :organizers="organizers"
                :vendors="vendors"
                :is-beragam-lot="isBeragamLot"
                :selected-item="selectedItem"
              />

              <!-- Consumable LOT Section -->
              <LotConsumableSection
                v-if="isConsumable"
                :form="form"
                :errors="errors"
                :is-single="isSingle"
                :selected-item="selectedItem"
                :locations="locations"
                :projects="projects"
                :lot-uom="lotUom"
                v-model:is-image-deleted="isImageDeleted"
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
                  :disabled="form.processing"
                  variant="white"
                  size="xl"
                >
                  {{ t('common.cancel') }}
                </Button>
                <Button
                  @click="handleSubmit"
                  :disabled="form.processing"
                  variant="primary"
                  size="xl"
                  class="relative"
                >
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
