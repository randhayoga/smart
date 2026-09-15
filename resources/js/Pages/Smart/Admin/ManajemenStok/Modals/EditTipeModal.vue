<script setup lang="ts">
/**
 * Edit Tipe Modal component for updating item definitions, UOM, brand, specifications, and default images.
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

const isSingle = computed(() => props.items.length === 1);
const selectedItem = computed(() => isSingle.value ? props.items[0] : null);

const isConsumable = computed(() => {
  if (!selectedItem.value) return false;
  return Boolean(
    selectedItem.value.is_consumable ??
    selectedItem.value.subcategory?.category?.is_consumable ??
    false
  );
});

const initialStock = computed(() => {
  if (!isSingle.value || !selectedItem.value) return 0;
  if (props.lots && props.lots.length > 0) {
    const itemLots = props.lots.filter(l => String(l.barang_id) === String(selectedItem.value.id));
    if (itemLots.length > 0) {
      return itemLots.reduce((sum, l) => sum + Number(l.initial_quantity || 0), 0);
    }
  }
  return Number(selectedItem.value.initial_stock ?? 0);
});

const currentAvailableStock = computed(() => {
  if (!isSingle.value || !selectedItem.value) return 0;
  if (props.lots && props.lots.length > 0) {
    const itemLots = props.lots.filter(l => String(l.barang_id) === String(selectedItem.value.id));
    if (itemLots.length > 0) {
      return itemLots.reduce((sum, l) => sum + Number(l.current_quantity ?? l.availableAssetCount ?? 0), 0);
    }
  }
  return Number(selectedItem.value.available_stock ?? selectedItem.value.amount ?? 0);
});

const form = useForm({
  ids: [] as number[],
  uom_id: null as number | null,
  brand_id: null as number | null,
  name: '',
  specification: '',
  photo: null as File | null,
  photoName: '',
  available_stock: null as number | null,
});

const errors = ref({
  uom_id: '',
  brand_id: '',
  name: '',
  available_stock: '',
});

const resetErrors = () => {
  errors.value = { uom_id: '', brand_id: '', name: '', available_stock: '' };
};

// Reactive error clearing
watch(() => form.uom_id, v => { if (v && errors.value.uom_id) errors.value.uom_id = ''; });
watch(() => form.brand_id, v => { if (v && errors.value.brand_id) errors.value.brand_id = ''; });
watch(() => form.name, v => { if (v && errors.value.name) errors.value.name = ''; });
watch(() => form.available_stock, v => { if (v !== null && errors.value.available_stock) errors.value.available_stock = ''; });

// Initialize form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  form.ids = props.items.map(r => r.id);

  // Explicitly reset
  form.uom_id = null;
  form.brand_id = null;
  form.name = '';
  form.specification = '';
  form.photo = null;
  form.photoName = '';
  form.available_stock = null;

  if (isSingle.value && selectedItem.value) {
    form.uom_id = selectedItem.value.uom_id;
    form.brand_id = selectedItem.value.brand_id;
    form.name = selectedItem.value.name;
    form.specification = selectedItem.value.specification;
    form.photo = null;
    form.photoName = selectedItem.value.image_url ? selectedItem.value.image_url.split('/').pop() : '';
    form.available_stock = currentAvailableStock.value;
  }
});

const closeModal = () => {
  emit('update:open', false);
  form.ids = [];
  form.uom_id = null;
  form.brand_id = null;
  form.name = '';
  form.specification = '';
  form.photo = null;
  form.photoName = '';
  form.available_stock = null;
  form.clearErrors();
  resetErrors();
};

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { alert(t('inventory.invalidFileFormat')); return; }
  if (file.size > 1024 * 1024) { alert(t('inventory.fileTooLarge1Mb')); return; }
  form.photo = file;
  form.photoName = file.name;
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-tipe-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.photo) {
    const url = URL.createObjectURL(form.photo);
    window.open(url, '_blank');
  } else if (selectedItem.value && selectedItem.value.image_url) {
    window.open('/media/' + selectedItem.value.image_url, '_blank');
  }
};

const handleSubmit = () => {
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (!form.uom_id) { errors.value.uom_id = t('inventory.uomRequired'); isValid = false; }
    if (!form.brand_id) { errors.value.brand_id = t('inventory.brandRequired'); isValid = false; }
    if (!form.name) { errors.value.name = t('inventory.nameRequired'); isValid = false; }

    if (isConsumable.value && form.available_stock !== null && form.available_stock !== undefined) {
      const parsedStock = Number(form.available_stock);
      if (isNaN(parsedStock) || parsedStock < 0) {
        errors.value.available_stock = t('inventory.availableStockMin0') || 'Stok tidak boleh kurang dari 0.';
        isValid = false;
      } else if (parsedStock > currentAvailableStock.value) {
        errors.value.available_stock = `Stok hanya dapat dikurangi (maksimal ${currentAvailableStock.value}).`;
        isValid = false;
      }
    }

    if (!isValid) return;
  } else {
    const hasAtLeastOneField = !!(
      form.uom_id || form.brand_id || form.name || form.specification || form.photo
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
      formData.specification = data.specification;
      if (data.photo) formData.image_url = data.photo;
      if (isConsumable.value && data.available_stock !== null && data.available_stock !== undefined && Number(data.available_stock) !== currentAvailableStock.value) {
        formData.available_stock = Number(data.available_stock);
      }
    } else {
      if (data.uom_id) formData.uom_id = data.uom_id;
      if (data.brand_id) formData.brand_id = data.brand_id;
      if (data.name) formData.name = data.name;
      if (data.specification) formData.specification = data.specification;
      if (data.photo) formData.image_url = data.photo;
    }
    return formData;
  }).post(isSingle.value && selectedItem.value ? `/smart/inventory/barangs/${selectedItem.value.id}` : '/smart/inventory/barangs/bulk', {
    onSuccess: () => {
      closeModal();
      emit('success');
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
            class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? t('inventory.editTypeDetail') : t('inventory.editTypeSelected') }}
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
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
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
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

                  <Field :data-invalid="(isSingle && !!errors.name) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.typeName') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        v-model="form.name"
                        maxlength="255"
                        :placeholder="isSingle ? t('inventory.typeNamePlaceholder') : t('inventory.unchanged')" 
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10"
                        :class="[isSingle && errors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.name">{{ errors.name }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>{{ t('inventory.specification') }}</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        v-model="form.specification"
                        maxlength="255"
                        :placeholder="isSingle ? t('inventory.specificationPlaceholder') : t('inventory.unchanged')" 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field>
                    <FieldLabel>{{ t('inventory.defaultPhoto') }}</FieldLabel>
                    <FieldContent>
                      <div class="flex flex-col gap-1 w-full">
                        <div class="flex gap-2 w-full">
                          <div 
                            class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                            :class="[
                              (form.photo || (selectedItem && selectedItem.image_url)) 
                                ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                                : 'text-muted-foreground cursor-default'
                            ]"
                            @click="(form.photo || (selectedItem && selectedItem.image_url)) && viewImageInNewTab()"
                          >
                            {{ form.photoName || (isSingle ? t('inventory.noPhotoSelected') : t('inventory.unchanged')) }}
                          </div>
                          <input 
                            type="file" 
                            id="edit-tipe-photo-upload" 
                            class="hidden" 
                            accept=".jpg,.jpeg,.png"
                            @change="handleFileUpload"
                          />
                          <Button 
                            @click="triggerFileInput"
                            size="lg"
                          >
                            {{ t('inventory.chooseFile') }}
                          </Button>
                        </div>
                        <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize1Mb') }}</p>
                      </div>
                    </FieldContent>
                  </Field>

                  <!-- Stock input (Disabled in Edit) -->
                  <Field data-disabled>
                    <FieldLabel><span>{{ t('inventory.stockCount') }}</span></FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="isSingle && selectedItem ? initialStock : t('inventory.cannotBeChanged')" 
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <!-- Available stock input (Stok tersedia) -->
                  <Field :data-invalid="(isSingle && !!errors.available_stock) || undefined" :data-disabled="(!isSingle || !isConsumable) || undefined">
                    <FieldLabel><span>{{ t('inventory.availableStock') }}<span v-if="isSingle && isConsumable" class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input 
                        type="number" 
                        v-model="form.available_stock" 
                        :disabled="!isSingle || !isConsumable" 
                        :placeholder="!isSingle ? t('inventory.cannotBeChangedBulk') : t('inventory.availableStockPlaceholder')" 
                        min="0" 
                        :max="currentAvailableStock"
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                        :class="[isSingle && errors.available_stock ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.available_stock">{{ errors.available_stock }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ isSingle ? t('inventory.requiredMarker') : t('inventory.bulkEmptyMarker') }}
              </p>
              <div class="flex items-center gap-3">
                <Button 
                  @click="closeModal"
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
