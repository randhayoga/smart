<script setup lang="ts">
import { ref, watch, h, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  Trash2,
  Printer,
  FileDown,
  Eye,
  X,
  Check,
  ChevronsUpDown
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

// Dummy Data
const categories = ['Elektronik', 'Furnitur', 'ATK', 'Kendaraan'];

const subcategoryMap: Record<string, string[]> = {
  'Elektronik': ['Laptop', 'Keyboard', 'Monitor', 'Mouse'],
  'Furnitur': ['Meja', 'Kursi', 'Lemari', 'Sofa'],
  'ATK': ['Kertas', 'Pulpen', 'Buku', 'Penghapus'],
  'Kendaraan': ['Mobil', 'Motor', 'Sepeda']
};

const brandMap: Record<string, string[]> = {
  'Elektronik': ['Asus', 'Lenovo', 'Samsung', 'Logitech'],
  'Furnitur': ['IKEA', 'Informa', 'Olympic'],
  'ATK': ['Sinar Dunia', 'Standard', 'Joyko'],
  'Kendaraan': ['Toyota', 'Honda', 'Yamaha']
};
const units = Array.from({ length: 150 }, (_, i) => (i + 1).toString());

const dummyInventory = [
  { id: 1, code: 'ELE-LAP-0001', category: 'Elektronik', subcategory: 'Laptop', brand: 'Asus', specification: 'ROG Zephyrus G14, 16GB RAM, 512GB SSD', lastUpdate: '05-05-2026 10:00', amount: 5 },
  { id: 2, code: 'FUR-MEJ-0001', category: 'Furnitur', subcategory: 'Meja', brand: 'IKEA', specification: 'Linnmon Table, White, 120x60cm', lastUpdate: '05-05-2026 11:30', amount: 12 },
  { id: 3, code: 'ATK-KER-0001', category: 'ATK', subcategory: 'Kertas', brand: 'Sinar Dunia', specification: 'A4 80gsm, 500 sheets', lastUpdate: '04-05-2026 09:15', amount: 50 },
  { id: 4, code: 'KEN-MOB-0001', category: 'Kendaraan', subcategory: 'Mobil', brand: 'Toyota', specification: 'Avanza 2023, Silver Metallic', lastUpdate: '03-05-2026 15:45', amount: 2 },
  { id: 5, code: 'ELE-LAP-0002', category: 'Elektronik', subcategory: 'Laptop', brand: 'Lenovo', specification: 'ThinkPad X1 Carbon, 32GB RAM', lastUpdate: '05-05-2026 14:20', amount: 3 },
];

const searchQuery = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const rowsPerPage = ref('Semua baris');

// Combobox open states
const subcategoryOpen = ref(false);
const brandOpen = ref(false);

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
        'Jumlah',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'font-medium text-foreground' }, row.getValue('amount')),
  },
  {
    id: 'actions',
    size: 84,
    header: () => h('div', { class: 'w-full no-print pr-6' }, 'Aksi'),
    cell: ({ row }) => {
      return h('div', { class: 'flex items-center justify-end gap-2 no-print' }, [
        h('button', {
          onClick: () => handleViewDetail(row.original),
          class: 'p-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50'
        }, [h(Eye, { class: 'w-3.5 h-3.5' })]),
        h('button', {
          onClick: () => openDeleteModal(row.original),
          class: 'p-2 bg-rose-500 hover:bg-rose-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50'
        }, [h(Trash2, { class: 'w-3.5 h-3.5' })])
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
  if (!dataTableRef.value) return dummyInventory;
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const handleViewDetail = (item: any) => {
  const code = item.code || 'CAT-SUB-XXXX';
  router.get(`/smart/inventory/${code}`);
};

// Export & Print Logic
const handlePrint = () => {
  window.print();
};

const handleExportCSV = () => {
  const data = getExportData();
  if (data.length === 0) return;
  
  const headers = ['Kode', 'Kategori', 'Subkategori', 'Merek', 'Spesifikasi', 'Pembaruan Terakhir', 'Jumlah'];
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

const handleExportPDF = () => {
  window.print();
};

// Create Modal Logic
const isCreateModalOpen = ref(false);
const newItem = ref({
  code: '',
  category: '',
  subcategory: '',
  brand: '',
  unit: '',
  specification: '',
  photo: null as File | null,
  photoName: ''
});

const filteredSubcategories = computed(() => {
  return newItem.value.category ? subcategoryMap[newItem.value.category] || [] : [];
});

const filteredBrands = computed(() => {
  return newItem.value.category ? brandMap[newItem.value.category] || [] : [];
});

const mainFilteredSubcategories = ref<string[]>([]);
const mainFilteredBrands = ref<string[]>([]);

// Function to update filtered lists
const updateMainFilters = () => {
  const cat = categoryFilter.value;
  if (!cat) {
    mainFilteredSubcategories.value = ([] as string[]).concat(...Object.values(subcategoryMap));
    mainFilteredBrands.value = ([] as string[]).concat(...Object.values(brandMap));
  } else {
    mainFilteredSubcategories.value = subcategoryMap[cat] || [];
    mainFilteredBrands.value = brandMap[cat] || [];
  }
};

// Update filters on mount and when category changes
onMounted(() => {
  updateMainFilters();
});

watch(categoryFilter, () => {
  updateMainFilters();
  subcategoryFilter.value = '';
  brandFilter.value = '';
});

watch(subcategoryFilter, () => {
  brandFilter.value = '';
});

const openCreateModal = () => {
  newItem.value = {
    code: '',
    category: '',
    subcategory: '',
    brand: '',
    unit: '',
    specification: '',
    photo: null,
    photoName: ''
  };
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
};

const generateCode = () => {
  if (!newItem.value.category || !newItem.value.subcategory) return;
  
  const catCode = newItem.value.category.substring(0, 3).toUpperCase();
  const subCode = newItem.value.subcategory.substring(0, 3).toUpperCase();
  
  // Find the highest number for this subcategory in dummyInventory
  // Note: Standard format expected is CAT[CAT]-SUB[SUB]-[XXXX]
  const sameSubItems = dummyInventory.filter(item => item.subcategory === newItem.value.subcategory);
  
  let nextNumber = 1;
  if (sameSubItems.length > 0) {
    const numbers = sameSubItems.map(item => {
      // Extract the last 4 digits from the code
      // If code doesn't match the new format, it will return 0
      const parts = item.code.split('-');
      const lastPart = parts[parts.length - 1];
      const num = parseInt(lastPart);
      return isNaN(num) ? 0 : num;
    });
    nextNumber = Math.max(...numbers) + 1;
  }
  
  const formattedNumber = nextNumber.toString().padStart(4, '0');
  newItem.value.code = `${catCode}-${subCode}-${formattedNumber}`;
};

watch(() => newItem.value.category, () => { 
  newItem.value.code = ''; 
  newItem.value.subcategory = ''; 
  newItem.value.brand = '';
});
watch(() => newItem.value.subcategory, () => { 
  newItem.value.code = ''; 
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

  newItem.value.photo = file;
  newItem.value.photoName = file.name;
};

const triggerFileInput = () => {
  const input = document.getElementById('photo-upload') as HTMLInputElement;
  input?.click();
};

const isFormValid = computed(() => {
  return newItem.value.code && 
         newItem.value.category && 
         newItem.value.subcategory && 
         newItem.value.brand && 
         newItem.value.unit && 
         newItem.value.specification &&
         newItem.value.photo;
});

const handleCreateItem = () => {
  if (!isFormValid.value) return;
  const newCode = newItem.value.code;
  closeCreateModal();
  router.get(`/smart/inventory/${newCode}`);
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
  // Logic to actually delete items (e.g., API call)
  alert(`Berhasil menghapus ${itemsToDelete.value.length} barang`);
  
  if (dataTableRef.value) {
    dataTableRef.value.table.resetRowSelection();
  }
  
  closeDeleteModal();
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
        <div class="py-5 no-print">
          <h2 class="text-lg font-bold text-foreground">Daftar Stok</h2>
          
          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                  <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                  <TableSearch 
                    v-model="searchQuery"
                    placeholder="Cari kode Barang..." 
                  />
                </div>

                <!-- Category Dropdown (regular) -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-[200px] justify-between rounded-[14px] font-normal text-muted-foreground">
                      <span class="truncate">{{ categoryFilter || 'Semua kategori' }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="categoryFilter = ''">Semua kategori</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in categories" :key="cat" @select="categoryFilter = cat">
                      {{ cat }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Subcategory Combobox (searchable/scrollable) -->
                <Popover v-model:open="subcategoryOpen">
                  <PopoverTrigger asChild>
                    <Button variant="outline" role="combobox" :aria-expanded="subcategoryOpen" class="w-[200px] justify-between rounded-[14px] font-normal text-muted-foreground">
                      <span class="truncate">{{ subcategoryFilter || 'Semua subkategori' }}</span>
                      <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent class="w-[200px] p-0 rounded-[14px]" align="start">
                    <Command>
                      <CommandInput placeholder="Cari subkategori..." />
                      <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                      <CommandList>
                        <CommandGroup>
                          <CommandItem value="semua-subkategori" @select="subcategoryFilter = ''; subcategoryOpen = false">
                            <Check :class="['mr-2 h-4 w-4', !subcategoryFilter ? 'opacity-100' : 'opacity-0']" />
                            Semua subkategori
                          </CommandItem>
                          <CommandItem
                            v-for="sub in mainFilteredSubcategories"
                            :key="sub"
                            :value="sub"
                            @select="subcategoryFilter = sub; subcategoryOpen = false"
                          >
                            <Check :class="['mr-2 h-4 w-4', subcategoryFilter === sub ? 'opacity-100' : 'opacity-0']" />
                            {{ sub }}
                          </CommandItem>
                        </CommandGroup>
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>

                <!-- Brand Combobox (searchable/scrollable) -->
                <Popover v-model:open="brandOpen">
                  <PopoverTrigger asChild>
                    <Button variant="outline" role="combobox" :aria-expanded="brandOpen" class="w-[200px] justify-between rounded-[14px] font-normal text-muted-foreground">
                      <span class="truncate">{{ brandFilter || 'Semua merek' }}</span>
                      <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent class="w-[200px] p-0 rounded-[14px]" align="start">
                    <Command>
                      <CommandInput placeholder="Cari merek..." />
                      <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                      <CommandList>
                        <CommandGroup>
                          <CommandItem value="semua-merek" @select="brandFilter = ''; brandOpen = false">
                            <Check :class="['mr-2 h-4 w-4', !brandFilter ? 'opacity-100' : 'opacity-0']" />
                            Semua merek
                          </CommandItem>
                          <CommandItem
                            v-for="brand in mainFilteredBrands"
                            :key="brand"
                            :value="brand"
                            @select="brandFilter = brand; brandOpen = false"
                          >
                            <Check :class="['mr-2 h-4 w-4', brandFilter === brand ? 'opacity-100' : 'opacity-0']" />
                            {{ brand }}
                          </CommandItem>
                        </CommandGroup>
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>
              </div>

              <!-- Rows Per Page -->
              <div class="flex items-center gap-3 text-sm text-muted-foreground pb-0.5">
                <span class="whitespace-nowrap">Baris per halaman</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-[140px] justify-between rounded-[14px] font-normal text-muted-foreground">
                      {{ rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
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
                    class="flex items-center gap-2 px-4 py-2 bg-destructive hover:bg-destructive text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm disabled:opacity-50"
                  >
                    <Trash2 class="w-4 h-4" />
                    <span class="hidden sm:inline">Hapus Barang</span>
                  </button>
                  <ExportButtonGroup 
                    @print="handlePrint"
                    @export-excel="handleExportExcel"
                    @export-pdf="handleExportPDF"
                    @export-csv="handleExportCSV"
                  />
                </div>
              </div>
              
              <button 
                @click="openCreateModal"
                class="flex items-center gap-1.5 bg-gradient-primary hover:bg-primary/90 text-primary-foreground px-5 py-2.5 rounded-[14px] text-sm font-semibold transition-all hover:scale-[1.02] active:scale-[0.98] shadow-sm whitespace-nowrap"
              >
                <Plus class="w-4 h-4" />
                <span>Barang Baru</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="dummyInventory" 
            :filter-value="searchQuery"
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
              <div class="flex items-center justify-between p-5 border-b border-border">
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
                      <label class="text-sm font-medium text-foreground block">Kode Barang<span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <input 
                          type="text" 
                          v-model="newItem.code"
                          disabled
                          placeholder="Kode Barang belum di-generate" 
                          class="flex-grow px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                        />
                        <button 
                          @click="generateCode"
                          :disabled="!newItem.category || !newItem.subcategory"
                          class="px-6 py-2 bg-primary hover:bg-primary/90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors disabled:opacity-50"
                        >
                          Generate
                        </button>
                      </div>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Kategori<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-muted-foreground">
                            {{ newItem.category || 'Pilih kategori' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-[452px] rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="cat in categories" :key="cat" @select="newItem.category = cat">
                            {{ cat }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Subkategori<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-muted-foreground" :disabled="!newItem.category">
                            {{ newItem.subcategory || 'Pilih subkategori' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-[452px] rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="sub in filteredSubcategories" :key="sub" @select="newItem.subcategory = sub">
                            {{ sub }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Satuan<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-muted-foreground">
                            {{ newItem.unit || 'Pilih satuan barang' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-[452px] max-h-[300px] overflow-y-auto rounded-[14px] scrollbar-hide z-[1001]">
                          <DropdownMenuItem v-for="unit in units" :key="unit" @select="newItem.unit = unit">
                            {{ unit }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Merek<span class="text-rose-500">*</span></label>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-muted-foreground">
                            {{ newItem.brand || 'Pilih merek' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-[452px] rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="brand in filteredBrands" :key="brand" @select="newItem.brand = brand">
                            {{ brand }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Spesifikasi<span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="newItem.specification"
                        maxlength="255"
                        placeholder="Input spesifikasinya di sini..." 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </div>

                    <div class="space-y-1.5">
                      <label class="text-sm font-medium text-foreground block">Foto <span class="italic text-muted-foreground">default</span><span class="text-rose-500">*</span></label>
                      <div class="flex gap-2">
                        <div class="flex-grow px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 text-muted-foreground truncate flex items-center">
                          {{ newItem.photoName || 'Belum ada foto yang dipilih' }}
                        </div>
                        <input 
                          type="file" 
                          id="photo-upload" 
                          class="hidden" 
                          accept=".jpg,.jpeg,.png"
                          @change="handleFileUpload"
                        />
                        <button 
                          @click="triggerFileInput"
                          class="px-6 py-2 bg-primary hover:bg-primary/90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors"
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
              <div class="p-6 border-t border-border flex items-center justify-between">
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
                    class="px-8 py-2.5 bg-primary hover:bg-primary/90 text-primary-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98] disabled:opacity-50"
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
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />
  </AppLayout>
</template>