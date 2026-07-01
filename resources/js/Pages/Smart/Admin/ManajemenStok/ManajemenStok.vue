<script setup lang="ts">
import { ref, watch, h, onMounted, onUnmounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  Trash2,
  X,
  Pencil,
  Eye
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import Combobox from '@/Components/Combobox.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';

interface Category    { id: number; code: string; name: string; is_consumable: boolean; }
interface Subcategory { id: number; code: string; name: string; category_id: number; category: Category; }
interface SimpleItem  { id: number; name: string; }

interface Props {
  user: { name: string; email: string; };
  categories:    Category[];
  subcategories: Subcategory[];
  uoms:          SimpleItem[];
  brands:        SimpleItem[];
  barangs?:      any[];
}

const props = withDefaults(defineProps<Props>(), {
  categories:    () => [],
  subcategories: () => [],
  uoms:          () => [],
  brands:        () => [],
  barangs:       () => [],
});

import CreateTipeModal from './Modals/CreateTipeModal.vue';
import EditTipeModal from './Modals/EditTipeModal.vue';

const searchQuery = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const rowsPerPage = ref('Semua baris');



const hasActiveFilters = computed(() => {
  return !!(categoryFilter.value || subcategoryFilter.value || brandFilter.value || searchQuery.value);
});

const clearFilters = () => {
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  searchQuery.value = '';
};

const dataTableRef = ref<any>(null);

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
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Nama',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate', title: row.getValue('name') }, row.getValue('name')),
  },
  {
    accessorKey: 'specification',
    size: 225,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Spesifikasi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const spec = row.getValue('specification') as string | null | undefined;
      return h('div', {
        class: `${spec ? 'text-foreground' : 'text-muted-foreground'} truncate`,
        title: spec || '-'
      }, spec || '-');
    },
  },
  {
    accessorKey: 'category',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kategori',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('category')),
    filterFn: (row, id, value) => {
      if (!value) return true;
      return row.getValue(id) === value;
    }
  },
  {
    accessorKey: 'subcategory',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Subkategori',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('subcategory')),
    filterFn: (row, id, value) => {
      if (!value) return true;
      return row.getValue(id) === value;
    }
  },
  {
    accessorKey: 'brand',
    size: 225,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Merek',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('brand')),
    filterFn: (row, id, value) => {
      if (!value) return true;
      return row.getValue(id) === value;
    }
  },
  {
    accessorKey: 'amount',
    size: 95,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Total',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'font-medium text-foreground' }, row.getValue('amount')),
  },
  {
    accessorKey: 'lastUpdate',
    size: 181,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Pembaruan Terakhir',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
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
    header: () => h('div', { class: 'no-print' }, 'Aksi'),
    cell: ({ row }) => {
      return h('div', { class: 'flex items-center justify-end gap-2 no-print' }, [
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: 'Lihat Detail',
          onClick: () => handleViewDetail(row.original),
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

// Watchers for custom filters
watch(categoryFilter, (val) => {
  if (dataTableRef.value) {
    dataTableRef.value.table.getColumn('category')?.setFilterValue(val);
  }
});

watch(subcategoryFilter, (val) => {
  if (dataTableRef.value) {
    dataTableRef.value.table.getColumn('subcategory')?.setFilterValue(val);
  }
});

watch(brandFilter, (val) => {
  if (dataTableRef.value) {
    dataTableRef.value.table.getColumn('brand')?.setFilterValue(val);
  }
});

watch(rowsPerPage, (val) => {
  if (dataTableRef.value) {
    if (val === 'Semua baris') {
      dataTableRef.value.table.setPageSize(999999);
    } else {
      dataTableRef.value.table.setPageSize(Number(val));
    }
  }
}, { immediate: true });

onMounted(() => {
  if (dataTableRef.value && rowsPerPage.value === 'Semua baris') {
    dataTableRef.value.table.setPageSize(999999);
  }
});

const getExportData = () => {
  if (!dataTableRef.value) return props.barangs || [];
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleViewDetail = (item: any) => {
  router.get(`/smart/inventory/${item.id}`);
};

// Export & Print Logic

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode', 'Kategori', 'Subkategori', 'Merek', 'Nama', 'Spesifikasi', 'Pembaruan Terakhir', 'Total'];
  const rows = data.map((item: any) => [
    `"${item.code}"`,
    `"${item.category}"`,
    `"${item.subcategory}"`,
    `"${item.brand}"`,
    `"${item.name}"`,
    `"${item.specification}"`,
    `"${item.lastUpdate}"`,
    `"${item.amount}"`
  ]);

  let csvContent = "\uFEFFsep=,\n" 
    + headers.map(h => `"${h}"`).join(",") + "\n"
    + rows.map((e: any) => e.join(",")).join("\n");

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `inventory_export_${new Date().getTime()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const handleExportExcel = () => {
  handleExportCSV();
};

// Create Modal Logic
const isCreateModalOpen = ref(false);
const openCreateModal = () => {
  isCreateModalOpen.value = true;
};

const mainFilteredSubcategories = computed(() => {
  const cat = props.categories.find(c => c.name === categoryFilter.value);
  if (!cat) return props.subcategories.map(s => s.name);
  return props.subcategories.filter(s => s.category_id == cat.id).map(s => s.name);
});

const mainFilteredBrands = computed(() => {
  return props.brands.map(b => b.name);
});

// Update filters on mount and when category changes
onMounted(() => {
  // Filters updated via computed
});

watch(categoryFilter, () => {
  subcategoryFilter.value = '';
  brandFilter.value = '';
});

watch(subcategoryFilter, () => {
  brandFilter.value = '';
});

// Bulk Edit Modal Logic
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

// Delete Modal Logic
const isDeleteModalOpen = ref(false);
const itemsToDelete = ref<any[]>([]);

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
      onSuccess: () => {
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
        closeDeleteModal();
      }
    });
  } else {
    router.delete('/smart/inventory/barangs/bulk', {
      data: { ids },
      onSuccess: () => {
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
        closeDeleteModal();
      }
    });
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

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <AppLayout title="Manajemen Stok">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory">Manajemen Stok</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>


    <div class="space-y-4">
      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">Daftar Stok</h2>
          
          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                  <label for="search-barang" class="text-xs text-muted-foreground font-medium block">Filter</label>
                  <TableSearch 
                    id="search-barang"
                    name="search"
                    v-model="searchQuery"
                    placeholder="Cari Tipe..." 
                  />
                </div>

                <!-- Category Dropdown (regular) -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !categoryFilter ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ categoryFilter || 'Semua kategori' }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="categoryFilter = ''">Semua kategori</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in props.categories" :key="cat.id" @select="categoryFilter = cat.name">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Subcategory Combobox (searchable/scrollable) -->
                <Combobox
                  v-model="subcategoryFilter"
                  :options="mainFilteredSubcategories"
                  search-placeholder="Cari subkategori..."
                  default-label="Semua subkategori"
                />

                <!-- Brand Combobox (searchable/scrollable) -->
                <Combobox
                  v-model="brandFilter"
                  :options="mainFilteredBrands"
                  search-placeholder="Cari merek..."
                  default-label="Semua merek"
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
                  <ExportButtonGroup 
                    @export-excel="handleExportExcel"
                    @export-csv="handleExportCSV"
                  />
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
          <!-- Print-only Filter Info -->
          <div v-if="searchQuery || categoryFilter || subcategoryFilter || brandFilter" class="print-only mb-4 text-left">
            <div class="font-bold text-xs text-foreground mb-1">Filter:</div>
            <div class="text-[10px] text-muted-foreground space-y-0.5">
              <div v-if="searchQuery">Pencarian: {{ searchQuery }}</div>
              <div v-if="categoryFilter">Kategori: {{ categoryFilter }}</div>
              <div v-if="subcategoryFilter">Subkategori: {{ subcategoryFilter }}</div>
              <div v-if="brandFilter">Merek: {{ brandFilter }}</div>
            </div>
          </div>

          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="props.barangs || []" 
            :filter-value="searchQuery"
            :default-sorting="[{ id: 'lastUpdate', desc: true }]"
          />
        </div>
      </div>
    </div>

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
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />
    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </AppLayout>
</template>