<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed, h } from 'vue';
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
import { 
  Breadcrumb, 
  BreadcrumbLink, 
  BreadcrumbList, 
  BreadcrumbItem, 
  BreadcrumbSeparator 
} from '@/Components/ui/breadcrumb';
import TableSearch from '@/Components/TableSearch.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Combobox from '@/Components/Combobox.vue';
import CreateTipeModal from '../Modals/CreateTipeModal.vue';
import EditTipeModal from '../Modals/EditTipeModal.vue';
import DaftarLOTTab from './DaftarLOTTab.vue';

interface Props {
  barangs?: any[];
  categories?: any[];
  subcategories?: any[];
  brands?: any[];
  uoms?: any[];
  lots?: any[];
  organizers?: any[];
  vendors?: any[];
  locations?: any[];
  floors?: any[];
  rooms?: any[];
  projects?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  barangs: () => [],
  categories: () => [],
  subcategories: () => [],
  brands: () => [],
  uoms: () => [],
  lots: () => [],
  organizers: () => [],
  vendors: () => [],
  locations: () => [],
  floors: () => [],
  rooms: () => [],
  projects: () => [],
});

// Selected Barang for LOT view
const selectedBarang = ref<any | null>(null);

const activeBarang = computed(() => {
  if (!selectedBarang.value) return null;
  return props.barangs.find(b => String(b.id) === String(selectedBarang.value.id)) || selectedBarang.value;
});

const lotsForActiveBarang = computed(() => {
  if (!activeBarang.value) return [];
  return props.lots.filter(lot => String(lot.barang_id) === String(activeBarang.value.id));
});

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  const barangIdParam = urlParams.get('barang_id');
  if (barangIdParam) {
    const found = props.barangs.find(b => String(b.id) === barangIdParam);
    if (found) {
      selectedBarang.value = found;
    }
  }
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

watch(selectedBarang, (newVal) => {
  if (newVal) {
    window.history.replaceState({}, '', `/smart/inventory/stok-habis-pakai?barang_id=${newVal.id}`);
  } else {
    window.history.replaceState({}, '', '/smart/inventory/stok-habis-pakai');
  }
});

// Filters for Barang list
const searchQuery = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const rowsPerPage = ref('Semua baris');
const dataTableRef = ref<any>(null);

const hasActiveFilters = computed(() => {
  return !!(categoryFilter.value || subcategoryFilter.value || brandFilter.value || searchQuery.value);
});

const clearFilters = () => {
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  searchQuery.value = '';
};

const filteredBarangs = computed(() => {
  let list = props.barangs || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(item => 
      (item.name && item.name.toLowerCase().includes(q)) || 
      (item.code && item.code.toLowerCase().includes(q)) ||
      (item.specification && item.specification.toLowerCase().includes(q))
    );
  }

  if (categoryFilter.value) {
    list = list.filter(item => item.category === categoryFilter.value);
  }

  if (subcategoryFilter.value) {
    list = list.filter(item => item.subcategory === subcategoryFilter.value);
  }

  if (brandFilter.value) {
    list = list.filter(item => item.brand === brandFilter.value);
  }

  return list;
});

const filteredCategories = computed(() => {
  return props.categories || [];
});

const filteredSubcategories = computed(() => {
  const cat = filteredCategories.value.find(c => c.name === categoryFilter.value);
  if (!cat) return props.subcategories.map(s => s.name);
  return props.subcategories.filter(s => s.category_id == cat.id).map(s => s.name);
});

const filteredBrands = computed(() => {
  return props.brands.map(b => b.name);
});

watch(categoryFilter, () => {
  subcategoryFilter.value = '';
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

// Table columns for Consumable Barang
const columns: ColumnDef<any>[] = [
  {
    id: 'select',
    size: 40,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
        'aria-label': 'Select all',
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
        'aria-label': 'Select row',
      })
    ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'name',
    size: 225,
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Nama',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground truncate font-medium', title: row.getValue('name') }, row.getValue('name')),
  },
  {
    accessorKey: 'specification',
    size: 225,
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Spesifikasi',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => {
      const spec = row.getValue('specification') as string | null | undefined;
      return h('div', {
        class: `${spec ? 'text-foreground' : 'text-muted-foreground'} truncate`,
        title: spec || '-'
      }, spec || '-');
    },
  },
  {
    accessorKey: 'brand',
    size: 225,
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Merek',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('brand')),
  },
  {
    accessorKey: 'category',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kategori',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('category')),
  },
  {
    accessorKey: 'subcategory',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Subkategori',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('subcategory')),
  },
  {
    accessorKey: 'amount',
    size: 110,
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Total Stok',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => {
      const item = row.original;
      const remainingStock = Number(item.amount ?? 0);
      const totalStock = (props.lots || [])
        .filter(l => String(l.barang_id) === String(item.id))
        .reduce((sum, l) => sum + Number(l.initial_quantity ?? 0), 0);

      const threshold = item.min_stock_threshold !== null && item.min_stock_threshold !== undefined
        ? Number(item.min_stock_threshold)
        : null;

      let textColorClass = 'text-foreground';
      if (remainingStock === 0) {
        textColorClass = 'text-black dark:text-black font-semibold';
      } else if (threshold !== null && remainingStock <= threshold) {
        textColorClass = 'text-rose-600 font-semibold';
      }

      return h('div', { class: `font-medium ${textColorClass}` }, `${remainingStock}/${totalStock}`);
    },
  },
  {
    accessorKey: 'lastUpdate',
    size: 181,
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Pembaruan Terakhir',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('lastUpdate')),
    sortingFn: (rowA, rowB, columnId) => {
      const parseDate = (str: string) => {
        if (!str || str === '-') return 0;
        const parts = str.trim().split(/\s+/);
        const dateParts = parts[0].split('/').map(Number);
        if (dateParts.length !== 3) return 0;
        const [d, m, y] = dateParts;
        let hour = 0, minute = 0;
        if (parts[1]) {
          const timeParts = parts[1].split(':').map(Number);
          hour = timeParts[0] || 0;
          minute = timeParts[1] || 0;
        }
        return new Date(y, m - 1, d, hour, minute).getTime();
      };
      return parseDate(rowA.getValue(columnId)) - parseDate(rowB.getValue(columnId));
    }
  },
  {
    id: 'actions',
    size: 84,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, 'Aksi'),
    cell: ({ row }) => {
      return h('div', { class: 'flex items-center justify-end gap-2 no-print' }, [
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: 'Lihat Detail',
          onClick: () => {
            selectedBarang.value = row.original;
          },
        }, () => [
          h(Eye),
          h('span', { class: 'sr-only' }, 'Lihat Detail')
        ]),
        h(Button, {
          variant: 'table-destructive',
          size: 'icon-sm',
          title: 'Hapus',
          onClick: () => openDeleteModal(row.original),
        }, () => [
          h(Trash2),
          h('span', { class: 'sr-only' }, 'Hapus')
        ])
      ]);
    },
  },
];

const getRowClass = (item: any) => {
  const remainingStock = Number(item.amount ?? 0);
  const threshold = item.min_stock_threshold !== null && item.min_stock_threshold !== undefined
    ? Number(item.min_stock_threshold)
    : null;

  if (remainingStock === 0) {
    return 'bg-red-100 hover:bg-red-200 text-black font-medium border-red-200';
  } else if (threshold !== null && remainingStock <= threshold) {
    return 'bg-amber-100 hover:bg-amber-200 text-black font-medium border-amber-200';
  }
  return '';
};

// Create Tipe Modal Logic
const isCreateModalOpen = ref(false);
const openCreateModal = () => {
  isCreateModalOpen.value = true;
};

// Bulk Edit Tipe Modal Logic
const isBulkEditModalOpen = ref(false);
const selectedItemsForEdit = ref<any[]>([]);

const openBulkEditModal = () => {
  if (!dataTableRef.value) return;
  const selectedRows = dataTableRef.value.table.getFilteredRowModel().rows
    .filter((r: any) => r.getIsSelected())
    .map((r: any) => r.original);
  
  if (selectedRows.length === 0) return;
  selectedItemsForEdit.value = selectedRows;
  isBulkEditModalOpen.value = true;
};

const handleEditSuccess = () => {
  if (dataTableRef.value) {
    dataTableRef.value.table.resetRowSelection();
  }
};

// Delete Barang Modal Logic
const isDeleteModalOpen = ref(false);
const itemsToDelete = ref<any[]>([]);
const processing = ref(false);

const openDeleteModal = (items: any | any[]) => {
  itemsToDelete.value = Array.isArray(items) ? items : [items];
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
    router.delete(`/smart/inventory/barangs/${ids[0]}`, {
      onStart: () => { processing.value = true; },
      onFinish: () => { processing.value = false; },
      onSuccess: () => {
        if (selectedBarang.value && ids.includes(selectedBarang.value.id)) {
          selectedBarang.value = null;
        }
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
        closeDeleteModal();
      }
    });
  } else {
    router.delete('/smart/inventory/barangs/bulk', {
      data: { ids },
      onStart: () => { processing.value = true; },
      onFinish: () => { processing.value = false; },
      onSuccess: () => {
        if (selectedBarang.value && ids.includes(selectedBarang.value.id)) {
          selectedBarang.value = null;
        }
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
        closeDeleteModal();
      }
    });
  }
};

// Error Modal
const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

const closeErrorModal = () => {
  isErrorModalOpen.value = false;
  if ((page.props as any).flash) {
    (page.props as any).flash.error = null;
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

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isCreateModalOpen.value) {
      isCreateModalOpen.value = false;
    } else if (isBulkEditModalOpen.value) {
      isBulkEditModalOpen.value = false;
    } else if (isDeleteModalOpen.value) {
      closeDeleteModal();
    } else if (isErrorModalOpen.value) {
      closeErrorModal();
    }
  }
};
</script>

<template>
  <div>
    <!-- Adjusted Breadcrumb -->
    <Breadcrumb class="no-print mb-3">
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink 
            :href="selectedBarang ? undefined : '/smart/inventory/stok-habis-pakai'"
            @click.prevent="selectedBarang = null"
            :class="selectedBarang ? 'cursor-pointer hover:text-foreground' : ''"
          >
            Daftar Stok (Habis Pakai)
          </BreadcrumbLink>
        </BreadcrumbItem>
        <template v-if="selectedBarang && activeBarang">
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <span class="text-muted-foreground">{{ activeBarang.code }}</span>
          </BreadcrumbItem>
        </template>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Detail View: DaftarLOTTab when a consumable Barang is selected -->
    <div v-if="selectedBarang && activeBarang">
      <DaftarLOTTab
        :barang="activeBarang"
        :lots="lotsForActiveBarang"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :locations="props.locations"
        :floors="props.floors"
        :rooms="props.rooms"
        :projects="props.projects"
      />
    </div>

    <!-- Table View: Consumable Barangs list -->
    <div v-else class="space-y-4">
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
                    placeholder="Cari Tipe..." 
                  />
                </div>

                <!-- Merek Combobox -->
                <Combobox
                  v-model="brandFilter"
                  :options="filteredBrands"
                  search-placeholder="Cari merek..."
                  default-label="Semua merek"
                />

                <!-- Kategori Dropdown -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !categoryFilter ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ categoryFilter || 'Semua kategori' }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="categoryFilter = ''">Semua kategori</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in filteredCategories" :key="cat.id" @select="categoryFilter = cat.name">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Subkategori Combobox -->
                <Combobox
                  v-model="subcategoryFilter"
                  :options="filteredSubcategories"
                  search-placeholder="Cari subkategori..."
                  default-label="Semua subkategori"
                />

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

            <!-- Row 2: Bulk Actions & New Item -->
            <div class="flex flex-wrap items-end justify-between gap-4 pt-2">
              <div class="space-y-2 flex-1 min-w-0">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">Aksi Terpilih</label>
                <div class="flex flex-wrap gap-2">
                  <Button 
                    @click="openBulkEditModal"
                    :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                    variant="more-round-warning"
                  >
                    <Pencil class="w-4 h-4" />
                    <span class="hidden sm:inline">Edit Terpilih</span>
                  </Button>
                  <Button 
                    @click="openDeleteModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
                    :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                    variant="destructive"
                  >
                    <Trash2 class="w-4 h-4" />
                    <span class="hidden sm:inline">Hapus Terpilih</span>
                  </Button>
                </div>
              </div>
              
              <Button
                @click="openCreateModal"
                variant="primary"
                size="lg"
              >
                <Plus class="w-4 h-4" />
                <span>Tipe Baru</span>               
              </Button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="filteredBarangs" 
            :filter-value="searchQuery"
            :default-sorting="[{ id: 'lastUpdate', desc: true }]"
            :row-class="getRowClass"
          />
        </div>
      </div>
    </div>

    <!-- Modals -->
    <CreateTipeModal
      v-model:open="isCreateModalOpen"
      :categories="props.categories"
      :subcategories="props.subcategories"
      :uoms="props.uoms"
      :brands="props.brands"
      :barangs="props.barangs"
    />

    <EditTipeModal
      v-model:open="isBulkEditModalOpen"
      :items="selectedItemsForEdit"
      :uoms="props.uoms"
      :brands="props.brands"
      @success="handleEditSuccess"
    />

    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="itemsToDelete.length"
      item-name="Tipe"
      :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : itemsToDelete"
      :processing="processing"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </div>
</template>
