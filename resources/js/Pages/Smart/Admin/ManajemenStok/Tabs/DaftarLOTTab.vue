<script setup lang="ts">
/**
 * Daftar LOT Tab component rendering batch/procurement lot tables, quantity metrics, and batch actions.
 */
import { ref, watch, onMounted, onUnmounted, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus,
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
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import { formatDate } from '@/lib/utils';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import DetailLOTConsumables from '../DetailLOTConsumables.vue';
import CreateLotModal from '../Modals/CreateLotModal.vue';
import EditLotModal from '../Modals/EditLotModal.vue';
import { printManajemenStok } from '@/utils/printManajemenStok';
import { exportCSV } from '@/utils/exportCSV';
import { exportExcel } from '@/utils/exportExcel';

interface Props {
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
  lots: {
    id: number;
    number: string;
    po_number?: string | null;
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
    burden?: string;
    updated_at: string;
  }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  floors?: any[];
  rooms?: any[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();

import { usePermissions } from '@/composables/usePermissions';

const { t, locale } = useI18n();
const { can } = usePermissions();

const searchQuery = ref('');
const timeFilter = ref('');
const organizerFilter = ref('');
const vendorFilter = ref('');
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
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

const formatLocation = (loc: string | null, floor?: string | null, room?: string | null) => {
  if (loc && (!floor && !room)) return loc;
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.length > 0 ? parts.join(' - ') : '-';
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
  const formatted = Math.floor(num).toLocaleString(loc);
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
        t('inventory.lotCode'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono truncate font-medium' }, row.original.number),
    },
    {
      accessorKey: 'assetCount',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.stockCount'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, 
        props.barang.is_consumable 
          ? `${row.original.current_quantity ?? 0}/${row.original.initial_quantity ?? 0}`
          : row.original.assetCount
      ),
    },
    {
      accessorKey: 'po_number',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.poNumber'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 font-medium' }, row.original.po_number || '-'),
    },
    {
      accessorKey: 'date_of_receipt',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.registrationDate'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, formatDate(row.original.date_of_receipt)),
      sortingFn: (rowA, rowB) => {
        const valA = String(rowA.original.date_of_receipt || '');
        const valB = String(rowB.original.date_of_receipt || '');
        return valA.localeCompare(valB);
      }
    },
    {
      accessorKey: 'unitPrice',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.unitPrice'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, formatRupiah(row.original.unitPrice)),
    }
  ];

  cols.push(
    {
      accessorKey: 'organizer',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.organizer'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0' }, row.original.organizer),
    }
  );

  cols.push(
    {
      accessorKey: 'location',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.location'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground text-sm' }, formatLocation(row.original.location, row.original.floor, row.original.room)),
    },
    {
      id: 'actions',
      size: 100,
      header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('common.actions')),
      cell: ({ row }) => {
        const buttons = [];
        if (props.barang.is_consumable) {
          buttons.push(
            h(Button, {
              variant: 'table-view',
              size: 'icon-sm',
              title: t('inventory.viewDetails'),
              onClick: () => openDetailLOTConsumables(row.original)
            }, () => [
              h(Eye),
              h('span', { class: 'sr-only' }, t('inventory.viewDetails'))
            ])
          );
        } else {
          buttons.push(
            h(Button, {
              variant: 'table-view',
              size: 'icon-sm',
              title: t('inventory.viewDetails'),
              onClick: () => {
                const lotParam = row.original.number 
                  ? String(row.original.number).replace(/[^a-zA-Z0-9]/g, '') 
                  : row.original.id;
                router.get(`/smart/inventory/lots/${lotParam}`);
              }
            }, () => [
              h(Eye),
              h('span', { class: 'sr-only' }, t('inventory.viewDetails'))
            ])
          );
        }
        if (can('inventory.manage')) {
          buttons.push(
            h(Button, {
              variant: 'table-destructive',
              size: 'icon-sm',
              title: t('common.delete'),
              onClick: () => openDeleteLotModal(row.original),
            }, () => [
              h(Trash2),
              h('span', { class: 'sr-only' }, t('common.delete'))
            ])
          );
        }
        return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, buttons);
      }
    }
  );

  return cols;
});

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'all' || (rowsPerPage.value as any) === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

onMounted(() => {
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
      
      if (timeFilter.value === 'Hari ini' || timeFilter.value === 'today') {
        return entryDateObj.toDateString() === today.toDateString();
      } else if (timeFilter.value === 'Bulan ini' || timeFilter.value === 'thisMonth') {
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

const getExportData = () => {
  if (!dataTableRef.value) return filteredLots.value;
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const getExportPayload = () => {
  const data = getExportData();
  const headers = [
    t('inventory.lotCode'),
    t('inventory.poNumber'),
    t('inventory.registrationDate'),
    t('inventory.organizer'),
    t('inventory.stockCount')
  ];
  const rows = data.map((item: any) => [
    item.number,
    item.po_number,
    formatDate(item.date_of_receipt),
    item.organizer,
    props.barang.is_consumable ? (item.current_quantity ?? 0) : item.assetCount
  ]);
  return { headers, rows };
};

const handleExportCSV = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportCSV(headers, rows, 'lot_export');
};

const handleExportExcel = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportExcel(headers, rows, 'lot_export');
};

const handlePrint = () => {
  const data = getExportData();
  if (!data || data.length === 0) return;
  printManajemenStok(data, {});
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

    const isConsumable = props.barang.is_consumable;
    const availableStock = isConsumable ? (data.current_quantity ?? 0) : (data.availableAssetCount ?? 0);
    const initialStock = isConsumable ? (data.initial_quantity ?? 0) : (data.assetCount ?? 0);

    const fields = [
      { label: t('inventory.lotCode'), value: data.number },
      { label: t('inventory.category'), value: props.barang.category },
      { label: t('inventory.subcategory'), value: props.barang.subcategory },
      { label: t('inventory.brand'), value: props.barang.brand },
      { label: t('inventory.name'), value: props.barang.name },
      { label: t('inventory.specification'), value: props.barang.specification || '-' },
      { label: t('inventory.availableStock'), value: availableStock },
      { label: t('inventory.initialStock'), value: initialStock },
      { label: t('inventory.location'), value: formatLocation(data.location, data.floor, data.room) },
      { label: t('inventory.poNumber'), value: data.po_number || '-' },
      { label: t('inventory.registrationDate'), value: formatDate(data.date_of_receipt) },
      { label: t('inventory.age'), value: data.age !== undefined && data.age !== null ? `${data.age} ${t('inventory.yearUnit')}` : '-' },
      { label: t('inventory.unitPrice'), value: formatRupiah(data.unitPrice) },
      { label: t('inventory.organizer'), value: data.organizer },
      { label: t('inventory.vendor'), value: data.vendor },
      { label: t('inventory.burden'), value: data.burden || '-' },
      { label: t('inventory.lastUpdate'), value: data.updated_at || '-' }
    ];
    
    return fields;
  }
  
  return null;
});

const openDeleteLotModal = (lots: any | any[]) => {
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
    if (isCreateLotModalOpen.value) {
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
</script>

<template>
  <!-- Daftar LOT Card -->
  <div class="bg-card rounded-xl border border-border px-4 py-3 shadow-sm overflow-hidden">
    <div class="no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">{{ t('inventory.lotList') }}</h2>

      <!-- Filters Row -->
      <div class="mb-4 flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.filter') }}</label>
          <TableSearch 
            v-model="searchQuery"
            :placeholder="t('inventory.searchLotPlaceholder')" 
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ timeFilter === 'today' ? t('inventory.today') : (timeFilter === 'thisMonth' ? t('inventory.thisMonth') : t('inventory.allTimeRanges')) }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="timeFilter = ''">{{ t('inventory.allTimeRanges') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'today'">{{ t('inventory.today') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'thisMonth'">{{ t('inventory.thisMonth') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !organizerFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ organizerFilter || t('inventory.allOrganizers') }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="organizerFilter = ''">{{ t('inventory.allOrganizers') }}</DropdownMenuItem>
            <DropdownMenuItem v-for="org in uniqueOrganizers" :key="org" @select="organizerFilter = org">
              {{ org }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>{{ t('inventory.rowsPerPage') }}</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPage === 'all' ? t('inventory.allRows') : rowsPerPage }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'all'">{{ t('inventory.allRows') }}</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <!-- Actions Row -->
      <div v-if="can('inventory.manage')" class="mb-4 flex flex-wrap items-end justify-between gap-4 pt-2">
        <div class="space-y-2 flex-1 min-w-0">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.selectedActions') }}</label>
          <div class="flex flex-wrap gap-2">
            <Button 
              @click="handleEditTerpilih"
              :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
              variant="more-round-warning"
            >
              <Pencil class="w-4 h-4" />
              <span class="hidden sm:inline">{{ t('inventory.editSelected') }}</span>
            </Button>
            <Button 
              @click="openDeleteLotModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
              :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
              variant="destructive"
            >
              <Trash2 class="w-4 h-4" />
              <span class="hidden sm:inline">{{ t('inventory.deleteSelected') }}</span>
            </Button>
          </div>
        </div>
        
        <Button @click="openCreateLotModal" variant="primary" size="lg">
          <Plus class="w-4 h-4" />
          <span>{{ t('inventory.newLot') }}</span>
        </Button>
      </div>
    </div>

    <!-- Table -->
    <div class="pb-2">
      <DataTable 
        ref="dataTableRef"
        :columns="columns" 
        :data="filteredLots" 
        :page-size="pageSizeNumber"
        :default-sorting="[{ id: 'number', desc: true }]"
      />
    </div>
  </div>

  <CreateLotModal
    v-model:open="isCreateLotModalOpen"
    :barang="props.barang"
    :lots="props.lots"
    :organizers="props.organizers"
    :vendors="props.vendors"
    :locations="props.locations"
    :projects="props.projects"
  />

  <EditLotModal
    v-model:open="isBulkEditModalOpen"
    :items="selectedLotsForEdit"
    :isConsumable="props.barang.is_consumable"
    :uom="props.barang.uom"
    :parentImageUrl="props.barang.image_url"
    :organizers="props.organizers"
    :vendors="props.vendors"
    :locations="props.locations"
    :projects="props.projects"
    @success="handleEditSuccess"
  />

  <DeleteConfirmationModal 
    :is-open="isDeleteModalOpen"
    :item-count="itemsToDelete.length"
    :item-name="t('inventory.lotItem')"
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
