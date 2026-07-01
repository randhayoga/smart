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
  Pencil,
  Trash2,
  Eye
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

import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Combobox from '@/Components/Combobox.vue';
import { Checkbox } from '@/Components/ui/checkbox';
import DetailLOTConsumables from './DetailLOTConsumables.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import EditTipeModal from './Modals/EditTipeModal.vue';
import CreateLotModal from './Modals/CreateLotModal.vue';
import EditLotModal from './Modals/EditLotModal.vue';

interface Props {
  itemId: string | number;
  barang: {
    id: number;
    code: string;
    category: string;
    subcategory: string;
    brand: string;
    name: string;
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
const isCreateLotModalOpen = ref(false);
const isBulkEditModalOpen = ref(false);
const selectedLotsForEdit = ref<any[]>([]);

const isDetailConsumablesOpen = ref(false);
const selectedLotForDetail = ref<number | null>(null);

const openDetailLOTConsumables = (lot: any) => {
  selectedLotForDetail.value = lot.id;
  isDetailConsumablesOpen.value = true;
};

const generateLotCode = () => {
  const year = new Date().getFullYear();
  const barangCode = props.barang.code;
  const prefix = `LOT-${year}-${barangCode}-`;
  
  const matchingLots = (props.lots || []).filter(lot => lot.number.startsWith(prefix));
  
  let nextNum = 1;
  if (matchingLots.length > 0) {
    const numbers = matchingLots.map(lot => {
      const suffix = lot.number.replace(prefix, '');
      return parseInt(suffix, 10) || 0;
    });
    nextNum = Math.max(...numbers) + 1;
  }
  
  const paddedNum = String(nextNum).padStart(4, '0');
  return `${prefix}${paddedNum}`;
};

const openCreateLotModal = () => {
  isCreateLotModalOpen.value = true;
};

const openEditLotModal = (lot: any) => {
  selectedLotsForEdit.value = [{ ...lot, barang_id: lot.barang_id || props.barang.id }];
  isBulkEditModalOpen.value = true;
};

const handleEditSuccess = () => {
  if (dataTableRef.value) {
    dataTableRef.value.table.resetRowSelection();
  }
};

const formatDateWithSlashes = (dateStr: string | null) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
  }
  return dateStr.replace(/-/g, '/');
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
    accessorKey: 'number',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kode LOT',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.original.number),
  },
  {
    accessorKey: 'po_number',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nomor PO',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 font-medium' }, row.original.po_number),
  },
  {
    accessorKey: 'date_of_receipt',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Tanggal Registrasi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, formatDateWithSlashes(row.original.date_of_receipt)),
    sortingFn: (rowA, rowB) => {
      const valA = String(rowA.original.date_of_receipt || '');
      const valB = String(rowB.original.date_of_receipt || '');
      return valA.localeCompare(valB);
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
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.original.organizer),
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
        : row.original.assetCount
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
          h(Button, {
            variant: 'table-view',
            size: 'icon-sm',
            title: 'Lihat Detail',
            onClick: () => openDetailLOTConsumables(row.original)
          }, () => [
            h(Eye),
            h('span', { class: 'sr-only' }, 'Lihat Detail')
          ])
        );
      } else {
        buttons.push(
          h(Button, {
            variant: 'table-view',
            size: 'icon-sm',
            title: 'Lihat Detail',
            onClick: () => router.get(`/smart/inventory/lots/${row.original.id}`)
          }, () => [
            h(Eye),
            h('span', { class: 'sr-only' }, 'Lihat Detail')
          ])
        );
      }
      buttons.push(
        h(Button, {
          variant: 'table-destructive',
          size: 'icon-sm',
          title: 'Hapus',
          onClick: () => openDeleteLotModal(row.original),
        }, () => [
          h(Trash2),
          h('span', { class: 'sr-only' }, 'Hapus')
        ])
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
      (lot.number && lot.number.toLowerCase().includes(q)) || 
      (lot.po_number && lot.po_number.toLowerCase().includes(q))
    );
  }

  if (organizerFilter.value) {
    list = list.filter(lot => lot.organizer === organizerFilter.value);
  }

  if (timeFilter.value) {
    const today = new Date();
    list = list.filter(lot => {
      if (!lot.date_of_receipt) return false;
      const entryDateObj = new Date(lot.date_of_receipt);
      if (isNaN(entryDateObj.getTime())) return false;
      
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
  const isConsumable = props.barang.is_consumable;
  return (props.lots || []).reduce((acc, lot) => {
    const qty = isConsumable ? (lot.current_quantity ?? 0) : (lot.assetCount ?? 0);
    return acc + Number(qty);
  }, 0);
});

const getExportData = () => {
  if (!dataTableRef.value) return filteredLots.value;
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode LOT', 'Nomor PO', 'Tanggal Registrasi', 'Organizer', 'Jml. Stok'];
  const rows = data.map((item: any) => [
    `"${item.number}"`,
    `"${item.po_number}"`,
    `"${formatDateWithSlashes(item.date_of_receipt)}"`,
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

const isEditModalOpen = ref(false);
const openEditModal = () => {
  isEditModalOpen.value = true;
};

const handleEditTerpilih = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length === 1) {
    openEditLotModal(selectedRows[0].original);
  } else if (selectedRows.length > 1) {
    selectedLotsForEdit.value = selectedRows.map((row: any) => row.original);
    isBulkEditModalOpen.value = true;
  }
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
      if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
        const [y, m, d] = dateStr.split('-');
        return `${d}-${m}-${y}`;
      }
      return dateStr.replace(/\//g, '-');
    };
    
    const formatRupiah = (val: number | string | null | undefined) => {
      if (val === null || val === undefined || val === '') return '-';
      const num = typeof val === 'string' ? parseFloat(val) : val;
      if (isNaN(num)) return '-';
      const formatted = Math.floor(num).toLocaleString('id-ID');
      return `Rp${formatted}`;
    };

    const isConsumable = props.barang.is_consumable;
    const availableStock = isConsumable ? (data.current_quantity ?? 0) : (data.availableAssetCount ?? 0);
    const initialStock = isConsumable ? (data.initial_quantity ?? 0) : (data.assetCount ?? 0);

    const fields = [
      { label: 'Kode LOT', value: data.number },
      { label: 'Kategori', value: props.barang.category },
      { label: 'Subkategori', value: props.barang.subcategory },
      { label: 'Merek', value: props.barang.brand },
      { label: 'Nama', value: props.barang.name },
      { label: 'Spesifikasi', value: props.barang.specification || '-' },
      { label: 'Jumlah stok tersedia', value: availableStock },
      { label: 'Jumlah stok diawal', value: initialStock },
      { label: 'Lokasi', value: formatLocation(data.location, data.floor, data.room) },
      { label: 'Nomor PO', value: data.po_number },
      { label: 'Tanggal registrasi', value: formatDateWithDashes(data.date_of_receipt) },
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
    name: props.barang.name,
    specification: props.barang.specification,
    lastUpdate: props.barang.lastUpdate,
    amount: totalStok.value,
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
    barang_nama: lot.barang_nama || props.barang.name,
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

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isEditModalOpen.value) {
      isEditModalOpen.value = false;
    } else if (isCreateLotModalOpen.value) {
      isCreateLotModalOpen.value = false;
    } else if (isBulkEditModalOpen.value) {
      isBulkEditModalOpen.value = false;
    } else if (isDeleteModalOpen.value) {
      closeDeleteModal();
    } else if (isErrorModalOpen.value) {
      closeErrorModal();
    } else if (isDetailConsumablesOpen.value) {
      isDetailConsumablesOpen.value = false;
    }
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <AppLayout title="Detail Tipe">
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
        <Button @click="openEditModal" variant="primary" size="lg">
          Edit Detail Tipe
        </Button>
        <Button @click="openDeleteModal" variant="destructive" size="lg">
          Hapus Tipe
        </Button>
      </div>
    </div>

    <div class="space-y-4">
      <!-- Detail Barang Card -->
      <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
        <h2 class="text-lg font-bold text-foreground mb-4">Detail Tipe</h2>
        
        <div class="flex flex-col md:flex-row gap-6">
          <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
            <img v-if="props.barang.image_url" :src="'/storage/' + props.barang.image_url" class="w-full h-full object-cover" />
            <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
          </div>

          <div class="flex-grow">
            <p class="font-bold text-foreground"><span class="text-foreground">Kode Tipe:</span> {{ props.barang.code }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.barang.brand }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.barang.name }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.barang.specification || '-' }}</p>
            <p class="text-foreground">Kategori: {{ props.barang.category }}</p>
            <p class="text-foreground">Subkategori: {{ props.barang.subcategory }}</p>
            <p class="text-foreground">Jumlah LOT: {{ props.lots.length }}</p>
            <p class="text-foreground">Total stok: {{ totalStok }} {{ props.barang.uom }}</p>
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
                <Button 
                  @click="handleEditTerpilih"
                  :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                  variant="more-round-warning"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">Edit Terpilih</span>
                </Button>
                <Button 
                  @click="openDeleteLotModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
                  :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                  variant="destructive"
                >
                  <Trash2 class="w-4 h-4" />
                  <span class="hidden sm:inline">Hapus Terpilih</span>
                </Button>
                <ExportButtonGroup 
                  @export-excel="handleExportExcel"
                  @export-csv="handleExportCSV"
                />
              </div>
            </div>
            
            <Button @click="openCreateLotModal" variant="primary" size="lg">
              <Plus class="w-4 h-4" />
              <span>LOT Baru</span>
            </Button>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-2">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="filteredLots" 
            :filter-value="searchQuery"
            :default-sorting="[{ id: 'date_of_receipt', desc: true }]"
          />
        </div>
      </div>
    </div>



    <EditTipeModal
      v-model:open="isEditModalOpen"
      :items="[props.barang]"
      :uoms="props.uoms"
      :brands="props.brands"
    />

    <CreateLotModal
      v-model:open="isCreateLotModalOpen"
      :barang="props.barang"
      :lots="props.lots"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
    />

    <EditLotModal
      v-model:open="isBulkEditModalOpen"
      :items="selectedLotsForEdit"
      :isConsumable="props.barang.is_consumable"
      :parentImageUrl="props.barang.image_url"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      @success="handleEditSuccess"
    />

    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="itemsToDelete.length"
      :item-name="deleteMode === 'barang' ? 'Tipe' : 'LOT'"
      :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : itemsToDelete"
      :fields="deleteFields"
      :max-width-class="itemsToDelete.length === 1 ? 'max-w-2xl' : undefined"
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
