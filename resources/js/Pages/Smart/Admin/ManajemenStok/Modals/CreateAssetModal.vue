<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, ChevronDown, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Props {
  open: boolean;
  lot: any;
  units: any[];
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

const isVehicle = computed(() => props.barang?.category === 'Kendaraan');
const arrNeedApproval = ['Rusak Total', 'Hilang',];

const form = useForm({
  _method: 'POST',
  number: '',
  lot_id: props.lot?.id,
  location_id: '' as string | number,
  floor_id: null as string | number | null,
  room_id: null as string | number | null,
  status: '',
  condition: '',
  price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  is_bulk: false,
  bulk_quantity: '' as string | number,
  vehicle_registration: '',
  memo_file: null as File | null,
  memo_file_name: '',
  lost_doc_file: null as File | null,
  lost_doc_file_name: '',
});

const errors = ref({
  location_id: '',
  status: '',
  condition: '',
  image_url: '',
  vehicle_registration: '',
  bulk_quantity: '',
  memo_file: '',
  lost_doc_file: '',
});

const resetErrors = () => {
  errors.value = {
    location_id: '',
    status: '',
    condition: '',
    image_url: '',
    vehicle_registration: '',
    bulk_quantity: '',
    memo_file: '',
    lost_doc_file: '',
  };
};

// Reactive error clearing
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.status, v => { if (v && errors.value.status) errors.value.status = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.bulk_quantity, v => { if (v !== '' && errors.value.bulk_quantity) errors.value.bulk_quantity = ''; });
watch(() => form.memo_file, v => { if (v && errors.value.memo_file) errors.value.memo_file = ''; });
watch(() => form.lost_doc_file, v => { if (v && errors.value.lost_doc_file) errors.value.lost_doc_file = ''; });

const filteredFloors = computed(() => {
  if (!form.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(form.location_id));
});

const filteredRooms = computed(() => {
  if (!form.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(form.floor_id));
});

watch(() => form.location_id, (newVal) => {
  const valid = filteredFloors.value.some(f => Number(f.id) === Number(form.floor_id));
  if (!valid) { form.floor_id = null; form.room_id = null; }
});

watch(() => form.floor_id, (newVal) => {
  const valid = filteredRooms.value.some(r => Number(r.id) === Number(form.room_id));
  if (!valid) form.room_id = null;
});

watch(() => form.status, (newVal) => {
  if (!arrNeedApproval.includes(newVal)) {
    form.memo_file = null;
    form.memo_file_name = '';
  }
  if (newVal !== 'Hilang') {
    form.lost_doc_file = null;
    form.lost_doc_file_name = '';
  }
});

const generateAssetCode = () => {
  const tipeCode = props.barang?.subcategory_code || '';
  const organizerCode = props.lot?.organizer || '';
  const combination = `${tipeCode}-${organizerCode}-PTRE`;
  
  let yy = String(new Date().getFullYear()).slice(-2);
  if (props.lot?.date_of_receipt) {
    const dateObj = new Date(props.lot.date_of_receipt);
    if (!isNaN(dateObj.getTime())) {
      yy = String(dateObj.getFullYear()).slice(-2);
    }
  }

  const pattern = `-${combination}-`;
  const matchingUnits = (props.units || []).filter(unit => {
    return unit.number?.includes(pattern);
  });

  let nextNum = 1;
  if (matchingUnits.length > 0) {
    const numbers = matchingUnits.map(unit => {
      const firstPart = unit.number.split('-')[0];
      return parseInt(firstPart, 10) || 0;
    });
    nextNum = Math.max(...numbers) + 1;
  }
  const paddedNum = String(nextNum).padStart(5, '0');
  return `${paddedNum}-${combination}-${yy}`;
};

// Init form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  form.lot_id = props.lot?.id;
  form.number = generateAssetCode();
  form.image_url = null;
  form.image_url_name = '';
  form.use_lot_image = false;
  form.memo_file = null;
  form.memo_file_name = '';
  form.lost_doc_file = null;
  form.lost_doc_file_name = '';
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
  const input = document.getElementById('create-asset-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    window.open(URL.createObjectURL(form.image_url), '_blank');
  } else if (form.use_lot_image && props.lot?.imageUrl) {
    window.open('/storage/' + props.lot.imageUrl, '_blank');
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
  const input = document.getElementById('create-asset-memo-upload') as HTMLInputElement;
  input?.click();
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    window.open(URL.createObjectURL(form.memo_file), '_blank');
  }
};

const handleLostDocUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error('Format file salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png'); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error('Gagal! Ukuran dokumen maksimal 2MB'); return; }
  form.lost_doc_file = file;
  form.lost_doc_file_name = file.name;
};

const triggerLostDocFileInput = () => {
  const input = document.getElementById('create-asset-lost-doc-upload') as HTMLInputElement;
  input?.click();
};

const viewLostDocInNewTab = () => {
  if (form.lost_doc_file) {
    window.open(URL.createObjectURL(form.lost_doc_file), '_blank');
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

  let isValid = true;
  if (!form.location_id) { errors.value.location_id = 'Lokasi belum dipilih'; isValid = false; }
  if (!form.status) { errors.value.status = 'Status belum dipilih'; isValid = false; }
  if (!form.condition) { errors.value.condition = 'Kondisi belum dipilih'; isValid = false; }
  if (!form.image_url && !form.image_url_name) { errors.value.image_url = 'Foto belum dipilih'; isValid = false; }
  if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = 'TNKB (Nomor Polisi) belum diisi'; isValid = false; }
  if (arrNeedApproval.includes(form.status) && !form.memo_file_name) { errors.value.memo_file = 'Berita Acara / Memo belum dipilih'; isValid = false; }
  if (form.status === 'Hilang' && !form.lost_doc_file_name) { errors.value.lost_doc_file = 'Surat Keterangan Kehilangan belum dipilih'; isValid = false; }
  if (form.is_bulk && (form.bulk_quantity === '' || form.bulk_quantity === null)) { errors.value.bulk_quantity = 'Jumlah aset belum diisi'; isValid = false; }
  if (!isValid) return;

  form.transform((data) => {
    const fd: any = {
      _method: 'POST',
      number: data.number,
      lot_id: data.lot_id,
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
    if (data.lost_doc_file) fd.lost_doc_file = data.lost_doc_file;
    if (data.is_bulk) {
      fd.is_bulk = data.is_bulk;
      fd.bulk_quantity = data.bulk_quantity;
    }
    return fd;
  });

  const url = form.is_bulk ? '/smart/inventory/units/bulk' : '/smart/inventory/units';
  form.post(url, {
    onSuccess: () => { closeModal(); emit('success'); },
  });
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
                Pembuatan Aset Baru
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
                      <input type="text" :value="form.number" disabled class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.location_id || undefined">
                    <FieldLabel>
                      <span>Lokasi<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.location_id" :options="locations" search-placeholder="Cari lokasi..." default-label="Pilih lokasi" width-class="w-full h-10 px-4" :error="!!errors.location_id" />
                    </FieldContent>
                    <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
                  </Field>

                  <Field :data-disabled="!form.location_id || undefined">
                    <FieldLabel>
                      <span>Lantai</span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.floor_id" :options="filteredFloors" search-placeholder="Cari lantai..." default-label="Pilih lantai (opsional)" width-class="w-full h-10 px-4" :disabled="!form.location_id" />
                    </FieldContent>
                  </Field>

                  <Field :data-disabled="!form.floor_id || undefined">
                    <FieldLabel>
                      <span>Ruangan</span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox v-model="form.room_id" :options="filteredRooms" search-placeholder="Cari ruangan..." default-label="Pilih ruangan (opsional)" width-class="w-full h-10 px-4" :disabled="!form.floor_id" />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.status || undefined">
                    <FieldLabel>
                      <span>Status<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.status ? 'text-muted-foreground' : 'text-foreground', errors.status ? 'border-destructive' : '']">
                            {{ form.status || 'Pilih status' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem @select="form.status = 'Tersedia'">Tersedia</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Dipinjam'">Dipinjam</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Perbaikan'">Perbaikan</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Rusak Total'">Rusak Total</DropdownMenuItem>
                          <DropdownMenuItem @select="form.status = 'Hilang'">Hilang</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.status">{{ errors.status }}</FieldError>
                  </Field>

                  <!-- Bulk Creation (Only when not vehicle category) -->
                  <Field v-if="!isVehicle" :data-invalid="!!errors.bulk_quantity || undefined">
                    <FieldContent>
                      <div class="flex items-center gap-2 w-full pt-2">
                        <Checkbox id="auto-create-checkbox" v-model="form.is_bulk" />
                        <label for="auto-create-checkbox" class="cursor-pointer select-none text-sm font-medium text-foreground">
                          Buat
                        </label>
                        <input type="number" v-model="form.bulk_quantity" placeholder="..." min="1" :disabled="!form.is_bulk"
                          class="w-16 px-2 py-1 text-sm border rounded-[10px] bg-background focus:outline-none focus:ring-2 transition-colors h-8 disabled:opacity-50 disabled:cursor-not-allowed mx-1"
                          :class="[errors.bulk_quantity ? 'border-destructive' : 'border-input']"
                        />
                        <span class="text-sm font-medium text-foreground">aset secara otomatis dengan nilai default.</span>
                      </div>
                    </FieldContent>
                    <FieldError v-if="errors.bulk_quantity" class="pl-6">{{ errors.bulk_quantity }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="!!errors.condition || undefined">
                    <FieldLabel>
                      <span>Kondisi<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground', errors.condition ? 'border-destructive' : '']">
                            {{ form.condition || 'Pilih kondisi' }}
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
                    <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>
                      <span>Harga Satuan</span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                          <input type="number" v-model="form.price" placeholder="Contoh: 60000" min="0" class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" />
                        </div>
                        <Button type="button" @click="handleSamakanPrice" variant="warning" size="lg">Samakan</Button>
                      </div>
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.image_url || undefined">
                    <FieldLabel>
                      <span>Foto<span class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.image_url ? 'border-destructive' : 'border-input']"
                          @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                        >
                          {{ form.image_url_name || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input type="file" id="create-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                        <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg">Samakan</Button>
                        <Button type="button" @click="triggerFileInput" size="lg">Pilih File</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 1 MB (.jpg, .jpeg, .png)</p>
                    </FieldContent>
                    <FieldError v-if="errors.image_url">{{ errors.image_url }}</FieldError>
                  </Field>

                  <!-- TNKB (Only for Vehicles) -->
                  <Field v-if="isVehicle" :data-invalid="!!errors.vehicle_registration || undefined">
                    <FieldLabel><span>TNKB (Nomor Polisi)<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input type="text" v-model="form.vehicle_registration" placeholder="Contoh: B 1234 ABC"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                        :class="[errors.vehicle_registration ? 'border-destructive' : '']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
                  </Field>

                  <!-- Document Upload (Required for approval statuses) -->
                  <Field v-if="arrNeedApproval.includes(form.status)" :data-invalid="!!errors.memo_file || undefined">
                    <FieldLabel><span>Berita Acara / Memo<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[form.memo_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.memo_file ? 'border-destructive' : 'border-input']"
                          @click="form.memo_file_name && viewMemoInNewTab()"
                        >
                          {{ form.memo_file_name || 'Belum ada file yang dipilih' }}
                        </div>
                        <input type="file" id="create-asset-memo-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleMemoUpload" />
                        <Button type="button" @click="triggerMemoFileInput" size="lg">Pilih Dokumen</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 2 MB (.pdf, .jpg, .jpeg, .png)</p>
                    </FieldContent>
                    <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
                  </Field>

                  <!-- Lost Document Upload (Required only if status is Hilang) -->
                  <Field v-if="form.status === 'Hilang'" :data-invalid="!!errors.lost_doc_file || undefined">
                    <FieldLabel><span>Surat Keterangan Kehilangan<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[form.lost_doc_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.lost_doc_file ? 'border-destructive' : 'border-input']"
                          @click="form.lost_doc_file_name && viewLostDocInNewTab()"
                        >
                          {{ form.lost_doc_file_name || 'Belum ada file yang dipilih' }}
                        </div>
                        <input type="file" id="create-asset-lost-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleLostDocUpload" />
                        <Button type="button" @click="triggerLostDocFileInput" size="lg">Pilih Dokumen</Button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 2 MB (.pdf, .jpg, .jpeg, .png)</p>
                    </FieldContent>
                    <FieldError v-if="errors.lost_doc_file">{{ errors.lost_doc_file }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
              <div class="flex items-center gap-3">
                <Button @click="closeModal" variant="white" size="xl">Batal</Button>
                <Button @click="handleSubmit" :disabled="form.processing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': form.processing }">Buat Aset</span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
