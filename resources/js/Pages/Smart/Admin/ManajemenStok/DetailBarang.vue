<script setup lang="ts">
import { ref, watch, onMounted, computed, h, nextTick } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus,
  X,
  Pencil,
  Trash2
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
import EditTableButton from '@/Components/EditTableButton.vue';
import DeleteTableButton from '@/Components/DeleteTableButton.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Combobox from '@/Components/Combobox.vue';
import { Checkbox } from '@/Components/ui/checkbox';
import DetailLOTConsumables from './DetailLOTConsumables.vue';

interface Props {
  itemId: string | number;
  barang: {
    id: number;
    code: string;
    category: string;
    subcategory: string;
    brand: string;
    specification: string;
    lastUpdate: string;
    amount: number;
    image_url: string | null;
    uom: string;
    subcategory_id: number;
    category_id: number;
    brand_id: number;
    uom_id: number;
    is_consumable: boolean;
  };
  brands: { id: number; name: string; }[];
  uoms: { id: number; name: string; }[];
  lots: {
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
    assetCount: number;
    availableAssetCount: number;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    updated_at: string;
  }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
}

const props = defineProps<Props>();

const tabs = computed(() => ['Detail', props.barang.is_consumable ? 'Daftar LOT' : 'Daftar Aset']);
const activeTab = ref('Detail');

const searchQuery = ref('');
const timeFilter = ref('');
const organizerFilter = ref('');
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

// Lot Modal Setup
const isLotModalOpen = ref(false);
const lotModalMode = ref<'create' | 'edit'>('create');
const selectedLotId = ref<number | null>(null);

const isDetailConsumablesOpen = ref(false);
const selectedLotForDetail = ref<number | null>(null);

const openDetailLOTConsumables = (lot: any) => {
  selectedLotForDetail.value = lot.id;
  isDetailConsumablesOpen.value = true;
};

const lotForm = useForm({
  _method: 'POST',
  number: '',
  barang_id: props.barang.id,
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  location_id: '' as string | number,
  floor_id: null as string | number | null,
  room_id: null as string | number | null,
  initial_quantity: '' as string | number,
  current_quantity: '' as string | number,
  auto_create_assets: false,
  auto_create_assets_count: '' as string | number,
  po_number: '',
  date_of_receipt: '',
  unit_price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_parent_image: false,
  total_item: 1,
});

const generateLotCode = () => {
  const year = new Date().getFullYear();
  const barangCode = props.barang.code;
  const prefix = `LOT-${year}-${barangCode}-`;
  
  const matchingLots = (props.lots || []).filter(lot => lot.lotCode.startsWith(prefix));
  
  let nextNum = 1;
  if (matchingLots.length > 0) {
    const numbers = matchingLots.map(lot => {
      const suffix = lot.lotCode.replace(prefix, '');
      return parseInt(suffix, 10) || 0;
    });
    nextNum = Math.max(...numbers) + 1;
  }
  
  const paddedNum = String(nextNum).padStart(4, '0');
  lotForm.number = `${prefix}${paddedNum}`;
};

const openCreateLotModal = () => {
  lotModalMode.value = 'create';
  selectedLotId.value = null;
  lotForm.reset();
  lotForm.barang_id = props.barang.id;
  lotForm._method = 'POST';
  generateLotCode();
  lotForm.organizer_id = '';
  lotForm.vendor_id = '';
  lotForm.location_id = '';
  lotForm.floor_id = null;
  lotForm.room_id = null;
  lotForm.initial_quantity = '';
  lotForm.current_quantity = '';
  lotForm.auto_create_assets = false;
  lotForm.auto_create_assets_count = '';
  lotForm.po_number = '';
  lotForm.date_of_receipt = '';
  lotForm.unit_price = '';
  lotForm.image_url = null;
  lotForm.image_url_name = '';
  lotForm.use_parent_image = false;
  lotForm.total_item = 1;
  lotForm.clearErrors();
  isLotModalOpen.value = true;
};

const openEditLotModal = (lot: any) => {
  lotModalMode.value = 'edit';
  selectedLotId.value = lot.id;
  lotForm.clearErrors();
  
  lotForm._method = 'PUT';
  lotForm.number = lot.lotCode;
  lotForm.barang_id = props.barang.id;
  lotForm.organizer_id = lot.organizer_id;
  lotForm.vendor_id = lot.vendor_id;
  lotForm.location_id = lot.location_id;
  lotForm.floor_id = lot.floor_id;
  lotForm.room_id = lot.room_id;
  lotForm.initial_quantity = lot.initial_quantity ?? '';
  lotForm.current_quantity = lot.current_quantity ?? '';
  lotForm.auto_create_assets = false;
  lotForm.auto_create_assets_count = '';
  lotForm.po_number = lot.poNumber;
  
  if (lot.entryDate && lot.entryDate !== '-') {
    const parts = lot.entryDate.split(/[-/]/);
    if (parts.length === 3) {
      lotForm.date_of_receipt = `${parts[2]}-${parts[1]}-${parts[0]}`;
    } else {
      lotForm.date_of_receipt = '';
    }
  } else {
    lotForm.date_of_receipt = '';
  }
  
  lotForm.unit_price = lot.unitPrice;
  lotForm.image_url = null;
  lotForm.image_url_name = lot.imageUrl ? lot.imageUrl.split('/').pop() : '';
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
  } else if (lotForm.use_parent_image && props.barang.image_url) {
    window.open('/storage/' + props.barang.image_url, '_blank');
  } else if (lotModalMode.value === 'edit') {
    const lot = props.lots.find(l => l.id === selectedLotId.value);
    if (lot && lot.imageUrl) {
      window.open('/storage/' + lot.imageUrl, '_blank');
    }
  }
};

const handleSamakanPhoto = () => {
  if (props.barang.image_url) {
    lotForm.use_parent_image = true;
    lotForm.image_url = null;
    lotForm.image_url_name = props.barang.image_url.split('/').pop() || '';
  } else {
    toast.error('Barang parent tidak memiliki foto.');
  }
};

const filteredFloors = computed(() => {
  if (!lotForm.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(lotForm.location_id));
});

const filteredRooms = computed(() => {
  if (!lotForm.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(lotForm.floor_id));
});

watch(() => lotForm.location_id, (newVal) => {
  if (newVal) {
    const valid = filteredFloors.value.some(f => Number(f.id) === Number(lotForm.floor_id));
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
    const valid = filteredRooms.value.some(r => Number(r.id) === Number(lotForm.room_id));
    if (!valid) {
      lotForm.room_id = null;
    }
  } else {
    lotForm.room_id = null;
  }
});

const isLotFormValid = computed(() => {
  const baseValid = lotForm.number && 
         lotForm.organizer_id && 
         lotForm.vendor_id && 
         lotForm.location_id && 
         lotForm.po_number && 
         lotForm.date_of_receipt && 
         lotForm.unit_price !== '' && 
         (lotForm.image_url || lotForm.image_url_name) &&
         !lotForm.processing;

  if (!baseValid) return false;

  if (lotModalMode.value === 'create') {
    if (props.barang.is_consumable) {
      return lotForm.initial_quantity !== '' && lotForm.initial_quantity !== null && Number(lotForm.initial_quantity) >= 0;
    } else {
      if (lotForm.auto_create_assets) {
        return lotForm.auto_create_assets_count !== '' && lotForm.auto_create_assets_count !== null && Number(lotForm.auto_create_assets_count) >= 1;
      }
      return lotForm.total_item !== null && lotForm.total_item !== undefined && Number(lotForm.total_item) >= 1;
    }
  }

  return true;
});

const handleSaveLot = () => {
  if (!isLotFormValid.value) return;

  const autoCreate = lotForm.auto_create_assets;
  const autoCreateCount = Number(lotForm.auto_create_assets_count);
  const lotNumber = lotForm.number;
  
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
    if (lotModalMode.value === 'create') {
      if (props.barang.is_consumable) {
        formData.initial_quantity = data.initial_quantity;
        formData.current_quantity = data.current_quantity;
      } else {
        formData.auto_create_assets = data.auto_create_assets;
        formData.auto_create_assets_count = data.auto_create_assets_count;
      }
    }
    if (data.image_url) {
      formData.image_url = data.image_url;
    }
    if (data.use_parent_image) {
      formData.use_parent_image = data.use_parent_image;
    }
    if (lotModalMode.value === 'create') {
      formData.total_item = data.total_item;
    }
    return formData;
  });

  if (lotModalMode.value === 'create') {
    lotForm.post('/smart/inventory/lots', {
      onSuccess: (page) => {
        isLotModalOpen.value = false;
        
        if (autoCreate && autoCreateCount > 0) {
          const updatedLots = (page.props as any).lots || props.lots;
          const newLot = updatedLots.find((l: any) => l.lotCode === lotNumber);
          if (newLot) {
            router.post('/smart/inventory/units/bulk', {
              number: `${newLot.lotCode}-U01`,
              lot_id: newLot.id,
              location_id: newLot.location_id,
              floor_id: newLot.floor_id,
              room_id: newLot.room_id,
              status: 'tersedia',
              condition: 'Baik',
              price: Number(newLot.unitPrice),
              use_lot_image: true,
              bulk_quantity: autoCreateCount
            }, {
              onError: (errors) => {
                const errMsg = Object.values(errors).join(', ');
                toast.error(`Gagal membuat unit secara otomatis: ${errMsg}`);
              }
            });
          } else {
            toast.error('Gagal menemukan data LOT yang baru dibuat untuk pembuatan aset otomatis.');
          }
        }
      }
    });
  } else {
    lotForm.post(`/smart/inventory/lots/${selectedLotId.value}`, {
      onSuccess: () => {
        isLotModalOpen.value = false;
      }
    });
  }
};

const columns: ColumnDef<any>[] = [
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
    accessorKey: 'lotCode',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kode LOT',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.getValue('lotCode')),
  },
  {
    accessorKey: 'poNumber',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nomor PO',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 font-medium' }, row.getValue('poNumber')),
  },
  {
    accessorKey: 'entryDate',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Tanggal Masuk',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('entryDate')),
    sortingFn: (rowA, rowB, columnId) => {
      const parseDate = (str: string) => {
        if (!str || str === '-') return 0;
        const parts = str.trim().split(/\s+/);
        const dateParts = parts[0].split('/').map(Number);
        if (dateParts.length !== 3) return 0;
        const [d, m, y] = dateParts;
        return new Date(y, m - 1, d).getTime();
      };
      return parseDate(rowA.getValue(columnId)) - parseDate(rowB.getValue(columnId));
    }
  },
  {
    accessorKey: 'organizer',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Organizer',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('organizer')),
  },
  {
    accessorKey: 'assetCount',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Jml. Stok',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, 
      props.barang.is_consumable 
        ? (row.original.current_quantity !== null && row.original.current_quantity !== undefined ? row.original.current_quantity : 0) 
        : row.getValue('assetCount')
    ),
  },
  {
    id: 'actions',
    size: 100,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => {
      const buttons = [];
      if (props.barang.is_consumable) {
        buttons.push(
          h(ViewTableButton, {
            onClick: () => openDetailLOTConsumables(row.original)
          })
        );
      } else {
        buttons.push(
          h(ViewTableButton, {
            onClick: () => router.get(`/smart/inventory/lots/${row.original.id}`)
          })
        );
      }
      buttons.push(
        h(DeleteTableButton, {
          onClick: () => openDeleteLotModal(row.original),
        })
      );
      return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, buttons);
    }
  },
];

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

const filteredLots = computed(() => {
  let list = props.lots || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(lot => 
      (lot.lotCode && lot.lotCode.toLowerCase().includes(q)) || 
      (lot.poNumber && lot.poNumber.toLowerCase().includes(q))
    );
  }

  if (organizerFilter.value) {
    list = list.filter(lot => lot.organizer === organizerFilter.value);
  }

  if (timeFilter.value) {
    const today = new Date();
    list = list.filter(lot => {
      if (!lot.entryDate || lot.entryDate === '-') return false;
      const parts = lot.entryDate.split(/[-/]/);
      if (parts.length !== 3) return false;
      const entryDateObj = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
      
      if (timeFilter.value === 'Hari ini') {
        return entryDateObj.toDateString() === today.toDateString();
      } else if (timeFilter.value === 'Bulan ini') {
        return entryDateObj.getMonth() === today.getMonth() && entryDateObj.getFullYear() === today.getFullYear();
      }
      return true;
    });
  }

  return list;
});

const uniqueOrganizers = computed(() => {
  const names = (props.lots || []).map(lot => lot.organizer).filter(Boolean);
  return [...new Set(names)];
});

const totalStok = computed(() => {
  return (props.lots || []).reduce((acc, lot) => acc + (Number(lot.current_quantity) || 0), 0);
});

const getExportData = () => {
  if (!dataTableRef.value) return filteredLots.value;
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode LOT', 'Nomor PO', 'Tanggal Masuk', 'Organizer', 'Jml. Stok'];
  const rows = data.map((item: any) => [
    `"${item.lotCode}"`,
    `"${item.poNumber}"`,
    `"${item.entryDate}"`,
    `"${item.organizer}"`,
    `"${props.barang.is_consumable ? (item.current_quantity ?? 0) : item.assetCount}"`
  ]);

  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map((e: any) => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `lot_export_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
};

// Edit Modal Logic
const isEditModalOpen = ref(false);
const editForm = useForm({
  _method: 'PUT',
  kode: props.barang.code,
  kategori: props.barang.category,
  subkategori: props.barang.subcategory,
  satuan: props.barang.uom,
  merek: props.barang.brand,
  spesifikasi: props.barang.specification,
  foto: null as File | null,
  fotoName: props.barang.image_url ? props.barang.image_url.split('/').pop() : '',
  uom_id: props.barang.uom_id,
  brand_id: props.barang.brand_id,
  subcategory_id: props.barang.subcategory_id,
});

const openEditModal = () => {
  editForm.kode = props.barang.code;
  editForm.kategori = props.barang.category;
  editForm.subkategori = props.barang.subcategory;
  editForm.uom_id = props.barang.uom_id;
  editForm.satuan = props.barang.uom;
  editForm.brand_id = props.barang.brand_id;
  editForm.merek = props.barang.brand;
  editForm.spesifikasi = props.barang.specification;
  editForm.foto = null;
  editForm.fotoName = props.barang.image_url ? props.barang.image_url.split('/').pop() : '';
  editForm.clearErrors();
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  isEditModalOpen.value = false;
};

const handleEditFileUpload = (e: any) => {
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

  editForm.foto = file;
  editForm.fotoName = file.name;
};

const triggerEditFileInput = () => {
  const input = document.getElementById('edit-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (editForm.foto) {
    const url = URL.createObjectURL(editForm.foto);
    window.open(url, '_blank');
  } else if (props.barang.image_url) {
    window.open('/storage/' + props.barang.image_url, '_blank');
  }
};

const isEditFormValid = computed(() => {
  return editForm.uom_id && editForm.brand_id && editForm.spesifikasi && !editForm.processing;
});

const handleSaveChanges = () => {
  if (!isEditFormValid.value) return;
  editForm.transform((data) => {
    const formData: any = {
      _method: 'PUT',
      number: data.kode,
      subcategory_id: data.subcategory_id,
      brand_id: data.brand_id,
      uom_id: data.uom_id,
      specification: data.spesifikasi,
    };
    if (data.foto) {
      formData.image_url = data.foto;
    }
    return formData;
  }).post(`/smart/inventory/barangs/${props.barang.id}`, {
    onSuccess: () => {
      closeEditModal();
    },
  });
};

// Bulk Edit LOT Modal Logic
const isBulkEditModalOpen = ref(false);
const selectedLotItem = ref<any | null>(null);
const isInitializingBulkForm = ref(false);

const bulkLotForm = useForm({
  ids: [] as number[],
  organizer_id: '' as string | number,
  vendor_id: '' as string | number,
  location_id: '' as string | number,
  floor_id: null as string | number | null,
  room_id: null as string | number | null,
  unit_price: '' as string | number,
  image_url: null as File | null,
  image_url_name: '',
  use_parent_image: false,
  po_number: '',
  date_of_receipt: '',
});

const openBulkEditModal = () => {
  if (!dataTableRef.value) return;
  const selectedRows = dataTableRef.value.table.getFilteredRowModel().rows
    .filter((r: any) => r.getIsSelected())
    .map((r: any) => r.original);
  
  if (selectedRows.length === 0) return;

  isInitializingBulkForm.value = true;

  bulkLotForm.reset();
  bulkLotForm.clearErrors();
  bulkLotForm.ids = selectedRows.map((r: any) => r.id);
  
  // Explicitly reset the edit values first
  bulkLotForm.organizer_id = '';
  bulkLotForm.vendor_id = '';
  bulkLotForm.location_id = '';
  bulkLotForm.floor_id = null;
  bulkLotForm.room_id = null;
  bulkLotForm.unit_price = '';
  bulkLotForm.image_url = null;
  bulkLotForm.image_url_name = '';
  bulkLotForm.use_parent_image = false;
  bulkLotForm.po_number = '';
  bulkLotForm.date_of_receipt = '';

  if (selectedRows.length === 1) {
    selectedLotItem.value = selectedRows[0];
    bulkLotForm.organizer_id = selectedLotItem.value.organizer_id;
    bulkLotForm.vendor_id = selectedLotItem.value.vendor_id;
    bulkLotForm.location_id = selectedLotItem.value.location_id;
    bulkLotForm.floor_id = selectedLotItem.value.floor_id;
    bulkLotForm.room_id = selectedLotItem.value.room_id;
    bulkLotForm.unit_price = selectedLotItem.value.unitPrice;
    bulkLotForm.po_number = selectedLotItem.value.poNumber || '';
    
    if (selectedLotItem.value.entryDate && selectedLotItem.value.entryDate !== '-') {
      const parts = selectedLotItem.value.entryDate.split(/[-/]/);
      if (parts.length === 3) {
        bulkLotForm.date_of_receipt = `${parts[2]}-${parts[1]}-${parts[0]}`;
      } else {
        bulkLotForm.date_of_receipt = '';
      }
    } else {
      bulkLotForm.date_of_receipt = '';
    }

    bulkLotForm.image_url = null;
    bulkLotForm.image_url_name = selectedLotItem.value.imageUrl ? selectedLotItem.value.imageUrl.split('/').pop() : '';
    bulkLotForm.use_parent_image = false;
  } else {
    selectedLotItem.value = null;
  }
  isBulkEditModalOpen.value = true;
  nextTick(() => {
    isInitializingBulkForm.value = false;
  });
};

const closeBulkEditModal = () => {
  isBulkEditModalOpen.value = false;
  bulkLotForm.ids = [];
  bulkLotForm.organizer_id = '';
  bulkLotForm.vendor_id = '';
  bulkLotForm.location_id = '';
  bulkLotForm.floor_id = null;
  bulkLotForm.room_id = null;
  bulkLotForm.unit_price = '';
  bulkLotForm.image_url = null;
  bulkLotForm.image_url_name = '';
  bulkLotForm.use_parent_image = false;
  bulkLotForm.po_number = '';
  bulkLotForm.date_of_receipt = '';
  bulkLotForm.clearErrors();
  selectedLotItem.value = null;
};

const handleBulkLotFileUpload = (e: any) => {
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

  bulkLotForm.image_url = file;
  bulkLotForm.image_url_name = file.name;
  bulkLotForm.use_parent_image = false;
};

const triggerBulkLotFileInput = () => {
  const input = document.getElementById('bulk-lot-photo-upload') as HTMLInputElement;
  input?.click();
};

const handleBulkLotSamakanPhoto = () => {
  if (props.barang.image_url) {
    bulkLotForm.use_parent_image = true;
    bulkLotForm.image_url = null;
    bulkLotForm.image_url_name = props.barang.image_url.split('/').pop() || '';
  } else {
    toast.error('Barang parent tidak memiliki foto.');
  }
};

const viewBulkLotImageInNewTab = () => {
  if (bulkLotForm.image_url) {
    const url = URL.createObjectURL(bulkLotForm.image_url);
    window.open(url, '_blank');
  } else if (bulkLotForm.use_parent_image && props.barang.image_url) {
    window.open('/storage/' + props.barang.image_url, '_blank');
  } else if (bulkLotForm.ids.length === 1 && selectedLotItem.value && selectedLotItem.value.imageUrl) {
    window.open('/storage/' + selectedLotItem.value.imageUrl, '_blank');
  }
};

const bulkFilteredFloors = computed(() => {
  if (!bulkLotForm.location_id) return [];
  return props.floors.filter(f => Number(f.location_id) === Number(bulkLotForm.location_id));
});

const bulkFilteredRooms = computed(() => {
  if (!bulkLotForm.floor_id) return [];
  return props.rooms.filter(r => Number(r.floor_id) === Number(bulkLotForm.floor_id));
});

watch(() => bulkLotForm.location_id, (newVal) => {
  if (isInitializingBulkForm.value) return;
  console.log('[DEBUG DetailBarang] location_id watch triggered, newVal:', newVal);
  bulkLotForm.floor_id = null;
  bulkLotForm.room_id = null;
});

watch(() => bulkLotForm.floor_id, (newVal) => {
  if (isInitializingBulkForm.value) return;
  console.log('[DEBUG DetailBarang] floor_id watch triggered, newVal:', newVal);
  bulkLotForm.room_id = null;
});

const isBulkLotFormValid = computed(() => {
  if (bulkLotForm.ids.length === 1) {
    return !!(
      bulkLotForm.organizer_id &&
      bulkLotForm.vendor_id &&
      bulkLotForm.location_id &&
      bulkLotForm.po_number &&
      bulkLotForm.date_of_receipt &&
      bulkLotForm.unit_price !== '' &&
      (bulkLotForm.image_url || bulkLotForm.use_parent_image || bulkLotForm.image_url_name) &&
      !bulkLotForm.processing
    );
  } else {
    const hasAtLeastOneField = !!(
      bulkLotForm.organizer_id ||
      bulkLotForm.vendor_id ||
      bulkLotForm.location_id ||
      bulkLotForm.floor_id ||
      bulkLotForm.room_id ||
      bulkLotForm.unit_price !== '' ||
      bulkLotForm.image_url ||
      bulkLotForm.use_parent_image
    );
    return hasAtLeastOneField && !bulkLotForm.processing;
  }
});

const handleSaveBulkChanges = () => {
  if (!isBulkLotFormValid.value) return;

  bulkLotForm.transform((data) => {
    const formData: any = {
      _method: 'PUT',
      ids: data.ids,
    };
    if (data.ids.length === 1) {
      formData.organizer_id = data.organizer_id;
      formData.vendor_id = data.vendor_id;
      formData.location_id = data.location_id;
      formData.floor_id = data.floor_id;
      formData.room_id = data.room_id;
      formData.po_number = data.po_number;
      formData.date_of_receipt = data.date_of_receipt;
      formData.unit_price = data.unit_price;
      if (data.image_url) {
        formData.image_url = data.image_url;
      }
      if (data.use_parent_image) {
        formData.use_parent_image = data.use_parent_image;
      }
    } else {
      if (data.organizer_id) formData.organizer_id = data.organizer_id;
      if (data.vendor_id) formData.vendor_id = data.vendor_id;
      if (data.location_id) {
        formData.location_id = data.location_id;
        formData.floor_id = data.floor_id;
        formData.room_id = data.room_id;
      } else {
        if (data.floor_id) formData.floor_id = data.floor_id;
        if (data.room_id) formData.room_id = data.room_id;
      }
      if (data.unit_price !== '') formData.unit_price = data.unit_price;
      if (data.image_url) formData.image_url = data.image_url;
      if (data.use_parent_image) formData.use_parent_image = data.use_parent_image;
    }
    return formData;
  }).post('/smart/inventory/lots/bulk', {
    onSuccess: () => {
      closeBulkEditModal();
      if (dataTableRef.value) {
        dataTableRef.value.table.resetRowSelection();
      }
    },
  });
};

// Delete Modal Logic
const isDeleteModalOpen = ref(false);
const deleteMode = ref<'barang' | 'lot'>('barang');
const itemsToDelete = ref<any[]>([]);

const deleteFields = computed(() => {
  if (deleteMode.value === 'barang') {
    return null;
  }
  
  if (deleteMode.value === 'lot' && itemsToDelete.value.length === 1) {
    const data = itemsToDelete.value[0];
    
    // Helpers
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
    
    const formatRupiah = (val: number | string | null | undefined) => {
      if (val === null || val === undefined || val === '') return 'Rp0';
      const num = typeof val === 'string' ? parseFloat(val) : val;
      if (isNaN(num)) return 'Rp0';
      const formatted = Math.floor(num).toLocaleString('id-ID');
      return `Rp${formatted}`;
    };

    const isConsumable = props.barang.is_consumable;
    const availableStock = isConsumable ? (data.current_quantity ?? 0) : (data.availableAssetCount ?? 0);
    const initialStock = isConsumable ? (data.initial_quantity ?? 0) : (data.assetCount ?? 0);

    const fields = [
      { label: 'Kode LOT', value: data.lotCode },
      { label: 'Kategori', value: props.barang.category },
      { label: 'Subkategori', value: props.barang.subcategory },
      { label: 'Merek', value: props.barang.brand },
      { label: 'Spesifikasi', value: props.barang.specification },
      { label: 'Jumlah stok tersedia', value: availableStock },
      { label: 'Jumlah stok diawal', value: initialStock },
      { label: 'Lokasi', value: formatLocation(data.location, data.floor, data.room) },
      { label: 'Nomor PO', value: data.poNumber },
      { label: 'Tanggal masuk', value: formatDateWithDashes(data.entryDate) },
      { label: 'Harga satuan', value: formatRupiah(data.unitPrice) },
      { label: 'Organizer', value: data.organizer },
      { label: 'Vendor', value: data.vendor },
      { label: 'Pembaruan Terakhir', value: data.updated_at || '-' }
    ];
    
    return fields;
  }
  
  return null;
});

const openDeleteModal = () => {
  deleteMode.value = 'barang';
  itemsToDelete.value = [{
    id: props.barang.id,
    code: props.barang.code,
    category: props.barang.category,
    subcategory: props.barang.subcategory,
    brand: props.barang.brand,
    specification: props.barang.specification,
    lastUpdate: props.barang.lastUpdate,
    amount: 0
  }];
  isDeleteModalOpen.value = true;
};

const openDeleteLotModal = (lots: any | any[]) => {
  deleteMode.value = 'lot';
  const rawLots = Array.isArray(lots) ? lots : [lots];
  itemsToDelete.value = rawLots.map((lot: any) => ({
    ...lot,
    barang_code: lot.barang_code || props.barang.code,
    barang_brand: lot.barang_brand || props.barang.brand,
    barang_specification: lot.barang_specification || props.barang.specification,
    barang_category: lot.barang_category || props.barang.category,
    barang_subcategory: lot.barang_subcategory || props.barang.subcategory,
    barang_uom: lot.barang_uom || props.barang.uom,
    is_consumable: lot.is_consumable !== undefined ? lot.is_consumable : props.barang.is_consumable,
  }));
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  itemsToDelete.value = [];
};

const handleConfirmDelete = () => {
  if (itemsToDelete.value.length === 0) return;

  const ids = itemsToDelete.value.map(item => item.id);
  
  if (deleteMode.value === 'barang') {
    router.delete(`/smart/inventory/barangs/${ids[0]}`, {
      onSuccess: () => {
        closeDeleteModal();
      }
    });
  } else {
    if (ids.length === 1) {
      router.delete(`/smart/inventory/lots/${ids[0]}`, {
        onSuccess: () => {
          closeDeleteModal();
          if (dataTableRef.value) {
            dataTableRef.value.table.resetRowSelection();
          }
        }
      });
    } else {
      router.delete('/smart/inventory/lots/bulk', {
        data: { ids },
        onSuccess: () => {
          closeDeleteModal();
          if (dataTableRef.value) {
            dataTableRef.value.table.resetRowSelection();
          }
        }
      });
    }
  }
};

// Flash Notifications
const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);

watch(flashSuccess, (newVal) => {
  if (newVal) {
    toast.success(newVal);
    if ((page.props as any).flash) {
      (page.props as any).flash.success = null;
    }
  }
}, { immediate: true });

const flashError = computed(() => (page.props as any).flash?.error);
const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

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
</script>

<template>
  <AppLayout title="Detail Barang">
    <!-- Breadcrumb -->
    <Breadcrumb class="no-print">
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory">Manajemen Stok</BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <span class="text-muted-foreground">{{ props.barang.code }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-2 no-print">
      <Tabs v-model="activeTab" :tabs="tabs" />

      <div class="flex items-center gap-3">
        <button @click="openEditModal" class="flex items-center gap-1.5 bg-gradient-primary hover:opacity-90 text-primary-foreground px-5 py-2.5 rounded-[14px] text-sm font-bold">
          Edit Detail Barang
        </button>
        <button @click="openDeleteModal" class="flex items-center gap-1.5 bg-destructive hover:opacity-70 text-primary-foreground px-5 py-2.5 rounded-[14px] text-sm font-bold">
          Hapus Barang
        </button>
      </div>
    </div>

    <div class="space-y-4">
      <!-- Detail Barang Card -->
      <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
        <h2 class="text-lg font-bold text-foreground mb-4">Detail Barang</h2>
        
        <div class="flex flex-col md:flex-row gap-6">
          <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
            <img v-if="props.barang.image_url" :src="'/storage/' + props.barang.image_url" class="w-full h-full object-cover" />
            <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
          </div>

          <div class="flex-grow">
            <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ props.barang.code }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.barang.brand }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.barang.specification }}</p>
            <p class="text-foreground">Kategori: {{ props.barang.category }}</p>
            <p class="text-foreground">Subkategori: {{ props.barang.subcategory }}</p>
            <p class="text-foreground">Jumlah LOT: {{ props.lots.length }}</p>
            <p class="text-foreground">Total stok: {{ totalStok }}</p>
            <p class="text-foreground">Satuan: {{ props.barang.uom }}</p>
            <p class="text-foreground">Pembaruan terakhir: {{ props.barang.lastUpdate }}</p>
          </div>
        </div>
      </div>

      <!-- Daftar LOT Card -->
      <div class="bg-card rounded-xl border border-border px-4 py-3 shadow-sm overflow-hidden">
        <div class="no-print">
          <h2 class="text-lg font-bold text-foreground mb-4">Daftar LOT</h2>

          <!-- Filters Row -->
          <div class="mb-4 flex flex-wrap items-end gap-4">
            <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
              <TableSearch 
                v-model="searchQuery"
                placeholder="Cari Kode LOT atau nomor PO..." 
              />
            </div>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ timeFilter || 'Semua kurun waktu' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="timeFilter = ''">Semua kurun waktu</DropdownMenuItem>
                <DropdownMenuItem @select="timeFilter = 'Hari ini'">Hari ini</DropdownMenuItem>
                <DropdownMenuItem @select="timeFilter = 'Bulan ini'">Bulan ini</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !organizerFilter ? 'text-muted-foreground' : 'text-foreground']">
                  <span class="truncate">{{ organizerFilter || 'Semua organizer' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="organizerFilter = ''">Semua organizer</DropdownMenuItem>
                <DropdownMenuItem v-for="org in uniqueOrganizers" :key="org" @select="organizerFilter = org">
                  {{ org }}
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
                <button 
                  @click="openBulkEditModal"
                  :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                  class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm disabled:opacity-50 font-bold"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">Edit Terpilih</span>
                </button>
                <button 
                  @click="openDeleteLotModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
                  :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                  class="flex items-center gap-2 px-4 py-2 bg-destructive hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm disabled:opacity-50 font-bold"
                >
                  <Trash2 class="w-4 h-4" />
                  <span class="hidden sm:inline">Hapus Terpilih</span>
                </button>
                <ExportButtonGroup 
                  @export-excel="handleExportExcel"
                  @export-csv="handleExportCSV"
                />
              </div>
            </div>
            
            <button @click="openCreateLotModal" class="px-5 py-2.5 bg-gradient-primary hover:opacity-90 text-white text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
              <Plus class="w-4 h-4" />
              LOT Baru
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-2">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="filteredLots" 
            :filter-value="searchQuery"
            :default-sorting="[{ id: 'entryDate', desc: true }]"
          />
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isEditModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isEditModalOpen" 
              class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Edit Detail Barang</h3>
                <button @click="closeEditModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kode Barang</label>
                      <input 
                        type="text" 
                        v-model="editForm.kode"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kategori</label>
                      <input 
                        type="text" 
                        v-model="editForm.kategori"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Subkategori</label>
                      <input 
                        type="text" 
                        v-model="editForm.subkategori"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Satuan<span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="editForm.uom_id"
                        :options="props.uoms"
                        search-placeholder="Cari satuan..."
                        default-label="Pilih satuan"
                        width-class="w-full h-10 px-4"
                      />
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Merek<span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="editForm.brand_id"
                        :options="props.brands"
                        search-placeholder="Cari merek..."
                        default-label="Pilih merek"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Spesifikasi<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="editForm.spesifikasi"
                        maxlength="255"
                        placeholder="Input spesifikasinya di sini..." 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Foto <span class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div 
                          class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[
                            (editForm.foto || props.barang.image_url) 
                              ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                              : 'text-muted-foreground cursor-default'
                          ]"
                          @click="(editForm.foto || props.barang.image_url) && viewImageInNewTab()"
                        >
                          {{ editForm.fotoName || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="edit-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleEditFileUpload"
                        />
                        <button 
                          @click="triggerEditFileInput"
                          class="w-[120px] shrink-0 flex items-center justify-center bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors h-10"
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
                    @click="closeEditModal"
                    class="px-8 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleSaveChanges"
                    :disabled="!isEditFormValid"
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

    <!-- Add/Edit LOT Modal -->
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
                <h3 class="text-lg font-bold text-foreground">
                  {{ lotModalMode === 'create' ? 'Tambah LOT Baru' : 'Edit LOT' }}
                </h3>
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
                        placeholder="Kode LOT belum di-generate"
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
                      <label class="text-sm font-medium text-foreground block">Lokasi <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="lotForm.location_id"
                        :options="props.locations"
                        search-placeholder="Cari lokasi..."
                        default-label="Pilih lokasi"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Lantai <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span></label>
                      <Combobox
                        v-model="lotForm.floor_id"
                        :options="filteredFloors"
                        search-placeholder="Cari lantai..."
                        default-label="Pilih lantai (opsional)"
                        width-class="w-full h-10 px-4"
                        :disabled="!lotForm.location_id"
                      />
                    </div>

                    <!-- Conditional Stock Input / Auto Asset Creation Field (Create Mode Only) -->
                    <template v-if="lotModalMode === 'create'">
                      <div v-if="props.barang.is_consumable" class="space-y-1.5">
                        <label class="text-sm font-medium text-foreground block">Jumlah stok<span class="text-rose-500">*</span></label>
                        <input 
                          type="number" 
                          v-model="lotForm.initial_quantity"
                          placeholder="Contoh: 10"
                          min="0"
                          class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                          @input="lotForm.current_quantity = lotForm.initial_quantity"
                        />
                      </div>
                      <div v-else-if="props.barang.category !== 'Kendaraan'" class="space-y-1.5 flex items-center gap-2 pt-2 flex-wrap">
                        <Checkbox 
                          id="auto-create-checkbox"
                          v-model="lotForm.auto_create_assets"
                        />
                        <label for="auto-create-checkbox" class="cursor-pointer select-none text-sm font-medium text-foreground">
                          Buat
                        </label>
                        <input 
                          type="number" 
                          v-model="lotForm.auto_create_assets_count"
                          placeholder="..."
                          min="1"
                          :disabled="!lotForm.auto_create_assets"
                          class="w-16 px-2 py-1 text-sm border border-input rounded-[10px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-8 disabled:opacity-50 disabled:cursor-not-allowed mx-1"
                        />
                        <span class="text-sm font-medium text-foreground">aset secara otomatis dengan nilai default.</span>
                      </div>
                    </template>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Ruangan <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span></label>
                      <Combobox
                        v-model="lotForm.room_id"
                        :options="filteredRooms"
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
                      <label class="text-sm font-medium text-foreground block">Harga Satuan <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
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
                      <label class="text-sm font-medium text-foreground block">Foto <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
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
                    {{ lotModalMode === 'create' ? 'Tambah LOT' : 'Simpan Perubahan' }}
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <!-- Bulk Edit LOT Modal -->
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
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">
                  {{ bulkLotForm.ids.length === 1 ? 'Edit LOT' : 'Edit LOT Terpilih' }}
                </h3>
                <button @click="closeBulkEditModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kode LOT</label>
                      <input 
                        type="text" 
                        :value="bulkLotForm.ids.length === 1 && selectedLotItem ? selectedLotItem.lotCode : 'Tidak dapat diubah'"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Organizer<span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !bulkLotForm.organizer_id ? 'text-muted-foreground' : 'text-foreground']">
                            {{ props.organizers.find(o => o.id == bulkLotForm.organizer_id)?.name || 'Pilih organizer' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="org in props.organizers" :key="org.id" @select="bulkLotForm.organizer_id = org.id">
                            {{ org.name }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Vendor<span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="bulkLotForm.vendor_id"
                        :options="props.vendors"
                        search-placeholder="Cari vendor..."
                        default-label="Pilih vendor"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Lokasi <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span><span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="bulkLotForm.location_id"
                        :options="props.locations"
                        search-placeholder="Cari lokasi..."
                        default-label="Pilih lokasi"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Lantai <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span></label>
                      <Combobox
                        v-model="bulkLotForm.floor_id"
                        :options="bulkFilteredFloors"
                        search-placeholder="Cari lantai..."
                        default-label="Pilih lantai (opsional)"
                        width-class="w-full h-10 px-4"
                        :disabled="!bulkLotForm.location_id"
                      />
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Ruangan <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span></label>
                      <Combobox
                        v-model="bulkLotForm.room_id"
                        :options="bulkFilteredRooms"
                        search-placeholder="Cari ruangan..."
                        default-label="Pilih ruangan (opsional)"
                        width-class="w-full h-10 px-4"
                        :disabled="!bulkLotForm.floor_id"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Nomor PO<span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="bulkLotForm.po_number"
                        :disabled="bulkLotForm.ids.length > 1"
                        :placeholder="bulkLotForm.ids.length > 1 ? 'Tidak dapat diubah' : 'Contoh: PO-02'"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Tanggal Masuk<span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <input 
                        type="date" 
                        v-model="bulkLotForm.date_of_receipt"
                        :disabled="bulkLotForm.ids.length > 1"
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Harga Satuan <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span><span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <div class="flex w-full rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">
                          Rp
                        </span>
                        <input 
                          type="number" 
                          v-model="bulkLotForm.unit_price"
                          placeholder="Contoh: 60000"
                          min="0"
                          class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                        />
                      </div>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Foto <span v-if="!props.barang.is_consumable" class="italic text-muted-foreground">default</span><span v-if="bulkLotForm.ids.length === 1" class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div 
                          class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                          :class="[
                            (bulkLotForm.image_url || bulkLotForm.image_url_name) 
                              ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                              : 'text-muted-foreground cursor-default'
                          ]"
                          @click="(bulkLotForm.image_url || bulkLotForm.image_url_name) && viewBulkLotImageInNewTab()"
                        >
                          {{ bulkLotForm.image_url_name || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="bulk-lot-photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleBulkLotFileUpload"
                        />
                        <button 
                          type="button"
                          @click="handleBulkLotSamakanPhoto"
                          class="w-[90px] shrink-0 flex items-center justify-center bg-orange-400 hover:opacity-90 text-white text-sm font-medium rounded-[14px] transition-colors h-10"
                        >
                          Samakan
                        </button>
                        <button 
                          type="button"
                          @click="triggerBulkLotFileInput"
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
                <p class="text-sm text-rose-500 italic font-medium">
                  {{ bulkLotForm.ids.length === 1 ? '*Wajib diisi' : '*Kosongkan input yang tidak ingin diubah' }}
                </p>
                <div class="flex items-center gap-3">
                  <button 
                    @click="closeBulkEditModal"
                    class="px-8 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleSaveBulkChanges"
                    :disabled="!isBulkLotFormValid"
                    class="px-8 py-2.5 bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98] disabled:opacity-50"
                  >
                    {{ bulkLotForm.ids.length === 1 ? 'Simpan Perubahan' : 'Simpan Perubahan Massal' }}
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="itemsToDelete.length"
      :item-name="'Barang'"
      :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : null"
      :fields="deleteFields"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />
    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
    <DetailLOTConsumables 
      :is-open="isDetailConsumablesOpen"
      :lot-id="selectedLotForDetail"
      @close="isDetailConsumablesOpen = false"
      @edit="openEditLotModal"
      @delete="openDeleteLotModal"
    />
  </AppLayout>
</template>
