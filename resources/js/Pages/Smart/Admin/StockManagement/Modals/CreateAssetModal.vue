<script setup lang="ts">
/**
 * Create Asset Modal component for registering new asset units (single or bulk batch creation) with photo compression.
 */
import { ref, watch, computed } from 'vue';
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
  projects?: { id?: number; id_project?: number; no_project: string; project_name: string; client_id?: string }[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const isVehicle = computed(() => {
  return props.barang?.category_code === 'KEND' ||
    props.barang?.category === 'Kendaraan' ||
    props.barang?.category === 'KEND' ||
    props.lot?.barang_category_code === 'KEND';
});

const isComp = computed(() => {
  return props.barang?.category_code === 'COMP' ||
    props.barang?.category === 'Computer' ||
    props.barang?.category === 'COMP' ||
    props.lot?.barang_category_code === 'COMP';
});

const projectOptions = computed(() => {
  return (props.projects || [])
    .filter(p => Boolean(p.no_project?.trim() && p.project_name?.trim()))
    .map(p => ({
      id: (p.id ?? p.id_project) as number,
      name: `[${p.no_project}] ${p.project_name}`,
    }));
});

const getConditionLabel = (c: string) => {
  if (!c) return t('inventory.selectCondition');
  const map: Record<string, string> = {
    'Bagus': t('inventory.conditionGood'),
    'Rusak': t('inventory.conditionDamaged'),
  };
  return map[c] || c;
};

const getClassificationLabel = (c: string) => {
  if (!c) return '';
  if (c === 'Aset') return t('inventory.classificationAsset');
  if (c === 'Inventaris') return t('inventory.classificationInventory');
  return c;
};

const form = useForm({
  _method: 'POST',
  lot_id: props.lot?.id,
  location_id: '' as string | number,
  status: 'Belum Diverifikasi',
  condition: '',
  type: '',
  classification: '',
  burden: 'Corporate' as 'Corporate' | 'Project',
  project_id: '' as string | number,
  price: '' as string | number,
  specification: '',
  image_url: null as File | null,
  image_url_name: '',
  is_bulk: false,
  bulk_quantity: '' as string | number,
  vehicle_registration: '',
});

const errors = ref({
  specification: '',
  location_id: '',
  condition: '',
  type: '',
  classification: '',
  burden: '',
  project_id: '',
  image_url: '',
  vehicle_registration: '',
  bulk_quantity: '',
});

const resetErrors = () => {
  errors.value = {
    specification: '',
    location_id: '',
    condition: '',
    type: '',
    classification: '',
    burden: '',
    project_id: '',
    image_url: '',
    vehicle_registration: '',
    bulk_quantity: '',
  };
};

// Reactive error clearing
watch(() => form.specification, v => { if (v && errors.value.specification) errors.value.specification = ''; });
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.type, v => { if (v && errors.value.type) errors.value.type = ''; });
watch(() => form.classification, v => { if (v && errors.value.classification) errors.value.classification = ''; });
watch(() => form.burden, v => {
  if (v && errors.value.burden) errors.value.burden = '';
  if (v !== 'Project') {
    form.project_id = '';
    errors.value.project_id = '';
  }
});
watch(() => form.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.bulk_quantity, v => { if (v !== '' && errors.value.bulk_quantity) errors.value.bulk_quantity = ''; });

const onSelectBurden = (burden: 'Corporate' | 'Project') => {
  form.burden = burden;
  if (burden !== 'Project') {
    form.project_id = '';
    errors.value.project_id = '';
  }
};

// Init form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  form.lot_id = props.lot?.id;
  form.status = 'Belum Diverifikasi';
  form.condition = '';
  form.type = '';
  form.classification = '';
  form.burden = 'Corporate';
  form.project_id = '';
  form.specification = '';
  form.image_url = null;
  form.image_url_name = '';
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
  }
};

const hasImage = computed(() => Boolean(form.image_url || form.image_url_name));

const handleDeletePhoto = () => {
  form.image_url = null;
  form.image_url_name = '';
  const input = document.getElementById('create-asset-photo-upload') as HTMLInputElement;
  if (input) input.value = '';
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

const handleSubmit = () => {
  if (form.processing) return;
  resetErrors();

  let isValid = true;
  if (isComp.value && !form.specification?.trim()) { errors.value.specification = t('inventory.specificationRequired'); isValid = false; }
  if (!form.type) { errors.value.type = t('inventory.typeRequired'); isValid = false; }
  if (!form.condition) { errors.value.condition = t('inventory.conditionRequired'); isValid = false; }
  if (!form.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
  if (!form.classification) { errors.value.classification = t('inventory.classificationRequired'); isValid = false; }
  if (!form.burden) { errors.value.burden = t('inventory.burdenRequired'); isValid = false; }
  if (form.burden === 'Project' && !form.project_id) { errors.value.project_id = t('inventory.projectRequired'); isValid = false; }
  if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = t('inventory.nopolRequired'); isValid = false; }
  if (form.is_bulk && (form.bulk_quantity === '' || form.bulk_quantity === null)) { errors.value.bulk_quantity = t('inventory.assetCountRequired'); isValid = false; }
  if (!isValid) return;

  form.transform((data) => {
    const fd: any = {
      _method: 'POST',
      lot_id: data.lot_id,
      location_id: data.location_id,
      status: data.status,
      condition: data.condition,
      type: data.type,
      borrowing_type: data.type,
      classification: data.classification,
      burden: data.burden,
      project_id: data.burden === 'Project' ? data.project_id : null,
      price: data.price !== '' && data.price !== null ? parseCurrencyToNumber(data.price) : null,
    };
    if (isVehicle.value) fd.vehicle_registration = data.vehicle_registration;
    if (isComp.value) {
      fd.comp_specification = data.specification || null;
      fd.specification = data.specification || null;
    }
    if (data.image_url) fd.image_url = data.image_url;
    if (data.is_bulk) {
      fd.is_bulk = data.is_bulk;
      fd.bulk_quantity = data.bulk_quantity;
    }
    return fd;
  });

  const url = form.is_bulk ? '/smart/stock-management/assets/bulk' : '/smart/stock-management/assets';
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
          <div v-if="open" class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-6 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">{{ t('inventory.createNewAsset') }}</h3>
              <button @click="closeModal" :disabled="form.processing" class="p-2 hover:bg-muted rounded-full transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] overscroll-contain">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column: Form Fields -->
                <div class="space-y-6">
                  <!-- Specification (Only for COMP category) -->
                  <Field v-if="isComp" :data-invalid="!!errors.specification || undefined">
                    <FieldLabel for="create-unit-specification">
                      <span>{{ t('inventory.specification') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <textarea
                        id="create-unit-specification"
                        name="specification"
                        v-model="form.specification"
                        rows="3"
                        maxlength="1000"
                        :placeholder="t('inventory.specificationPlaceholder')"
                        class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
                        :class="[errors.specification ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.specification">{{ errors.specification }}</FieldError>
                  </Field>

                  <!-- Borrowing Type (LT, ST) -->
                  <Field :data-invalid="!!errors.type || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.borrowingType') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.type ? 'text-muted-foreground' : 'text-foreground', errors.type ? 'border-destructive' : '']">
                            {{ form.type || t('inventory.selectBorrowingType') }}
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

                  <!-- Condition (Bagus, Rusak) -->
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
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
                  </Field>

                  <!-- Location -->
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
                  <!-- Price -->
                  <Field>
                    <FieldLabel>
                      <span>{{ t('inventory.price') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                        <input type="number" v-model="form.price" placeholder="0" min="0" step="any" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                      </div>
                    </FieldContent>
                  </Field>

                  <!-- Classification -->
                  <Field :data-invalid="!!errors.classification || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.classification') }}<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
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
                    </FieldContent>
                    <FieldError v-if="errors.classification">{{ errors.classification }}</FieldError>
                  </Field>

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
                            {{ form.burden === 'Project' ? t('inventory.project') : t('inventory.corporate') }}
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
                        width-class="w-full h-10 px-4"
                        :error="!!errors.project_id"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
                  </Field>

                  <!-- Photo -->
                  <Field :data-invalid="!!errors.image_url || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.photo') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.image_url ? 'border-destructive' : 'border-input']"
                          @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || t('inventory.noPhotoSelected') }}
                        </div>
                        <input type="file" id="create-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png,.webp" @change="handleFileUpload" />
                        <Button type="button" @click="triggerFileInput" size="lg" class="shrink-0">{{ t('inventory.chooseFile') }}</Button>
                        <Button
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
                    <FieldError v-if="errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>

                  <!-- Plate Number / TNKB (Only for Vehicles) -->
                  <Field v-if="isVehicle" :data-invalid="!!errors.vehicle_registration || undefined">
                    <FieldLabel><span>{{ t('inventory.plateNumber') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.vehicle_registration" :placeholder="t('inventory.nopolPlaceholder')"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 uppercase"
                        :class="[errors.vehicle_registration ? 'border-destructive' : '']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('inventory.requiredMarker') }}</p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" :disabled="form.processing" variant="white" size="xl">{{ t('common.cancel') }}</Button>
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
