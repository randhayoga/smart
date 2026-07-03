<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed, h } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
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
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EditAssetModal from './Modals/EditAssetModal.vue';
import DetailAssetModal from './Modals/DetailAssetModal.vue';

interface Props {
  units: {
    id: number;
    number: string;
    status: string;
    proposed_status: string | null;
    doc_url: string | null;
    condition: string;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    updated_at: string;
    
    // Location info
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;

    // Parent lot info
    lot_id: number;
    lot_number: string;
    lot_imageUrl: string | null;
    lot_unitPrice: number | string | null;

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
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const statusFilter = ref('');
const conditionFilter = ref('');
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

// View Asset Modal Setup
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);
const openViewAssetModal = (asset: any) => {
  selectedAssetForView.value = asset;
  isViewAssetModalOpen.value = true;
};

// Edit Asset Modal Setup
const isEditAssetModalOpen = ref(false);
const selectedAssetsForEdit = ref<any[]>([]);

const openEditAssetModal = (asset: any) => {
  if (!asset) return;
  selectedAssetsForEdit.value = [asset];
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

// Dynamic lot and barang context for the Edit Modal
const activeLotForEdit = computed(() => {
  if (selectedAssetsForEdit.value.length === 1) {
    const item = selectedAssetsForEdit.value[0];
    return {
      id: item.lot_id,
      imageUrl: item.lot_imageUrl,
      unitPrice: item.lot_unitPrice,
    };
  }
  return null;
});

const activeBarangForEdit = computed(() => {
  if (selectedAssetsForEdit.value.length === 1) {
    const item = selectedAssetsForEdit.value[0];
    return {
      category: item.barang_category
    };
  }
  return null;
});

const getExportData = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return filteredUnits.value;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length > 0) {
    return selectedRows.map((row: any) => row.original);
  }
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode Aset', 'Nama', 'Brand', 'Kategori', 'Subkategori', 'Status', 'Kondisi', 'Nilai', 'Lokasi Penyimpanan', 'TNKB (Nopol)'];
  const rows = data.map((item: any) => [
    `"${item.number}"`,
    `"${item.barang_nama}"`,
    `"${item.barang_brand}"`,
    `"${item.barang_category}"`,
    `"${item.barang_subcategory}"`,
    `"${item.status}"`,
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
  link.setAttribute("download", `daftar_aset_export_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
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

// Filtered Units list for Table
const filteredUnits = computed(() => {
  let list = props.units || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(u => 
      (u.number && u.number.toLowerCase().includes(q)) ||
      (u.barang_nama && u.barang_nama.toLowerCase().includes(q)) ||
      (u.barang_brand && u.barang_brand.toLowerCase().includes(q)) ||
      (u.barang_category && u.barang_category.toLowerCase().includes(q)) ||
      (u.barang_subcategory && u.barang_subcategory.toLowerCase().includes(q)) ||
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

const hasActiveFilters = computed(() => {
  return !!(statusFilter.value || conditionFilter.value || searchQuery.value);
});

const clearFilters = () => {
  statusFilter.value = '';
  conditionFilter.value = '';
  searchQuery.value = '';
};

// Dynamic values for dropdown filters
const availableStatuses = ['Tersedia', 'Dipinjam', 'Perbaikan', 'Rusak Total', 'Hilang', 'Pending', 'Tidak Aktif'];
const availableConditions = ['Baik', 'Kurang Baik', 'Rusak'];

const getStatusLabel = (status: string) => {
  return status || '';
};

const getConditionLabel = (cond: string) => {
  return cond || '';
};

// Table Columns configuration
const columns: ColumnDef<any>[] = [
  {
    id: 'select',
    size: 40,
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate font-medium', title: row.getValue('barang_nama') }, row.getValue('barang_nama')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('barang_brand')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('barang_category')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('barang_subcategory')),
  },
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
      else if (cond === 'Rusak' || cond === 'Rusak Berat' || cond === 'Rusak Ringan' || cond === 'Rusak Total') textClass = 'text-rose-600 font-semibold';
      
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
      'Lokasi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => {
      const r = row.original;
      return h('div', { class: 'text-muted-foreground text-sm' }, formatLocation(r.location, r.floor, r.room));
    }
  },
  {
    id: 'actions',
    size: 80,
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

const formatDateWithDashes = (dateStr: string | null) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

const isVehicle = (asset: any) => {
  if (!asset) return false;
  const category = (asset.barang_category || '').toLowerCase();
  const subcategory = (asset.barang_subcategory || '').toLowerCase();
  return category.includes('kendaraan') || subcategory.includes('kendaraan') ||
         category.includes('mobil') || subcategory.includes('mobil') ||
         category.includes('motor') || subcategory.includes('motor');
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isEditAssetModalOpen.value) {
      isEditAssetModalOpen.value = false;
    } else if (isViewAssetModalOpen.value) {
      isViewAssetModalOpen.value = false;
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
  <AppLayout title="Daftar Aset">
    <!-- Breadcrumb -->
    <Breadcrumb class="no-print">
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory/assets">Daftar Aset</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-4">
      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">Daftar Aset</h2>
          
          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                  <label for="search-aset" class="text-xs text-muted-foreground font-medium block">Filter</label>
                  <TableSearch 
                    id="search-aset"
                    name="search"
                    v-model="searchQuery"
                    placeholder="Cari Aset..." 
                  />
                </div>

                <!-- Status Filter Dropdown -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal h-10 px-4', !statusFilter ? 'text-muted-foreground' : 'text-foreground']">
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

                <!-- Condition Filter Dropdown -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal h-10 px-4', !conditionFilter ? 'text-muted-foreground' : 'text-foreground']">
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

            <!-- Row 2: Bulk Actions -->
            <div class="flex flex-wrap items-end justify-between gap-4 pt-2">
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
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
          <!-- Print-only Filter Info -->
          <div v-if="searchQuery || statusFilter || conditionFilter" class="print-only mb-4 text-left">
            <div class="font-bold text-xs text-foreground mb-1">Filter:</div>
            <div class="text-[10px] text-muted-foreground space-y-0.5">
              <div v-if="searchQuery">Pencarian: {{ searchQuery }}</div>
              <div v-if="statusFilter">Status: {{ statusFilter }}</div>
              <div v-if="conditionFilter">Kondisi: {{ conditionFilter }}</div>
            </div>
          </div>

          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="filteredUnits" 
            :filter-value="searchQuery"
            :default-sorting="[{ id: 'number', desc: false }]"
          />

          <div class="text-xs text-muted-foreground pl-1 mt-3 no-print">
            {{ totalAsetTerpilihCount }} dari {{ filteredUnits.length }} baris dipilih
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Asset Modal -->
    <EditAssetModal
      v-model:open="isEditAssetModalOpen"
      :items="selectedAssetsForEdit"
      :lot="activeLotForEdit"
      :barang="activeBarangForEdit"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      @success="handleAssetSuccess"
    />

    <!-- Delete Error Modal -->
    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />

    <!-- Detail Asset Modal (Units) -->
    <DetailAssetModal
      v-model:open="isViewAssetModalOpen"
      :asset="selectedAssetForView"
      @edit="openEditAssetModal"
    />
  </AppLayout>
</template>
