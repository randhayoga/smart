<script setup lang="ts">
/**
 * Detail Karyawan Modal component displaying employee information,
 * active borrowed assets (Peminjaman Aktif), and historical loans (Peminjaman Historis).
 * Adheres to Cruddy by Design, KISS, and DRY principles.
 */
import { ref, computed, watch, onMounted, onUnmounted, h } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import Combobox from '@/Components/Combobox.vue';
import DataTable from '@/Components/DataTable.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import Tabs from '@/Components/Tabs.vue';
import DetailAssetModal from '@/Pages/Smart/Admin/ManajemenStok/Modals/DetailAssetModal.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { X, ArrowUpDown, Loader2, ChevronDown, Eye } from 'lucide-vue-next';

export interface EmployeeData {
  id: number;
  user_id?: number | null;
  employee_id: string;
  name: string;
  department: string;
  active_assets_count?: number;
}

export interface LoanItem {
  id: number;
  unit_id: number;
  unit_number: string;
  barang_nama: string;
  brand: string;
  condition: string;
  location: string;
  start_date: string;
  start_date_raw?: string | null;
  due_date?: string;
  due_date_raw?: string | null;
  return_date?: string;
  return_date_raw?: string | null;
  asset?: any;
}

interface Props {
  open: boolean;
  employee: EmployeeData | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
}>();

// --- View Asset Modal Setup ---
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);

const openViewAssetModal = (rowOriginal: any) => {
  selectedAssetForView.value = rowOriginal.asset || rowOriginal;
  isViewAssetModalOpen.value = true;
};

// --- Active Tab State ---
const activeTab = ref('Peminjaman Aktif');
const isLoading = ref(false);
const activeLoans = ref<LoanItem[]>([]);
const historicalLoans = ref<LoanItem[]>([]);

// --- Filters for Peminjaman Aktif ---
const activeSearchQuery = ref('');
const activeBrandFilter = ref('');
const activeConditionFilter = ref('');
const activeLocationFilter = ref('');
const activeRowsPerPage = ref('10');

// --- Filters for Peminjaman Historis ---
const historySearchQuery = ref('');
const historyBrandFilter = ref('');
const historyConditionFilter = ref('');
const historyLocationFilter = ref('');
const historyRowsPerPage = ref('10');

// Dynamic tab labels with counters
const tabLabels = computed(() => [
  `Peminjaman Aktif (${activeLoans.value.length})`,
  `Peminjaman Historis (${historicalLoans.value.length})`,
]);

const currentTabName = computed({
  get() {
    return activeTab.value.startsWith('Peminjaman Aktif') ? tabLabels.value[0] : tabLabels.value[1];
  },
  set(val: string) {
    if (val.startsWith('Peminjaman Aktif')) {
      activeTab.value = 'Peminjaman Aktif';
    } else {
      activeTab.value = 'Peminjaman Historis';
    }
  }
});

// Condition styling helper matching DaftarAsetTab.vue
const renderConditionBadge = (condition: string) => {
  const cond = condition || '';
  let textClass = 'text-foreground text-sm';
  if (cond === 'Bagus' || cond === 'QC Passed') textClass = 'text-emerald-600 font-semibold text-sm';
  else if (cond === 'Lelang/Hibah') textClass = 'text-purple-600 font-semibold text-sm';
  else if (cond === 'Rusak' || cond === 'Rusak Total' || cond === 'Hilang') textClass = 'text-rose-600 font-semibold text-sm';

  return h('span', { class: textClass }, cond || '-');
};

// --- Active Tab Filtering ---
const hasActiveLoansFilters = computed(() => {
  return !!(
    activeSearchQuery.value ||
    activeBrandFilter.value ||
    activeConditionFilter.value ||
    activeLocationFilter.value
  );
});

const clearActiveFilters = () => {
  activeSearchQuery.value = '';
  activeBrandFilter.value = '';
  activeConditionFilter.value = '';
  activeLocationFilter.value = '';
};

const availableActiveBrands = computed(() => {
  const brands = activeLoans.value.map(i => i.brand).filter(Boolean);
  return [...new Set(brands)].sort();
});

const availableActiveLocations = computed(() => {
  const locs = activeLoans.value.map(i => i.location).filter(Boolean);
  return [...new Set(locs)].sort();
});

const filteredActiveLoans = computed(() => {
  let list = activeLoans.value;

  if (activeSearchQuery.value) {
    const q = activeSearchQuery.value.toLowerCase();
    list = list.filter(item =>
      (item.unit_number && item.unit_number.toLowerCase().includes(q)) ||
      (item.barang_nama && item.barang_nama.toLowerCase().includes(q))
    );
  }

  if (activeBrandFilter.value) {
    list = list.filter(item => item.brand === activeBrandFilter.value);
  }

  if (activeConditionFilter.value) {
    list = list.filter(item => item.condition === activeConditionFilter.value);
  }

  if (activeLocationFilter.value) {
    list = list.filter(item => item.location === activeLocationFilter.value);
  }

  return list;
});

// --- Historical Tab Filtering ---
const hasHistoryLoansFilters = computed(() => {
  return !!(
    historySearchQuery.value ||
    historyBrandFilter.value ||
    historyConditionFilter.value ||
    historyLocationFilter.value
  );
});

const clearHistoryFilters = () => {
  historySearchQuery.value = '';
  historyBrandFilter.value = '';
  historyConditionFilter.value = '';
  historyLocationFilter.value = '';
};

const availableHistoryBrands = computed(() => {
  const brands = historicalLoans.value.map(i => i.brand).filter(Boolean);
  return [...new Set(brands)].sort();
});

const availableHistoryLocations = computed(() => {
  const locs = historicalLoans.value.map(i => i.location).filter(Boolean);
  return [...new Set(locs)].sort();
});

const filteredHistoricalLoans = computed(() => {
  let list = historicalLoans.value;

  if (historySearchQuery.value) {
    const q = historySearchQuery.value.toLowerCase();
    list = list.filter(item =>
      (item.unit_number && item.unit_number.toLowerCase().includes(q)) ||
      (item.barang_nama && item.barang_nama.toLowerCase().includes(q))
    );
  }

  if (historyBrandFilter.value) {
    list = list.filter(item => item.brand === historyBrandFilter.value);
  }

  if (historyConditionFilter.value) {
    list = list.filter(item => item.condition === historyConditionFilter.value);
  }

  if (historyLocationFilter.value) {
    list = list.filter(item => item.location === historyLocationFilter.value);
  }

  return list;
});

const conditionOptions = ['Bagus', 'QC Passed', 'Lelang/Hibah', 'Rusak', 'Rusak Total', 'Hilang'];

// Fetch employee loans from endpoint
const fetchEmployeeLoans = async () => {
  if (!props.employee) return;
  isLoading.value = true;

  try {
    const url = `/smart/karyawan/${props.employee.id}/loans`;
    const response = await axios.get(url);
    activeLoans.value = response.data.active || [];
    historicalLoans.value = response.data.history || [];
  } catch (err: any) {
    console.error('Failed to fetch employee loans:', err);
    toast.error('Gagal memuat data peminjaman karyawan.');
    activeLoans.value = [];
    historicalLoans.value = [];
  } finally {
    isLoading.value = false;
  }
};

watch(() => props.open, (isOpen) => {
  if (isOpen && props.employee) {
    activeTab.value = 'Peminjaman Aktif';
    clearActiveFilters();
    clearHistoryFilters();
    activeRowsPerPage.value = '10';
    historyRowsPerPage.value = '10';
    fetchEmployeeLoans();
  } else {
    activeLoans.value = [];
    historicalLoans.value = [];
  }
});

const closeModal = () => {
  emit('update:open', false);
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) {
    if (isViewAssetModalOpen.value) {
      isViewAssetModalOpen.value = false;
      return;
    }
    closeModal();
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

const activePageSizeNumber = computed(() => {
  if (activeRowsPerPage.value === 'Semua baris' || !activeRowsPerPage.value) {
    return 999999;
  }
  return parseInt(activeRowsPerPage.value, 10) || 10;
});

const historyPageSizeNumber = computed(() => {
  if (historyRowsPerPage.value === 'Semua baris' || !historyRowsPerPage.value) {
    return 999999;
  }
  return parseInt(historyRowsPerPage.value, 10) || 10;
});

// --- Table Columns: Peminjaman Aktif ---
// Kode Aset | Nama | Merek | Kondisi | Lokasi | Waktu Mulai | Waktu Tenggat
const activeColumns: ColumnDef<LoanItem>[] = [
  {
    accessorKey: 'unit_number',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kode Aset',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-mono text-muted-foreground font-medium text-sm select-none' }, row.getValue('unit_number'))
  },
  {
    accessorKey: 'barang_nama',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nama',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-medium text-foreground text-sm' }, row.getValue('barang_nama'))
  },
  {
    accessorKey: 'brand',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Merek',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground text-sm' }, row.getValue('brand') || '-')
  },
  {
    accessorKey: 'condition',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kondisi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => renderConditionBadge(row.getValue('condition') as string)
  },
  {
    accessorKey: 'location',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Lokasi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm' }, row.getValue('location') || '-')
  },
  {
    accessorKey: 'start_date_raw',
    id: 'start_date',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Mulai',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground text-sm whitespace-nowrap' }, row.original.start_date || '-')
  },
  {
    accessorKey: 'due_date_raw',
    id: 'due_date',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Tenggat',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm whitespace-nowrap' }, row.original.due_date || '-')
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
  },
];

// --- Table Columns: Peminjaman Historis ---
// Kode Aset | Nama | Merek | Kondisi | Lokasi | Waktu Mulai | Waktu Selesai | Aksi
const historyColumns: ColumnDef<LoanItem>[] = [
  {
    accessorKey: 'unit_number',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kode Aset',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-mono text-muted-foreground font-medium text-sm select-none' }, row.getValue('unit_number'))
  },
  {
    accessorKey: 'barang_nama',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nama',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'font-medium text-foreground text-sm' }, row.getValue('barang_nama'))
  },
  {
    accessorKey: 'brand',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Merek',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground text-sm' }, row.getValue('brand') || '-')
  },
  {
    accessorKey: 'condition',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kondisi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => renderConditionBadge(row.getValue('condition') as string)
  },
  {
    accessorKey: 'location',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Lokasi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm' }, row.getValue('location') || '-')
  },
  {
    accessorKey: 'start_date_raw',
    id: 'start_date',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Mulai',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground text-sm whitespace-nowrap' }, row.original.start_date || '-')
  },
  {
    accessorKey: 'return_date_raw',
    id: 'return_date',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Waktu Selesai',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground text-sm whitespace-nowrap' }, row.original.return_date || '-')
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
  },
];
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open" 
            class="bg-card w-[95vw] max-w-[95vw] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                Detail Karyawan
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[75vh] space-y-4">
              <!-- Static Employee Info Section -->
              <div class="bg-muted/30 rounded-xl p-4 border border-border/60">
                <div class="space-y-1 text-sm">
                  <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.employee?.name || '-' }}</p>
                  <p class="font-bold text-foreground"><span class="text-foreground">NPK:</span> {{ props.employee?.employee_id || '-' }}</p>
                  <p class="text-foreground">Departemen: {{ props.employee?.department || '-' }}</p>
                </div>
              </div>

              <!-- Tabs Navigation -->
              <div class="pt-1">
                <Tabs
                  v-model="currentTabName"
                  :tabs="tabLabels"
                  class="mb-2"
                />
              </div>

              <!-- Loading State -->
              <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center gap-3">
                <Loader2 class="w-8 h-8 animate-spin text-primary" />
                <span class="text-sm text-muted-foreground">Memuat data peminjaman...</span>
              </div>

              <!-- Tab 1: Peminjaman Aktif -->
              <div v-else-if="activeTab.startsWith('Peminjaman Aktif')" class="space-y-4">
                <!-- Toolbar: Search + Filters & Rows per page -->
                <div v-if="activeLoans.length > 0" class="flex items-center justify-between gap-4 flex-wrap">
                  <div class="flex items-center gap-3 flex-wrap flex-grow">
                    <!-- Search Input -->
                    <div class="w-full sm:w-72">
                      <TableSearch 
                        v-model="activeSearchQuery" 
                        placeholder="Cari Kode Aset atau Nama Aset" 
                        bg-class="bg-background"
                      />
                    </div>

                    <!-- Merek Filter Combobox -->
                    <div v-if="availableActiveBrands.length > 0" class="w-full sm:w-48">
                      <Combobox
                        v-model="activeBrandFilter"
                        :options="availableActiveBrands"
                        placeholder="Semua Merek"
                        default-label="Semua Merek"
                        search-placeholder="Cari merek..."
                        width-class="w-full sm:w-48"
                      />
                    </div>

                    <!-- Kondisi Filter Dropdown -->
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-[160px] justify-between rounded-[14px] font-normal', !activeConditionFilter ? 'text-muted-foreground' : 'text-foreground']">
                          <span class="truncate">{{ activeConditionFilter || 'Semua kondisi' }}</span>
                          <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="w-[160px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                        <DropdownMenuItem @select="activeConditionFilter = ''">Semua kondisi</DropdownMenuItem>
                        <DropdownMenuItem v-for="cond in conditionOptions" :key="cond" @select="activeConditionFilter = cond">
                          {{ cond }}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Lokasi Filter Combobox -->
                    <div v-if="availableActiveLocations.length > 0" class="w-full sm:w-56">
                      <Combobox
                        v-model="activeLocationFilter"
                        :options="availableActiveLocations"
                        placeholder="Semua Lokasi"
                        default-label="Semua Lokasi"
                        search-placeholder="Cari lokasi..."
                        width-class="w-full sm:w-56"
                      />
                    </div>

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
                        v-if="hasActiveLoansFilters"
                        @click="clearActiveFilters"
                      />
                    </Transition>
                  </div>

                  <!-- Right: Baris per Halaman -->
                  <div class="flex items-center gap-2 text-sm text-muted-foreground shrink-0">
                    <span class="whitespace-nowrap">Baris per halaman</span>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-[130px] justify-between rounded-[14px] font-normal', (activeRowsPerPage === 'Semua baris' || !activeRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                          {{ activeRowsPerPage }}
                          <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="w-[130px] rounded-[14px]" align="end" :side-offset="4">
                        <DropdownMenuItem @select="activeRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                        <DropdownMenuItem @select="activeRowsPerPage = '10'">10</DropdownMenuItem>
                        <DropdownMenuItem @select="activeRowsPerPage = '25'">25</DropdownMenuItem>
                        <DropdownMenuItem @select="activeRowsPerPage = '50'">50</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                <!-- DataTable Peminjaman Aktif -->
                <div class="pb-1">
                  <DataTable
                    :columns="activeColumns"
                    :data="filteredActiveLoans"
                    :page-size="activePageSizeNumber"
                    :show-selection-count="false"
                    :default-sorting="[{ id: 'start_date', desc: true }]"
                    table-container-class="max-h-[360px]"
                  />
                </div>
              </div>

              <!-- Tab 2: Peminjaman Historis -->
              <div v-else class="space-y-4">
                <!-- Toolbar: Search + Filters & Rows per page (hidden if no entry) -->
                <div v-if="historicalLoans.length > 0" class="flex items-center justify-between gap-4 flex-wrap">
                  <div class="flex items-center gap-3 flex-wrap flex-grow">
                    <!-- Search Input -->
                    <div class="w-full sm:w-72">
                      <TableSearch 
                        v-model="historySearchQuery" 
                        placeholder="Cari Kode Aset atau Nama Aset" 
                        bg-class="bg-background"
                      />
                    </div>

                    <!-- Merek Filter Combobox -->
                    <div v-if="availableHistoryBrands.length > 0" class="w-full sm:w-48">
                      <Combobox
                        v-model="historyBrandFilter"
                        :options="availableHistoryBrands"
                        placeholder="Semua Merek"
                        default-label="Semua Merek"
                        search-placeholder="Cari merek..."
                        width-class="w-full sm:w-48"
                      />
                    </div>

                    <!-- Kondisi Filter Dropdown -->
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-[160px] justify-between rounded-[14px] font-normal', !historyConditionFilter ? 'text-muted-foreground' : 'text-foreground']">
                          <span class="truncate">{{ historyConditionFilter || 'Semua kondisi' }}</span>
                          <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="w-[160px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
                        <DropdownMenuItem @select="historyConditionFilter = ''">Semua kondisi</DropdownMenuItem>
                        <DropdownMenuItem v-for="cond in conditionOptions" :key="cond" @select="historyConditionFilter = cond">
                          {{ cond }}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Lokasi Filter Combobox -->
                    <div v-if="availableHistoryLocations.length > 0" class="w-full sm:w-56">
                      <Combobox
                        v-model="historyLocationFilter"
                        :options="availableHistoryLocations"
                        placeholder="Semua Lokasi"
                        default-label="Semua Lokasi"
                        search-placeholder="Cari lokasi..."
                        width-class="w-full sm:w-56"
                      />
                    </div>

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
                        v-if="hasHistoryLoansFilters"
                        @click="clearHistoryFilters"
                      />
                    </Transition>
                  </div>

                  <!-- Right: Baris per Halaman -->
                  <div class="flex items-center gap-2 text-sm text-muted-foreground shrink-0">
                    <span class="whitespace-nowrap">Baris per halaman</span>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-[130px] justify-between rounded-[14px] font-normal', (historyRowsPerPage === 'Semua baris' || !historyRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                          {{ historyRowsPerPage }}
                          <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="w-[130px] rounded-[14px]" align="end" :side-offset="4">
                        <DropdownMenuItem @select="historyRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                        <DropdownMenuItem @select="historyRowsPerPage = '10'">10</DropdownMenuItem>
                        <DropdownMenuItem @select="historyRowsPerPage = '25'">25</DropdownMenuItem>
                        <DropdownMenuItem @select="historyRowsPerPage = '50'">50</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                <!-- DataTable Peminjaman Historis -->
                <div class="pb-1">
                  <DataTable
                    :columns="historyColumns"
                    :data="filteredHistoricalLoans"
                    :page-size="historyPageSizeNumber"
                    :show-selection-count="false"
                    :default-sorting="[{ id: 'return_date', desc: true }]"
                    table-container-class="max-h-[360px]"
                  />
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2 py-3 px-6 border-t border-border bg-muted/20">
              <Button variant="outline" @click="closeModal" class="rounded-[14px]">
                Tutup
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>

  <!-- Detail Asset Modal (Units) -->
  <DetailAssetModal
    v-model:open="isViewAssetModalOpen"
    :asset="selectedAssetForView"
  />
</template>
