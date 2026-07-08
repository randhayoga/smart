<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed, h } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Pencil,
  Trash2,
  Eye,
  SlidersHorizontal
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import DetailLOTConsumables from '../DetailLOTConsumables.vue';
import EditLotModal from '../Modals/EditLotModal.vue';
import { Switch } from '@/Components/ui/switch';

interface Props {
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
    initial_quantity: number | null;
    current_quantity: number | null;
    updated_at: string;
    
    // Parent barang info
    barang_id: number;
    barang_code: string;
    barang_nama: string;
    barang_brand: string;
    barang_specification: string;
    barang_category: string;
    barang_subcategory: string;
    barang_uom: string;
  }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const timeFilter = ref('');
const organizerFilter = ref('');
const vendorFilter = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const locationFilter = ref('');
const floorFilter = ref('');
const roomFilter = ref('');
const stokFilter = ref(false);
const showAdvancedFilters = ref(false);
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

// Lot Modal Setup
const isBulkEditModalOpen = ref(false);
const selectedLotsForEdit = ref<any[]>([]);

const isDetailConsumablesOpen = ref(false);
const selectedLotForDetail = ref<number | null>(null);

const openDetailLOTConsumables = (lot: any) => {
  selectedLotForDetail.value = lot.id;
  isDetailConsumablesOpen.value = true;
};

const openEditLotModal = (lot: any) => {
  selectedLotsForEdit.value = [{ ...lot }];
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

const formatLocation = (loc: string | null, floor: string | null, room: string | null) => {
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.join(' - ');
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

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
        'Kode LOT',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.original.number),
    },
    {
      accessorKey: 'current_quantity',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Jml. Stok',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.original.current_quantity ?? 0),
    },
    {
      accessorKey: 'barang_nama',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Nama',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-foreground truncate font-medium', title: row.original.barang_nama }, row.original.barang_nama),
    },
    {
      accessorKey: 'barang_brand',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Merek',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.original.barang_brand),
    },
    {
      accessorKey: 'barang_category',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kategori',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.original.barang_category),
    },
    {
      accessorKey: 'barang_subcategory',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Subkategori',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.original.barang_subcategory),
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
      accessorKey: 'location',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Lokasi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground text-sm' }, formatLocation(row.original.location, row.original.floor, row.original.room)),
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
            onClick: () => openDetailLOTConsumables(row.original)
          }, () => [
            h(Eye),
            h('span', { class: 'sr-only' }, 'Lihat Detail')
          ]),
          h(Button, {
            variant: 'table-destructive',
            size: 'icon-sm',
            title: 'Hapus',
            onClick: () => openDeleteLotModal(row.original),
          }, () => [
            h(Trash2),
            h('span', { class: 'sr-only' }, 'Hapus')
          ])
        ]);
      }
    }
  ];
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

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

const filteredLots = computed(() => {
  let list = props.lots || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(lot => 
      (lot.number && lot.number.toLowerCase().includes(q)) || 
      (lot.barang_nama && lot.barang_nama.toLowerCase().includes(q))
    );
  }

  if (categoryFilter.value) {
    list = list.filter(lot => (lot.barang_category || '').toLowerCase() === categoryFilter.value.toLowerCase());
  }

  if (subcategoryFilter.value) {
    list = list.filter(lot => (lot.barang_subcategory || '').toLowerCase() === subcategoryFilter.value.toLowerCase());
  }

  if (stokFilter.value) {
    list = list.filter(lot => (lot.current_quantity ?? 0) > 0);
  }

  if (brandFilter.value) {
    list = list.filter(lot => (lot.barang_brand || '').toLowerCase() === brandFilter.value.toLowerCase());
  }

  if (locationFilter.value) {
    list = list.filter(lot => String(lot.location_id) === locationFilter.value);
  }

  if (floorFilter.value) {
    list = list.filter(lot => String(lot.floor_id) === floorFilter.value);
  }

  if (roomFilter.value) {
    list = list.filter(lot => String(lot.room_id) === roomFilter.value);
  }

  if (organizerFilter.value) {
    list = list.filter(lot => lot.organizer_id === Number(organizerFilter.value));
  }

  if (vendorFilter.value) {
    list = list.filter(lot => lot.vendor_id === Number(vendorFilter.value));
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

const availableCategories = computed<string[]>(() => {
  const cats = props.lots.map(l => l.barang_category).filter((c): c is string => !!c);
  return [...new Set(cats)].sort();
});

const availableSubcategories = computed<string[]>(() => {
  let list = props.lots;
  if (categoryFilter.value) {
    list = list.filter(l => l.barang_category === categoryFilter.value);
  }
  const subcats = list.map(l => l.barang_subcategory).filter((s): s is string => !!s);
  return [...new Set(subcats)].sort();
});

const availableBrands = computed<string[]>(() => {
  const brands = props.lots.map(l => l.barang_brand).filter((b): b is string => !!b);
  return [...new Set(brands)].sort();
});

const filteredFloors = computed(() => {
  if (!locationFilter.value) return props.floors;
  return props.floors.filter(f => f.location_id.toString() === locationFilter.value);
});

const filteredRooms = computed(() => {
  if (!floorFilter.value) return props.rooms;
  return props.rooms.filter(r => r.floor_id.toString() === floorFilter.value);
});

// Watch category to reset subcategory
watch(categoryFilter, () => {
  subcategoryFilter.value = '';
});

// Watch location to reset floor/room
watch(locationFilter, () => {
  floorFilter.value = '';
  roomFilter.value = '';
});

// Watch floor to reset room
watch(floorFilter, () => {
  roomFilter.value = '';
});

const getExportData = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return filteredLots.value;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length > 0) {
    return selectedRows.map((row: any) => row.original);
  }
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode LOT', 'Jml. Stok', 'Nama', 'Merek', 'Kategori', 'Subkategori', 'Nomor PO', 'Organizer', 'Lokasi'];
  const rows = data.map((item: any) => [
    `"${item.number}"`,
    `"${item.current_quantity ?? 0}"`,
    `"${item.barang_nama}"`,
    `"${item.barang_brand}"`,
    `"${item.barang_category}"`,
    `"${item.barang_subcategory}"`,
    `"${item.po_number}"`,
    `"${item.organizer}"`,
    `"${formatLocation(item.location, item.floor, item.room)}"`
  ]);

  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map((e: any) => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `stok_habis_pakai_export_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
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

// Delete LOT Modal Logic
const isDeleteModalOpen = ref(false);
const itemsToDelete = ref<any[]>([]);
const processing = ref(false);

const deleteFields = computed(() => {
  if (itemsToDelete.value.length === 1) {
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

    const fields = [
      { label: 'Kode LOT', value: data.number },
      { label: 'Kategori', value: data.barang_category },
      { label: 'Subkategori', value: data.barang_subcategory },
      { label: 'Merek', value: data.barang_brand },
      { label: 'Nama', value: data.barang_nama },
      { label: 'Spesifikasi', value: data.barang_specification || '-' },
      { label: 'Jumlah stok tersedia', value: data.current_quantity ?? 0 },
      { label: 'Jumlah stok diawal', value: data.initial_quantity ?? 0 },
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

const openDeleteLotModal = (lots: any | any[]) => {
  const rawLots = Array.isArray(lots) ? lots : [lots];
  itemsToDelete.value = rawLots.map((lot: any) => ({
    ...lot,
    barang_code: lot.barang_code,
    barang_brand: lot.barang_brand,
    barang_nama: lot.barang_nama,
    barang_specification: lot.barang_specification,
    barang_category: lot.barang_category,
    barang_subcategory: lot.barang_subcategory,
    barang_uom: lot.barang_uom,
    is_consumable: true,
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
  
  if (ids.length === 1) {
    router.delete(`/smart/inventory/lots/${ids[0]}`, {
      onStart: () => { processing.value = true; },
      onFinish: () => { processing.value = false; },
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
      onStart: () => { processing.value = true; },
      onFinish: () => { processing.value = false; },
      onSuccess: () => {
        closeDeleteModal();
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
      }
    });
  }
};

const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

const closeErrorModal = () => {
  isErrorModalOpen.value = false;
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isBulkEditModalOpen.value) {
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

const totalLotsTerpilihCount = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return 0;
  return Object.keys(dataTableRef.value.table.getState().rowSelection).length;
});

const activeParentImageUrl = computed(() => {
  if (selectedLotsForEdit.value.length === 1) {
    return selectedLotsForEdit.value[0].imageUrl || null;
  }
  return null;
});

const hasActiveFilters = computed(() => {
  return !!(
    timeFilter.value || 
    organizerFilter.value || 
    vendorFilter.value ||
    searchQuery.value ||
    categoryFilter.value ||
    subcategoryFilter.value ||
    brandFilter.value ||
    locationFilter.value ||
    floorFilter.value ||
    roomFilter.value ||
    stokFilter.value
  );
});

const clearFilters = () => {
  timeFilter.value = '';
  organizerFilter.value = '';
  vendorFilter.value = '';
  searchQuery.value = '';
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  locationFilter.value = '';
  floorFilter.value = '';
  roomFilter.value = '';
  stokFilter.value = false;
};
</script>

<template>
  <div class="space-y-4">
    <!-- Main Card -->
    <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
      <div class="py-3 no-print">
        <h2 class="text-lg font-bold text-foreground">Daftar Stok (Habis Pakai)</h2>

        <!-- Filters & Actions -->
        <div class="mt-4 flex flex-col space-y-4">
          <!-- Row 1: Filters & Rows Per Page -->
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-end gap-3 flex-1">
              <!-- Search -->
              <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                <label for="search-stok-habis-pakai" class="text-xs text-muted-foreground font-medium block">Filter</label>
                <TableSearch 
                  id="search-stok-habis-pakai"
                  name="search"
                  v-model="searchQuery"
                  placeholder="Cari Kode LOT, Nama..." 
                />
              </div>

              <!-- Stok Tersedia Filter Switch -->
              <div class="flex items-center justify-between gap-3 h-[34px] px-4 border border-input rounded-[14px] bg-background w-[200px] select-none">
                <span :class="['text-sm font-normal transition-colors', stokFilter ? 'text-foreground font-medium' : 'text-muted-foreground']">Stok tersedia saja</span>
                <Switch v-model="stokFilter" />
              </div>

              <!-- Kategori Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !categoryFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ categoryFilter || 'Semua kategori' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="categoryFilter = ''">Semua kategori</DropdownMenuItem>
                  <DropdownMenuItem v-for="cat in availableCategories" :key="cat" @select="categoryFilter = cat">
                    {{ cat }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Subkategori Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !subcategoryFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ subcategoryFilter || 'Semua subkategori' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="subcategoryFilter = ''">Semua subkategori</DropdownMenuItem>
                  <DropdownMenuItem v-for="sub in availableSubcategories" :key="sub" @select="subcategoryFilter = sub">
                    {{ sub }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Advanced Filter Toggle Button -->
              <Button 
                variant="outline" 
                @click="showAdvancedFilters = !showAdvancedFilters" 
                :class="['rounded-[14px] font-normal gap-2', showAdvancedFilters ? 'bg-muted border-primary/30 text-foreground' : 'text-muted-foreground']"
              >
                <SlidersHorizontal class="w-4 h-4 opacity-70" />
                <span>Filter Lanjutan</span>
                <ChevronDown :class="['w-4 h-4 opacity-50 shrink-0 transition-transform duration-200', showAdvancedFilters ? 'rotate-180' : '']" />
              </Button>

              <!-- Reset Filters Button -->
              <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="transform scale-95 opacity-0"
                enter-to-class="transform scale-100 opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="transform scale-100 opacity-100"
                leave-to-class="transform scale-95 opacity-0"
              >
                <ResetFilterButton 
                  v-if="hasActiveFilters"
                  @click="clearFilters"
                />
              </Transition>
            </div>

            <!-- Rows Per Page -->
            <div class="flex items-center gap-3 text-sm text-muted-foreground pb-0.5">
              <span class="whitespace-nowrap text-right">Baris per halaman</span>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                    {{ rowsPerPage }}
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Advanced Filters Row -->
          <div 
            v-if="showAdvancedFilters" 
            class="p-4 bg-muted/30 rounded-xl border border-border/80 flex flex-wrap gap-4"
          >
            <!-- Brand Filter -->
            <div class="space-y-1.5 w-[200px]">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Merek</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !brandFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ brandFilter || 'Semua merek' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="brandFilter = ''">Semua merek</DropdownMenuItem>
                  <DropdownMenuItem v-for="br in availableBrands" :key="br" @select="brandFilter = br">
                    {{ br }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Location Filter (Step 1) -->
            <div class="space-y-1.5 w-[200px]">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Lokasi</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !locationFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ locationFilter ? (props.locations.find(l => l.id.toString() === locationFilter)?.name || 'Semua lokasi') : 'Semua lokasi' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="locationFilter = ''; floorFilter = ''; roomFilter = ''">Semua lokasi</DropdownMenuItem>
                  <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="locationFilter = loc.id.toString(); floorFilter = ''; roomFilter = ''">
                    {{ loc.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Floor Filter (Step 2) -->
            <div class="space-y-1.5 w-[200px]">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Lantai</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !floorFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ floorFilter ? (props.floors.find(f => f.id.toString() === floorFilter)?.name || 'Semua lantai') : 'Semua lantai' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="floorFilter = ''; roomFilter = ''">Semua lantai</DropdownMenuItem>
                  <DropdownMenuItem v-for="fl in filteredFloors" :key="fl.id" @select="floorFilter = fl.id.toString(); roomFilter = ''">
                    {{ fl.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Room Filter (Step 3) -->
            <div class="space-y-1.5 w-3xs">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Ruangan</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !roomFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ roomFilter ? (props.rooms.find(r => r.id.toString() === roomFilter)?.name || 'Semua ruangan') : 'Semua ruangan' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="roomFilter = ''">Semua ruangan</DropdownMenuItem>
                  <DropdownMenuItem v-for="rm in filteredRooms" :key="rm.id" @select="roomFilter = rm.id.toString()">
                    {{ rm.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Organizer Filter -->
            <div class="space-y-1.5 w-[170px]">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Organizer</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !organizerFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ organizerFilter ? (props.organizers.find(o => o.id.toString() === organizerFilter)?.name || 'Semua organizer') : 'Semua organizer' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="organizerFilter = ''">Semua organizer</DropdownMenuItem>
                  <DropdownMenuItem v-for="org in props.organizers" :key="org.id" @select="organizerFilter = org.id.toString()">
                    {{ org.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Vendor Filter -->
            <div class="space-y-1.5 w-3xs">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Vendor</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !vendorFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ vendorFilter ? (props.vendors.find(v => v.id.toString() === vendorFilter)?.name || 'Semua vendor') : 'Semua vendor' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="vendorFilter = ''">Semua vendor</DropdownMenuItem>
                  <DropdownMenuItem v-for="vend in props.vendors" :key="vend.id" @select="vendorFilter = vend.id.toString()">
                    {{ vend.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Time Filter (Kurun Waktu) -->
            <div class="space-y-1.5 w-[200px]">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Kurun Waktu</label>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ timeFilter || 'Semua kurun waktu' }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="timeFilter = ''">Semua kurun waktu</DropdownMenuItem>
                  <DropdownMenuItem @select="timeFilter = 'Hari ini'">Hari ini</DropdownMenuItem>
                  <DropdownMenuItem @select="timeFilter = 'Bulan ini'">Bulan ini</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Row 2: Bulk Actions -->
          <div class="flex flex-wrap items-end justify-between gap-4 pt-2">
            <div class="space-y-2 flex-1 min-w-0">
              <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
              <div class="flex flex-wrap gap-2">
                <!-- Edit Terpilih -->
                <Button 
                  @click="handleEditTerpilih()"
                  :disabled="totalLotsTerpilihCount === 0"
                  variant="more-round-warning"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">Edit Terpilih</span>
                </Button>
                <!-- Hapus Terpilih -->
                <Button 
                  @click="openDeleteLotModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
                  :disabled="totalLotsTerpilihCount === 0"
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
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="pb-4">
        <!-- Print-only Filter Info -->
        <div v-if="searchQuery || timeFilter || organizerFilter || vendorFilter || categoryFilter || subcategoryFilter || brandFilter || locationFilter || floorFilter || roomFilter || stokFilter" class="print-only mb-4 text-left">
          <div class="font-bold text-xs text-foreground mb-1">Filter:</div>
          <div class="text-[10px] text-muted-foreground space-y-0.5">
            <div v-if="searchQuery">Pencarian: {{ searchQuery }}</div>
            <div v-if="categoryFilter">Kategori: {{ categoryFilter }}</div>
            <div v-if="subcategoryFilter">Subkategori: {{ subcategoryFilter }}</div>
            <div v-if="stokFilter">Stok: Tersedia</div>
            <div v-if="brandFilter">Merek: {{ brandFilter }}</div>
            <div v-if="locationFilter">Lokasi: {{ props.locations.find(l => l.id.toString() === locationFilter)?.name }}</div>
            <div v-if="floorFilter">Lantai: {{ props.floors.find(f => f.id.toString() === floorFilter)?.name }}</div>
            <div v-if="roomFilter">Ruangan: {{ props.rooms.find(r => r.id.toString() === roomFilter)?.name }}</div>
            <div v-if="organizerFilter">Organizer: {{ props.organizers.find(o => o.id.toString() === organizerFilter)?.name }}</div>
            <div v-if="vendorFilter">Vendor: {{ props.vendors.find(v => v.id.toString() === vendorFilter)?.name }}</div>
            <div v-if="timeFilter">Kurun Waktu: {{ timeFilter }}</div>
          </div>
        </div>

        <DataTable 
          ref="dataTableRef"
          :columns="columns" 
          :data="filteredLots" 
          :filter-value="searchQuery"
          :default-sorting="[{ id: 'number', desc: false }]"
        />

        <div class="text-xs text-muted-foreground pl-1 mt-3 no-print">
          {{ totalLotsTerpilihCount }} dari {{ filteredLots.length }} baris dipilih
        </div>
      </div>
    </div>
  </div>

  <EditLotModal
    v-model:open="isBulkEditModalOpen"
    :items="selectedLotsForEdit"
    :isConsumable="true"
    :parentImageUrl="activeParentImageUrl"
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
    :item-name="'LOT'"
    :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : itemsToDelete"
    :fields="deleteFields"
    :max-width-class="itemsToDelete.length === 1 ? 'max-w-2xl' : undefined"
    :processing="processing"
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
</template>
