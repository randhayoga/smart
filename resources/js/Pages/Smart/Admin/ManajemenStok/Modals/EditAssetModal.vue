<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
  /** The asset items to edit (single/bulk) */
  items: any[];
  /** Parent LOT object containing default location details and image_url */
  lot: any;
  /** Parent Barang object to check category */
  barang: any;
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
const isVehicle = computed(() => props.barang?.category === 'Kendaraan');

const arrNeedApproval = ['Rusak Total', 'Hilang', 'Pending'];

const form = useForm({
  ids: [] as number[],
  number: '',
  location_id: '' as string | number,
  floor_id: null as string | number | null,
  room_id: null as string | number | null,
  status: '',
  condition: '',
  price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  vehicle_registration: '',
  memo_file: null as File | null,
  memo_file_name: '',
});

const errors = ref({
  location_id: '',
  status: '',
  condition: '',
  image_url: '',
  vehicle_registration: '',
  memo_file: '',
});

const resetErrors = () => {
  errors.value = {
    location_id: '',
    status: '',
    condition: '',
    image_url: '',
    vehicle_registration: '',
    memo_file: '',
  };
};

// Reactive error clearing
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.status, v => { if (v && errors.value.status) errors.value.status = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.memo_file, v => { if (v && errors.value.memo_file) errors.value.memo_file = ''; });

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

watch(() => form.status, (newVal) => {
  if (!arrNeedApproval.includes(newVal)) {
    form.memo_file = null;
    form.memo_file_name = '';
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
  form.use_lot_image = false;
  form.memo_file = null;
  form.memo_file_name = '';

  if (isSingle.value && selectedItem.value) {
    const item = selectedItem.value;
    form.number = item.number || '';
    form.location_id = item.location_id || '';
    form.floor_id = item.floor_id || null;
    form.room_id = item.room_id || null;
    form.status = item.status || '';
    form.condition = item.condition || '';
    form.price = item.price || '';
    form.image_url_name = item.image_url ? item.image_url.split('/').pop() || '' : '';
    form.vehicle_registration = item.vehicle_registration || '';
    form.memo_file_name = item.memo_file_name || (item.doc_url ? item.doc_url.split('/').pop() || '' : '');
  } else {
    form.number = '';
    form.location_id = '';
    form.floor_id = null;
    form.room_id = null;
    form.status = '';
    form.condition = '';
    form.price = '';
    form.vehicle_registration = '';
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
  form.use_lot_image = false;
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-asset-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (form.use_lot_image && props.lot?.imageUrl) {
    window.open('/storage/' + props.lot.imageUrl, '_blank');
  } else if (isSingle.value && selectedItem.value?.image_url) {
    window.open('/storage/' + selectedItem.value.image_url, '_blank');
  }
};

const handleSamakanPhoto = () => {
  if (props.lot?.imageUrl) {
    form.use_lot_image = true;
    form.image_url = null;
    form.image_url_name = props.lot.imageUrl.split('/').pop() || '';
  } else {
    toast.error('LOT tidak memiliki foto.');
  }
};

const handleMemoUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error('Format file salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png'); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error('Gagal! Ukuran dokumen maksimal 2MB'); return; }
  form.memo_file = file;
  form.memo_file_name = file.name;
};

const triggerMemoFileInput = () => {
  const input = document.getElementById('edit-asset-memo-upload') as HTMLInputElement;
  input?.click();
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    window.open(URL.createObjectURL(form.memo_file), '_blank');
  } else if (isSingle.value && selectedItem.value?.doc_url) {
    window.open('/storage/' + selectedItem.value.doc_url, '_blank');
  }
};

const handleSamakanPrice = () => {
  if (props.lot?.unitPrice) {
    form.price = props.lot.unitPrice;
  } else if (props.lot?.unit_price) {
    form.price = props.lot.unit_price;
  } else {
    toast.error('LOT tidak memiliki harga default.');
  }
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
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (!form.location_id) { errors.value.location_id = 'Lokasi belum dipilih'; isValid = false; }
    if (!form.status) { errors.value.status = 'Status belum dipilih'; isValid = false; }
    if (!form.condition) { errors.value.condition = 'Kondisi belum dipilih'; isValid = false; }
    if (!form.image_url && !form.image_url_name) { errors.value.image_url = 'Foto belum dipilih'; isValid = false; }
    if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = 'TNKB (Nomor Polisi) belum diisi'; isValid = false; }
    if (arrNeedApproval.includes(form.status) && !form.memo_file_name) { errors.value.memo_file = 'Berita Acara / Memo belum dipilih'; isValid = false; }
    if (!isValid) return;

    form.transform((data) => {
      const fd: any = {
        _method: 'PUT',
        number: data.number,
        lot_id: props.lot.id,
        location_id: data.location_id,
        floor_id: data.floor_id,
        room_id: data.room_id,
        status: data.status,
        condition: data.condition,
        price: data.price !== '' && data.price !== null ? parseCurrencyToNumber(data.price) : null,
      };
      if (isVehicle.value) fd.vehicle_registration = data.vehicle_registration;
      if (data.image_url) fd.image_url = data.image_url;
      if (data.use_lot_image) fd.use_lot_image = data.use_lot_image;
      if (data.memo_file) fd.memo_file = data.memo_file;
      return fd;
    }).post(`/smart/inventory/units/${form.ids[0]}`, {
      onSuccess: () => { closeModal(); emit('success'); },
    });
  } else {
    // Bulk edit
    const hasField = !!(
      form.status || form.condition || form.location_id || form.floor_id ||
      form.room_id || form.price || form.image_url || form.use_lot_image || form.memo_file
    );
    if (!hasField) {
      toast.error('Harap isi minimal satu input untuk melakukan perubahan massal.');
      return;
    }

    if (form.status && arrNeedApproval.includes(form.status) && !form.memo_file_name) {
      errors.value.memo_file = 'Berita Acara / Memo wajib diisi jika status Hilang, Rusak Total, atau Pending.';
      return;
    }

    const payload: any = { ids: form.ids };
    if (form.status) payload.status = form.status;
    if (form.condition) payload.condition = form.condition;
    
    if (form.location_id) {
      payload.location_id = form.location_id;
      payload.floor_id = form.floor_id || null;
      payload.room_id = form.room_id || null;
    } else {
      if (form.floor_id) payload.floor_id = form.floor_id;
      if (form.room_id) payload.room_id = form.room_id;
    }
    
    if (form.price) payload.price = parseCurrencyToNumber(form.price).toString();
    if (form.use_lot_image) payload.use_lot_image = true;
    if (form.image_url instanceof File) payload.image_url = form.image_url;
    if (form.memo_file instanceof File) payload.memo_file = form.memo_file;

    router.post('/smart/inventory/units/bulk-update', payload, {
      onSuccess: () => { closeModal(); emit('success'); },
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
                {{ isSingle ? (isVehicle ? 'Edit Detail Aset Kendaraan' : 'Edit Detail Aset') : 'Edit Aset Terpilih' }}
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
                    <FieldLabel>Kode Aset</FieldLabel>
                    <FieldContent>
                      <input type="text" :value="isSingle ? form.number : 'Tidak dapat diubah secara massal'" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.location_id) || undefined">
                    <FieldLabel>
                      <span>Lokasi<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.location_id" :options="locations" search-placeholder="Cari lokasi..." :default-label="isSingle ? 'Pilih lokasi' : 'Tidak berubah'" width-class="w-full h-10 px-4" />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.location_id">{{ errors.location_id }}</FieldError>
                  </Field>

                  <Field :data-disabled="!form.location_id || undefined">
                    <FieldLabel>
                      <span>Lantai</span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.floor_id" :options="filteredFloors" search-placeholder="Cari lantai..." :default-label="isSingle ? 'Pilih lantai (opsional)' : 'Tidak berubah'" width-class="w-full h-10 px-4" :disabled="!form.location_id" />
                    </FieldContent>
                  </Field>

                  <Field :data-disabled="!form.floor_id || undefined">
                    <FieldLabel>
                      <span>Ruangan</span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.room_id" :options="filteredRooms" search-placeholder="Cari ruangan..." :default-label="isSingle ? 'Pilih ruangan (opsional)' : 'Tidak berubah'" width-class="w-full h-10 px-4" :disabled="!form.floor_id" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.status) || undefined">
                    <FieldLabel>
                      <span>Status<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.status ? 'text-muted-foreground' : 'text-foreground']">
                            {{ form.status || (isSingle ? 'Pilih status' : 'Tidak berubah') }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.status = 'Tersedia'">Tersedia</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Dipinjam'">Dipinjam</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Perbaikan'">Perbaikan</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Rusak Total'">Rusak Total</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Hilang'">Hilang</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Pending'">Pending</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.status">{{ errors.status }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="(isSingle && !!errors.condition) || undefined">
                    <FieldLabel>
                      <span>Kondisi<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground']">
                            {{ form.condition || (isSingle ? 'Pilih kondisi' : 'Tidak berubah') }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.condition = 'Baik'">Baik</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Rusak Ringan'">Rusak Ringan</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Rusak Berat'">Rusak Berat</DropdownMenuItem>
                          <DropdownMenuItem @select="form.condition = 'Rusak Total'">Rusak Total</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.condition">{{ errors.condition }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>
                      <span>Harga Satuan</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input type="number" v-model="form.price" :placeholder="isSingle ? 'Contoh: 60000' : 'Tidak berubah'" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                        </div>
                        <Button type="button" @click="handleSamakanPrice" variant="warning" size="lg">Samakan</Button>
                      </div>
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.image_url) || undefined">
                    <FieldLabel>
                      <span>Foto<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', (isSingle && errors.image_url) ? 'border-destructive' : 'border-input']"
                          @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || (isSingle ? 'Belum ada foto yang dipilih' : 'Tidak berubah') }}
                        </div>
                        <input type="file" id="edit-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg">Samakan</Button>
                        <Button type="button" @click="triggerFileInput" size="lg">Pilih File</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 1 MB</p>
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>

                  <!-- TNKB (Only for Vehicles) -->
                  <Field v-if="isVehicle" :data-invalid="(isSingle && !!errors.vehicle_registration) || undefined" :data-disabled="(!isSingle) || undefined">
                    <FieldLabel><span>TNKB (Nomor Polisi)<span v-if="isSingle" class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.vehicle_registration" :disabled="!isSingle" :placeholder="!isSingle ? 'Tidak dapat diubah secara massal' : 'Contoh: B 1234 ABC'"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
                  </Field>

                  <!-- Document Upload (Required for approval statuses) -->
                  <Field v-if="arrNeedApproval.includes(form.status)" :data-invalid="!!errors.memo_file || undefined">
                    <FieldLabel><span>Berita Acara / Memo<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10 cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted border-input"
                          @click="form.memo_file_name && viewMemoInNewTab()"
                        >
                          {{ form.memo_file_name || 'Pilih dokumen...' }}
                        </div>
                        <input type="file" id="edit-asset-memo-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleMemoUpload" />
                        <Button type="button" @click="triggerMemoFileInput" size="lg">Pilih Dokumen</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 2 MB (.pdf, .jpg, .jpeg, .png)</p>
                    </FieldContent>
                    <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
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
