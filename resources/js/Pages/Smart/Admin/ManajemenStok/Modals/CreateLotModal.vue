<script setup lang="ts">
/**
 * Create LOT Modal component for registering new procurement batches, PO references, vendor information, and initial quantities.
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
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';

interface Props {
  open: boolean;
  barang: {
    id: number; code: string; category: string; subcategory: string;
    brand: string; name: string; specification: string; image_url: string | null;
    uom: string; is_consumable: boolean;
  };
  lots: any[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const lotForm = useForm({
  _method: 'POST',
  number: '',
  barang_id: props.barang.id,
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  location_id: '' as string | number,
  initial_quantity: '' as string | number,
  current_quantity: '' as string | number,
  po_number: '',
  date_of_receipt: '',
  unit_price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_parent_image: false,
  total_item: 1,
  burden: 'Corporate',
  project_id: '' as string | number,
});

const errors = ref({
  number: '', organizer_id: '', vendor_id: '', location_id: '',
  po_number: '', date_of_receipt: '', image_url: '',
  initial_quantity: '',
  project_id: '',
});

const resetErrors = () => {
  errors.value = {
    number: '', organizer_id: '', vendor_id: '', location_id: '',
    po_number: '', date_of_receipt: '', image_url: '',
    initial_quantity: '',
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
watch(() => lotForm.organizer_id, v => { if (v && errors.value.organizer_id) errors.value.organizer_id = ''; });
watch(() => lotForm.vendor_id, v => { if (v && errors.value.vendor_id) errors.value.vendor_id = ''; });
watch(() => lotForm.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => lotForm.po_number, v => { if (v && errors.value.po_number) errors.value.po_number = ''; });
watch(() => lotForm.date_of_receipt, v => {
  if (v) {
    if (errors.value.date_of_receipt) errors.value.date_of_receipt = '';
    generateLotCode();
  }
});
watch(() => lotForm.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => lotForm.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => lotForm.initial_quantity, v => { if ((v !== '' && v !== null) && errors.value.initial_quantity) errors.value.initial_quantity = ''; });
watch(() => lotForm.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });
watch(() => lotForm.burden, v => {
  if (v !== 'Project') {
    lotForm.project_id = '';
    errors.value.project_id = '';
  }
});

const generateLotCode = () => {
  let yy = String(new Date().getFullYear()).slice(-2);
  if (lotForm.date_of_receipt) {
    const dateObj = new Date(lotForm.date_of_receipt);
    if (!isNaN(dateObj.getTime())) {
      yy = String(dateObj.getFullYear()).slice(-2);
    }
  }
  const tipeCode = props.barang.code;
  const suffix = `-${yy}-${tipeCode}`;
  const matchingLots = (props.lots || []).filter(lot => {
    return lot.number.startsWith('LOT-') && lot.number.endsWith(suffix);
  });
  let nextNum = 1;
  if (matchingLots.length > 0) {
    const numbers = matchingLots.map(lot => {
      const parts = lot.number.split('-');
      return parseInt(parts[1], 10) || 0;
    });
    nextNum = Math.max(...numbers) + 1;
  }
  lotForm.number = `LOT-${String(nextNum).padStart(4, '0')}-${yy}-${tipeCode}`;
};

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { alert(t('inventory.invalidFileFormat')); return; }
  if (file.size > 1024 * 1024) { alert(t('inventory.fileTooLarge1Mb')); return; }
  lotForm.image_url = file;
  lotForm.image_url_name = file.name;
  lotForm.use_parent_image = false;
};

const triggerFileInput = () => {
  const input = document.getElementById('create-lot-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (lotForm.image_url) {
    window.open(URL.createObjectURL(lotForm.image_url), '_blank');
  } else if (lotForm.use_parent_image && props.barang.image_url) {
    window.open('/media/' + props.barang.image_url, '_blank');
  }
};

const handleSamakanPhoto = () => {
  if (props.barang.image_url) {
    lotForm.use_parent_image = true;
    lotForm.image_url = null;
    lotForm.image_url_name = props.barang.image_url.split('/').pop() || '';
  } else {
    toast.error(t('inventory.parentNoPhoto'));
  }
};

const hasImage = computed(() => Boolean(lotForm.image_url || lotForm.use_parent_image || lotForm.image_url_name));

const handleDeletePhoto = () => {
  lotForm.image_url = null;
  lotForm.image_url_name = '';
  lotForm.use_parent_image = false;
  const input = document.getElementById('create-lot-photo-upload') as HTMLInputElement;
  if (input) input.value = '';
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
  lotForm._method = 'POST';
  generateLotCode();
  lotForm.organizer_id = ''; lotForm.vendor_id = ''; lotForm.location_id = '';
  lotForm.initial_quantity = ''; lotForm.current_quantity = '';
  lotForm.po_number = ''; lotForm.date_of_receipt = '';
  lotForm.unit_price = ''; lotForm.image_url = null; lotForm.image_url_name = '';
  lotForm.use_parent_image = false; lotForm.total_item = 1;
  lotForm.burden = 'Corporate';
  lotForm.project_id = '';
  lotForm.clearErrors();
  resetErrors();
}, { immediate: true });

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown, true);
});

const handleSubmit = () => {
  resetErrors();
  let isValid = true;
  if (!lotForm.number) { errors.value.number = t('inventory.lotCodeRequired'); isValid = false; }
  if (!lotForm.organizer_id) { errors.value.organizer_id = t('inventory.organizerRequired'); isValid = false; }
  if (!lotForm.date_of_receipt) { errors.value.date_of_receipt = t('inventory.dateOfReceiptRequired'); isValid = false; }
  if (props.barang.is_consumable) {
    if (!lotForm.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
    if (lotForm.burden === 'Project' && !lotForm.project_id) {
      errors.value.project_id = t('inventory.projectRequired');
      isValid = false;
    }
    if (lotForm.initial_quantity === '' || lotForm.initial_quantity === null) {
      errors.value.initial_quantity = t('inventory.stockQtyRequired'); isValid = false;
    }
  }
  if (!isValid) return;

  lotForm.transform((data) => {
    const formData: any = {
      _method: data._method, number: data.number, barang_id: data.barang_id,
      organizer_id: data.organizer_id, vendor_id: data.vendor_id ? Number(data.vendor_id) : null,
      location_id: data.location_id ? Number(data.location_id) : null,
      po_number: data.po_number || null, date_of_receipt: data.date_of_receipt,
      unit_price: data.unit_price,
      burden: props.barang.is_consumable ? data.burden : null,
      project_id: (props.barang.is_consumable && data.burden === 'Project') ? data.project_id : null,
    };
    if (props.barang.is_consumable) {
      formData.initial_quantity = data.initial_quantity;
      formData.current_quantity = data.current_quantity;
    }
    if (data.image_url) formData.image_url = data.image_url;
    if (data.use_parent_image) formData.use_parent_image = data.use_parent_image;
    formData.total_item = data.total_item;
    return formData;
  });

  lotForm.post('/smart/inventory/lots', {
    onSuccess: () => {
      closeModal();
      emit('success');
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
            <div class="p-6 overflow-y-auto max-h-[90vh] overscroll-contain">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <Field>
                    <FieldLabel><span>{{ t('inventory.lotCode') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="lotForm.number" disabled :placeholder="t('inventory.lotNotGenerated')" class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.po_number || undefined">
                    <FieldLabel><span>{{ t('inventory.poNumber') }}</span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="lotForm.po_number" :placeholder="t('inventory.poNumberPlaceholder')"
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10"
                        :class="[errors.po_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.po_number">{{ errors.po_number }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.date_of_receipt || undefined">
                    <FieldLabel><span>{{ t('inventory.registrationDate') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="date" v-model="lotForm.date_of_receipt"
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10"
                        :class="[errors.date_of_receipt ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.date_of_receipt">{{ errors.date_of_receipt }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.organizer_id || undefined">
                    <FieldLabel><span>{{ t('inventory.organizer') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !lotForm.organizer_id ? 'text-muted-foreground' : 'text-foreground', errors.organizer_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
                            {{ organizers.find(o => o.id == lotForm.organizer_id)?.name || t('inventory.selectOrganizer') }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="org in organizers" :key="org.id" @select="lotForm.organizer_id = org.id">{{ org.name }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.organizer_id">{{ errors.organizer_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.vendor_id || undefined">
                    <FieldLabel><span>{{ t('inventory.vendor') }}</span></FieldLabel>
                    <FieldContent>
                      <Combobox v-model="lotForm.vendor_id" :options="vendors" :search-placeholder="t('inventory.searchVendorPlaceholder')" :default-label="t('inventory.selectVendor')" width-class="w-full h-10 px-4" :error="!!errors.vendor_id" />
                    </FieldContent>
                    <FieldError v-if="errors.vendor_id">{{ errors.vendor_id }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="!!errors.location_id || undefined">
                    <FieldLabel>
                      <span>{{ barang.is_consumable ? t('inventory.location') : t('inventory.defaultLocation') }}<span v-if="barang.is_consumable" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <LocationCombobox
                        v-model="lotForm.location_id"
                        :locations="locations"
                        :placeholder="t('inventory.selectLocation')"
                        :error="!!errors.location_id"
                        :active-only="true"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>
                      <span>{{ barang.is_consumable ? t('inventory.unitPrice') : t('inventory.defaultUnitPrice') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                        <input type="number" v-model="lotForm.unit_price" :placeholder="t('inventory.unitPricePlaceholder')" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                      </div>
                    </FieldContent>
                  </Field>

                  <!-- Consumable: Stock input -->
                  <Field v-if="barang.is_consumable" :data-invalid="!!(lotForm.errors.initial_quantity || errors.initial_quantity) || undefined">
                    <FieldLabel><span>{{ t('inventory.totalStock') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex w-full rounded-[14px] border bg-background focus-within:ring-2 transition-colors h-10 overflow-hidden"
                        :class="[(lotForm.errors.initial_quantity || errors.initial_quantity) ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive' : 'border-input focus-within:ring-primary/20 focus-within:border-primary']"
                      >
                        <input type="number" v-model="lotForm.initial_quantity" :placeholder="t('inventory.stockCountPlaceholder')" min="0"
                          class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          @input="lotForm.current_quantity = lotForm.initial_quantity"
                        />
                        <span v-if="barang.uom" class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-l border-input select-none font-medium">
                          {{ barang.uom }}
                        </span>
                      </div>
                    </FieldContent>
                    <FieldError v-if="lotForm.errors.initial_quantity || errors.initial_quantity">{{ lotForm.errors.initial_quantity || errors.initial_quantity }}</FieldError>
                  </Field>


                  <Field :data-invalid="!!errors.image_url || undefined">
                    <FieldLabel>
                      <span>{{ barang.is_consumable ? t('inventory.photo') : t('inventory.defaultPhoto') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(lotForm.image_url || lotForm.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.image_url ? 'border-destructive' : 'border-input']"
                          @click="(lotForm.image_url || lotForm.image_url_name) && viewImageInNewTab()"
                        >
                          {{ lotForm.image_url_name || t('inventory.noPhotoSelected') }}
                        </div>
                        <input type="file" id="create-lot-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg" class="shrink-0">{{ t('inventory.sameAsParent') }}</Button>
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

                  <template v-if="props.barang.is_consumable">
                    <Field>
                      <FieldLabel><span>{{ t('inventory.burden') }}<span class="text-rose-500">*</span></span></FieldLabel>
                      <FieldContent>
                        <RadioGroup v-model="lotForm.burden" class="flex items-center gap-6 h-10">
                          <div class="flex items-center space-x-2">
                            <RadioGroupItem id="create-burden-corporate" value="Corporate" />
                            <label for="create-burden-corporate" class="text-sm font-medium text-foreground cursor-pointer select-none">Corporate</label>
                          </div>
                          <div class="flex items-center space-x-2">
                            <RadioGroupItem id="create-burden-project" value="Project" />
                            <label for="create-burden-project" class="text-sm font-medium text-foreground cursor-pointer select-none">Project</label>
                          </div>
                        </RadioGroup>
                      </FieldContent>
                    </Field>

                    <Field v-if="lotForm.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
                      <FieldLabel><span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span></FieldLabel>
                      <FieldContent>
                        <Combobox v-model="lotForm.project_id" :options="projectOptions" :search-placeholder="t('inventory.searchProjectPlaceholder')" :default-label="t('inventory.selectProject')" width-class="w-full h-10 px-4" :error="!!errors.project_id" />
                      </FieldContent>
                      <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
                    </Field>
                  </template>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('inventory.requiredMarker') }}</p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" :disabled="isProcessing" variant="white" size="xl">{{ t('common.cancel') }}</Button>
                <Button @click="handleSubmit" :disabled="isProcessing" variant="primary" size="xl" class="relative flex items-center justify-center">
                  <Loader2 v-if="isProcessing" class="h-5 w-5 animate-spin mr-2" />
                  <span>{{ t('inventory.createLotBtn') }}</span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
