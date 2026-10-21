<script setup lang="ts">
/**
 * Input New Stock unified modal component replacing CreateModelModal.
 * Integrates cascading Model, LOT, and Asset configuration into an atomic scrollable form.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm } from '@inertiajs/vue3';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import ModelSection from './InputNewStock/ModelSection.vue';
import LotSection from './InputNewStock/LotSection.vue';
import AssetSection from './InputNewStock/AssetSection.vue';

interface Category {
  id: number;
  code: string;
  name: string;
}

interface TypeItem {
  id: number;
  code: string;
  name: string;
  category_id: number;
  is_consumable: boolean;
}

interface SimpleItem {
  id: number;
  name: string;
}

interface ModelItem {
  id: number;
  number?: string;
  code?: string;
  name: string;
  specification?: string | null;
  brand_id: number;
  type_id?: number;
  subcategory_id?: number;
  category_id?: number;
  is_consumable?: boolean;
}

interface ProjectItem {
  id_project: number;
  project_name: string;
  no_project?: string;
}

interface Props {
  open: boolean;
  categories: Category[];
  types?: TypeItem[];
  subcategories?: TypeItem[];
  brands: SimpleItem[];
  uoms: SimpleItem[];
  models?: ModelItem[];
  barangs?: ModelItem[];
  organizers?: SimpleItem[];
  vendors?: SimpleItem[];
  locations?: any[];
  projects?: ProjectItem[];
  lots?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  types: () => [],
  subcategories: () => [],
  brands: () => [],
  uoms: () => [],
  models: () => [],
  barangs: () => [],
  organizers: () => [],
  vendors: () => [],
  locations: () => [],
  projects: () => [],
  lots: () => [],
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const effectiveTypes = computed(() => {
  return props.types && props.types.length > 0 ? props.types : props.subcategories;
});

const effectiveModels = computed(() => {
  return props.models && props.models.length > 0 ? props.models : props.barangs;
});

const form = useForm({
  category_id: null as number | null,
  type_id: null as number | null,
  brand_id: null as number | null,
  model_id: null as number | null,
  create_new_type: false,
  model_name: '',
  model_specification: '',
  uom_id: null as number | null,
  min_stock_threshold: null as number | string | null,

  input_lot: false,
  lot_id: null as number | null,
  create_new_lot: false,
  entry_type: 'Procurement' as 'Procurement' | 'Hibah',
  po_number: '',
  purchase_date: '',
  memo_number: '',
  date_of_grant: '',
  memo_file: null as File | null,
  memo_file_name: '',
  organizer_id: null as number | null,
  vendor_id: null as number | null,
  burden: 'Corporate' as 'Corporate' | 'Project',
  project_id: null as number | null,
  initial_quantity: '' as number | string,
  unit_price: '' as number | string,
  lot_photo: null as File | null,
  lot_photo_name: '',
  use_parent_image: false,

  input_asset: false,
  asset_specification: '',
  borrowing_type: 'LT' as 'LT' | 'ST',
  condition: 'Bagus' as 'Bagus' | 'Rusak',
  location_id: null as number | null,
  price: '' as string | number,
  classification: 'Aset' as 'Aset' | 'Inventaris',
  photo: null as File | null,
  photo_name: '',
  plate_number: '',
  is_bulk: false,
  bulk_quantity: '' as string | number,
});

const errors = ref<Record<string, string>>({});

const resetErrors = () => {
  errors.value = {};
};

const selectedCategory = computed(() => {
  return props.categories.find(c => c.id === form.category_id);
});

const isComp = computed(() => {
  if (!selectedCategory.value) return false;
  return selectedCategory.value.code === 'COMP' || selectedCategory.value.name?.toLowerCase() === 'computer';
});

const isKend = computed(() => {
  return selectedCategory.value?.code === 'KEND';
});

const selectedType = computed(() => {
  if (form.type_id === null || form.type_id === undefined) return undefined;
  return effectiveTypes.value.find(t => t.id == form.type_id);
});

const selectedModel = computed(() => {
  if (form.model_id === null || form.model_id === undefined) return undefined;
  return effectiveModels.value.find(m => m.id == form.model_id);
});

const isConsumable = computed(() => {
  if (selectedType.value) {
    return Boolean(selectedType.value.is_consumable);
  }
  if (selectedModel.value) {
    const m = selectedModel.value as any;
    return Boolean(m.is_consumable ?? m.type?.is_consumable);
  }
  if (form.type_id !== null && form.type_id !== undefined) {
    const t = effectiveTypes.value.find(x => x.id == form.type_id);
    if (t) return Boolean(t.is_consumable);
  }
  if (form.category_id !== null && form.category_id !== undefined) {
    const typesInCat = effectiveTypes.value.filter(t => t.category_id == form.category_id);
    if (typesInCat.length > 0 && typesInCat.every(t => Boolean(t.is_consumable))) {
      return true;
    }
  }
  if (effectiveTypes.value.length > 0 && effectiveTypes.value.every(t => Boolean(t.is_consumable))) {
    return true;
  }
  return false;
});

const activeUomName = computed(() => {
  if (form.create_new_type) {
    return props.uoms.find(u => u.id === form.uom_id)?.name || '';
  }
  if (selectedModel.value) {
    const m = selectedModel.value as any;
    if (m.uom) return m.uom;
    if (m.uom_name) return m.uom_name;
    if (m.uom_id) return props.uoms.find(u => u.id === m.uom_id)?.name || '';
  }
  return '';
});

const parentImageUrl = computed(() => {
  if (!form.create_new_type && selectedModel.value) {
    return (selectedModel.value as any).image_url || null;
  }
  return null;
});

const lotReady = computed(() => {
  return form.input_lot && !isConsumable.value && (Boolean(form.lot_id) || form.create_new_lot);
});

const resetFormState = () => {
  form.reset();
  form.clearErrors();
  resetErrors();
  form.memo_file = null;
  form.memo_file_name = '';
  form.photo = null;
  form.photo_name = '';
  form.is_bulk = false;
  form.bulk_quantity = '';
  form.vendor_id = null;
  form.initial_quantity = '';
  form.unit_price = '';
  form.lot_photo = null;
  form.lot_photo_name = '';
  form.use_parent_image = false;
};

const closeModal = () => {
  emit('update:open', false);
  resetFormState();
};

const openModal = () => {
  resetFormState();
  emit('update:open', true);
};

watch(() => form.initial_quantity, (v) => {
  if (v !== '' && errors.value.initial_quantity) {
    delete errors.value.initial_quantity;
  }
});

watch(() => form.bulk_quantity, (v) => {
  if (v !== '' && errors.value.bulk_quantity) {
    delete errors.value.bulk_quantity;
  }
});

watch(isKend, (val) => {
  if (val) {
    form.is_bulk = false;
    form.bulk_quantity = '';
    if (errors.value.bulk_quantity) {
      delete errors.value.bulk_quantity;
    }
  }
});

watch(() => form.entry_type, (type) => {
  if (type === 'Hibah') {
    form.vendor_id = null;
    if (errors.value.vendor_id) {
      delete errors.value.vendor_id;
    }
  }
});

watch(() => form.vendor_id, (val) => {
  if (val && errors.value.vendor_id) {
    delete errors.value.vendor_id;
  }
});

watch(isConsumable, (val) => {
  if (val) {
    form.input_asset = false;
    form.asset_specification = '';
    form.photo = null;
    form.photo_name = '';
    form.plate_number = '';
    form.is_bulk = false;
    form.bulk_quantity = '';
  }
}, { immediate: true });

watch(() => props.open, (val) => {
  if (val) {
    openModal();
  } else {
    resetFormState();
  }
});

const handleSubmit = () => {
  if (form.processing) return;
  resetErrors();
  let isValid = true;

  if (!form.category_id) { errors.value.category_id = t('inventory.categoryRequired'); isValid = false; }
  if (!form.type_id) { errors.value.type_id = t('inventory.typeRequired'); isValid = false; }
  if (!form.brand_id) { errors.value.brand_id = t('inventory.brandRequired'); isValid = false; }

  if (!form.create_new_type) {
    if (!form.model_id) { errors.value.model_id = t('inventory.modelNameRequired'); isValid = false; }
  } else {
    if (!form.model_name) { errors.value.model_name = t('inventory.modelNameRequired'); isValid = false; }
    if (!form.uom_id) { errors.value.uom_id = t('inventory.uomRequired'); isValid = false; }
    if (!isComp.value && !form.model_specification) {
      errors.value.model_specification = t('inventory.requiredMarker');
      isValid = false;
    }
    if (isConsumable.value) {
      if (form.min_stock_threshold === null || form.min_stock_threshold === '' || form.min_stock_threshold === undefined) {
        errors.value.min_stock_threshold = t('inventory.minStockThresholdRequired');
        isValid = false;
      } else {
        const thresholdNum = Number(form.min_stock_threshold);
        if (isNaN(thresholdNum) || !Number.isInteger(thresholdNum) || thresholdNum < 0) {
          errors.value.min_stock_threshold = t('inventory.thresholdInteger');
          isValid = false;
        }
      }
    }
  }

  if (isConsumable.value) {
    form.input_asset = false;
  }

  if (form.input_lot) {
    if (!form.create_new_lot) {
      if (!form.lot_id) { errors.value.lot_id = t('inventory.lotNumberRequired'); isValid = false; }
    } else {
      if (isConsumable.value) {
        const qtyVal = form.initial_quantity;
        if (qtyVal === '' || qtyVal === null || qtyVal === undefined) {
          errors.value.initial_quantity = t('inventory.stockQtyRequired');
          isValid = false;
        } else {
          const qty = Number(qtyVal);
          if (isNaN(qty) || !Number.isInteger(qty) || qty < 0) {
            errors.value.initial_quantity = t('inventory.stockQtyRequired');
            isValid = false;
          }
        }
        if (!form.location_id) {
          errors.value.location_id = t('inventory.locationRequired');
          isValid = false;
        }
      }

      if (!form.organizer_id) { errors.value.organizer_id = t('inventory.organizerRequired'); isValid = false; }
      if (isConsumable.value) {
        if (!form.burden) { errors.value.burden = t('inventory.burdenRequired'); isValid = false; }
        if (form.burden === 'Project' && !form.project_id) {
          errors.value.project_id = t('inventory.projectRequired');
          isValid = false;
        }
      }

      if (form.entry_type === 'Procurement') {
        if (!form.vendor_id) { errors.value.vendor_id = t('inventory.vendorRequired'); isValid = false; }
        if (!form.po_number) { errors.value.po_number = t('inventory.poNumberRequired'); isValid = false; }
        if (!form.purchase_date) { errors.value.purchase_date = t('inventory.purchaseDateRequired'); isValid = false; }
      } else if (form.entry_type === 'Hibah') {
        if (!form.memo_number) { errors.value.memo_number = t('inventory.memoNumberRequired'); isValid = false; }
        if (!form.date_of_grant) { errors.value.date_of_grant = t('inventory.dateOfGrantRequired'); isValid = false; }
      }
    }

    if (!isConsumable.value && form.input_asset) {
      if (isComp.value && !form.asset_specification) {
        errors.value.asset_specification = t('inventory.requiredMarker');
        isValid = false;
      }
      if (!form.borrowing_type) { errors.value.borrowing_type = t('inventory.borrowingTypeRequired'); isValid = false; }
      if (!form.condition) { errors.value.condition = t('inventory.conditionRequired'); isValid = false; }
      if (!form.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
      if (!form.classification) { errors.value.classification = t('inventory.classificationRequired'); isValid = false; }
      if (!form.burden) { errors.value.burden = t('inventory.burdenRequired'); isValid = false; }
      if (form.burden === 'Project' && !form.project_id) {
        errors.value.project_id = t('inventory.projectRequired');
        isValid = false;
      }
      if (isKend.value && !form.plate_number) {
        errors.value.plate_number = t('inventory.plateNumberRequired');
        isValid = false;
      }
      if (!isKend.value && form.is_bulk) {
        const bulkQty = form.bulk_quantity;
        if (bulkQty === '' || bulkQty === null || bulkQty === undefined) {
          errors.value.bulk_quantity = t('inventory.assetCountRequired');
          isValid = false;
        } else {
          const qty = Number(bulkQty);
          if (isNaN(qty) || !Number.isInteger(qty) || qty < 1) {
            errors.value.bulk_quantity = t('inventory.assetCountRequired');
            isValid = false;
          }
        }
      }
    }
  }

  if (!isValid) return;

  form.post('/smart/stock-management/new-stock', {
    onSuccess: () => {
      resetFormState();
      closeModal();
      emit('success');
    },
    onError: (backendErrors: Record<string, string>) => {
      Object.assign(errors.value, backendErrors);
    },
  });
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
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain"
        @click="closeModal"
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
            v-if="open"
            class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">{{ t('inventory.inputNewStock') }}</h3>
              <button @click="closeModal" :disabled="form.processing" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6">
              <!-- Section 1: Model Selection & Creation -->
              <ModelSection
                :form="form"
                :errors="errors"
                :categories="categories"
                :types="effectiveTypes"
                :brands="brands"
                :uoms="uoms"
                :models="effectiveModels"
              />

              <!-- Section 2: LOT Selection & Creation -->
              <LotSection
                :form="form"
                :errors="errors"
                :lots="lots"
                :organizers="organizers"
                :vendors="props.vendors"
                :locations="locations"
                :projects="projects"
                :active-model-id="form.model_id"
                :is-consumable="isConsumable"
                :uom-name="activeUomName"
                :parent-image-url="parentImageUrl"
              />

              <!-- Section 3: Asset Details (Non-Consumable Only) -->
              <AssetSection
                v-if="!isConsumable"
                :form="form"
                :errors="errors"
                :locations="locations"
                :projects="projects"
                :is-comp="isComp"
                :is-kend="isKend"
                :lot-ready="lotReady"
                :is-consumable="isConsumable"
              />
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between shrink-0">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('inventory.requiredMarker') }}</p>
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
                    {{ t('inventory.inputNewStock') }}
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
