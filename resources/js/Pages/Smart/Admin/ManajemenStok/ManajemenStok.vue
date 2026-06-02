<script setup lang="ts">
import { ref, watch, h, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  Trash2,
  Printer,
  FileDown,
  X
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import Combobox from '@/Components/Combobox.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';

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
import ViewTableButton from '@/Components/ViewTableButton.vue';
import DeleteTableButton from '@/Components/DeleteTableButton.vue';

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

import { useForm } from '@inertiajs/vue3';

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
    accessorKey: 'code',
    size: 117,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kode',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.getValue('code')),
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
    accessorKey: 'specification',
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate', title: row.getValue('specification') }, row.getValue('specification')),
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
    id: 'actions',
    size: 84,
    header: () => h('div', { class: 'text-right no-print' }, 'Aksi'),
    cell: ({ row }) => {
      return h('div', { class: 'flex items-center justify-end gap-2 no-print' }, [
        h(ViewTableButton, {
          onClick: () => handleViewDetail(row.original),
        }),
        h(DeleteTableButton, {
          onClick: () => openDeleteModal(row.original),
        })
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
  
  const headers = ['Kode', 'Kategori', 'Subkategori', 'Merek', 'Spesifikasi', 'Pembaruan Terakhir', 'Total'];
  const rows = data.map((item: any) => [
    `"${item.code}"`,
    `"${item.category}"`,
    `"${item.subcategory}"`,
    `"${item.brand}"`,
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
const newItem = useForm({
  code: '',
  category_id: null as number | null,
  subcategory_id: null as number | null,
  brand_id: null as number | null,
  uom_id: null as number | null,
  specification: '',
  photo: null as File | null,
  photoName: ''
});

const filteredSubcategories = computed(() => {
  return newItem.category_id ? props.subcategories.filter(s => s.category_id == newItem.category_id) : props.subcategories;
});

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

const openCreateModal = () => {
  newItem.reset();
  newItem.clearErrors();
  newItem.photoName = '';
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
};

const generateCode = () => {
  if (!newItem.category_id || !newItem.subcategory_id) return;
  
  const cat = props.categories.find(c => c.id === newItem.category_id);
  const sub = props.subcategories.find(s => s.id === newItem.subcategory_id);
  
  if (!cat || !sub) return;

  const catCode = cat.code.trim();
  const subCode = sub.code.trim();
  
  const sameSubItems = (props.barangs || []).filter(item => item.subcategory_id == newItem.subcategory_id);
  
  let nextNumber = 1;
  if (sameSubItems.length > 0) {
    const numbers = sameSubItems.map(item => {
      const parts = item.code.trim().split('-');
      const lastPart = parts[parts.length - 1];
      const num = parseInt(lastPart);
      return isNaN(num) ? 0 : num;
    });
    nextNumber = Math.max(...numbers) + 1;
  }
  
  const formattedNumber = nextNumber.toString().padStart(4, '0');
  newItem.code = `${catCode}-${subCode}-${formattedNumber}`;
};

watch(() => newItem.category_id, () => { 
  newItem.code = ''; 
  newItem.subcategory_id = null; 
  newItem.brand_id = null;
});
watch(() => newItem.subcategory_id, () => { 
  newItem.code = ''; 
});

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    alert('Hanya diperbolehkan file .jpg, .jpeg, atau .png');
    return;
  }

  if (file.size > 1024 * 1024) {
    alert('Ukuran foto maksimal 1MB');
    return;
  }

  newItem.photo = file;
  newItem.photoName = file.name;
};

const triggerFileInput = () => {
  const input = document.getElementById('photo-upload') as HTMLInputElement;
  input?.click();
};

const isFormValid = computed(() => {
  return newItem.code && 
         newItem.category_id && 
         newItem.subcategory_id && 
         newItem.brand_id && 
         newItem.uom_id && 
         newItem.specification &&
         newItem.photo && !newItem.processing;
});

const handleCreateItem = () => {
  if (!isFormValid.value) return;
  newItem.transform((data) => ({
    number: data.code,
    subcategory_id: data.subcategory_id,
    brand_id: data.brand_id,
    uom_id: data.uom_id,
    specification: data.specification,
    image_url: data.photo,
  })).post('/smart/inventory/barangs', {
    onSuccess: () => {
      closeCreateModal();
    },
  });
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
    // If multiple deletion is supported by backend, else loop or alert
    ids.forEach(id => {
       router.delete(`/smart/inventory/barangs/${id}`);
    });
    if (dataTableRef.value) {
      dataTableRef.value.table.resetRowSelection();
    }
    closeDeleteModal();
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
                    placeholder="Cari Barang..." 
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
                  <button 
                    @click="openDeleteModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
                    :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                    class="flex items-center gap-2 px-4 py-2 bg-destructive hover:opacity-70 text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm disabled:opacity-50"
                  >
                    <Trash2 class="w-4 h-4" />
                    <span class="hidden sm:inline">Hapus Barang</span>
                  </button>
                  <ExportButtonGroup 
                    @export-excel="handleExportExcel"
                    @export-csv="handleExportCSV"
                  />
                </div>
              </div>
              
              <button 
                @click="openCreateModal"
                class="flex items-center gap-1.5 bg-gradient-primary hover:opacity-90 text-primary-foreground px-5 py-2.5 rounded-[14px] text-sm font-semibold transition-all shadow-sm whitespace-nowrap"
              >
                <Plus class="w-4 h-4" />
                <span>Barang Baru</span>
              </button>
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

    <!-- Create Item Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isCreateModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              v-if="isCreateModalOpen"
              class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col"
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
                <h3 class="text-lg font-bold text-foreground">Pembuatan Barang Baru</h3>
                <button @click="closeCreateModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label for="newItemCode" class="text-sm font-medium text-foreground block">Kode Barang<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <input 
                          type="text" 
                          id="newItemCode"
                          name="code"
                          v-model="newItem.code"
                          disabled
                          placeholder="Kode Barang belum di-generate" 
                          class="flex-grow px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                        />
                        <button 
                          @click="generateCode"
                          :disabled="!newItem.category_id || !newItem.subcategory_id"
                          class="px-6 py-2 bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors disabled:opacity-50"
                        >
                          Generate
                        </button>
                      </div>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kategori<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !newItem.category_id ? 'text-muted-foreground' : 'text-foreground']">
                            {{ props.categories.find(c => c.id === newItem.category_id)?.name || 'Pilih kategori' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="cat in props.categories" :key="cat.id" @select="newItem.category_id = cat.id">
                            {{ cat.name }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Subkategori<span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="newItem.subcategory_id"
                        :options="filteredSubcategories"
                        search-placeholder="Cari subkategori..."
                        default-label="Pilih subkategori"
                        width-class="w-full h-10 px-4"
                        :disabled="!newItem.category_id"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Satuan<span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="newItem.uom_id"
                        :options="props.uoms"
                        search-placeholder="Cari satuan..."
                        default-label="Pilih satuan barang"
                        width-class="w-full h-10 px-4"
                      />
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Merek<span class="text-rose-500">*</span></label>
                      <Combobox
                        v-model="newItem.brand_id"
                        :options="props.brands"
                        search-placeholder="Cari merek..."
                        default-label="Pilih merek"
                        width-class="w-full h-10 px-4"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label for="newItemSpecification" class="text-sm font-medium text-foreground block">Spesifikasi<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        id="newItemSpecification"
                        name="specification"
                        v-model="newItem.specification"
                        maxlength="255"
                        placeholder="Input spesifikasinya di sini..." 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label for="photo-upload" class="text-sm font-medium text-foreground block">Foto <span class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 text-muted-foreground truncate flex items-center h-10">
                          {{ newItem.photoName || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="photo-upload" 
                          name="photo"
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleFileUpload"
                        />
                        <button 
                          @click="triggerFileInput"
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
                    @click="closeCreateModal"
                    class="px-8 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors"
                  >
                    Batal
                  </button>
                  <button 
                    @click="handleCreateItem"
                    :disabled="!isFormValid"
                    class="px-8 py-2.5 bg-gradient-primary hover:opacity-90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98] disabled:opacity-50"
                  >
                    Buat Barang
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
      item-name="Barang"
      :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : null"
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