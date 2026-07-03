<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, ChevronDown, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

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
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const isSingle = computed(() => props.items.length === 1);
const selectedItem = computed(() => isSingle.value ? props.items[0] : null);

const form = useForm({
  ids: [] as number[],
  barang_id: '' as string | number,
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  location_id: '' as string | number,
  floor_id: null as string | number | null,
  room_id: null as string | number | null,
  po_number: '',
  date_of_receipt: '',
  unit_price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_parent_image: false,
  number: '',
});

const errors = ref({
  organizer_id: '', vendor_id: '', location_id: '',
  po_number: '', date_of_receipt: '', image_url: '',
});

const resetErrors = () => {
  errors.value = { organizer_id: '', vendor_id: '', location_id: '', po_number: '', date_of_receipt: '', image_url: '' };
};

// Reactive error clearing
watch(() => form.organizer_id, v => { if (v && errors.value.organizer_id) errors.value.organizer_id = ''; });
watch(() => form.vendor_id, v => { if (v && errors.value.vendor_id) errors.value.vendor_id = ''; });
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.po_number, v => { if (v && errors.value.po_number) errors.value.po_number = ''; });
watch(() => form.date_of_receipt, v => { if (v && errors.value.date_of_receipt) errors.value.date_of_receipt = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });

const filteredFloors = computed(() => {
  if (!form.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(form.location_id));
});

const filteredRooms = computed(() => {
  if (!form.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(form.floor_id));
});

watch(() => form.location_id, (newVal) => {
  if (newVal) {
    const valid = filteredFloors.value.some(f => Number(f.id) === Number(form.floor_id));
    if (!valid) { form.floor_id = null; form.room_id = null; }
  } else { form.floor_id = null; form.room_id = null; }
});

watch(() => form.floor_id, (newVal) => {
  if (newVal) {
    const valid = filteredRooms.value.some(r => Number(r.id) === Number(form.room_id));
    if (!valid) form.room_id = null;
  } else { form.room_id = null; }
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
    form.floor_id = item.floor_id || null;
    form.room_id = item.room_id || null;
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
  } else {
    form.organizer_id = '';
    form.vendor_id = '';
    form.location_id = '';
    form.floor_id = null;
    form.room_id = null;
    form.po_number = '';
    form.date_of_receipt = '';
    form.unit_price = '';
    form.number = '';
    form.barang_id = '';
  }
});

const closeModal = () => { emit('update:open', false); };

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { alert('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png'); return; }
  if (file.size > 1024 * 1024) { alert('Gagal! Ukuran foto maksimal 1MB'); return; }
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
    window.open('/storage/' + props.parentImageUrl, '_blank');
  } else if (isSingle.value && selectedItem.value) {
    const imageUrl = selectedItem.value.imageUrl || selectedItem.value.image_url;
    if (imageUrl) window.open('/storage/' + imageUrl, '_blank');
  }
};

const handleSamakanPhoto = () => {
  if (props.parentImageUrl) {
    form.use_parent_image = true;
    form.image_url = null;
    form.image_url_name = props.parentImageUrl.split('/').pop() || '';
  } else {
    toast.error('Tipe parent tidak memiliki foto.');
  }
};

const handleSubmit = () => {
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (!form.organizer_id) { errors.value.organizer_id = 'Organizer belum dipilih'; isValid = false; }
    if (!form.vendor_id) { errors.value.vendor_id = 'Vendor belum dipilih'; isValid = false; }
    if (!form.location_id) { errors.value.location_id = 'Lokasi belum dipilih'; isValid = false; }
    if (!form.po_number) { errors.value.po_number = 'Nomor PO belum diisi'; isValid = false; }
    if (!form.date_of_receipt) { errors.value.date_of_receipt = 'Tanggal Registrasi belum diisi'; isValid = false; }
    if (!form.image_url && !form.image_url_name) {
      errors.value.image_url = `Foto${props.isConsumable ? '' : ' default'} belum dipilih`;
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
        floor_id: data.floor_id,
        room_id: data.room_id,
        po_number: data.po_number,
        date_of_receipt: data.date_of_receipt,
        unit_price: data.unit_price,
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
        toast.error('Gagal memperbarui LOT. Periksa kembali input Anda.');
      }
    });
  } else {
    // Bulk edit
    const hasField = !!(
      form.organizer_id || form.vendor_id || form.location_id || form.floor_id ||
      form.room_id || form.po_number || form.date_of_receipt || form.unit_price ||
      form.image_url || form.use_parent_image
    );
    if (!hasField) {
      toast.error('Harap isi minimal satu input untuk melakukan perubahan massal.');
      return;
    }

    form.transform((data) => {
      const fd: any = { _method: 'PUT', ids: data.ids };
      if (data.organizer_id) fd.organizer_id = data.organizer_id;
      if (data.vendor_id) fd.vendor_id = data.vendor_id;
      if (data.location_id) fd.location_id = data.location_id;
      if (data.floor_id) fd.floor_id = data.floor_id;
      if (data.room_id) fd.room_id = data.room_id;
      if (data.po_number) fd.po_number = data.po_number;
      if (data.date_of_receipt) fd.date_of_receipt = data.date_of_receipt;
      if (data.unit_price) fd.unit_price = data.unit_price;
      if (data.image_url) fd.image_url = data.image_url;
      if (data.use_parent_image) fd.use_parent_image = data.use_parent_image;
      return fd;
    }).post('/smart/inventory/lots/bulk', {
      onSuccess: () => { closeModal(); emit('success'); },
      onError: (errs) => {
        toast.error('Gagal melakukan perubahan massal. Periksa kembali input Anda.');
      }
    });
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
          <div v-if="open" class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? 'Edit LOT' : 'Edit LOT Terpilih' }}
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh]">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <Field>
                    <FieldLabel>Kode LOT</FieldLabel>
                    <FieldContent>
                      <input type="text" :value="isSingle && selectedItem ? (selectedItem.number ) : 'Tidak dapat diubah'" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                   <Field :data-invalid="(isSingle && !!errors.organizer_id) || undefined">
                    <FieldLabel>
                      <span>Organizer<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.organizer_id ? 'text-muted-foreground' : 'text-foreground']">
                            {{ organizers.find(o => o.id == form.organizer_id)?.name || (isSingle ? 'Pilih organizer' : 'Tidak berubah') }}
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
                      <span>Vendor<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.vendor_id" :options="vendors" search-placeholder="Cari vendor..." :default-label="isSingle ? 'Pilih vendor' : 'Tidak berubah'" width-class="w-full h-10 px-4" />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.vendor_id">{{ errors.vendor_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.location_id) || undefined">
                    <FieldLabel>
                      <span>Lokasi<span v-if="!isConsumable" class="italic"> default</span><span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.location_id" :options="locations" search-placeholder="Cari lokasi..." :default-label="isSingle ? 'Pilih lokasi' : 'Tidak berubah'" width-class="w-full h-10 px-4" />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.location_id">{{ errors.location_id }}</FieldError>
                  </Field>

                  <Field :data-disabled="!form.location_id || undefined">
                    <FieldLabel>
                      <span>Lantai<span v-if="!isConsumable" class="italic"> default</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.floor_id" :options="filteredFloors" search-placeholder="Cari lantai..." :default-label="isSingle ? 'Pilih lantai (opsional)' : 'Tidak berubah'" width-class="w-full h-10 px-4" :disabled="!form.location_id" />
                    </FieldContent>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-disabled="!form.floor_id || undefined">
                    <FieldLabel>
                      <span>Ruangan<span v-if="!isConsumable" class="italic"> default</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.room_id" :options="filteredRooms" search-placeholder="Cari ruangan..." :default-label="isSingle ? 'Pilih ruangan (opsional)' : 'Tidak berubah'" width-class="w-full h-10 px-4" :disabled="!form.floor_id" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.po_number) || undefined" :data-disabled="(!isSingle) || undefined">
                    <FieldLabel><span>Nomor PO<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.po_number" :disabled="!isSingle" :placeholder="!isSingle ? 'Tidak dapat diubah secara massal' : 'Contoh: PO-02'"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.po_number">{{ errors.po_number }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.date_of_receipt) || undefined" :data-disabled="(!isSingle) || undefined">
                    <FieldLabel><span>Tanggal Registrasi<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="date" v-model="form.date_of_receipt" :disabled="!isSingle"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.date_of_receipt">{{ errors.date_of_receipt }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>
                      <span>Harga Satuan<span v-if="!isConsumable" class="italic"> default</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                        <input type="number" v-model="form.unit_price" :placeholder="isSingle ? 'Contoh: 60000' : 'Tidak berubah'" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                      </div>
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.image_url) || undefined">
                    <FieldLabel>
                      <span>Foto<span v-if="!isConsumable" class="italic"> default</span><span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', (isSingle && errors.image_url) ? 'border-destructive' : 'border-input']"
                          @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || (isSingle ? 'Belum ada foto yang dipilih' : 'Tidak berubah') }}
                        </div>
                        <input type="file" id="edit-lot-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg">Samakan</Button>
                        <Button type="button" @click="triggerFileInput" size="lg">Pilih File</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 1 MB</p>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ isSingle ? '*Wajib diisi' : '*Kosongkan input yang tidak ingin diubah' }}
              </p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" variant="white" size="xl">Batal</Button>
                <Button @click="handleSubmit" :disabled="form.processing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': form.processing }">
                    {{ isSingle ? 'Simpan Perubahan' : 'Simpan Perubahan Massal' }}
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
