<script setup lang="ts">
/**
 * Daftar Aset Tab component providing asset inventory data table, multi-column filters, export, and batch operations.
 */
import { ref, watch, onMounted, onUnmounted, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { 
  ChevronDown, 
  ArrowUpDown, 
  X,
  Eye,
  Pencil,
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
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import DataTable from '@/Components/DataTable.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EditAssetModal from '../Modals/EditAssetModal.vue';
import DetailAssetModal from '../Modals/DetailAssetModal.vue';
import { printManajemenStok } from '@/utils/printManajemenStok';
import { exportCSV } from '@/utils/exportCSV';
import { exportExcel } from '@/utils/exportExcel';

interface Props {
  units: {
    id: number;
    number: string;
    status: string;
    proposed_status?: string | null;
    doc_url?: string | null;
    condition: string;
    type?: string;
    classification?: string;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    created_at?: string | null;
    updated_at: string;
    
    // Location info
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;

    // Parent lot info
    lot_id?: number;
    lot_number?: string;
    lot_imageUrl?: string | null;
    lot_unitPrice?: number | string | null;
    organizer?: string;
    organizer_id?: number | null;
    vendor?: string;
    vendor_id?: number | null;
    lot_organizer?: string;
    lot_date_of_receipt?: string | null;
    lot_vendor?: string;
    lot_po_number?: string;

    // Parent barang info
    barang_id?: number;
    barang_code?: string;
    barang_nama?: string;
    barang_brand?: string;
    barang_specification?: string;
    barang_category?: string;
    barang_subcategory?: string;
    barang_uom?: string;
  }[];
  locations: any[];
  floors?: any[];
  rooms?: any[];
  organizers?: { id: number; name: string; }[];
  vendors?: { id: number; name: string; }[];
  users?: { id: number; name: string; }[];
  hideBarangColumns?: boolean;
  hideStatusFilter?: boolean;
  customPrintHandler?: (items: any[]) => void;
  lot?: any;
  barang?: {
    category: string;
  };
  filterVariant?: 'simple' | 'full';
  hideExport?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  hideBarangColumns: false,
  hideStatusFilter: false,
  filterVariant: 'full',
  hideExport: false,
});

const searchQuery = ref('');
const statusFilter = ref('');
const conditionFilter = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const locationFilter = ref('');
const organizerFilter = ref('');
const vendorFilter = ref('');
const showAdvancedFilters = ref(false);
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
const dataTableRef = ref<any>(null);

const { t, te, locale } = useI18n();

// View Asset Modal Setup
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);
const openViewAssetModal = (asset: any) => {
  selectedAssetForView.value = asset;
  isViewAssetModalOpen.value = true;
};

watch(() => props.units, (newUnits) => {
  if (selectedAssetForView.value && newUnits) {
    const updated = newUnits.find((u: any) => u.id === selectedAssetForView.value.id);
    if (updated) {
      selectedAssetForView.value = updated;
    }
  }
}, { deep: true });

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
  if (props.lot) return props.lot;
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
  if (props.barang) return props.barang;
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

const getExportPayload = () => {
  const data = getExportData();
  const headers = [
    t('inventory.assetCode'),
    t('inventory.name'),
    t('inventory.brand'),
    t('inventory.category'),
    t('inventory.subcategory'),
    t('inventory.status'),
    t('inventory.condition'),
    t('inventory.value'),
    t('inventory.storageLocation'),
    t('inventory.tnkbShort')
  ];
  const rows = data.map((item: any) => [
    item.number,
    item.barang_nama,
    item.barang_brand,
    item.barang_category,
    item.barang_subcategory,
    item.status,
    item.condition,
    item.price,
    formatLocation(item.location, item.floor, item.room),
    item.vehicle_registration || '-'
  ]);
  return { headers, rows };
};

const handleExportCSV = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportCSV(headers, rows, 'daftar_aset_export');
};

const handleExportExcel = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportExcel(headers, rows, 'daftar_aset_export');
};

const handlePrint = () => {
  const data = getExportData();
  if (!data || data.length === 0) {
    toast.error(t('inventory.noDataToPrint'));
    return;
  }

  if (props.customPrintHandler) {
    props.customPrintHandler(data);
    return;
  }

  const filteredLokasi = locationFilter.value
    ? (props.locations?.find((l: any) => String(l.id) === String(locationFilter.value))?.name || locationFilter.value)
    : null;

  printManajemenStok(data, {
    lokasi: filteredLokasi,
  });
};

// Permissions & Notifications
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const { can } = usePermissions();
const flashSuccess = computed(() => (page.props as any).flash?.success);
const flashError = computed(() => (page.props as any).flash?.error);

watch(flashSuccess, (newVal) => {
  if (newVal && (page.props as any).flash?.success) {
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
      (u.barang_nama && u.barang_nama.toLowerCase().includes(q))
    );
  }

  if (categoryFilter.value) {
    list = list.filter(u => (u.barang_category || '').toLowerCase() === categoryFilter.value.toLowerCase());
  }

  if (subcategoryFilter.value) {
    list = list.filter(u => (u.barang_subcategory || '').toLowerCase() === subcategoryFilter.value.toLowerCase());
  }

  if (statusFilter.value) {
    const filterVal = statusFilter.value.toLowerCase();
    list = list.filter(u => {
      const status = (u.status || '').toLowerCase();
      const proposed = (u.proposed_status || '').toLowerCase();
      return status === filterVal || (['pending', 'dihapus'].includes(status) && proposed === filterVal);
    });
  }

  if (conditionFilter.value) {
    list = list.filter(u => (u.condition || '').toLowerCase() === conditionFilter.value.toLowerCase());
  }

  if (brandFilter.value) {
    list = list.filter(u => (u.barang_brand || '').toLowerCase() === brandFilter.value.toLowerCase());
  }

  if (locationFilter.value) {
    const locId = Number(locationFilter.value);
    const targetIds = [locId, ...getDescendantIds(locId)];
    list = list.filter(u => targetIds.includes(Number(u.location_id)));
  }

  if (organizerFilter.value) {
    list = list.filter(u => String(u.organizer_id) === String(organizerFilter.value));
  }

  if (vendorFilter.value) {
    list = list.filter(u => String(u.vendor_id) === String(vendorFilter.value));
  }

  return list;
});

const getDescendantIds = (parentId: number): number[] => {
  const result: number[] = [];
  const findChildren = (pid: number) => {
    (props.locations || []).forEach((loc: any) => {
      if (Number(loc.parent_id) === pid) {
        result.push(loc.id);
        findChildren(loc.id);
      }
    });
  };
  findChildren(parentId);
  return result;
};

const hasActiveFilters = computed(() => {
  return !!(
    statusFilter.value || 
    conditionFilter.value || 
    searchQuery.value ||
    categoryFilter.value ||
    subcategoryFilter.value ||
    brandFilter.value ||
    locationFilter.value ||
    organizerFilter.value ||
    vendorFilter.value
  );
});

const clearFilters = () => {
  statusFilter.value = '';
  conditionFilter.value = '';
  searchQuery.value = '';
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  locationFilter.value = '';
  organizerFilter.value = '';
  vendorFilter.value = '';
};

const availableCategories = computed<string[]>(() => {
  const cats = props.units.map(u => u.barang_category).filter((c): c is string => !!c);
  return [...new Set(cats)].sort();
});

const availableSubcategories = computed<string[]>(() => {
  let list = props.units;
  if (categoryFilter.value) {
    list = list.filter(u => u.barang_category === categoryFilter.value);
  }
  const subcats = list.map(u => u.barang_subcategory).filter((s): s is string => !!s);
  return [...new Set(subcats)].sort();
});

const availableBrands = computed<string[]>(() => {
  const brands = props.units.map(u => u.barang_brand).filter((b): b is string => !!b);
  return [...new Set(brands)].sort();
});

// Watch category to reset subcategory
watch(categoryFilter, () => {
  subcategoryFilter.value = '';
});

// Dynamic values for dropdown filters
const availableStatuses = computed(() => {
  const dynamic = Array.from(new Set((props.units || []).map((u: any) => u.status).filter(Boolean)));
  if (dynamic.length > 0) return dynamic;
  return ['Tersedia', 'Dipinjam', 'Standby', 'Tidak Aktif'];
});
const availableConditions = ['Bagus', 'Rusak', 'QC Passed', 'Lelang/Hibah', 'Rusak Total', 'Hilang'];

const STATUS_LABEL_MAP: Record<string, string> = {
  'tersedia': 'status.tersedia',
  'dipinjam': 'status.dipinjam',
  'standby': 'status.standby',
  'tidak aktif': 'status.tidakAktif',
  'pending': 'status.pending',
  'pending:dm': 'status.pendingDm',
  'pending: dm': 'status.pendingDm',
  'bagus': 'status.bagus',
  'rusak': 'status.rusak',
  'qc passed': 'status.qcPassed',
  'lelang/hibah': 'status.lelangHibah',
  'rusak total': 'status.rusakTotal',
  'hilang': 'status.hilang',
  'dihapus': 'status.dihapus',
  'ditolak': 'status.ditolak',
  'disetujui': 'status.disetujui',
  'sukses': 'status.sukses',
};

const getStatusLabel = (status: string) => {
  if (!status) return '';
  const key = STATUS_LABEL_MAP[status.toLowerCase()];
  return key && te(key) ? t(key) : status;
};

const CONDITION_KEY_MAP: Record<string, string> = {
  'bagus': 'inventory.conditionGood',
  'rusak': 'inventory.conditionDamaged',
  'qc passed': 'inventory.conditionQcPassed',
  'lelang/hibah': 'inventory.conditionAuctionGrant',
  'rusak total': 'inventory.conditionTotalDamage',
  'hilang': 'inventory.conditionLost',
};

const getConditionLabel = (cond: string) => {
  if (!cond) return '';
  const key = CONDITION_KEY_MAP[cond.toLowerCase()];
  return key && te(key) ? t(key) : cond;
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
  const formatted = Math.floor(num).toLocaleString(loc);
  return `Rp${formatted}`;
};

// Table Columns configuration
const columns = computed<ColumnDef<any>[]>(() => {
  const list: ColumnDef<any>[] = [
    {
      id: 'created_at',
      accessorKey: 'created_at',
      enableHiding: true,
      sortingFn: (rowA, rowB) => {
        const timeA = rowA.original.created_at ? new Date(rowA.original.created_at).getTime() : 0;
        const timeB = rowB.original.created_at ? new Date(rowB.original.created_at).getTime() : 0;
        if (timeA !== timeB) {
          return timeA - timeB;
        }
        return (rowA.original.id || 0) - (rowB.original.id || 0);
      },
    },
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
        t('inventory.assetCode'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('number')),
    }
  ];

  if (!props.hideBarangColumns) {
    list.push(
      {
        accessorKey: 'barang_nama',
        header: ({ column }) => h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('inventory.name'),
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
          t('inventory.brand'),
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
          t('inventory.category'),
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
          t('inventory.subcategory'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]),
        cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('barang_subcategory')),
      }
    );
  }

  list.push(
    {
      accessorKey: 'status',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.status'),
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
        t('inventory.condition'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => {
        const cond = row.getValue('condition') as string || '';
        let textClass = 'text-foreground';
        if (cond === 'Bagus' || cond === 'QC Passed') textClass = 'text-emerald-600 font-semibold';
        else if (cond === 'Lelang/Hibah') textClass = 'text-purple-600 font-semibold';
        else if (cond === 'Rusak' || cond === 'Rusak Total' || cond === 'Hilang') textClass = 'text-rose-600 font-semibold';
        
        return h('span', { class: textClass }, getConditionLabel(cond));
      }
    },
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
      cell: ({ row }) => {
        const r = row.original;
        return h('div', { class: 'text-muted-foreground text-sm' }, formatLocation(r.location, r.floor, r.room));
      }
    }
  );

  if (props.hideBarangColumns) {
    list.push({
      accessorKey: 'price',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('inventory.value'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]),
      cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm font-medium' }, formatRupiah(row.original.price)),
    });
  }

  list.push(
    {
      id: 'actions',
      size: 80,
      header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('common.actions')),
      cell: ({ row }) => {
        return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, [
          h(Button, {
            variant: 'table-view',
            size: 'icon-sm',
            title: t('inventory.viewDetails'),
            onClick: () => openViewAssetModal(row.original)
          }, () => [
            h(Eye),
            h('span', { class: 'sr-only' }, t('inventory.viewDetails'))
          ])
        ]);
      }
    }
  );

  return list;
});

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'all' || (rowsPerPage.value as any) === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

const checkSearchParam = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search');
  if (searchParam) {
    searchQuery.value = searchParam;
  }
};

onMounted(() => {
  checkSearchParam();
  document.addEventListener('keydown', closeOnEscape);
});

watch(() => page.url, (newUrl) => {
  if (newUrl) {
    const url = new URL(newUrl, window.location.origin);
    const searchParam = url.searchParams.get('search');
    if (searchParam) {
      searchQuery.value = searchParam;
    }
  }
});

const formatLocation = (loc: string | null, floor?: string | null, room?: string | null) => {
  if (loc && (!floor && !room)) return loc;
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.length > 0 ? parts.join(' - ') : '-';
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
  <div class="space-y-4">
    <!-- Main Card -->
    <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
      <div class="py-3 no-print">
        <h2 class="text-lg font-bold text-foreground">{{ t('inventory.assetList') }}</h2>
        
        <!-- Filters & Actions -->
        <div class="mt-4 flex flex-col space-y-4">
          <!-- Row 1: Filters & Rows Per Page -->
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-end gap-3 flex-1">
              <!-- Search -->
              <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                <label for="search-aset" class="text-xs text-muted-foreground font-medium block">{{ t('inventory.filter') }}</label>
                <TableSearch 
                  id="search-aset"
                  name="search"
                  v-model="searchQuery"
                  :placeholder="t('inventory.searchAssetPlaceholder')" 
                />
              </div>

              <!-- Kategori Filter Dropdown -->
              <DropdownMenu v-if="props.filterVariant !== 'simple'">
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !categoryFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ categoryFilter || t('inventory.allCategories') }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="categoryFilter = ''">{{ t('inventory.allCategories') }}</DropdownMenuItem>
                  <DropdownMenuItem v-for="cat in availableCategories" :key="cat" @select="categoryFilter = cat">
                    {{ cat }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Subkategori Filter Combobox -->
              <Combobox 
                v-if="props.filterVariant !== 'simple'"
                v-model="subcategoryFilter"
                :options="availableSubcategories"
                :search-placeholder="t('inventory.searchSubcategoryPlaceholder')"
                :default-label="t('inventory.allSubcategories')"
              />

              <!-- Status Filter Dropdown -->
              <DropdownMenu v-if="!props.hideStatusFilter">
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !statusFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ statusFilter ? getStatusLabel(statusFilter) : t('inventory.allStatuses') }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="statusFilter = ''">{{ t('inventory.allStatuses') }}</DropdownMenuItem>
                  <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="statusFilter = st">
                    {{ getStatusLabel(st) }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Condition Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !conditionFilter ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ conditionFilter ? getConditionLabel(conditionFilter) : t('inventory.allConditions') }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                  <DropdownMenuItem @select="conditionFilter = ''">{{ t('inventory.allConditions') }}</DropdownMenuItem>
                  <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="conditionFilter = cond">
                    {{ getConditionLabel(cond) }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Location Filter -->
              <div class="w-[280px]">
                <LocationCombobox
                  v-model="locationFilter"
                  :locations="props.locations"
                  :placeholder="t('inventory.allLocations')"
                  :clearable="true"
                  width-class="w-full"
                />
              </div>

              <!-- Advanced Filter Toggle Button -->
              <Button 
                v-if="props.filterVariant !== 'simple'"
                variant="outline" 
                @click="showAdvancedFilters = !showAdvancedFilters" 
                :class="['rounded-[14px] font-normal gap-2', showAdvancedFilters ? 'bg-muted border-primary/30 text-foreground' : 'text-muted-foreground']"
              >
                <SlidersHorizontal class="w-4 h-4 opacity-70" />
                <span>{{ t('inventory.advancedFilter') }}</span>
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
              <span class="whitespace-nowrap text-right">{{ t('inventory.rowsPerPage') }}</span>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                    {{ rowsPerPage === 'all' ? t('inventory.allRows') : rowsPerPage }}
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="rowsPerPage = 'all'">{{ t('inventory.allRows') }}</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                  <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Advanced Filters Row -->
          <Transition
            enter-active-class="transition-all ease-out duration-300 transform"
            enter-from-class="-translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all ease-in duration-200 transform"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-4 opacity-0"
          >
            <div 
              v-if="showAdvancedFilters && props.filterVariant !== 'simple'" 
              class="p-4 bg-muted/30 rounded-xl border border-border/80 flex flex-wrap gap-4"
            >
              <!-- Brand Filter -->
              <div class="space-y-1.5 w-[200px]">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.brand') }}</label>
                <Combobox
                  v-model="brandFilter"
                  :options="availableBrands"
                  :search-placeholder="t('inventory.searchBrandPlaceholder')"
                  :default-label="t('inventory.allBrands')"
                  width-class="w-full bg-background"
                />
              </div>

              <!-- Organizer Filter -->
              <div class="space-y-1.5 w-[170px]">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.organizer') }}</label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal bg-background', !organizerFilter ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ organizerFilter ? (props.organizers?.find(o => o.id.toString() === organizerFilter)?.name || t('inventory.allOrganizers')) : t('inventory.allOrganizers') }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                    <DropdownMenuItem @select="organizerFilter = ''">{{ t('inventory.allOrganizers') }}</DropdownMenuItem>
                    <DropdownMenuItem v-for="org in props.organizers" :key="org.id" @select="organizerFilter = org.id.toString()">
                      {{ org.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <!-- Vendor Filter -->
              <div class="space-y-1.5 w-3xs">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.vendor') }}</label>
                <Combobox
                  v-model="vendorFilter"
                  :options="props.vendors || []"
                  :search-placeholder="t('inventory.searchVendorPlaceholder')"
                  :default-label="t('inventory.allVendors')"
                  width-class="w-full bg-background"
                />
              </div>
            </div>
          </Transition>

          <!-- Row 2: Bulk Actions -->
          <div class="flex flex-wrap items-end justify-between gap-4 pt-2">
            <div class="space-y-2 flex-1 min-w-0">
              <label v-if="can('inventory.manage')" class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.selectedActions') }}</label>
              <div class="flex flex-wrap gap-2">
                <!-- Edit Terpilih -->
                <Button 
                  v-if="can('inventory.manage')"
                  @click="handleEditTerpilih()"
                  :disabled="totalAsetTerpilihCount === 0"
                  variant="more-round-warning"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ t('inventory.editSelected') }}</span>
                </Button>
                <ExportButtonGroup 
                  v-if="!hideExport"
                  @print="handlePrint"
                  @export-pdf="handlePrint"
                  @export-excel="handleExportExcel"
                  @export-csv="handleExportCSV"
                />
              </div>
            </div>

            <slot v-if="can('inventory.manage')" name="extra-actions"></slot>
          </div>
        </div>
      </div>
      <!-- Table -->
      <div class="pb-4">
        <DataTable 
          ref="dataTableRef"
          :columns="columns" 
          :data="filteredUnits" 
          :page-size="pageSizeNumber"
          :show-selection-count="false"
          :default-sorting="[{ id: 'created_at', desc: true }]"
          :default-column-visibility="{ created_at: false }"
        />

        <div class="text-xs text-muted-foreground pl-1 mt-3 no-print">
          {{ t('inventory.rowsSelected', { selected: totalAsetTerpilihCount, total: filteredUnits.length }) }}
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
    :lot="props.lot"
    :users="props.users"
    @edit="openEditAssetModal"
  />
</template>
