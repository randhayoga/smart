<script setup lang="ts">
/**
 * Edit Model Modal component for updating catalog model definitions, UOM, brand, specifications, and min stock threshold.
 * Matches InputNewStock ModelSection.vue styling, fields, and performance standards.
 */
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface SimpleItem { id: number; name: string; }

interface Props {
  open: boolean;
  items: any[];
  uoms: SimpleItem[];
  brands: SimpleItem[];
  lots?: any[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

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

const isCompCategory = computed(() => {
  if (!props.open || !selectedItem.value) return false;
  const code = selectedItem.value.category_code || selectedItem.value.barang_category_code;
  const cat = selectedItem.value.category || selectedItem.value.barang_category;
  return code === 'COMP' || cat === 'Computer' || cat === 'COMP';
});

const hasCompCategoryInItems = computed(() => {
  if (!props.open || !props.items || props.items.length === 0) return false;
  return props.items.some(item => {
    const code = item.category_code || item.barang_category_code;
    const cat = item.category || item.barang_category;
    return code === 'COMP' || cat === 'Computer' || cat === 'COMP';
  });
});

const isConsumable = computed(() => {
  if (!props.open) return false;
  if (isSingle.value) {
    if (!selectedItem.value) return false;
    return Boolean(
      selectedItem.value.is_consumable ??
      selectedItem.value.subcategory?.is_consumable ??
      false
    );
  }
  return props.items.every(item => Boolean(
    item.is_consumable ??
    item.subcategory?.is_consumable ??
    false
  ));
});

const form = useForm({
  ids: [] as number[],
  uom_id: null as number | null,
  brand_id: null as number | null,
  name: '',
  specification: '',
  min_stock_threshold: null as number | string | null,
});

const errors = ref({
  uom_id: '',
  brand_id: '',
  name: '',
  specification: '',
  min_stock_threshold: '',
});

const resetErrors = () => {
  errors.value = {
    uom_id: '',
    brand_id: '',
    name: '',
    specification: '',
    min_stock_threshold: '',
  };
};

// Reactive error clearing
watch(() => form.uom_id, v => { if (v && errors.value.uom_id) errors.value.uom_id = ''; });
watch(() => form.brand_id, v => { if (v && errors.value.brand_id) errors.value.brand_id = ''; });
watch(() => form.name, v => { if (v && errors.value.name) errors.value.name = ''; });
watch(() => form.min_stock_threshold, v => { if (v !== null && errors.value.min_stock_threshold) errors.value.min_stock_threshold = ''; });

const resetModalState = () => {
  form.reset();
  form.clearErrors();
  resetErrors();
  form.ids = [];
  form.uom_id = null;
  form.brand_id = null;
  form.name = '';
  form.specification = '';
  form.min_stock_threshold = null;
};

// Initialize form when modal opens
watch(() => props.open, (val) => {
  if (!val) {
    resetModalState();
    return;
  }
  resetModalState();
  form.ids = props.items.map(r => r.id);

  if (isSingle.value && selectedItem.value) {
    form.uom_id = selectedItem.value.uom_id;
    form.brand_id = selectedItem.value.brand_id;
    form.name = selectedItem.value.name;
    form.specification = isCompCategory.value ? '' : (selectedItem.value.specification || '');
    form.min_stock_threshold = selectedItem.value.min_stock_threshold ?? null;
  }
});

const closeModal = () => {
  emit('update:open', false);
  resetModalState();
};

const handleSubmit = () => {
  if (form.processing) return;
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (!form.uom_id) { errors.value.uom_id = t('inventory.uomRequired'); isValid = false; }
    if (!form.brand_id) { errors.value.brand_id = t('inventory.brandRequired'); isValid = false; }
    if (!form.name) { errors.value.name = t('inventory.nameRequired'); isValid = false; }

    if (isConsumable.value && form.min_stock_threshold !== null && form.min_stock_threshold !== '') {
      const thresholdNum = Number(form.min_stock_threshold);
      if (isNaN(thresholdNum) || !Number.isInteger(thresholdNum) || thresholdNum < 0) {
        errors.value.min_stock_threshold = t('inventory.thresholdInteger');
        isValid = false;
      }
    }

    if (!isValid) {
      toast.error(t('common.modals.formInvalid'));
      return;
    }
  } else {
    const hasAtLeastOneField = !!(
      form.uom_id || form.brand_id || form.name || (!hasCompCategoryInItems.value && form.specification) || (form.min_stock_threshold !== null && form.min_stock_threshold !== '')
    );
    if (!hasAtLeastOneField) {
      toast.error(t('inventory.atLeastOneField'));
      return;
    }
  }

  form.transform((data) => {
    const formData: any = {
      _method: 'PUT',
      ids: data.ids,
    };
    if (isSingle.value) {
      formData.number = selectedItem.value ? selectedItem.value.code : '';
      formData.subcategory_id = selectedItem.value ? selectedItem.value.subcategory_id : null;
      formData.uom_id = data.uom_id;
      formData.brand_id = data.brand_id;
      formData.name = data.name;
      formData.specification = isCompCategory.value ? null : data.specification;
      if (isConsumable.value && data.min_stock_threshold !== null && data.min_stock_threshold !== '') {
        formData.min_stock_threshold = data.min_stock_threshold;
      }
    } else {
      if (data.uom_id) formData.uom_id = data.uom_id;
      if (data.brand_id) formData.brand_id = data.brand_id;
      if (data.name) formData.name = data.name;
      if (data.specification && !hasCompCategoryInItems.value) formData.specification = data.specification;
      if (isConsumable.value && data.min_stock_threshold !== null && data.min_stock_threshold !== '') {
        formData.min_stock_threshold = data.min_stock_threshold;
      }
    }
    return formData;
  }).post(isSingle.value && selectedItem.value ? `/smart/stock-management/models/${selectedItem.value.id}` : '/smart/stock-management/models/bulk', {
    onSuccess: () => {
      resetModalState();
      closeModal();
      emit('success');
    },
    onError: (backendErrors: Record<string, string>) => {
      Object.assign(errors.value, backendErrors);
      toast.error(t('common.modals.formInvalid'));
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
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? t('inventory.editTypeDetail') : t('inventory.editTypeSelected') }}
              </h3>
              <button @click="closeModal" :disabled="form.processing" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column: Master Reference Properties & Brand -->
                <div class="space-y-6">
                  <Field data-disabled>
                    <FieldLabel>{{ t('inventory.typeCode') }}</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="selectedItem ? selectedItem.code : t('inventory.cannotBeChanged')"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field data-disabled>
                    <FieldLabel>{{ t('inventory.category') }}</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="selectedItem ? selectedItem.category : t('inventory.cannotBeChanged')"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field data-disabled>
                    <FieldLabel>{{ t('inventory.subcategory') }}</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="selectedItem ? selectedItem.subcategory : t('inventory.cannotBeChanged')"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <!-- Brand -->
                  <Field :data-invalid="(isSingle && !!errors.brand_id) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.brand') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="form.brand_id"
                        :options="brands"
                        :search-placeholder="t('inventory.searchBrandPlaceholder')"
                        :default-label="isSingle ? t('inventory.selectBrand') : t('inventory.unchanged')"
                        width-class="w-full h-10 px-4"
                        :error="isSingle && !!errors.brand_id"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.brand_id">{{ errors.brand_id }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column: Model Name, UoM, Specification, and Min Stock Threshold -->
                <div class="space-y-6">
                  <!-- Model Name -->
                  <Field :data-invalid="(isSingle && !!errors.name) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.modelName') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        v-model="form.name"
                        maxlength="255"
                        :placeholder="isSingle ? t('inventory.modelNamePlaceholder') : t('inventory.unchanged')" 
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10"
                        :class="[isSingle && errors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.name">{{ errors.name }}</FieldError>
                  </Field>

                  <!-- UoM -->
                  <Field :data-invalid="(isSingle && !!errors.uom_id) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.uom') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="form.uom_id"
                        :options="uoms"
                        :search-placeholder="t('inventory.searchUomPlaceholder')"
                        :default-label="isSingle ? t('inventory.selectUom') : t('inventory.unchanged')"
                        width-class="w-full h-10 px-4"
                        :error="isSingle && !!errors.uom_id"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.uom_id">{{ errors.uom_id }}</FieldError>
                  </Field>

                  <!-- Specification -->
                  <Field v-if="!isSingle && hasCompCategoryInItems" data-disabled>
                    <FieldLabel>{{ t('inventory.specification') }}</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="t('inventory.cannotBeChanged')"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field v-else-if="!isSingle || !isCompCategory">
                    <FieldLabel>{{ t('inventory.specification') }}</FieldLabel>
                    <FieldContent>
                      <textarea 
                        v-model="form.specification"
                        rows="3"
                        maxlength="1000"
                        :placeholder="isSingle ? t('inventory.specificationPlaceholder') : t('inventory.unchanged')" 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
                      />
                    </FieldContent>
                  </Field>

                  <!-- Minimum Stock Threshold (Consumables only) -->
                  <Field v-if="isConsumable" :data-invalid="!!errors.min_stock_threshold || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.minStockThreshold') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <input 
                        type="number"
                        min="0"
                        step="1"
                        v-model="form.min_stock_threshold"
                        :placeholder="isSingle ? '0' : t('inventory.unchanged')"
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                        :class="[errors.min_stock_threshold ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.min_stock_threshold">{{ errors.min_stock_threshold }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
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
