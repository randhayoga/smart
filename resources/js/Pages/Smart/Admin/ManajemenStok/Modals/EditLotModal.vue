<script setup lang="ts">
/**
 * Edit LOT Modal component supporting single LOT updates and bulk updates for locations, vendors, and receipt parameters.
 */
import { ref, watch, computed } from 'vue';
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
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';

interface Props {
  open: boolean;
  /** The LOT item to edit (single), or array of LOT items for bulk edit */
  items: any[];
  /** Whether the parent barang is consumable (affects label display) */
  isConsumable: boolean;
  /** Parent barang image URL for "Samakan" button */
  parentImageUrl: string | null;
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

const isSingle = computed(() => props.items.length === 1);
const selectedItem = computed(() => isSingle.value ? props.items[0] : null);

const form = useForm({
  ids: [] as number[],
  barang_id: '' as string | number,
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  location_id: '' as string | number,
  po_number: '',
  date_of_receipt: '',
  unit_price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_parent_image: false,
  number: '',
  burden: '',
  project_id: '' as string | number,
});

const errors = ref({
  organizer_id: '', vendor_id: '', location_id: '',
  po_number: '', date_of_receipt: '', image_url: '',
  burden: '',
  project_id: '',
});

const resetErrors = () => {
  errors.value = { organizer_id: '', vendor_id: '', location_id: '', po_number: '', date_of_receipt: '', image_url: '', burden: '', project_id: '' };
};

const projectOptions = computed(() => {
  return (props.projects || []).map(p => ({
    id: p.id,
    name: `[${p.no_project}] ${p.project_name}`
  }));
});

// Reactive error clearing
watch(() => form.organizer_id, v => { if (v && errors.value.organizer_id) errors.value.organizer_id = ''; });
watch(() => form.vendor_id, v => { if (v && errors.value.vendor_id) errors.value.vendor_id = ''; });
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.po_number, v => { if (v && errors.value.po_number) errors.value.po_number = ''; });
watch(() => form.date_of_receipt, v => { if (v && errors.value.date_of_receipt) errors.value.date_of_receipt = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.burden, v => { if (v && errors.value.burden) errors.value.burden = ''; });
watch(() => form.project_id, v => { if (v && errors.value.project_id) errors.value.project_id = ''; });
watch(() => form.burden, v => {
  if (v !== 'Project') {
    form.project_id = '';
    errors.value.project_id = '';
  }
});

// Init form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  form.ids = props.items.map(r => r.id);
  form.image_url = null;
  form.image_url_name = '';
  form.use_parent_image = false;

  if (isSingle.value && selectedItem.value) {
    const item = selectedItem.value;
    form.number = item.number || '';
    form.barang_id = item.barang_id || '';
    form.organizer_id = item.organizer_id || '';
    form.vendor_id = item.vendor_id || '';
    form.location_id = item.location_id || '';
    form.po_number = item.po_number || '';
    
    let receiptDate = item.date_of_receipt || '';
    if (!receiptDate && item.entryDate && item.entryDate !== '-') {
      const parts = item.entryDate.split('/');
      if (parts.length === 3) {
        receiptDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
      }
    }
    form.date_of_receipt = receiptDate;

    form.unit_price = item.unitPrice || item.unit_price || '';
    form.image_url_name = (item.imageUrl || item.image_url || '').split('/').pop() || '';
    form.burden = item.burden || '';
    form.project_id = item.project_id || '';
  } else {
    form.organizer_id = '';
    form.vendor_id = '';
    form.location_id = '';
    form.po_number = '';
    form.date_of_receipt = '';
    form.unit_price = '';
    form.number = '';
    form.barang_id = '';
    form.burden = 'Tidak berubah';
    form.project_id = '';
  }
});

const closeModal = () => { emit('update:open', false); };

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { alert(t('inventory.invalidFileFormat')); return; }
  if (file.size > 1024 * 1024) { alert(t('inventory.fileTooLarge1Mb')); return; }
  form.image_url = file;
  form.image_url_name = file.name;
  form.use_parent_image = false;
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-lot-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (form.use_parent_image && props.parentImageUrl) {
    window.open('/media/' + props.parentImageUrl, '_blank');
  } else if (isSingle.value && selectedItem.value) {
    const imageUrl = selectedItem.value.imageUrl || selectedItem.value.image_url;
    if (imageUrl) window.open('/media/' + imageUrl, '_blank');
  }
};

const handleSamakanPhoto = () => {
  if (props.parentImageUrl) {
    form.use_parent_image = true;
    form.image_url = null;
    form.image_url_name = props.parentImageUrl.split('/').pop() || '';
  } else {
    toast.error(t('inventory.parentNoPhoto'));
  }
};

const handleSubmit = () => {
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (!form.organizer_id) { errors.value.organizer_id = t('inventory.organizerRequired'); isValid = false; }
    if (!form.vendor_id) { errors.value.vendor_id = t('inventory.vendorRequired'); isValid = false; }
    if (!form.burden) { errors.value.burden = t('inventory.burdenRequired'); isValid = false; }
    if (form.burden === 'Project' && !form.project_id) { errors.value.project_id = t('inventory.projectRequired'); isValid = false; }
    if (!form.location_id) { errors.value.location_id = t('inventory.locationRequired'); isValid = false; }
    if (!form.po_number) { errors.value.po_number = t('inventory.poNumberRequired'); isValid = false; }
    if (!form.date_of_receipt) { errors.value.date_of_receipt = t('inventory.dateOfReceiptRequired'); isValid = false; }
    if (!form.image_url && !form.image_url_name) {
      errors.value.image_url = props.isConsumable ? t('inventory.assetPhotoRequired') : t('inventory.photoRequired');
      isValid = false;
    }
    if (!isValid) return;

    // Single edit - direct endpoint
    form.transform((data) => {
      const fd: any = {
        _method: 'PUT',
        number: data.number,
        barang_id: data.barang_id,
        organizer_id: data.organizer_id,
        vendor_id: data.vendor_id,
        location_id: data.location_id,
        po_number: data.po_number,
        date_of_receipt: data.date_of_receipt,
        unit_price: data.unit_price,
        burden: data.burden,
        project_id: data.burden === 'Project' ? data.project_id : null,
      };
      if (data.image_url) fd.image_url = data.image_url;
      if (data.use_parent_image) fd.use_parent_image = data.use_parent_image;
      return fd;
    }).post(`/smart/inventory/lots/${form.ids[0]}`, {
      onSuccess: () => { closeModal(); emit('success'); },
      onError: (errs) => {
        Object.keys(errs).forEach((key) => {
          if (key in errors.value) {
            (errors.value as any)[key] = errs[key];
          }
        });
        toast.error(t('inventory.editLotFailed'));
      }
    });
  } else {
    // Bulk edit
    let isValid = true;
    if (form.burden === 'Project' && !form.project_id) {
      errors.value.project_id = t('inventory.projectRequired');
      isValid = false;
    }
    if (!isValid) return;

    const hasField = !!(
      form.organizer_id || form.vendor_id || form.location_id ||
      form.po_number || form.date_of_receipt || form.unit_price ||
      form.image_url || form.use_parent_image || (form.burden && form.burden !== 'Tidak berubah')
    );
    if (!hasField) {
      toast.error(t('inventory.atLeastOneField'));
      return;
    }

    form.transform((data) => {
      const fd: any = { _method: 'PUT', ids: data.ids };
      if (data.organizer_id) fd.organizer_id = data.organizer_id;
      if (data.vendor_id) fd.vendor_id = data.vendor_id;
      if (data.location_id) fd.location_id = data.location_id;
      if (data.po_number) fd.po_number = data.po_number;
      if (data.date_of_receipt) fd.date_of_receipt = data.date_of_receipt;
      if (data.unit_price) fd.unit_price = data.unit_price;
      if (data.image_url) fd.image_url = data.image_url;
      if (data.use_parent_image) fd.use_parent_image = data.use_parent_image;
      if (data.burden && data.burden !== 'Tidak berubah') {
        fd.burden = data.burden;
        if (data.burden === 'Project') {
          fd.project_id = data.project_id;
        }
      }
      return fd;
    }).post('/smart/inventory/lots/bulk', {
      onSuccess: () => { closeModal(); emit('success'); },
      onError: (errs) => {
        toast.error(t('inventory.bulkLotFailed'));
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
          <div v-if="open" class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? t('inventory.editLotDetail') : t('inventory.editLotSelected') }}
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[80vh] overscroll-contain">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <Field>
                    <FieldLabel>{{ t('inventory.lotCode') }}</FieldLabel>
                    <FieldContent>
                      <input type="text" :value="isSingle && selectedItem ? (selectedItem.number ) : t('inventory.cannotBeChanged')" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.po_number) || undefined" :data-disabled="(!isSingle) || undefined">
                    <FieldLabel><span>{{ t('inventory.poNumber') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.po_number" :disabled="!isSingle" :placeholder="!isSingle ? t('inventory.cannotBeChangedBulk') : t('inventory.poNumberPlaceholder')"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.po_number">{{ errors.po_number }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.date_of_receipt) || undefined" :data-disabled="(!isSingle) || undefined">
                    <FieldLabel><span>{{ t('inventory.registrationDate') }}<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="date" v-model="form.date_of_receipt" :disabled="!isSingle"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.date_of_receipt">{{ errors.date_of_receipt }}</FieldError>
                  </Field>

                   <Field :data-invalid="(isSingle && !!errors.organizer_id) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.organizer') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.organizer_id ? 'text-muted-foreground' : 'text-foreground']">
                            {{ organizers.find(o => o.id == form.organizer_id)?.name || (isSingle ? t('inventory.selectOrganizer') : t('inventory.unchanged')) }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="org in organizers" :key="org.id" @select="form.organizer_id = org.id">{{ org.name }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.organizer_id">{{ errors.organizer_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.vendor_id) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.vendor') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.vendor_id" :options="vendors" :search-placeholder="t('inventory.searchVendorPlaceholder')" :default-label="isSingle ? t('inventory.selectVendor') : t('inventory.unchanged')" width-class="w-full h-10 px-4" />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.vendor_id">{{ errors.vendor_id }}</FieldError>
                  </Field>

                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="(isSingle && !!errors.location_id) || undefined">
                    <FieldLabel>
                      <span>{{ isConsumable ? t('inventory.location') : t('inventory.defaultLocation') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
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

                  <Field>
                    <FieldLabel>
                      <span>{{ isConsumable ? t('inventory.unitPrice') : t('inventory.defaultUnitPrice') }}</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                        <input type="number" v-model="form.unit_price" :placeholder="isSingle ? t('inventory.unitPricePlaceholder') : t('inventory.unchanged')" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                      </div>
                    </FieldContent>
                  </Field>

                  <!-- Consumable: Stock input (Disabled in Edit) -->
                  <Field v-if="isConsumable">
                    <FieldLabel><span>{{ t('inventory.stockCount') }}</span></FieldLabel>
                    <FieldContent>
                      <input type="text" :value="isSingle && selectedItem ? (selectedItem.initial_quantity ?? selectedItem.initialQuantity ?? t('inventory.cannotBeChanged')) : t('inventory.cannotBeChanged')" disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.image_url) || undefined">
                    <FieldLabel>
                      <span>{{ isConsumable ? t('inventory.photo') : t('inventory.defaultPhoto') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', (isSingle && errors.image_url) ? 'border-destructive' : 'border-input']"
                          @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || (isSingle ? t('inventory.noPhotoSelected') : t('inventory.unchanged')) }}
                        </div>
                        <input type="file" id="edit-lot-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg">{{ t('inventory.sameAsParent') }}</Button>
                        <Button type="button" @click="triggerFileInput" size="lg">{{ t('inventory.chooseFile') }}</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">{{ t('inventory.maxFileSize1Mb') }}</p>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.burden) || undefined">
                    <FieldLabel>
                      <span>{{ t('inventory.burden') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <RadioGroup v-model="form.burden" class="flex items-center gap-6 h-10">
                        <div v-if="!isSingle" class="flex items-center space-x-2">
                          <RadioGroupItem id="edit-burden-none" value="Tidak berubah" />
                          <label for="edit-burden-none" class="text-sm font-medium text-foreground cursor-pointer select-none">{{ t('inventory.unchanged') }}</label>
                        </div>
                        <div class="flex items-center space-x-2">
                          <RadioGroupItem id="edit-burden-corporate" value="Corporate" />
                          <label for="edit-burden-corporate" class="text-sm font-medium text-foreground cursor-pointer select-none">Corporate</label>
                        </div>
                        <div class="flex items-center space-x-2">
                          <RadioGroupItem id="edit-burden-project" value="Project" />
                          <label for="edit-burden-project" class="text-sm font-medium text-foreground cursor-pointer select-none">Project</label>
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
