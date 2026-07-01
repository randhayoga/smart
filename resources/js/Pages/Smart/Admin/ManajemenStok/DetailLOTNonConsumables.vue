<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed, h, nextTick } from 'vue';
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
import StatusBadge from '@/Components/StatusBadge.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import EditLotModal from './Modals/EditLotModal.vue';
import CreateAssetModal from './Modals/CreateAssetModal.vue';
import EditAssetModal from './Modals/EditAssetModal.vue';

interface Props {
  lot: {
    id: number;
    number: string;
    po_number: string;
    date_of_receipt: string;
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
    barang_nama: string;
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
const openEditLotModal = () => {
  isLotModalOpen.value = true;
};

// View Asset Modal Setup
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);
const openViewAssetModal = (asset: any) => {
  selectedAssetForView.value = asset;
  isViewAssetModalOpen.value = true;
};

// Asset Modal Setup (Units)
const isCreateAssetModalOpen = ref(false);
const isEditAssetModalOpen = ref(false);
const selectedAssetsForEdit = ref<any[]>([]);

const isVehicle = computed(() => {
  const category = (props.lot.barang_category || '').toLowerCase();
  const subcategory = (props.lot.barang_subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
});

const openCreateAssetModal = () => {
  isCreateAssetModalOpen.value = true;
};

const openEditAssetModal = (asset: any) => {
  const assetObj = (asset && typeof asset === 'object' && 'value' in asset) ? asset.value : asset;
  if (!assetObj) return;
  selectedAssetsForEdit.value = [assetObj];
  isEditAssetModalOpen.value = true;
};

const handleEditTerpilih = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length === 1) {
    openEditAssetModal(selectedRows[0].original);
  } else if (selectedRows.length > 1) {
    selectedAssetsForEdit.value = selectedRows.map((row: any) => row.original);
    isEditAssetModalOpen.value = true;
  }
};

const handleAssetSuccess = () => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
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
  link.setAttribute("download", `daftar_aset_lot_${props.lot.number}_${new Date().getTime()}.csv`);
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
    list = list.filter(u => (u.status || '').toLowerCase() === statusFilter.value.toLowerCase());
  }

  if (conditionFilter.value) {
    list = list.filter(u => (u.condition || '').toLowerCase() === conditionFilter.value.toLowerCase());
  }

  return list;
});

// Dynamic values for dropdown filters
const availableStatuses = ['Tersedia', 'Dipinjam', 'Perbaikan', 'Rusak Total', 'Hilang', 'Tidak Aktif'];
const availableConditions = ['Baik', 'Kurang Baik', 'Rusak'];

const getStatusLabel = (status: string) => {
  return status || '';
};

const getConditionLabel = (cond: string) => {
  return cond || '';
};

const mapStatusToBackend = (status: string) => {
  return status;
};

const mapConditionToBackend = (cond: string) => {
  return cond;
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
        const status = row.getValue('status') as string || '';
        const proposedStatus = row.original.proposed_status;
        return h(StatusBadge, {
          status,
          proposedStatus,
          class: 'rounded-sm'
        });
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
        const cond = row.getValue('condition') as string || '';
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
          h(Button, {
            variant: 'table-view',
            size: 'icon-sm',
            title: 'Lihat Detail',
            onClick: () => openViewAssetModal(row.original)
          }, () => [
            h(Eye),
            h('span', { class: 'sr-only' }, 'Lihat Detail')
          ])
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
  document.addEventListener('keydown', closeOnEscape);
});

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

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

// Deletion Modals Logic
const isDeleteModalOpen = ref(false);
const deleteMode = ref<'lot' | 'asset'>('lot');
const lotToDelete = ref<any>(null);
const assetToDelete = ref<any>(null);

const deleteFields = computed(() => {
  if (deleteMode.value === 'lot') {
    return [
      { label: 'Kode LOT', value: props.lot.number },
      { label: 'Lokasi', value: formatLocation(props.lot.location, props.lot.floor, props.lot.room) },
      { label: 'Tanggal registrasi', value: formatDateWithDashes(props.lot.date_of_receipt) },
      { label: 'Harga default', value: formatRupiah(props.lot.unitPrice) },
    ];
  }
  if (deleteMode.value === 'asset' && assetToDelete.value) {
    const data = assetToDelete.value;
    return [
      { label: 'Kode Aset', value: data.number },
      { label: 'Lokasi', value: formatLocation(data.location, data.floor, data.room) },
      { label: 'Tanggal registrasi', value: formatDateWithDashes(props.lot.date_of_receipt) },
      { label: 'Harga', value: formatRupiah(data.price) },
    ];
  }
  return [];
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
};

const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

const handleConfirmDelete = () => {
  if (deleteMode.value === 'lot') {
    router.delete(`/smart/inventory/lots/${props.lot.id}`, {
      onSuccess: () => {
        closeDeleteModal();
      },
      onError: (err) => {
        if ((page.props as any).flash?.error) {
          closeDeleteModal();
        }
      }
    });
  } else if (deleteMode.value === 'asset' && assetToDelete.value) {
    router.delete(`/smart/inventory/units/${assetToDelete.value.id}`, {
      onSuccess: () => {
        closeDeleteModal();
      }
    });
  }
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isLotModalOpen.value) {
      isLotModalOpen.value = false;
    } else if (isCreateAssetModalOpen.value) {
      isCreateAssetModalOpen.value = false;
    } else if (isEditAssetModalOpen.value) {
      isEditAssetModalOpen.value = false;
    } else if (isViewAssetModalOpen.value) {
      isViewAssetModalOpen.value = false;
    } else if (isDeleteModalOpen.value) {
      closeDeleteModal();
    } else if (isErrorModalOpen.value) {
      closeErrorModal();
    }
  }
};

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
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
          <span class="text-muted-foreground">{{ props.lot.number }}</span>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-2 no-print">
      <Tabs v-model="activeTab" :tabs="tabs" />

      <div class="flex items-center gap-3">
        <Button @click="openEditLotModal" variant="primary" size="lg">
          Edit Detail LOT
        </Button>
        <Button @click="openDeleteLotModal" variant="destructive" size="lg">
          Hapus LOT
        </Button>
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
              <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.lot.barang_nama }}</p>
              <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.lot.barang_specification }}</p>
              <p class="text-foreground">Kategori: {{ props.lot.barang_category }}</p>
              <p class="text-foreground">Subkategori: {{ props.lot.barang_subcategory }}</p>
              <p class="text-foreground">Satuan: {{ props.lot.barang_uom }}</p>
            </div>
            <div class="md:col-span-8">
              <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ props.lot.number }}</p>
              <p class="text-foreground">Jumlah stok tersedia: {{ props.units.filter(u => u.status === 'Tersedia').length }}</p>
              <p class="text-foreground">Jumlah stok diawal: {{ props.units.length }}</p>
              <p class="text-foreground">Lokasi <span class="italic">default</span>: {{ formatLocation(props.lot.location, props.lot.floor, props.lot.room) }}</p>
              <p class="text-foreground">Nomor PO: {{ props.lot.po_number }}</p>
              <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(props.lot.date_of_receipt) }}</p>
              <p class="text-foreground">Harga satuan <span class="italic">default</span>: {{ formatRupiah(props.lot.unitPrice) }}</p>
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
                  <span class="truncate">{{ conditionFilter ? getConditionLabel(conditionFilter) : 'Semua kondisi' }}</span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="conditionFilter = ''">Semua kondisi</DropdownMenuItem>
                <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="conditionFilter = cond">
                  {{ getConditionLabel(cond) }}
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
                <Button 
                  @click="handleEditTerpilih()"
                  :disabled="totalAsetTerpilihCount === 0"
                  variant="more-round-warning"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">Edit Terpilih</span>
                </Button>
                <ExportButtonGroup 
                  @export-excel="handleExportExcel"
                  @export-csv="handleExportCSV"
                />
              </div>
            </div>
            
            <Button @click="openCreateAssetModal" variant="primary" size="lg">
              <Plus class="w-4 h-4" />
              <span>Aset Baru</span>
            </Button>
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

    <EditLotModal
      v-model:open="isLotModalOpen"
      :items="[props.lot]"
      :isConsumable="false"
      :parentImageUrl="(page.props as any).lot?.parent_image || null"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
    />

    <CreateAssetModal
      v-model:open="isCreateAssetModalOpen"
      :lot="props.lot"
      :units="props.units"
      :barang="{ category: props.lot.barang_category }"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      @success="handleAssetSuccess"
    />

    <EditAssetModal
      v-model:open="isEditAssetModalOpen"
      :items="selectedAssetsForEdit"
      :lot="props.lot"
      :barang="{ category: props.lot.barang_category }"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      @success="handleAssetSuccess"
    />

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
        <div v-if="isViewAssetModalOpen" @click="isViewAssetModalOpen = false" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
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
              class="bg-card w-full md:max-w-[90%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Detail Aset</h3>
                <button @click="isViewAssetModalOpen = false" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
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
                  <div v-if="selectedAssetForView" class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4 text-foreground">
                    <!-- Column 1: Item Info -->
                    <div class="md:col-span-3">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ props.lot.barang_code }}</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.lot.barang_brand }}</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.lot.barang_nama }}</p>
                      <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.lot.barang_specification }}</p>
                      <p class="text-foreground">Kategori: {{ props.lot.barang_category }}</p>
                      <p class="text-foreground">Subkategori: {{ props.lot.barang_subcategory }}</p>
                      <p class="text-foreground">Satuan: {{ props.lot.barang_uom }}</p>
                    </div>

                    <!-- Column 2: LOT Info -->
                    <div class="md:col-span-4">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ props.lot.number }}</p>
                      <p class="text-foreground">Organizer: {{ props.lot.organizer }}</p>
                      <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(props.lot.date_of_receipt) }}</p>
                      <p class="text-foreground">Vendor: {{ props.lot.vendor }}</p>
                      <p class="text-foreground">Nomor PO: {{ props.lot.po_number }}</p>
                    </div>

                    <!-- Column 3: Asset Info -->
                    <div class="md:col-span-5">
                      <p class="font-bold text-foreground"><span class="text-foreground">Kode Aset:</span> {{ selectedAssetForView.number }}</p>
                      <!-- TNKB (Nopol) -->
                      <p v-if="isVehicle" class="font-bold text-foreground">
                        <span class="text-foreground">Nopol:</span> {{ selectedAssetForView.vehicle_registration || '-' }}
                      </p>
                      <p class="text-foreground">
                        Status: 
                        <StatusBadge 
                          :status="selectedAssetForView.status" 
                          :proposed-status="selectedAssetForView.proposed_status" 
                        />
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
                          {{ getConditionLabel(selectedAssetForView.condition) }}
                        </span>
                      </p>
                      <p class="text-foreground">Nilai: {{ formatRupiah(selectedAssetForView.price) }}</p>
                      <p class="text-foreground">Lokasi penyimpanan: {{ formatLocation(selectedAssetForView.location, selectedAssetForView.floor, selectedAssetForView.room) }}</p>
                      <p class="text-foreground">Pembaruan terakhir: {{ selectedAssetForView.updated_at || '-' }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10">
                <Button 
                  @click="
                    isViewAssetModalOpen = false;
                    openEditAssetModal(selectedAssetForView);
                  "
                  variant="primary"
                  size="lg"
                >
                  Edit Detail Aset
                </Button>

                <Button 
                  @click="isViewAssetModalOpen = false"
                  variant="white"
                  size="lg"
                >
                  Kembali
                </Button>
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
