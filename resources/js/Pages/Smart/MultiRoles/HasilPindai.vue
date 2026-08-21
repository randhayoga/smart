<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ChevronLeft, ChevronDown, Loader2, Save, Info, FileText, Camera } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Breadcrumb, BreadcrumbList, BreadcrumbItem, BreadcrumbLink, BreadcrumbSeparator, BreadcrumbPage } from '@/Components/ui/breadcrumb';

interface Props {
  asset: any;
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  lot: any;
  barang: any;
}

const props = defineProps<Props>();

const activeTab = ref<'detail' | 'edit'>('detail');

const photoInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
const memoFileInput = ref<HTMLInputElement | null>(null);
const lostDocFileInput = ref<HTMLInputElement | null>(null);
const bodBocFileInput = ref<HTMLInputElement | null>(null);

const isVehicle = computed(() => {
  const category = (props.asset?.barang_category || props.barang?.category || '').toLowerCase();
  const subcategory = (props.asset?.barang_subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

const arrNeedApproval = ['Rusak Total', 'Hilang'];
const arrInactiveConditions = ['Rusak Total', 'Hilang', 'Lelang/Hibah'];

const isRestrictedStatus = (status: string | null | undefined) => {
  if (!status) return false;
  const s = String(status).trim().toLowerCase();
  return s === 'tidak aktif' || s === 'pending' || s.startsWith('pending');
};

const isRestrictedAsset = computed(() => {
  return isRestrictedStatus(props.asset?.status) || arrInactiveConditions.includes(props.asset?.condition);
});

const isKondisiDisabled = computed(() => {
  return isRestrictedAsset.value;
});

const isStatusDisabled = computed(() => {
  if (isRestrictedAsset.value) return true;
  return arrInactiveConditions.includes(form.condition);
});

const isDocumentDisabled = computed(() => {
  return isRestrictedAsset.value;
});

const isBodBocFieldVisible = computed(() => {
  return props.asset?.status === 'Pending:BoD/BoC';
});

// Form setup for editing
const form = useForm({
  number: props.asset?.number || '',
  lot_id: props.lot?.id || props.asset?.lot_id || '',
  location_id: props.asset?.location_id || '',
  floor_id: props.asset?.floor_id || null,
  room_id: props.asset?.room_id || null,
  status: props.asset?.status || '',
  condition: props.asset?.condition || '',
  price: props.asset?.price || '',
  image_url: null as File | null,
  image_url_name: props.asset?.image_url ? props.asset.image_url.split('/').pop() || '' : '',
  use_lot_image: false,
  vehicle_registration: props.asset?.vehicle_registration || '',
  memo_file: null as File | null,
  memo_file_name: props.asset?.memo_file_name || (props.asset?.memo_url ? props.asset.memo_url.split('/').pop() || '' : ''),
  lost_doc_file: null as File | null,
  lost_doc_file_name: props.asset?.lost_doc_file_name || (props.asset?.lost_doc_url ? props.asset.lost_doc_url.split('/').pop() || '' : ''),
  bod_boc_approval_file: null as File | null,
  bod_boc_approval_file_name: props.asset?.bod_boc_approval_file_name || (props.asset?.bod_boc_approval_url ? props.asset.bod_boc_approval_url.split('/').pop() || '' : ''),
});

watch(() => props.asset, (newAsset) => {
  if (!newAsset) return;
  form.number = newAsset.number || '';
  form.location_id = newAsset.location_id || '';
  form.floor_id = newAsset.floor_id || null;
  form.room_id = newAsset.room_id || null;
  form.status = newAsset.status || '';
  form.condition = newAsset.condition || '';
  if (arrInactiveConditions.includes(form.condition)) {
    form.status = 'Pending:BoD/BoC';
  }
  form.price = newAsset.price || '';
  form.image_url = null;
  form.image_url_name = newAsset.image_url ? newAsset.image_url.split('/').pop() || '' : '';
  form.use_lot_image = false;
  form.vehicle_registration = newAsset.vehicle_registration || '';
  form.memo_file = null;
  form.memo_file_name = newAsset.memo_file_name || (newAsset.memo_url ? newAsset.memo_url.split('/').pop() || '' : '');
  form.lost_doc_file = null;
  form.lost_doc_file_name = newAsset.lost_doc_file_name || (newAsset.lost_doc_url ? newAsset.lost_doc_url.split('/').pop() || '' : '');
  form.bod_boc_approval_file = null;
  form.bod_boc_approval_file_name = newAsset.bod_boc_approval_file_name || (newAsset.bod_boc_approval_url ? newAsset.bod_boc_approval_url.split('/').pop() || '' : '');
}, { deep: true, immediate: true });

const errors = ref({
  location_id: '',
  status: '',
  condition: '',
  image_url: '',
  vehicle_registration: '',
  memo_file: '',
  lost_doc_file: '',
  bod_boc_approval_file: '',
});

const resetErrors = () => {
  errors.value = {
    location_id: '',
    status: '',
    condition: '',
    image_url: '',
    vehicle_registration: '',
    memo_file: '',
    lost_doc_file: '',
    bod_boc_approval_file: '',
  };
};

// Clear errors reactively
watch(() => form.location_id, v => { if (v && errors.value.location_id) errors.value.location_id = ''; });
watch(() => form.status, v => { if (v && errors.value.status) errors.value.status = ''; });
watch(() => form.condition, v => { if (v && errors.value.condition) errors.value.condition = ''; });
watch(() => form.image_url, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.image_url_name, v => { if (v && errors.value.image_url) errors.value.image_url = ''; });
watch(() => form.vehicle_registration, v => { if (v && errors.value.vehicle_registration) errors.value.vehicle_registration = ''; });
watch(() => form.memo_file, v => { if (v && errors.value.memo_file) errors.value.memo_file = ''; });
watch(() => form.lost_doc_file, v => { if (v && errors.value.lost_doc_file) errors.value.lost_doc_file = ''; });
watch(() => form.bod_boc_approval_file, v => { if (v && errors.value.bod_boc_approval_file) errors.value.bod_boc_approval_file = ''; });

const filteredFloors = computed(() => {
  if (!form.location_id) return [];
  const locId = Number(form.location_id);
  return props.floors.filter(f => Number(f.location_id) === locId);
});

const filteredRooms = computed(() => {
  if (!form.floor_id) return [];
  const flId = Number(form.floor_id);
  return props.rooms.filter(r => Number(r.floor_id) === flId);
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

watch(() => form.condition, (newVal, oldVal) => {
  if (arrInactiveConditions.includes(newVal)) {
    form.status = 'Pending:BoD/BoC';
  } else if (oldVal && arrInactiveConditions.includes(oldVal) && (form.status === 'Pending' || form.status === 'Pending:BoD/BoC')) {
    if (props.asset?.status && !isRestrictedStatus(props.asset.status)) {
      form.status = props.asset.status;
    } else {
      form.status = '';
    }
  }

  if (!arrNeedApproval.includes(newVal)) {
    form.memo_file = null;
    form.memo_file_name = '';
  }
  if (newVal !== 'Hilang') {
    form.lost_doc_file = null;
    form.lost_doc_file_name = '';
  }
});

// Helper Functions
const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null, floor: string | null, room: string | null) => {
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.join(' - ');
};

const formatDateWithDashes = (dateStr: string | null) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png'); return; }
  if (file.size > 1024 * 1024) { toast.error('Ukuran foto maksimal 1MB'); return; }
  form.image_url = file;
  form.image_url_name = file.name;
  form.use_lot_image = false;
};

const triggerFileInput = () => {
  photoInput.value?.click();
};

const triggerCameraInput = () => {
  cameraInput.value?.click();
};

const viewImageInNewTab = () => {
  if (form.image_url) {
    const objectUrl = URL.createObjectURL(form.image_url);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (form.use_lot_image && (props.lot?.image_url || props.lot?.imageUrl)) {
    window.open('/media/' + (props.lot.image_url || props.lot.imageUrl), '_blank');
  } else if (props.asset?.image_url) {
    window.open('/media/' + props.asset.image_url, '_blank');
  }
};

const handleSamakanPhoto = () => {
  const lotImg = props.lot?.image_url || props.lot?.imageUrl;
  if (lotImg) {
    form.use_lot_image = true;
    form.image_url = null;
    form.image_url_name = lotImg.split('/').pop() || '';
  } else {
    toast.error('LOT tidak memiliki foto.');
  }
};

const handleMemoUpload = (e: any) => {
  if (isDocumentDisabled.value) return;
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error('Format file salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png'); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error('Gagal! Ukuran dokumen maksimal 2MB'); return; }
  form.memo_file = file;
  form.memo_file_name = file.name;
};

const triggerMemoFileInput = () => {
  if (isDocumentDisabled.value) return;
  memoFileInput.value?.click();
};

const viewMemoInNewTab = () => {
  if (form.memo_file) {
    const objectUrl = URL.createObjectURL(form.memo_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.memo_url) {
    window.open('/media/' + props.asset.memo_url, '_blank');
  }
};

const handleLostDocUpload = (e: any) => {
  if (isDocumentDisabled.value) return;
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error('Format file salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png'); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error('Gagal! Ukuran dokumen maksimal 2MB'); return; }
  form.lost_doc_file = file;
  form.lost_doc_file_name = file.name;
};

const triggerLostDocFileInput = () => {
  if (isDocumentDisabled.value) return;
  lostDocFileInput.value?.click();
};

const viewLostDocInNewTab = () => {
  if (form.lost_doc_file) {
    const objectUrl = URL.createObjectURL(form.lost_doc_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.lost_doc_url) {
    window.open('/media/' + props.asset.lost_doc_url, '_blank');
  }
};

const handleBodBocDocUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { toast.error('Format file salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, atau .png'); return; }
  if (file.size > 2 * 1024 * 1024) { toast.error('Gagal! Ukuran dokumen maksimal 2MB'); return; }
  form.bod_boc_approval_file = file;
  form.bod_boc_approval_file_name = file.name;
};

const triggerBodBocDocFileInput = () => {
  bodBocFileInput.value?.click();
};

const viewBodBocDocInNewTab = () => {
  if (form.bod_boc_approval_file) {
    const objectUrl = URL.createObjectURL(form.bod_boc_approval_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  } else if (props.asset?.bod_boc_approval_url) {
    window.open('/media/' + props.asset.bod_boc_approval_url, '_blank');
  }
};

const handleSamakanPrice = () => {
  const unitPrice = props.lot?.unit_price ?? props.lot?.unitPrice;
  if (unitPrice !== undefined && unitPrice !== null && unitPrice !== '') {
    form.price = unitPrice;
  } else {
    toast.error('LOT tidak memiliki harga default.');
  }
};

const parseCurrencyToNumber = (val: string | number) => {
  if (typeof val === 'number') return Math.max(0, val);
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
  return isNaN(parsed) ? 0 : Math.max(0, parsed);
};

const handleSubmit = () => {
  resetErrors();

  let isValid = true;
  if (!form.location_id) { errors.value.location_id = 'Lokasi belum dipilih'; isValid = false; }
  if (!form.status) { errors.value.status = 'Status belum dipilih'; isValid = false; }
  if (!form.condition) { errors.value.condition = 'Kondisi belum dipilih'; isValid = false; }
  if (!form.image_url && !form.image_url_name) { errors.value.image_url = 'Foto belum dipilih'; isValid = false; }
  if (isVehicle.value && !form.vehicle_registration) { errors.value.vehicle_registration = 'TNKB (Nomor Polisi) belum diisi'; isValid = false; }
  if (arrNeedApproval.includes(form.condition) && !isDocumentDisabled.value && !form.memo_file_name) { errors.value.memo_file = 'Berita Acara / Memo belum dipilih'; isValid = false; }
  if (form.condition === 'Hilang' && !isDocumentDisabled.value && !form.lost_doc_file_name) { errors.value.lost_doc_file = 'Surat Keterangan Kehilangan belum dipilih'; isValid = false; }

  if (!isValid) {
    toast.error('Harap lengkapi semua input yang wajib diisi.');
    return;
  }

  form.transform((data) => {
    const fd: any = {
      _method: 'PUT',
      number: data.number,
      lot_id: props.lot?.id || props.asset?.lot_id,
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
    if (data.bod_boc_approval_file) fd.bod_boc_approval_file = data.bod_boc_approval_file;
    return fd;
  }).post(`/smart/inventory/units/${props.asset.id}`, {
    onSuccess: () => {
      toast.success('Aset berhasil diperbarui.');
      activeTab.value = 'detail';
    },
    onError: (err) => {
      if (err.location_id) errors.value.location_id = err.location_id;
      if (err.status) errors.value.status = err.status;
      if (err.condition) errors.value.condition = err.condition;
      if (err.image_url) errors.value.image_url = err.image_url;
      if (err.vehicle_registration) errors.value.vehicle_registration = err.vehicle_registration;
      if (err.memo_file) errors.value.memo_file = err.memo_file;
      if (err.lost_doc_file) errors.value.lost_doc_file = err.lost_doc_file;
      if (err.bod_boc_approval_file) errors.value.bod_boc_approval_file = err.bod_boc_approval_file;
      toast.error('Terjadi kesalahan saat memperbarui aset.');
    }
  });
};
</script>

<template>
  <AppLayout :title="`Hasil Pindai - ${props.asset.number}`">
    <div class="max-w-md mx-auto space-y-4">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink href="/smart/scan">Pindai Barcode</BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>{{ props.asset.number }}</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      <div class="space-y-4">
        <!-- Tab Navigation -->
        <div class="flex rounded-xl bg-muted p-1 mb-4 border border-border">
          <button 
            @click="activeTab = 'detail'"
            class="flex-1 py-1.5 rounded-lg text-center text-sm font-bold transition-all duration-200"
            :class="activeTab === 'detail' ? 'bg-card text-primary shadow-sm border border-primary' : 'text-muted-foreground hover:text-foreground'"
          >
            Detail Aset
          </button>
          <button 
            @click="activeTab = 'edit'"
            class="flex-1 py-1.5 rounded-lg text-center text-sm font-bold transition-all duration-200"
            :class="activeTab === 'edit' ? 'bg-card text-primary shadow-sm border border-primary' : 'text-muted-foreground hover:text-foreground'"
          >
            Ubah Aset
          </button>
        </div>

        <!-- Tab: Detail -->
        <div v-if="activeTab === 'detail'" class="space-y-4">
          <!-- Image Card -->
          <div class="bg-card rounded-2xl p-4 border border-border shadow-sm flex flex-col items-center">
            <div class="w-full aspect-square rounded-xl bg-muted flex items-center justify-center overflow-hidden border border-border shadow-inner">
              <img 
                v-if="props.asset.image_url" 
                :src="'/media/' + props.asset.image_url" 
                class="w-full h-full object-cover" 
              />
              <img 
                v-else-if="props.lot?.image_url" 
                :src="'/media/' + props.lot.image_url" 
                class="w-full h-full object-cover" 
              />
              <div v-else class="w-full h-full flex flex-col items-center justify-center bg-muted/60 text-muted-foreground">
                <Camera class="w-12 h-12 opacity-40 mb-1" />
                <span class="text-xs font-medium opacity-60">Tidak Ada Foto</span>
              </div>
            </div>
            <div class="mt-4 text-center">
              <h1 class="text-base font-bold text-foreground uppercase tracking-wider">{{ props.asset.number }}</h1>
              <h2 class="text-lg font-extrabold text-foreground leading-tight">{{ props.asset.barang_nama }}</h2>
              <p class="text-sm text-foreground mt-1">
                {{ props.asset.barang_brand }} &bull; {{ props.asset.barang_category }} &bull; {{ props.asset.barang_subcategory }}
              </p>
            </div>
          </div>

          <!-- Info Cards -->
          <div class="bg-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="px-4 py-3 border-b border-border bg-muted/20 flex items-center gap-2">
              <Info class="w-4 h-4 text-primary" />
              <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">Status & Lokasi</h3>
            </div>
            <div class="p-4 space-y-3.5 text-sm">
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Status</span>
                <StatusBadge :status="props.asset.status" :proposed-status="props.asset.proposed_status" />
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Kondisi</span>
                <span 
                  :class="[
                    'font-semibold',
                    props.asset.condition === 'Bagus' || props.asset.condition === 'QC Passed' ? 'text-emerald-600' :
                    props.asset.condition === 'Lelang/Hibah' ? 'text-purple-600' :
                    'text-rose-600'
                  ]"
                >
                  {{ props.asset.condition }}
                </span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Lokasi</span>
                <span class="text-foreground font-semibold text-right max-w-[200px]">
                  {{ formatLocation(props.asset.location, props.asset.floor, props.asset.room) }}
                </span>
              </div>
              <div v-if="isVehicle" class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Nopol</span>
                <span class="text-foreground font-semibold">{{ props.asset.vehicle_registration || '-' }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Nilai Aset</span>
                <span class="text-foreground font-semibold">{{ formatRupiah(props.asset.price) }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Pembaruan</span>
                <span class="text-foreground font-semibold">{{ props.asset.updated_at }}</span>
              </div>
            </div>
          </div>

          <div class="bg-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="px-4 py-3 border-b border-border bg-muted/20 flex items-center gap-2">
              <FileText class="w-4 h-4 text-primary" />
              <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">Detail Tipe & LOT</h3>
            </div>
            <div class="p-4 space-y-3.5 text-sm">
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Spesifikasi</span>
                <span class="text-foreground font-semibold text-right max-w-[200px]">{{ props.asset.barang_specification }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Kode LOT</span>
                <span class="text-foreground font-semibold">{{ props.asset.lot_number }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Organizer</span>
                <span class="text-foreground font-semibold text-right">{{ props.asset.lot_organizer }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Vendor</span>
                <span class="text-foreground font-semibold text-right max-w-[200px]">{{ props.asset.lot_vendor }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Tgl Registrasi</span>
                <span class="text-foreground font-semibold">{{ formatDateWithDashes(props.asset.lot_date_of_receipt) }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">Umur</span>
                <span class="text-foreground font-semibold">{{ props.asset.lot_age !== null ? `${props.asset.lot_age} tahun` : '-' }}</span>
              </div>
              <div class="flex justify-between items-start">
                <span class="text-muted-foreground font-medium">No. PO</span>
                <span class="text-foreground font-semibold">{{ props.asset.lot_po_number }}</span>
              </div>
            </div>
          </div>

          <!-- Edit Button trigger -->
          <Button 
            @click="activeTab = 'edit'"
            variant="primary" 
            size="lg" 
            class="w-full h-12 rounded-xl flex items-center justify-center gap-2 text-sm font-bold shadow-md"
          >
            Ubah Lokasi & Detail Aset
          </Button>
        </div>

        <!-- Tab: Edit -->
        <div v-else-if="activeTab === 'edit'" class="space-y-6">
          <div class="bg-card rounded-2xl p-5 border border-border shadow-sm space-y-5">
            <div class="space-y-4">
              <Field>
                <FieldLabel>Kode Aset</FieldLabel>
                <FieldContent>
                  <input 
                    type="text" 
                    :value="form.number" 
                    disabled 
                    class="w-full px-4 py-2.5 text-sm border border-input rounded-xl bg-muted/30 text-muted-foreground cursor-not-allowed h-10" 
                  />
                </FieldContent>
              </Field>

              <Field :data-invalid="!!errors.condition || undefined" :data-disabled="isKondisiDisabled || undefined">
                <FieldLabel>
                  <span>Kondisi<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div v-if="isKondisiDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-xl bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
                    <span>{{ form.condition || 'Pilih kondisi' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </div>
                  <DropdownMenu v-else>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" :class="['w-full justify-between rounded-xl font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground']">
                        {{ form.condition || 'Pilih kondisi' }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                      <DropdownMenuItem @select="form.condition = 'Bagus'">Bagus</DropdownMenuItem>
                      <DropdownMenuItem @select="form.condition = 'Rusak'">Rusak</DropdownMenuItem>
                      <DropdownMenuItem @select="form.condition = 'QC Passed'">QC Passed</DropdownMenuItem>
                      <DropdownMenuItem @select="form.condition = 'Lelang/Hibah'">Lelang/Hibah</DropdownMenuItem>
                      <DropdownMenuItem @select="form.condition = 'Rusak Total'">Rusak Total</DropdownMenuItem>
                      <DropdownMenuItem @select="form.condition = 'Hilang'">Hilang</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </FieldContent>
                <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
              </Field>

              <Field :data-invalid="!!errors.location_id || undefined">
                <FieldLabel>
                  <span>Lokasi<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <Combobox 
                    v-model="form.location_id" 
                    :options="locations" 
                    search-placeholder="Cari lokasi..." 
                    default-label="Pilih lokasi" 
                    width-class="w-full h-10 px-4" 
                  />
                </FieldContent>
                <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
              </Field>

              <Field :data-disabled="!form.location_id || undefined">
                <FieldLabel>
                  <span>Lantai</span>
                </FieldLabel>
                <FieldContent>
                  <Combobox 
                    v-model="form.floor_id" 
                    :options="filteredFloors" 
                    search-placeholder="Cari lantai..." 
                    default-label="Pilih lantai (opsional)" 
                    width-class="w-full h-10 px-4" 
                    :disabled="!form.location_id" 
                  />
                </FieldContent>
              </Field>

              <Field :data-disabled="!form.floor_id || undefined">
                <FieldLabel>
                  <span>Ruangan</span>
                </FieldLabel>
                <FieldContent>
                  <Combobox 
                    v-model="form.room_id" 
                    :options="filteredRooms" 
                    search-placeholder="Cari ruangan..." 
                    default-label="Pilih ruangan (opsional)" 
                    width-class="w-full h-10 px-4" 
                    :disabled="!form.floor_id" 
                  />
                </FieldContent>
              </Field>

              <Field :data-invalid="!!errors.status || undefined" :data-disabled="isStatusDisabled || undefined">
                <FieldLabel>
                  <span>Status<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div v-if="isStatusDisabled" class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-xl bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none">
                    <span>{{ form.status || 'Pilih status' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </div>
                  <DropdownMenu v-else>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" :class="['w-full justify-between rounded-xl font-normal h-10 px-4', !form.status ? 'text-muted-foreground' : 'text-foreground']">
                        {{ form.status || 'Pilih status' }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
                      <DropdownMenuItem @select="form.status = 'Tersedia'">Tersedia</DropdownMenuItem>
                      <DropdownMenuItem @select="form.status = 'Dipinjam'">Dipinjam</DropdownMenuItem>
                      <DropdownMenuItem @select="form.status = 'Standby'">Standby</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </FieldContent>
                <FieldError v-if="errors.status">{{ errors.status }}</FieldError>
              </Field>
   
              <Field>
                <FieldLabel>
                  <span>Harga Satuan</span>
                </FieldLabel>
                <FieldContent>
                  <div class="flex gap-2 w-full">
                    <div class="flex flex-grow rounded-xl border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                      <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                      <input 
                        type="number" 
                        v-model="form.price" 
                        placeholder="Contoh: 60000" 
                        min="0" 
                        class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full" 
                      />
                    </div>
                    <Button type="button" @click="handleSamakanPrice" variant="warning" size="lg" class="rounded-xl h-10">Samakan</Button>
                  </div>
                </FieldContent>
              </Field>

              <Field :data-invalid="!!errors.image_url || undefined">
                <FieldLabel>
                  <span>Foto<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div class="flex gap-2 flex-col xs:flex-row">
                    <div 
                      class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl bg-muted/10 truncate flex items-center h-10"
                      :class="[(form.image_url || form.image_url_name) ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default', errors.image_url ? 'border-destructive' : 'border-input']"
                      @click="(form.image_url || form.image_url_name) && viewImageInNewTab()"
                    >
                      {{ form.image_url_name || 'Belum ada foto yang dipilih' }}
                    </div>
                    <div class="flex gap-2 shrink-0">
                      <input ref="cameraInput" type="file" id="edit-asset-camera-upload" class="hidden" accept="image/*" capture="environment" @change="handleFileUpload" />
                      <input ref="photoInput" type="file" id="edit-asset-photo-upload" class="hidden" accept=".jpg,.jpeg,.png" @change="handleFileUpload" />
                      <Button type="button" @click="handleSamakanPhoto" variant="warning" size="lg" class="rounded-xl h-10 flex-1 xs:flex-none">Samakan</Button>
                      <Button type="button" @click="triggerFileInput" size="lg" class="rounded-xl h-10 flex-1 xs:flex-none">Unggah</Button>
                      <Button type="button" @click="triggerCameraInput" variant="success" size="lg" class="rounded-xl h-10 px-3 bg-emerald-600 hover:bg-emerald-700 text-white shrink-0" title="Ambil Foto">
                        <Camera class="w-5 h-5" />
                      </Button>
                    </div>
                  </div>
                  <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 1 MB (.jpg, .jpeg, .png)</p>
                </FieldContent>
                <FieldError v-if="errors.image_url">{{ errors.image_url }}</FieldError>
              </Field>

              <!-- TNKB (Only for Vehicles) -->
              <Field v-if="isVehicle" :data-invalid="!!errors.vehicle_registration || undefined">
                <FieldLabel><span>TNKB (Nomor Polisi)<span class="text-rose-500">*</span></span></FieldLabel>
                <FieldContent>
                  <input 
                    type="text" 
                    v-model="form.vehicle_registration" 
                    placeholder="Contoh: B 1234 ABC"
                    class="w-full px-4 py-2 text-sm border border-input rounded-xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                  />
                </FieldContent>
                <FieldError v-if="errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
              </Field>

              <!-- Document Upload (Required for approval conditions) -->
              <Field v-if="arrNeedApproval.includes(form.condition)" :data-invalid="!!errors.memo_file || undefined" :data-disabled="isDocumentDisabled || undefined">
                <FieldLabel><span>Berita Acara / Memo<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span></FieldLabel>
                <FieldContent>
                  <div class="flex gap-2 flex-col xs:flex-row">
                    <div 
                      class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl truncate flex items-center h-10"
                      :class="[
                        form.memo_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                        errors.memo_file ? 'border-destructive' : 'border-input',
                        isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/10'
                      ]"
                      @click="form.memo_file_name && viewMemoInNewTab()"
                    >
                      {{ form.memo_file_name || 'Belum ada file yang dipilih' }}
                    </div>
                    <div class="flex gap-2 shrink-0">
                      <input ref="memoFileInput" type="file" id="edit-asset-memo-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleMemoUpload" :disabled="isDocumentDisabled" />
                      <Button type="button" @click="triggerMemoFileInput" size="lg" class="rounded-xl h-10 w-full xs:w-auto" :disabled="isDocumentDisabled">Pilih Dokumen</Button>
                    </div>
                  </div>
                  <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 2 MB (.pdf, .jpg, .jpeg, .png)</p>
                </FieldContent>
                <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
              </Field>

              <!-- Lost Document Upload (Required only if condition is Hilang) -->
              <Field v-if="form.condition === 'Hilang'" :data-invalid="!!errors.lost_doc_file || undefined" :data-disabled="isDocumentDisabled || undefined">
                <FieldLabel><span>Surat Keterangan Kehilangan<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span></FieldLabel>
                <FieldContent>
                  <div class="flex gap-2 flex-col xs:flex-row">
                    <div 
                      class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl truncate flex items-center h-10"
                      :class="[
                        form.lost_doc_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                        errors.lost_doc_file ? 'border-destructive' : 'border-input',
                        isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/10'
                      ]"
                      @click="form.lost_doc_file_name && viewLostDocInNewTab()"
                    >
                      {{ form.lost_doc_file_name || 'Belum ada file yang dipilih' }}
                    </div>
                    <div class="flex gap-2 shrink-0">
                      <input ref="lostDocFileInput" type="file" id="edit-asset-lost-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleLostDocUpload" :disabled="isDocumentDisabled" />
                      <Button type="button" @click="triggerLostDocFileInput" size="lg" class="rounded-xl h-10 w-full xs:w-auto" :disabled="isDocumentDisabled">Pilih Dokumen</Button>
                    </div>
                  </div>
                  <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 2 MB (.pdf, .jpg, .jpeg, .png)</p>
                </FieldContent>
                <FieldError v-if="errors.lost_doc_file">{{ errors.lost_doc_file }}</FieldError>
              </Field>

              <!-- Formulir Approval BoD/BoC (Only visible when existing status is Pending:BoD/BoC) -->
              <Field v-if="isBodBocFieldVisible" :data-invalid="!!errors.bod_boc_approval_file || undefined">
                <FieldLabel><span>Formulir Approval BoD/BoC</span></FieldLabel>
                <FieldContent>
                  <div class="flex gap-2 flex-col xs:flex-row">
                    <div 
                      class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-xl bg-muted/10 truncate flex items-center h-10"
                      :class="[
                        form.bod_boc_approval_file_name ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                        errors.bod_boc_approval_file ? 'border-destructive' : 'border-input'
                      ]"
                      @click="form.bod_boc_approval_file_name && viewBodBocDocInNewTab()"
                    >
                      {{ form.bod_boc_approval_file_name || 'Belum ada file yang dipilih' }}
                    </div>
                    <div class="flex gap-2 shrink-0">
                      <input ref="bodBocFileInput" type="file" id="edit-asset-bod-boc-doc-upload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleBodBocDocUpload" />
                      <Button type="button" @click="triggerBodBocDocFileInput" size="lg" class="rounded-xl h-10 w-full xs:w-auto">Pilih Dokumen</Button>
                    </div>
                  </div>
                  <p class="text-[10px] text-muted-foreground ml-1 mt-1">Maksimal ukuran 2 MB (.pdf, .jpg, .jpeg, .png)</p>
                </FieldContent>
                <FieldError v-if="errors.bod_boc_approval_file">{{ errors.bod_boc_approval_file }}</FieldError>
              </Field>
            </div>

            <div class="border-t border-border pt-4 flex gap-3">
              <Button 
                @click="activeTab = 'detail'" 
                variant="white" 
                size="lg" 
                class="flex-1 rounded-xl h-11 text-sm font-semibold"
              >
                Batal
              </Button>
              <Button 
                @click="handleSubmit" 
                :disabled="form.processing" 
                variant="primary" 
                size="lg" 
                class="flex-1 rounded-xl h-11 text-sm font-semibold relative"
              >
                <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                <span :class="{ 'opacity-0': form.processing }" class="flex items-center justify-center gap-1.5">
                  <Save class="w-4 h-4" />
                  Simpan
                </span>
              </Button>
            </div>
          </div>
        </div>

      </div>
    </div>
    
  </AppLayout>
</template>
