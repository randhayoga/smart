<script setup lang="ts">
import { ref, watch, onMounted, computed, h } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus,
  X,
  Eye,
  Pencil
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem, BreadcrumbSeparator } from '@/Components/ui/breadcrumb';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import ViewTableButton from '@/Components/ViewTableButton.vue';
import Tabs from '@/Components/Tabs.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Combobox from '@/Components/Combobox.vue';
import { Checkbox } from '@/Components/ui/checkbox';

interface Props {
  lot: {
    id: number;
    lotCode: string;
    poNumber: string;
    entryDate: string;
    organizer: string;
    organizer_id: number;
    vendor: string;
    vendor_id: number;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    unitPrice: number | string;
    imageUrl: string;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    updated_at: string;
    
    // Parent barang info
    barang_id: number;
    barang_code: string;
    barang_brand: string;
    barang_specification: string;
    barang_category: string;
    barang_subcategory: string;
    barang_uom: string;
  };
  units: {
    id: number;
    number: string;
    status: string;
    condition: string;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    updated_at: string;
  }[];
  brands: { id: number; name: string; }[];
  uoms: { id: number; name: string; }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
}

const props = defineProps<Props>();

const tabs = ['Detail', 'Daftar Aset'];
const activeTab = ref('Detail');

const searchQuery = ref('');
const statusFilter = ref('');
const conditionFilter = ref('');
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

// Lot Edit Modal Setup
const isLotModalOpen = ref(false);
const lotForm = useForm({
  _method: 'PUT',
  number: props.lot.lotCode,
  barang_id: props.lot.barang_id,
  organizer_id: props.lot.organizer_id,
  vendor_id: props.lot.vendor_id,
  location_id: props.lot.location_id,
  floor_id: props.lot.floor_id,
  room_id: props.lot.room_id,
  po_number: props.lot.poNumber,
  date_of_receipt: '',
  unit_price: props.lot.unitPrice,
  image_url: null as File | null,
  image_url_name: props.lot.imageUrl ? props.lot.imageUrl.split('/').pop() || '' : '',
  use_parent_image: false,
});

const openEditLotModal = () => {
  lotForm.clearErrors();
  lotForm.number = props.lot.lotCode;
  lotForm.barang_id = props.lot.barang_id;
  lotForm.organizer_id = props.lot.organizer_id;
  lotForm.vendor_id = props.lot.vendor_id;
  lotForm.location_id = props.lot.location_id;
  lotForm.floor_id = props.lot.floor_id;
  lotForm.room_id = props.lot.room_id;
  lotForm.po_number = props.lot.poNumber;
  
  if (props.lot.entryDate && props.lot.entryDate !== '-') {
    const parts = props.lot.entryDate.split(/[-/]/);
    if (parts.length === 3) {
      lotForm.date_of_receipt = `${parts[2]}-${parts[1]}-${parts[0]}`;
    } else {
      lotForm.date_of_receipt = '';
    }
  } else {
    lotForm.date_of_receipt = '';
  }

  lotForm.unit_price = props.lot.unitPrice;
  lotForm.image_url = null;
  lotForm.image_url_name = props.lot.imageUrl ? props.lot.imageUrl.split('/').pop() || '' : '';
  lotForm.use_parent_image = false;
  isLotModalOpen.value = true;
};

const handleLotFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    alert('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png');
    return;
  }

  if (file.size > 1024 * 1024) {
    alert('Gagal! Ukuran foto maksimal 1MB');
    return;
  }

  lotForm.image_url = file;
  lotForm.image_url_name = file.name;
  lotForm.use_parent_image = false;
};

const triggerLotFileInput = () => {
  const input = document.getElementById('lot-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewLotImageInNewTab = () => {
  if (lotForm.image_url) {
    const url = URL.createObjectURL(lotForm.image_url);
    window.open(url, '_blank');
  } else if (props.lot.imageUrl) {
    window.open('/storage/' + props.lot.imageUrl, '_blank');
  }
};

const handleSamakanPhoto = () => {
  router.reload({
    only: ['lot'],
    onSuccess: (page) => {
      const parentImage = (page.props as any).lot?.parent_image || props.lot.imageUrl;
      if (parentImage) {
        lotForm.use_parent_image = true;
        lotForm.image_url = null;
        lotForm.image_url_name = parentImage.split('/').pop() || '';
      } else {
        toast.error('Barang parent tidak memiliki foto.');
      }
    }
  });
};

const filteredFloorsForLot = computed(() => {
  if (!lotForm.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(lotForm.location_id));
});

const filteredRoomsForLot = computed(() => {
  if (!lotForm.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(lotForm.floor_id));
});

watch(() => lotForm.location_id, (newVal) => {
  if (newVal) {
    const valid = filteredFloorsForLot.value.some(f => Number(f.id) === Number(lotForm.floor_id));
    if (!valid) {
      lotForm.floor_id = null;
      lotForm.room_id = null;
    }
  } else {
    lotForm.floor_id = null;
    lotForm.room_id = null;
  }
});

watch(() => lotForm.floor_id, (newVal) => {
  if (newVal) {
    const valid = filteredRoomsForLot.value.some(r => Number(r.id) === Number(lotForm.room_id));
    if (!valid) {
      lotForm.room_id = null;
    }
  } else {
    lotForm.room_id = null;
  }
});

const isLotFormValid = computed(() => {
  return lotForm.number && 
         lotForm.organizer_id && 
         lotForm.vendor_id && 
         lotForm.location_id && 
         lotForm.po_number && 
         lotForm.date_of_receipt && 
         lotForm.unit_price !== '' && 
         (lotForm.image_url || lotForm.image_url_name) &&
         !lotForm.processing;
});

const handleSaveLot = () => {
  if (!isLotFormValid.value) return;
  
  lotForm.transform((data) => {
    const formData: any = {
      _method: data._method,
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
    if (data.image_url) {
      formData.image_url = data.image_url;
    }
    if (data.use_parent_image) {
      formData.use_parent_image = data.use_parent_image;
    }
    return formData;
  }).post(`/smart/inventory/lots/${props.lot.id}`, {
    onSuccess: () => {
      isLotModalOpen.value = false;
      toast.success('LOT berhasil diperbarui.');
    }
  });
};

// View Asset Modal Setup
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);

const openViewAssetModal = (asset: any) => {
  selectedAssetForView.value = asset;
  isViewAssetModalOpen.value = true;
};

// Asset Modal Setup (Units)
const isAssetModalOpen = ref(false);
const assetModalMode = ref<'create' | 'edit'>('create');
const selectedAssetId = ref<number | null>(null);

const isVehicle = computed(() => {
  const category = (props.lot.barang_category || '').toLowerCase();
  const subcategory = (props.lot.barang_subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

const isFieldDisabled = computed(() => {
  if (!isVehicle.value) return false;
  if (assetModalMode.value === 'create') return false;
  return assetForm.status === 'dipinjam';
});

const assetForm = useForm({
  _method: 'POST',
  number: '',
  lot_id: props.lot.id,
  location_id: '' as string | number,
  floor_id: null as string | number | null,
  room_id: null as string | number | null,
  status: 'tersedia',
  condition: 'Baik',
  price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  is_bulk: false,
  bulk_quantity: '' as string | number,
  vehicle_registration: '',
});

const generateAssetCode = () => {
  const lotNumber = props.lot.lotCode;
  const prefix = `${lotNumber}-U`;
  const matchingUnits = (props.units || []).filter(unit => unit.number.startsWith(prefix));
  let nextNum = 1;
  if (matchingUnits.length > 0) {
    const numbers = matchingUnits.map(unit => {
      const suffix = unit.number.replace(prefix, '');
      return parseInt(suffix, 10) || 0;
    });
    nextNum = Math.max(...numbers) + 1;
  }
  const paddedNum = String(nextNum).padStart(2, '0');
  return `${prefix}${paddedNum}`;
};

const openCreateAssetModal = () => {
  assetModalMode.value = 'create';
  selectedAssetId.value = null;
  assetForm.reset();
  assetForm._method = 'POST';
  assetForm.number = generateAssetCode();
  assetForm.location_id = props.lot.location_id;
  assetForm.floor_id = props.lot.floor_id;
  assetForm.room_id = props.lot.room_id;
  assetForm.price = props.lot.unitPrice;
  assetForm.status = 'tersedia';
  assetForm.condition = 'Baik';
  assetForm.use_lot_image = true;
  assetForm.image_url_name = props.lot.imageUrl ? props.lot.imageUrl.split('/').pop() || '' : '';
  assetForm.is_bulk = false;
  assetForm.bulk_quantity = '';
  assetForm.vehicle_registration = '';
  isAssetModalOpen.value = true;
};

const openEditAssetModal = (asset: any) => {
  assetModalMode.value = 'edit';
  selectedAssetId.value = asset.id;
  assetForm.reset();
  assetForm._method = 'PUT';
  assetForm.number = asset.number;
  assetForm.location_id = asset.location_id;
  assetForm.floor_id = asset.floor_id;
  assetForm.room_id = asset.room_id;
  assetForm.price = asset.price;
  assetForm.status = asset.status;
  assetForm.condition = asset.condition;
  assetForm.use_lot_image = false;
  assetForm.image_url_name = asset.image_url ? asset.image_url.split('/').pop() || '' : '';
  assetForm.is_bulk = false;
  assetForm.bulk_quantity = '';
  assetForm.vehicle_registration = asset.vehicle_registration || '';
  isAssetModalOpen.value = true;
};

const handleAssetFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    alert('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png');
    return;
  }

  if (file.size > 1024 * 1024) {
    alert('Gagal! Ukuran foto maksimal 1MB');
    return;
  }

  assetForm.image_url = file;
  assetForm.image_url_name = file.name;
  assetForm.use_lot_image = false;
};

const triggerAssetFileInput = () => {
  const input = document.getElementById('asset-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewAssetImageInNewTab = () => {
  if (assetForm.image_url) {
    const url = URL.createObjectURL(assetForm.image_url);
    window.open(url, '_blank');
  } else if (assetForm.use_lot_image && props.lot.imageUrl) {
    window.open('/storage/' + props.lot.imageUrl, '_blank');
  } else if (assetModalMode.value === 'edit' && selectedAssetId.value) {
    const asset = props.units.find(u => u.id === selectedAssetId.value);
    if (asset && asset.image_url) {
      window.open('/storage/' + asset.image_url, '_blank');
    }
  }
};

const handleSamakanAssetPhoto = () => {
  if (props.lot.imageUrl) {
    assetForm.use_lot_image = true;
    assetForm.image_url = null;
    assetForm.image_url_name = props.lot.imageUrl.split('/').pop() || '';
  } else {
    toast.error('LOT tidak memiliki foto.');
  }
};

const filteredFloorsForAsset = computed(() => {
  if (!assetForm.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(assetForm.location_id));
});

const filteredRoomsForAsset = computed(() => {
  if (!assetForm.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(assetForm.floor_id));
});

watch(() => assetForm.location_id, (newVal) => {
  if (newVal) {
    const valid = filteredFloorsForAsset.value.some(f => Number(f.id) === Number(assetForm.floor_id));
    if (!valid) {
      assetForm.floor_id = null;
      assetForm.room_id = null;
    }
  } else {
    assetForm.floor_id = null;
    assetForm.room_id = null;
  }
});

watch(() => assetForm.floor_id, (newVal) => {
  if (newVal) {
    const valid = filteredRoomsForAsset.value.some(r => Number(r.id) === Number(assetForm.room_id));
    if (!valid) {
      assetForm.room_id = null;
    }
  } else {
    assetForm.room_id = null;
  }
});

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

const isAssetFormValid = computed(() => {
  const baseValid = assetForm.number && 
         assetForm.location_id && 
         assetForm.status && 
         assetForm.condition && 
         assetForm.price !== '' && 
         (assetForm.image_url || assetForm.image_url_name) &&
         !assetForm.processing;

  if (!baseValid) return false;

  if (isVehicle.value && !assetForm.vehicle_registration) {
    return false;
  }

  if (assetModalMode.value === 'create' && assetForm.is_bulk) {
    return assetForm.bulk_quantity !== '' && assetForm.bulk_quantity !== null && Number(assetForm.bulk_quantity) >= 1;
  }

  return true;
});

const handleSaveAsset = () => {
  if (!isAssetFormValid.value) return;

  assetForm.transform((data) => {
    const formData: any = {
      _method: data._method,
      number: data.number,
      lot_id: data.lot_id,
      location_id: data.location_id,
      floor_id: data.floor_id,
      room_id: data.room_id,
      status: data.status,
      condition: data.condition,
      price: parseCurrencyToNumber(data.price),
    };
    if (isVehicle.value) {
      formData.vehicle_registration = data.vehicle_registration;
    }
    if (data.image_url) {
      formData.image_url = data.image_url;
    }
    if (data.use_lot_image) {
      formData.use_lot_image = data.use_lot_image;
    }
    if (assetModalMode.value === 'create') {
      formData.is_bulk = data.is_bulk;
      formData.bulk_quantity = data.bulk_quantity;
    }
    return formData;
  });

  if (assetModalMode.value === 'create') {
    const url = assetForm.is_bulk ? '/smart/inventory/units/bulk' : '/smart/inventory/units';
    assetForm.post(url, {
      onSuccess: () => {
        isAssetModalOpen.value = false;
        toast.success(assetForm.is_bulk ? 'Aset berhasil ditambahkan secara massal.' : 'Aset berhasil ditambahkan.');
      }
    });
  } else {
    assetForm.post(`/smart/inventory/units/${selectedAssetId.value}`, {
      onSuccess: () => {
        isAssetModalOpen.value = false;
        toast.success('Aset berhasil diperbarui.');
      }
    });
  }
};

// Formats & Helpers
const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined || val === '') return 'Rp0';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return 'Rp0';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string, floor: string | null, room: string | null) => {
  const parts = [];
  if (loc && loc !== '-') parts.push(loc);
  if (floor && floor !== '-') parts.push(floor);
  if (room && room !== '-') parts.push(room);
  return parts.join(', ') || '-';
};

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
  return dateStr.replace(/\//g, '-');
};

// Deletion Modals Logic
const isDeleteModalOpen = ref(false);
const deleteMode = ref<'lot' | 'asset'>('lot');
const lotToDelete = ref<any>(null);
const assetToDelete = ref<any>(null);

const deleteFields = computed(() => {
  if (deleteMode.value === 'lot' && lotToDelete.value) {
    const fields = [
      { label: 'Kode LOT', value: props.lot.lotCode },
      { label: 'Kategori', value: props.lot.barang_category },
      { label: 'Subkategori', value: props.lot.barang_subcategory },
      { label: 'Merek', value: props.lot.barang_brand },
      { label: 'Spesifikasi', value: props.lot.barang_specification },
      { label: 'Jumlah stok tersedia', value: props.units.filter(u => u.status === 'tersedia').length },
      { label: 'Jumlah stok diawal', value: props.units.length },
      { label: 'Lokasi', value: formatLocation(props.lot.location, props.lot.floor, props.lot.room) },
      { label: 'Nomor PO', value: props.lot.poNumber },
      { label: 'Tanggal masuk', value: formatDateWithDashes(props.lot.entryDate) },
      { label: 'Harga satuan', value: formatRupiah(props.lot.unitPrice) },
      { label: 'Organizer', value: props.lot.organizer },
      { label: 'Vendor', value: props.lot.vendor },
      { label: 'Pembaruan Terakhir', value: props.lot.updated_at }
    ];
    return fields;
  }
  
  if (deleteMode.value === 'asset' && assetToDelete.value) {
    const data = assetToDelete.value;
    const fields = [
      { label: 'Kode Aset', value: data.number },
      { label: 'Kode LOT', value: props.lot.lotCode },
      { label: 'Kategori', value: props.lot.barang_category },
      { label: 'Subkategori', value: props.lot.barang_subcategory },
      { label: 'Merek', value: props.lot.barang_brand },
      { label: 'Spesifikasi', value: props.lot.barang_specification },
    ];
    
    if (isVehicle.value) {
      fields.push({ label: 'TNKB (Nopol)', value: data.vehicle_registration || '-' });
    }
    
    fields.push(
      { label: 'Status', value: getStatusLabel(data.status) },
      { label: 'Kondisi', value: data.condition },
      { label: 'Lokasi', value: formatLocation(data.location, data.floor, data.room) },
      { label: 'Nomor PO', value: props.lot.poNumber },
      { label: 'Tanggal masuk', value: formatDateWithDashes(props.lot.entryDate) },
      { label: 'Harga', value: formatRupiah(data.price) },
      { label: 'Organizer', value: props.lot.organizer },
      { label: 'Vendor', value: props.lot.vendor },
      { label: 'Pembaruan Terakhir', value: data.updated_at || '-' }
    );
    
    return fields;
  }
  
  return null;
});



const openDeleteLotModal = () => {
  deleteMode.value = 'lot';
  lotToDelete.value = props.lot;
  isDeleteModalOpen.value = true;
};

const openDeleteAssetModal = (asset: any) => {
  deleteMode.value = 'asset';
  assetToDelete.value = asset;
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  lotToDelete.value = null;
  assetToDelete.value = null;
};

const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

const handleConfirmDelete = () => {
  if (deleteMode.value === 'lot') {
    router.delete(`/smart/inventory/lots/${props.lot.id}`, {
      onSuccess: (page) => {
        if ((page.props as any).flash?.error) {
          closeDeleteModal();
          return;
        }
        closeDeleteModal();
        toast.success('LOT berhasil dihapus.');
        // Redirect to parent detail page
        router.get(`/smart/inventory/${props.lot.barang_code}`);
      }
    });
  } else if (deleteMode.value === 'asset' && assetToDelete.value) {
    router.delete(`/smart/inventory/units/${assetToDelete.value.id}`, {
      onSuccess: () => {
        closeDeleteModal();
        toast.success('Aset berhasil dihapus.');
      }
    });
  }
};

// Bulk Edit & Export Logic
const isBulkEditModalOpen = ref(false);
const bulkEditForm = ref({
  ids: [] as number[],
  price: '',
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  condition: '',
  status: '',
  location_id: '' as string | number,
  floor_id: '' as string | number | null,
  room_id: '' as string | number | null,
});

// Bulk Edit Vehicle states
const isBulkVehicleEditModalOpen = ref(false);
const bulkVehicleEditForm = ref({
  ids: [] as number[],
  price: '',
  image_url: null as File | null,
  image_url_name: '',
  use_lot_image: false,
  condition: '',
  status: '',
  location_id: '' as string | number,
  floor_id: '' as string | number | null,
  room_id: '' as string | number | null,
});

const openBulkVehicleEditModal = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  
  bulkVehicleEditForm.value = {
    ids: selectedRows.map((row: any) => row.original.id),
    price: '',
    image_url: null,
    image_url_name: '',
    use_lot_image: false,
    condition: '',
    status: '',
    location_id: '',
    floor_id: '',
    room_id: '',
  };
  isBulkVehicleEditModalOpen.value = true;
};

const handleSaveBulkVehicleEdit = () => {
  isBulkVehicleEditModalOpen.value = false;
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
  toast.success('Aset kendaraan berhasil diperbarui secara massal.');
};

const filteredFloorsForBulkVehicle = computed(() => {
  if (!bulkVehicleEditForm.value.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(bulkVehicleEditForm.value.location_id));
});

const filteredRoomsForBulkVehicle = computed(() => {
  if (!bulkVehicleEditForm.value.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(bulkVehicleEditForm.value.floor_id));
});

watch(() => bulkVehicleEditForm.value.location_id, (newVal) => {
  if (newVal) {
    const valid = filteredFloorsForBulkVehicle.value.some(f => Number(f.id) === Number(bulkVehicleEditForm.value.floor_id));
    if (!valid) {
      bulkVehicleEditForm.value.floor_id = '';
      bulkVehicleEditForm.value.room_id = '';
    }
  } else {
    bulkVehicleEditForm.value.floor_id = '';
    bulkVehicleEditForm.value.room_id = '';
  }
});

watch(() => bulkVehicleEditForm.value.floor_id, (newVal) => {
  if (newVal) {
    const valid = filteredRoomsForBulkVehicle.value.some(r => Number(r.id) === Number(bulkVehicleEditForm.value.room_id));
    if (!valid) {
      bulkVehicleEditForm.value.room_id = '';
    }
  } else {
    bulkVehicleEditForm.value.room_id = '';
  }
});

const handleBulkVehicleSamakanPrice = () => {
  bulkVehicleEditForm.value.price = props.lot.unitPrice.toString();
};

const handleBulkVehicleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    toast.error('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png');
    return;
  }

  if (file.size > 1024 * 1024) {
    toast.error('Gagal! Ukuran foto maksimal 1MB');
    return;
  }

  bulkVehicleEditForm.value.image_url = file;
  bulkVehicleEditForm.value.image_url_name = file.name;
  bulkVehicleEditForm.value.use_lot_image = false;
};

const triggerBulkVehicleFileInput = () => {
  const input = document.getElementById('bulk-vehicle-photo-upload') as HTMLInputElement;
  input?.click();
};

const handleBulkVehicleSamakanPhoto = () => {
  if (props.lot.imageUrl) {
    bulkVehicleEditForm.value.use_lot_image = true;
    bulkVehicleEditForm.value.image_url = null;
    bulkVehicleEditForm.value.image_url_name = props.lot.imageUrl.split('/').pop() || '';
  } else {
    toast.error('LOT tidak memiliki foto.');
  }
};

const viewBulkVehicleImageInNewTab = () => {
  if (bulkVehicleEditForm.value.image_url) {
    const url = URL.createObjectURL(bulkVehicleEditForm.value.image_url);
    window.open(url, '_blank');
  } else if (bulkVehicleEditForm.value.use_lot_image && props.lot.imageUrl) {
    window.open('/storage/' + props.lot.imageUrl, '_blank');
  }
};

const handleBulkVehicleSamakanLocation = () => {
  bulkVehicleEditForm.value.location_id = props.lot.location_id;
};

const handleBulkVehicleSamakanFloor = () => {
  if (props.lot.floor_id) {
    bulkVehicleEditForm.value.floor_id = props.lot.floor_id;
  } else {
    toast.error('LOT tidak memiliki lantai default.');
  }
};

const handleBulkVehicleSamakanRoom = () => {
  if (props.lot.room_id) {
    bulkVehicleEditForm.value.room_id = props.lot.room_id;
  } else {
    toast.error('LOT tidak memiliki ruangan default.');
  }
};

const openBulkEditModal = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  
  bulkEditForm.value = {
    ids: selectedRows.map((row: any) => row.original.id),
    price: '',
    image_url: null,
    image_url_name: '',
    use_lot_image: false,
    condition: '',
    status: '',
    location_id: '',
    floor_id: '',
    room_id: '',
  };
  isBulkEditModalOpen.value = true;
};

const handleSaveBulkEdit = () => {
  isBulkEditModalOpen.value = false;
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
  toast.success('Aset berhasil diperbarui secara massal.');
};

const filteredFloorsForBulk = computed(() => {
  if (!bulkEditForm.value.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(bulkEditForm.value.location_id));
});

const filteredRoomsForBulk = computed(() => {
  if (!bulkEditForm.value.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(bulkEditForm.value.floor_id));
});

watch(() => bulkEditForm.value.location_id, (newVal) => {
  if (newVal) {
    const valid = filteredFloorsForBulk.value.some(f => Number(f.id) === Number(bulkEditForm.value.floor_id));
    if (!valid) {
      bulkEditForm.value.floor_id = '';
      bulkEditForm.value.room_id = '';
    }
  } else {
    bulkEditForm.value.floor_id = '';
    bulkEditForm.value.room_id = '';
  }
});

watch(() => bulkEditForm.value.floor_id, (newVal) => {
  if (newVal) {
    const valid = filteredRoomsForBulk.value.some(r => Number(r.id) === Number(bulkEditForm.value.room_id));
    if (!valid) {
      bulkEditForm.value.room_id = '';
    }
  } else {
    bulkEditForm.value.room_id = '';
  }
});

const handleBulkSamakanPrice = () => {
  bulkEditForm.value.price = props.lot.unitPrice.toString();
};

const handleBulkFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    toast.error('Format file salah! Hanya diperbolehkan file .jpg, .jpeg, atau .png');
    return;
  }

  if (file.size > 1024 * 1024) {
    toast.error('Gagal! Ukuran foto maksimal 1MB');
    return;
  }

  bulkEditForm.value.image_url = file;
  bulkEditForm.value.image_url_name = file.name;
  bulkEditForm.value.use_lot_image = false;
};

const triggerBulkFileInput = () => {
  const input = document.getElementById('bulk-photo-upload') as HTMLInputElement;
  input?.click();
};

const handleBulkSamakanPhoto = () => {
  if (props.lot.imageUrl) {
    bulkEditForm.value.use_lot_image = true;
    bulkEditForm.value.image_url = null;
    bulkEditForm.value.image_url_name = props.lot.imageUrl.split('/').pop() || '';
  } else {
    toast.error('LOT tidak memiliki foto.');
  }
};

const viewBulkImageInNewTab = () => {
  if (bulkEditForm.value.image_url) {
    const url = URL.createObjectURL(bulkEditForm.value.image_url);
    window.open(url, '_blank');
  } else if (bulkEditForm.value.use_lot_image && props.lot.imageUrl) {
    window.open('/storage/' + props.lot.imageUrl, '_blank');
  }
};

const handleBulkSamakanLocation = () => {
  bulkEditForm.value.location_id = props.lot.location_id;
};

const handleBulkSamakanFloor = () => {
  if (props.lot.floor_id) {
    bulkEditForm.value.floor_id = props.lot.floor_id;
  } else {
    toast.error('LOT tidak memiliki lantai default.');
  }
};

const handleBulkSamakanRoom = () => {
  if (props.lot.room_id) {
    bulkEditForm.value.room_id = props.lot.room_id;
  } else {
    toast.error('LOT tidak memiliki ruangan default.');
  }
};

const getExportData = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return props.units || [];
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length > 0) {
    return selectedRows.map((row: any) => row.original);
  }
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handlePrint = () => {
  window.print();
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode Aset', 'Status', 'Kondisi', 'Nilai', 'Lokasi Penyimpanan', 'TNKB (Nopol)'];
  const rows = data.map((item: any) => [
    `"${item.number}"`,
    `"${getStatusLabel(item.status)}"`,
    `"${item.condition}"`,
    `"${item.price}"`,
    `"${formatLocation(item.location, item.floor, item.room)}"`,
    `"${item.vehicle_registration || '-'}"`
  ]);

  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map((e: any) => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `daftar_aset_lot_${props.lot.lotCode}_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
};

const handleExportPDF = () => {
  window.print();
};

// Flash Notifications
const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);
const flashError = computed(() => (page.props as any).flash?.error);

watch(flashSuccess, (newVal) => {
  if (newVal) {
    toast.success(newVal);
    if ((page.props as any).flash) {
      (page.props as any).flash.success = null;
    }
  }
}, { immediate: true });

watch(flashError, (newVal) => {
  if (newVal) {
    errorModalMessage.value = newVal;
    isErrorModalOpen.value = true;
  }
}, { immediate: true });

const closeErrorModal = () => {
  isErrorModalOpen.value = false;
  if ((page.props as any).flash) {
    (page.props as any).flash.error = null;
  }
};

// Filtered Units list for Table
const filteredUnits = computed(() => {
  let list = props.units || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(u => 
      (u.number && u.number.toLowerCase().includes(q)) ||
      (formatLocation(u.location, u.floor, u.room).toLowerCase().includes(q))
    );
  }

  if (statusFilter.value) {
    list = list.filter(u => u.status === statusFilter.value);
  }

  if (conditionFilter.value) {
    list = list.filter(u => u.condition === conditionFilter.value);
  }

  return list;
});

// Dynamic values for dropdown filters
const availableStatuses = ['tersedia', 'dipinjam', 'dipakai', 'rusak'];
const availableConditions = ['Baik', 'Kurang Baik', 'Rusak'];

const getStatusLabel = (status: string) => {
  if (status === 'tersedia') return 'Tersedia';
  if (status === 'dipinjam') return 'Dipinjam';
  if (status === 'dipakai') return 'Dipakai';
  if (status === 'rusak') return 'Rusak';
  return status;
};

// Table Columns configuration
const columns = computed<ColumnDef<any>[]>(() => {
  const cols: ColumnDef<any>[] = [
    {
      id: 'select',
      size: 50,
      header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
        h('input', {
          type: 'checkbox',
          class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
          checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
          onChange: table.getToggleAllPageRowsSelectedHandler(),
        })
      ]),
      cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
        h('input', {
          type: 'checkbox',
          class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
          checked: row.getIsSelected(),
          onChange: row.getToggleSelectedHandler(),
        })
      ]),
    },
    {
      accessorKey: 'number',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kode Aset',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('number')),
    }
  ];

  if (isVehicle.value) {
    cols.push({
      accessorKey: 'vehicle_registration',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'TNKB',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm font-medium font-mono' }, row.getValue('vehicle_registration') || '-'),
    });
  }

  cols.push(
    {
      accessorKey: 'status',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Status',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const status = row.getValue('status') as string;
        let badgeClass = 'bg-gray-100 text-gray-800';
        if (status === 'tersedia') badgeClass = 'bg-emerald-100 text-emerald-800';
        else if (status === 'dipinjam') badgeClass = 'bg-amber-100 text-amber-800';
        else if (status === 'dipakai') badgeClass = 'bg-blue-100 text-blue-800';
        else if (status === 'rusak') badgeClass = 'bg-rose-100 text-rose-800';
        
        return h('span', { class: `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}` }, getStatusLabel(status));
      }
    },
    {
      accessorKey: 'condition',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kondisi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const cond = row.getValue('condition') as string;
        let textClass = 'text-foreground';
        if (cond === 'Baik') textClass = 'text-emerald-600 font-semibold';
        else if (cond === 'Kurang Baik') textClass = 'text-amber-600 font-semibold';
        else if (cond === 'Rusak') textClass = 'text-rose-600 font-semibold';
        
        return h('span', { class: textClass }, cond);
      }
    },
    {
      accessorKey: 'location',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Lokasi Penyimpanan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const r = row.original;
        return h('div', { class: 'text-muted-foreground text-sm' }, formatLocation(r.location, r.floor, r.room));
      }
    },
    {
      id: 'actions',
      size: 100,
      header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
      cell: ({ row }) => {
        return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, [
          h(ViewTableButton, {
            onClick: () => openViewAssetModal(row.original)
          })
        ]);
      }
    }
  );

  return cols;
});

watch(rowsPerPage, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    if (val === 'Semua baris' || !val) {
      dataTableRef.value.table.setPageSize(999999);
    } else {
      dataTableRef.value.table.setPageSize(Number(val));
    }
  }
});

onMounted(() => {
  if (dataTableRef.value && dataTableRef.value.table && rowsPerPage.value === 'Semua baris') {
    dataTableRef.value.table.setPageSize(999999);
  }
});

const totalAsetTerpilihCount = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return 0;
  return Object.keys(dataTableRef.value.table.getState().rowSelection).length;
});
</script>

<template>
  <AppLayout title="Detail LOT">
    <!-- Breadcrumb -->
    <Breadcrumb class="no-print">
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory">Manajemen Stok</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink :href="'/smart/inventory/' + props.lot.barang_id">{{ props.lot.barang_code }}</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ props.lot.lotCode }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-2 no-print">
      <Tabs v-model="activeTab" :tabs="tabs" />

      <div class="flex items-center gap-3">
        <button @click="openEditLotModal" class="flex items-center gap-1.5 bg-gradient-primary hover:opacity-90 text-primary-foreground px-5 py-2.5 rounded-[14px] text-sm font-bold shadow-sm">
          Edit Detail LOT
        </button>
        <button @click="openDeleteLotModal" class="flex items-center gap-1.5 bg-destructive hover:opacity-70 text-primary-foreground px-5 py-2.5 rounded-[14px] text-sm font-bold shadow-sm">
          Hapus LOT
        </button>
      </div>
    </div>

    <div class="space-y-4">
      <!-- Detail LOT Card -->
      <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
        <h2 class="text-lg font-bold text-foreground mb-4">Detail LOT</h2>
        
        <div class="flex flex-col md:flex-row gap-6">
          <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
            <img v-if="props.lot.imageUrl" :src="'/storage/' + props.lot.imageUrl" class="w-full h-full object-cover" />
            <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
          </div>

          <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
              <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ props.lot.barang_code }}</p>
              <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.lot.barang_brand }}</p>
              <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.lot.barang_specification }}</p>
              <p class="text-foreground">Kategori: {{ props.lot.barang_category }}</p>
              <p class="text-foreground">Subkategori: {{ props.lot.barang_subcategory }}</p>
              <p class="text-foreground">Satuan: {{ props.lot.barang_uom }}</p>
            </div>
            <div class="md:col-span-8">
              <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ props.lot.lotCode }}</p>
              <p class="text-foreground">Jumlah stok tersedia: {{ props.units.filter(u => u.status === 'tersedia').length }}</p>
              <p class="text-foreground">Jumlah stok diawal: {{ props.units.length }}</p>
              <p class="text-foreground">Lokasi <span class="italic text-muted-foreground">default</span>: {{ formatLocation(props.lot.location, props.lot.floor, props.lot.room) }}</p>
              <p class="text-foreground">Nomor PO: {{ props.lot.poNumber }}</p>
              <p class="text-foreground">Tanggal masuk: {{ formatDateWithDashes(props.lot.entryDate) }}</p>
              <p class="text-foreground">Harga satuan <span class="italic text-muted-foreground">default</span>: {{ formatRupiah(props.lot.unitPrice) }}</p>
              <p class="text-foreground">Organizer: {{ props.lot.organizer }}</p>
              <p class="text-foreground">Vendor: {{ props.lot.vendor }}</p>
              <p class="text-foreground">Pembaruan terakhir: {{ props.lot.updated_at }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Daftar Aset Card -->
      <div class="bg-card rounded-xl border border-border px-4 py-3 shadow-sm overflow-hidden">
        <div class="no-print">
          <h2 class="text-lg font-bold text-foreground mb-4">Daftar Aset</h2>

          <!-- Filters Row -->
          <div class="mb-4 flex flex-wrap items-end gap-4">
            <div class="space-y-1.5 flex-grow min-w-[250px] max-w-sm">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
              <TableSearch 
                v-model="searchQuery"
                placeholder="Cari Aset..." 
              />
            </div>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !statusFilter ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ statusFilter ? getStatusLabel(statusFilter) : 'Semua status' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="statusFilter = ''">Semua status</DropdownMenuItem>
                <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="statusFilter = st">
                  {{ getStatusLabel(st) }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !conditionFilter ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ conditionFilter || 'Semua kondisi' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="conditionFilter = ''">Semua kondisi</DropdownMenuItem>
                <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="conditionFilter = cond">
                  {{ cond }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
              <span>Baris per halaman</span>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                    {{ rowsPerPage }}
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Actions Row -->
          <div class="mb-4 flex flex-wrap items-end justify-between gap-4 pt-2">
            <div class="space-y-2 flex-1 min-w-0">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
              <div class="flex flex-wrap gap-2">
                <!-- Edit Terpilih -->
                <button 
                  @click="isVehicle ? openBulkVehicleEditModal() : openBulkEditModal()"
                  :disabled="totalAsetTerpilihCount === 0"
                  class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm disabled:opacity-50 font-bold"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">Edit Terpilih</span>
                </button>
                <ExportButtonGroup 
                  @export-excel="handleExportExcel"
                  @export-csv="handleExportCSV"
                />
              </div>
            </div>
            
            <button @click="openCreateAssetModal" class="px-5 py-2.5 bg-gradient-primary hover:opacity-90 text-white text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
              <Plus class="w-4 h-4" />
              Aset Baru
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-2">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="filteredUnits" 
            :filter-value="searchQuery"
            :default-sorting="[{ id: 'number', desc: false }]"
          />
        </div>

        <div class="text-xs text-muted-foreground pl-1 mt-1">
          {{ totalAsetTerpilihCount }} dari {{ filteredUnits.length }} baris dipilih
        </div>
      </div>
    </div>

    <!-- Edit LOT Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isLotModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isLotModalOpen" 
              class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Edit LOT Details</h3>
                <button @click="isLotModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kode LOT<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="lotForm.number"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Organizer<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !lotForm.organizer_id ? 'text-muted-foreground' : 'text-foreground']">
                            {{ props.organizers.find(o => o.id == lotForm.organizer_id)?.name || 'Pilih organizer' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="org in props.organizers" :key="org.id" @select="lotForm.organizer_id = org.id">
                            {{ org.name }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Vendor<span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="lotForm.vendor_id"
                        :options="props.vendors"
                        search-placeholder="Cari vendor..."
                        default-label="Pilih vendor"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Lokasi <span class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="lotForm.location_id"
                        :options="props.locations"
                        search-placeholder="Cari lokasi..."
                        default-label="Pilih lokasi"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Lantai <span class="italic text-muted-foreground">default</span></label>
                      <Combobox
                        v-model="lotForm.floor_id"
                        :options="filteredFloorsForLot"
                        search-placeholder="Cari lantai..."
                        default-label="Pilih lantai (opsional)"
                        width-class="w-full h-10 px-4"
                        :disabled="!lotForm.location_id"
                      />
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Ruangan <span class="italic text-muted-foreground">default</span></label>
                      <Combobox
                        v-model="lotForm.room_id"
                        :options="filteredRoomsForLot"
                        search-placeholder="Cari ruangan..."
                        default-label="Pilih ruangan (opsional)"
                        width-class="w-full h-10 px-4"
                        :disabled="!lotForm.floor_id"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Nomor PO<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="lotForm.po_number"
                        placeholder="Contoh: PO-02"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Tanggal Masuk<span class="text-rose-500">*</span></label>
                      <input 
                        type="date" 
                        v-model="lotForm.date_of_receipt"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Harga Satuan <span class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">
                          Rp
                        </span>
                        <input 
                          type="number" 
                          v-model="lotForm.unit_price"
                          placeholder="Contoh: 60000"
                          min="0"
                          class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                        />
                      </div>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Foto <span class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div 
                          class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[
                            (lotForm.image_url || lotForm.image_url_name) 
                              ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                              : 'text-muted-foreground cursor-default'
                          ]"
                          @click="(lotForm.image_url || lotForm.image_url_name) && viewLotImageInNewTab()"
                        >
                          {{ lotForm.image_url_name || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="lot-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleLotFileUpload"
                        />
                        <button 
                          type="button"
                          @click="handleSamakanPhoto"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-orange-400 hover:opacity-90 text-white text-sm font-medium rounded-[14px] transition-colors h-10"
                        >
                          Samakan
                        </button>
                        <button 
                          type="button"
                          @click="triggerLotFileInput"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors h-10"
                        >
                          Pilih File
                        </button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1">Maksimal ukuran 1 MB</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="py-3 px-4 border-t border-border flex items-center justify-between">
                <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
                <div class="flex items-center gap-3">
                  <button 
                    @click="isLotModalOpen = false"
                    class="px-8 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleSaveLot"
                    :disabled="!isLotFormValid"
                    class="px-8 py-2.5 bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98] disabled:opacity-50"
                  >
                    Simpan Perubahan
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Add/Edit Asset Modal (Units) -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isAssetModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isAssetModalOpen" 
              class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-5 pb-3 px-6 border-b border-border">
                <h3 class="text-xl font-bold text-foreground">
                  {{ assetModalMode === 'create' ? 'Tambah Aset Baru' : 'Edit Detail Aset' }}
                </h3>
                <button @click="isAssetModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-6 h-6 text-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 overflow-y-auto max-h-[75vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <!-- Kode Aset -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">
                        {{ assetModalMode === 'create' ? 'Kode Aset' : 'Kode Aset (tidak dapat diubah: sudah auto generate)' }}
                      </label>
                      <input 
                        type="text" 
                        v-model="assetForm.number"
                        :disabled="assetModalMode === 'edit'"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-[#F3F4F6] dark:bg-muted/30 text-gray-400 cursor-not-allowed h-10"
                        :class="assetModalMode === 'edit' ? 'cursor-not-allowed opacity-50' : 'bg-background'"
                      />
                    </div>

                    <!-- Nilai (Price) -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Nilai<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">
                            Rp
                          </span>
                          <input 
                            type="text" 
                            v-model="assetForm.price"
                            @input="assetForm.price = assetForm.price.toString().replace(/[^0-9.,]/g, '')"
                            placeholder="Nilai aset"
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          />
                        </div>
                        <button 
                          type="button"
                          @click="assetForm.price = props.lot.unitPrice"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Foto Aset -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Foto<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div 
                          class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10 text-muted-foreground"
                          :class="[
                            (assetForm.image_url || assetForm.image_url_name) 
                              ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                              : 'text-muted-foreground cursor-default'
                          ]"
                          @click="(assetForm.image_url || assetForm.image_url_name) && viewAssetImageInNewTab()"
                        >
                          {{ assetForm.image_url_name || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="asset-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleAssetFileUpload"
                        />
                        <button 
                          type="button"
                          @click="triggerAssetFileInput"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#5B46F6] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Pilih File
                        </button>
                        <button 
                          type="button"
                          @click="handleSamakanAssetPhoto"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1">Maksimal ukuran 1 MB</p>
                    </div>

                    <!-- Pembuatan massal (Create Mode Only) -->
                    <div v-if="assetModalMode === 'create' && props.lot.barang_category !== 'Kendaraan'" class="space-y-1.5 flex items-center gap-2 pt-2 flex-wrap">
                      <Checkbox 
                        id="is_bulk"
                        v-model="assetForm.is_bulk"
                      />
                      <label for="is_bulk" class="cursor-pointer select-none text-sm font-medium text-foreground">
                        Buat
                      </label>
                      <input 
                        type="number" 
                        v-model="assetForm.bulk_quantity"
                        placeholder="..."
                        min="1"
                        :disabled="!assetForm.is_bulk"
                        class="w-16 px-2 py-1 text-sm border border-input rounded-[10px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-8 disabled:opacity-50 disabled:cursor-not-allowed mx-1"
                      />
                      <span class="text-sm font-medium text-foreground">aset secara otomatis dengan nilai yang sama.</span>
                    </div>

                    <!-- TNKB (Nomor Polisi) - Shown if it's a vehicle -->
                    <div v-if="isVehicle" class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">TNKB (Nomor Polisi)<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="assetForm.vehicle_registration"
                        :disabled="isFieldDisabled"
                        maxlength="15"
                        placeholder="B 1234 ABC"
                        @input="assetForm.vehicle_registration = assetForm.vehicle_registration.toUpperCase()"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 disabled:opacity-50 disabled:cursor-not-allowed uppercase"
                      />
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <!-- Kondisi & Status (Side-by-Side) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <!-- Kondisi -->
                      <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-foreground block">Kondisi<span class="text-rose-500">*</span></label>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" :disabled="isFieldDisabled" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground disabled:opacity-50 disabled:cursor-not-allowed">
                              {{ assetForm.condition }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="assetForm.condition = cond">
                              {{ cond }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>

                      <!-- Status -->
                      <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-foreground block">Status<span class="text-rose-500">*</span></label>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" :disabled="isFieldDisabled" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground disabled:opacity-50 disabled:cursor-not-allowed">
                              {{ getStatusLabel(assetForm.status) }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="assetForm.status = st">
                              {{ getStatusLabel(st) }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </div>

                    <!-- Lokasi -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Lokasi<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="assetForm.location_id"
                          :options="props.locations"
                          search-placeholder="Cari lokasi..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="isFieldDisabled"
                        />
                        <button 
                          type="button"
                          :disabled="isFieldDisabled"
                          @click="
                            assetForm.location_id = props.lot.location_id; 
                            assetForm.floor_id = props.lot.floor_id; 
                            assetForm.room_id = props.lot.room_id;
                          "
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Lantai -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Lantai</label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="assetForm.floor_id"
                          :options="filteredFloorsForAsset"
                          search-placeholder="Cari lantai..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="!assetForm.location_id || isFieldDisabled"
                        />
                        <button 
                          type="button"
                          :disabled="!assetForm.location_id || isFieldDisabled"
                          @click="assetForm.floor_id = props.lot.floor_id"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Ruangan -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Ruangan</label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="assetForm.room_id"
                          :options="filteredRoomsForAsset"
                          search-placeholder="Cari ruangan..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="!assetForm.floor_id || isFieldDisabled"
                        />
                        <button 
                          type="button"
                          :disabled="!assetForm.floor_id || isFieldDisabled"
                          @click="assetForm.room_id = props.lot.room_id"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="py-4 px-6 border-t border-border flex items-center justify-between bg-card">
                <p class="text-sm text-rose-500 italic font-semibold">*Wajib diisi</p>
                <div class="flex items-center gap-3">
                  <button 
                    @click="isAssetModalOpen = false"
                    class="px-8 py-2.5 bg-background border border-gray-300 hover:bg-muted text-gray-700 text-sm font-semibold rounded-full transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleSaveAsset"
                    :disabled="!isAssetFormValid"
                    class="px-8 py-2.5 bg-[#5F38E6] hover:bg-[#4E2ED0] text-white text-sm font-semibold rounded-full transition-colors shadow-sm disabled:opacity-50"
                  >
                    {{ assetModalMode === 'create' ? 'Tambah Aset' : 'Simpan Perubahan' }}
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Detail Asset Modal (Units) -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isViewAssetModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isViewAssetModalOpen" 
              class="bg-card w-full max-w-[1100px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Detail Aset</h3>
                <button @click="isViewAssetModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 overflow-y-auto max-h-[70vh]">
                <div class="flex flex-col md:flex-row gap-6">
                  <!-- Image Column -->
                  <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
                    <img 
                      v-if="selectedAssetForView && selectedAssetForView.image_url" 
                      :src="'/storage/' + selectedAssetForView.image_url" 
                      class="w-full h-full object-cover" 
                    />
                    <img 
                      v-else-if="props.lot.imageUrl" 
                      :src="'/storage/' + props.lot.imageUrl" 
                      class="w-full h-full object-cover" 
                    />
                    <img 
                      v-else 
                      src="https://placehold.co/400x400?text=Placeholder" 
                      class="w-full h-full object-cover opacity-50" 
                    />
                  </div>

                  <!-- Details Columns -->
                  <div v-if="selectedAssetForView" class="flex-grow grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-foreground">
                    <!-- Column 1: Item Info -->
                    <div class="space-y-2">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ props.lot.barang_code }}</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.lot.barang_brand }}</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.lot.barang_specification }}</p>
                      <p class="text-foreground">Kategori: {{ props.lot.barang_category }}</p>
                      <p class="text-foreground">Subkategori: {{ props.lot.barang_subcategory }}</p>
                      <p class="text-foreground">Satuan: {{ props.lot.barang_uom }}</p>
                    </div>

                    <!-- Column 2: LOT Info -->
                    <div class="space-y-2">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ props.lot.lotCode }}</p>
                      <p class="text-foreground">Organizer: {{ props.lot.organizer }}</p>
                      <p class="text-foreground">Tanggal masuk: {{ formatDateWithDashes(props.lot.entryDate) }}</p>
                      <p class="text-foreground">Vendor: {{ props.lot.vendor }}</p>
                      <p class="text-foreground">Nomor PO: {{ props.lot.poNumber }}</p>
                    </div>

                    <!-- Column 3: Asset Info -->
                    <div class="space-y-2">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode Aset:</span> {{ selectedAssetForView.number }}</p>
                      <!-- TNKB (Nopol) -->
                      <p v-if="isVehicle" class="font-bold text-foreground">
                        <span class="text-foreground">Nopol:</span> {{ selectedAssetForView.vehicle_registration || '-' }}
                      </p>
                      <p class="text-foreground">
                        Status: 
                        <span 
                          :class="[
                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold',
                            selectedAssetForView.status === 'tersedia' ? 'bg-emerald-100 text-emerald-800' :
                            selectedAssetForView.status === 'dipinjam' ? 'bg-amber-100 text-amber-800' :
                            selectedAssetForView.status === 'dipakai' ? 'bg-blue-100 text-blue-800' :
                            'bg-rose-100 text-rose-800'
                          ]"
                        >
                          {{ getStatusLabel(selectedAssetForView.status) }}
                        </span>
                      </p>
                      <p class="text-foreground">
                        Kondisi: 
                        <span 
                          :class="[
                            'font-semibold',
                            selectedAssetForView.condition === 'Baik' ? 'text-emerald-600' :
                            selectedAssetForView.condition === 'Kurang Baik' ? 'text-amber-600' :
                            'text-rose-600'
                          ]"
                        >
                          {{ selectedAssetForView.condition }}
                        </span>
                      </p>
                      <p class="text-foreground">Nilai: {{ formatRupiah(selectedAssetForView.price) }}</p>
                      <p class="text-foreground">Lokasi penyimpanan: {{ formatLocation(selectedAssetForView.location, selectedAssetForView.floor, selectedAssetForView.room) }}</p>
                      <p class="text-foreground">Pembaruan terakhir: {{ selectedAssetForView.updated_at || '-' }}</p>
                      <p class="text-foreground">Diverifikasi: <span class="font-semibold text-emerald-600">Sudah</span></p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10">
                <button 
                  @click="
                    isViewAssetModalOpen = false;
                    openEditAssetModal(selectedAssetForView);
                  "
                  class="px-5 py-2.5 bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-bold rounded-xl transition-colors shadow-sm"
                >
                  Edit Detail Aset
                </button>

                <button 
                  @click="isViewAssetModalOpen = false"
                  class="px-5 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-bold rounded-xl transition-colors"
                >
                  Kembali
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="1"
      :item-name="deleteMode === 'lot' ? 'LOT' : 'Barang'"
      :item-data="deleteMode === 'lot' ? lotToDelete : assetToDelete"
      :fields="deleteFields"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <!-- Bulk Edit Assets Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isBulkEditModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isBulkEditModalOpen" 
              class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-5 pb-3 px-6 border-b border-border">
                <h3 class="text-xl font-bold text-foreground">Edit Aset Terpilih</h3>
                <button @click="isBulkEditModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-6 h-6 text-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 overflow-y-auto max-h-[75vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <!-- Kode LOT -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Kode LOT</label>
                      <input 
                        type="text" 
                        value="Tidak dapat diubah"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-[#F3F4F6] dark:bg-muted/30 text-gray-400 cursor-not-allowed h-10"
                      />
                    </div>

                    <!-- Nilai (Price) -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Nilai<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">
                            Rp
                          </span>
                          <input 
                            type="text" 
                            v-model="bulkEditForm.price"
                            @input="bulkEditForm.price = bulkEditForm.price.toString().replace(/[^0-9.,]/g, '')"
                            placeholder="Nilai aset"
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          />
                        </div>
                        <button 
                          type="button"
                          @click="handleBulkSamakanPrice"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Foto -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Foto<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div 
                          class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10 text-muted-foreground"
                          :class="[
                            (bulkEditForm.image_url || bulkEditForm.image_url_name) 
                              ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                              : 'text-muted-foreground cursor-default'
                          ]"
                          @click="(bulkEditForm.image_url || bulkEditForm.image_url_name) && viewBulkImageInNewTab()"
                        >
                          {{ bulkEditForm.image_url_name || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="bulk-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleBulkFileUpload"
                        />
                        <button 
                          type="button"
                          @click="triggerBulkFileInput"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#5B46F6] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Pilih File
                        </button>
                        <button 
                          type="button"
                          @click="handleBulkSamakanPhoto"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1">Maksimal ukuran 1 MB</p>
                    </div>

                    <!-- Footnote Note -->
                    <p class="text-sm text-rose-500 italic font-normal mt-4">*Kosongkan input yang tidak ingin diubah</p>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <!-- Kondisi & Status (Side-by-Side) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <!-- Kondisi -->
                      <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-foreground block">Kondisi<span class="text-rose-500">*</span></label>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground">
                              {{ bulkEditForm.condition || 'Pilih kondisi aset' }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem @select="bulkEditForm.condition = ''">Pilih kondisi aset</DropdownMenuItem>
                            <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="bulkEditForm.condition = cond">
                              {{ cond }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>

                      <!-- Status -->
                      <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-foreground block">Status<span class="text-rose-500">*</span></label>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground">
                              {{ bulkEditForm.status ? getStatusLabel(bulkEditForm.status) : 'Pilih status aset' }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem @select="bulkEditForm.status = ''">Pilih status aset</DropdownMenuItem>
                            <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="bulkEditForm.status = st">
                              {{ getStatusLabel(st) }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </div>

                    <!-- Lokasi -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Lokasi<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="bulkEditForm.location_id"
                          :options="props.locations"
                          search-placeholder="Cari lokasi..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                        />
                        <button 
                          type="button"
                          @click="handleBulkSamakanLocation"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Lantai -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Lantai</label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="bulkEditForm.floor_id"
                          :options="filteredFloorsForBulk"
                          search-placeholder="Cari lantai..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="!bulkEditForm.location_id"
                        />
                        <button 
                          type="button"
                          @click="handleBulkSamakanFloor"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Ruangan -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Ruangan</label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="bulkEditForm.room_id"
                          :options="filteredRoomsForBulk"
                          search-placeholder="Cari ruangan..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="!bulkEditForm.floor_id"
                        />
                        <button 
                          type="button"
                          @click="handleBulkSamakanRoom"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="py-4 px-6 border-t border-border flex items-center justify-end gap-3 bg-card">
                <button 
                  @click="isBulkEditModalOpen = false"
                  class="px-8 py-2.5 bg-background border border-gray-300 hover:bg-muted text-gray-700 text-sm font-semibold rounded-full transition-colors"
                >
                  Batal
                </button>
                <button 
                  @click="handleSaveBulkEdit"
                  class="px-8 py-2.5 bg-[#5F38E6] hover:bg-[#4E2ED0] text-white text-sm font-semibold rounded-full transition-colors shadow-sm"
                >
                  Simpan Perubahan Massal
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Bulk Edit Vehicle Assets Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isBulkVehicleEditModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isBulkVehicleEditModalOpen" 
              class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-5 pb-3 px-6 border-b border-border">
                <h3 class="text-xl font-bold text-foreground">Edit Aset Terpilih</h3>
                <button @click="isBulkVehicleEditModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-6 h-6 text-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 overflow-y-auto max-h-[75vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <!-- Kode LOT -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Kode LOT</label>
                      <input 
                        type="text" 
                        value="Tidak dapat diubah"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-[#F3F4F6] dark:bg-muted/30 text-gray-400 cursor-not-allowed h-10"
                      />
                    </div>

                    <!-- Nilai (Price) -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Nilai<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div class="flex flex-grow rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                          <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">
                            Rp
                          </span>
                          <input 
                            type="text" 
                            v-model="bulkVehicleEditForm.price"
                            @input="bulkVehicleEditForm.price = bulkVehicleEditForm.price.toString().replace(/[^0-9.,]/g, '')"
                            placeholder="Nilai aset"
                            class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                          />
                        </div>
                        <button 
                          type="button"
                          @click="handleBulkVehicleSamakanPrice"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Foto -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Foto<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div 
                          class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10 text-muted-foreground"
                          :class="[
                            (bulkVehicleEditForm.image_url || bulkVehicleEditForm.image_url_name) 
                              ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                              : 'text-muted-foreground cursor-default'
                          ]"
                          @click="(bulkVehicleEditForm.image_url || bulkVehicleEditForm.image_url_name) && viewBulkVehicleImageInNewTab()"
                        >
                          {{ bulkVehicleEditForm.image_url_name || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="bulk-vehicle-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleBulkVehicleFileUpload"
                        />
                        <button 
                          type="button"
                          @click="triggerBulkVehicleFileInput"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#5B46F6] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Pilih File
                        </button>
                        <button 
                          type="button"
                          @click="handleBulkVehicleSamakanPhoto"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                      <p class="text-[10px] text-muted-foreground ml-1">Maksimal ukuran 1 MB</p>
                    </div>

                    <!-- TNKB (Nomor Polisi) -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">TNKB (Nomor Polisi)<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        value="Tidak dapat diubah"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-[#F3F4F6] dark:bg-muted/30 text-gray-400 cursor-not-allowed h-10"
                      />
                    </div>

                    <!-- Footnote Note -->
                    <p class="text-sm text-rose-500 italic font-normal mt-4">*Kosongkan input yang tidak ingin diubah</p>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <!-- Kondisi & Status (Side-by-Side) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <!-- Kondisi -->
                      <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-foreground block">Kondisi<span class="text-rose-500">*</span></label>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground">
                              {{ bulkVehicleEditForm.condition || 'Pilih kondisi aset' }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem @select="bulkVehicleEditForm.condition = ''">Pilih kondisi aset</DropdownMenuItem>
                            <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="bulkVehicleEditForm.condition = cond">
                              {{ cond }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>

                      <!-- Status -->
                      <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-foreground block">Status<span class="text-rose-500">*</span></label>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground">
                              {{ bulkVehicleEditForm.status ? getStatusLabel(bulkVehicleEditForm.status) : 'Pilih status aset' }}
                              <ChevronDown class="w-4 h-4 opacity-50" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                            <DropdownMenuItem @select="bulkVehicleEditForm.status = ''">Pilih status aset</DropdownMenuItem>
                            <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="bulkVehicleEditForm.status = st">
                              {{ getStatusLabel(st) }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </div>

                    <!-- Lokasi -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Lokasi<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="bulkVehicleEditForm.location_id"
                          :options="props.locations"
                          search-placeholder="Cari lokasi..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                        />
                        <button 
                          type="button"
                          @click="handleBulkVehicleSamakanLocation"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Lantai -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Lantai</label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="bulkVehicleEditForm.floor_id"
                          :options="filteredFloorsForBulkVehicle"
                          search-placeholder="Cari lantai..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="!bulkVehicleEditForm.location_id"
                        />
                        <button 
                          type="button"
                          @click="handleBulkVehicleSamakanFloor"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>

                    <!-- Ruangan -->
                    <div class="space-y-1.5">
                      <label class="text-sm font-semibold text-foreground block">Ruangan</label>
                      <div class="flex gap-2">
                        <Combobox
                          v-model="bulkVehicleEditForm.room_id"
                          :options="filteredRoomsForBulkVehicle"
                          search-placeholder="Cari ruangan..."
                          default-label="Pilih lokasi aset"
                          width-class="flex-grow h-10 px-4"
                          :disabled="!bulkVehicleEditForm.floor_id"
                        />
                        <button 
                          type="button"
                          @click="handleBulkVehicleSamakanRoom"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-[#E58B35] hover:opacity-90 text-white text-sm font-semibold rounded-[14px] transition-colors h-10 shadow-sm"
                        >
                          Samakan
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="py-4 px-6 border-t border-border flex items-center justify-end gap-3 bg-card">
                <button 
                  @click="isBulkVehicleEditModalOpen = false"
                  class="px-8 py-2.5 bg-background border border-gray-300 hover:bg-muted text-gray-700 text-sm font-semibold rounded-full transition-colors"
                >
                  Batal
                </button>
                <button 
                  @click="handleSaveBulkVehicleEdit"
                  class="px-8 py-2.5 bg-[#5F38E6] hover:bg-[#4E2ED0] text-white text-sm font-semibold rounded-full transition-colors shadow-sm"
                >
                  Simpan Perubahan Massal
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete Error Modal -->
    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </AppLayout>
</template>

<style>
.print-only {
  display: none !important;
}

@media print {
  .print-only {
    display: block !important;
  }

  @page {
    size: landscape;
    margin: 1.5cm;
  }

  body {
    background: white !important;
    color: black !important;
  }

  aside,
  header,
  nav,
  .no-print,
  .bg-card > div:not(.pb-2):not(:has(table)),
  .flex.items-center.justify-between.gap-4.text-sm.text-muted-foreground,
  .flex.items-center.justify-between.gap-2.flex-wrap,
  th:first-child, td:first-child,
  th:last-child, td:last-child,
  th svg,
  .lucide,
  button:not(th button),
  input
  {
    display: none !important;
  }

  .bg-card {
    border: none !important;
    box-shadow: none !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    border-radius: 0 !important;
  }

  .bg-card div {
    border-radius: 0 !important;
  }

  tbody:has(tr[data-state="selected"]) tr:not([data-state="selected"]) {
    display: none !important;
  }

  table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 10px !important;
    table-layout: auto !important;
  }

  th, td {
    border: 1px solid #ddd !important;
    word-break: break-word !important;
    border-radius: 0 !important;
    text-align: left !important;
  }

  th {
    background-color: #f8fafc !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    color: black !important;
    font-weight: 600 !important;
  }

  th button {
    display: inline-flex !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    color: inherit !important;
    font-size: inherit !important;
    font-weight: inherit !important;
    box-shadow: none !important;
    pointer-events: none !important;
  }

  td {
    padding: 6px 8px !important;
  }

  .font-mono {
    font-size: 10px !important;
  }

  .truncate {
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: clip !important;
  }
}
</style>
