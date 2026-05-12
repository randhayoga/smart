<script setup lang="ts">
import { ref, computed, watch, h } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  Pencil, 
  Trash2,
  X
} from 'lucide-vue-next';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import Heading from '@/Components/Heading.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

interface Category    { id: number; code: string; name: string; }
interface Subcategory { id: number; code: string; name: string; category_id: number; category: Category; }
interface SimpleItem  { id: number; name: string; }

interface Props {
  user: { name: string; email: string; };
  categories:    Category[];
  subcategories: Subcategory[];
  uoms:          SimpleItem[];
  brands:        SimpleItem[];
  organizers:    SimpleItem[];
  vendors:       SimpleItem[];
  locations:     SimpleItem[];
}

const props = withDefaults(defineProps<Props>(), {
  categories:    () => [],
  subcategories: () => [],
  uoms:          () => [],
  brands:        () => [],
  organizers:    () => [],
  vendors:       () => [],
  locations:     () => [],
});

const tabs = [
  'Kategori', 'Subkategori', 'Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'
];

const activeTab = ref('Kategori');

const searchQuery = ref('');
const parentFilter = ref('');
const rowsPerPage = ref('Semua baris');

// Map subcategories to include a `parent` string for display/filter
const subcategoryRows = computed(() =>
  props.subcategories.map(s => ({
    ...s,
    parent:     s.category?.name ?? '',
    parentCode: s.category?.code ?? '',
  }))
);

const displayData = computed(() => {
  if (activeTab.value === 'Kategori')    return props.categories;
  if (activeTab.value === 'Subkategori') return subcategoryRows.value;
  if (activeTab.value === 'Satuan')      return props.uoms;
  if (activeTab.value === 'Merek')       return props.brands;
  if (activeTab.value === 'Organizer')   return props.organizers;
  if (activeTab.value === 'Vendor')      return props.vendors;
  if (activeTab.value === 'Lokasi')      return props.locations;
  return [];
});

const isEditModalOpen   = ref(false);
const isCreateModalOpen = ref(false);
const editingItem       = ref<any>(null);

// ── Create forms ────────────────────────────────────────────────
const categoryForm    = useForm({ code: '', name: '' });
const subcategoryForm = useForm({ category_id: null as number | null, code: '', name: '' });
const uomForm         = useForm({ name: '' });
const brandForm       = useForm({ name: '' });
const organizerForm   = useForm({ name: '' });
const vendorForm      = useForm({ name: '' });
const locationForm    = useForm({ name: '' });

// ── Edit forms ──────────────────────────────────────────────────
const editCategoryForm    = useForm({ id: null as number | null, code: '', name: '' });
const editSubcategoryForm = useForm({ id: null as number | null, name: '' });
const editUomForm         = useForm({ id: null as number | null, name: '' });
const editBrandForm       = useForm({ id: null as number | null, name: '' });
const editOrganizerForm   = useForm({ id: null as number | null, name: '' });
const editVendorForm      = useForm({ id: null as number | null, name: '' });
const editLocationForm    = useForm({ id: null as number | null, name: '' });

// Helper: active edit form
const activeEditForm = computed(() => {
  switch (activeTab.value) {
    case 'Kategori':    return editCategoryForm;
    case 'Subkategori': return editSubcategoryForm;
    case 'Satuan':      return editUomForm;
    case 'Merek':       return editBrandForm;
    case 'Organizer':   return editOrganizerForm;
    case 'Vendor':      return editVendorForm;
    case 'Lokasi':      return editLocationForm;
    default:            return editCategoryForm;
  }
});

// Helper: active create form
const activeCreateForm = computed(() => {
  switch (activeTab.value) {
    case 'Kategori':    return categoryForm;
    case 'Subkategori': return subcategoryForm;
    case 'Satuan':      return uomForm;
    case 'Merek':       return brandForm;
    case 'Organizer':   return organizerForm;
    case 'Vendor':      return vendorForm;
    case 'Lokasi':      return locationForm;
    default:            return categoryForm;
  }
});

const openEditModal = (item: any) => {
  editingItem.value = { ...item };
  const form = activeEditForm.value as any;
  form.id   = item.id;
  form.name = item.name;
  if (activeTab.value === 'Kategori') form.code = item.code;
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  isEditModalOpen.value = false;
  setTimeout(() => {
    editingItem.value = null;
    editCategoryForm.reset();    editSubcategoryForm.reset();
    editUomForm.reset();         editBrandForm.reset();
    editOrganizerForm.reset();   editVendorForm.reset();
    editLocationForm.reset();
  }, 200);
};

const openCreateModal = () => {
  categoryForm.reset();    subcategoryForm.reset();
  uomForm.reset();         brandForm.reset();
  organizerForm.reset();   vendorForm.reset();
  locationForm.reset();
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
};

// Reset filters when tab changes
watch(activeTab, () => {
  searchQuery.value = '';
  parentFilter.value = '';
});

const columns = computed<ColumnDef<any>[]>(() => {
  const cols: ColumnDef<any>[] = [];

  // Code column (if applicable)
  if (!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi'].includes(activeTab.value)) {
    cols.push({
      accessorKey: 'code',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'px-2 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          `Kode ${activeTab.value}`,
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground font-mono text-sm truncate' }, row.getValue('code')),
    });
  }

  // Name column
  cols.push({
    accessorKey: 'name',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        `Nama ${activeTab.value}`,
        h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'pl-2 text-foreground truncate' }, row.getValue('name')),
  });

  // Parent column (Subkategori only)
  if (activeTab.value === 'Subkategori') {
    cols.push({
      accessorKey: 'parent',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          'Kategori Induk',
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('parent')),
      // Enable filtering by parentCode if we want to use the dropdown for this column
      filterFn: (row, id, value) => {
        if (!value) return true;
        return row.original.parentCode === value;
      }
    });
  }

  // Actions column
  cols.push({
    id: 'actions',
    size: 84,
    header: () => h('div', 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-end gap-2' }, [
        h('button', {
          onClick: () => openEditModal(item),
          class: 'p-2 bg-amber-400 hover:bg-amber-500 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-400/50'
        }, [h(Pencil, { class: 'w-3.5 h-3.5' })]),
        h('button', {
          onClick: () => openDeleteModal(item),
          class: 'p-2 bg-rose-500 hover:bg-rose-600 text-white rounded-full transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50'
        }, [h(Trash2, { class: 'w-3.5 h-3.5' })])
      ]);
    },
  });

  return cols;
});

const dataTableRef = ref<any>(null);

// Sync parentFilter with the parent column filter in DataTable
watch(parentFilter, (val) => {
  if (activeTab.value === 'Subkategori' && dataTableRef.value) {
    dataTableRef.value.table.getColumn('parent')?.setFilterValue(val);
  }
});

// Delete Logic
const isDeleteModalOpen = ref(false);
const itemToDelete = ref<any>(null);

const openDeleteModal = (item: any) => {
  itemToDelete.value = item;
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  itemToDelete.value = null;
};

const deleteForm = useForm({});

const routeMap: Record<string, string> = {
  'Kategori':    'smart.master.categories.destroy',
  'Subkategori': 'smart.master.subcategories.destroy',
  'Satuan':      'smart.master.uoms.destroy',
  'Merek':       'smart.master.brands.destroy',
  'Organizer':   'smart.master.organizers.destroy',
  'Vendor':      'smart.master.vendors.destroy',
  'Lokasi':      'smart.master.locations.destroy',
};

const storeRouteMap: Record<string, string> = {
  'Kategori':    'smart.master.categories.store',
  'Subkategori': 'smart.master.subcategories.store',
  'Satuan':      'smart.master.uoms.store',
  'Merek':       'smart.master.brands.store',
  'Organizer':   'smart.master.organizers.store',
  'Vendor':      'smart.master.vendors.store',
  'Lokasi':      'smart.master.locations.store',
};

const updateRouteMap: Record<string, string> = {
  'Kategori':    'smart.master.categories.update',
  'Subkategori': 'smart.master.subcategories.update',
  'Satuan':      'smart.master.uoms.update',
  'Merek':       'smart.master.brands.update',
  'Organizer':   'smart.master.organizers.update',
  'Vendor':      'smart.master.vendors.update',
  'Lokasi':      'smart.master.locations.update',
};

const handleConfirmDelete = () => {
  if (!itemToDelete.value) return;
  deleteForm.delete(route(routeMap[activeTab.value], itemToDelete.value.id), {
    onSuccess: () => closeDeleteModal(),
  });
};

const submitCreate = () => {
  (activeCreateForm.value as any).post(route(storeRouteMap[activeTab.value]), {
    onSuccess: () => closeCreateModal(),
  });
};

const submitUpdate = () => {
  const form = activeEditForm.value as any;
  if (!form.id) return;
  form.put(route(updateRouteMap[activeTab.value], form.id), {
    onSuccess: () => closeEditModal(),
  });
};
</script>

<template>
  <AppLayout title="Master Data">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/master">Master Data</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>
    
    <div class="space-y-1">
      <!-- Tabs -->
      <div class="flex overflow-x-auto pb-2 scrollbar-hide">
        <div class="flex items-center border border-border rounded-full bg-card p-1 shadow-sm w-max">
          <button
            v-for="tab in tabs"
            :key="tab"
            @click="activeTab = tab"
            class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors whitespace-nowrap"
            :class="[
              activeTab === tab 
                ? 'border border-primary text-primary bg-primary/10' 
                : 'text-muted-foreground hover:text-primary hover:bg-primary/10'
            ]"
          >
            {{ tab }}
          </button>
        </div>
      </div>

      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-5">
          <Heading as="h2">Daftar {{ activeTab }}</Heading>
          
          <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <!-- Search -->
            <div class="flex items-end gap-3 w-full max-w-xl">
              <div class="space-y-1.5 flex-1 max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <TableSearch 
                  v-model="searchQuery"
                  :placeholder="`Cari Kode atau Nama ${activeTab}...`" 
                />
              </div>
              <div v-if="activeTab === 'Subkategori'" class="flex-1 max-w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ parentFilter ? (props.categories.find(c => c.code === parentFilter)?.name || 'Semua Kategori Induk') : 'Semua Kategori Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem @select="parentFilter = ''">Semua Kategori Induk</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in props.categories" :key="cat.code" @select="parentFilter = cat.code">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <!-- Right Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <span>Baris per halaman</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-[140px] justify-between rounded-[14px] font-normal">
                      {{ rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px]">
                    <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <button @click="openCreateModal" class="flex items-center gap-1.5 bg-gradient-primary hover:bg-primary/90 text-primary-foreground px-4 py-2 rounded-[14px] text-sm font-medium transition-colors shadow-sm">
                <Plus class="w-4 h-4" />
                <span>{{ activeTab }} Baru</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-4">
          <DataTable 
            ref="dataTableRef"
            :columns="columns" 
            :data="displayData" 
            :filter-value="searchQuery"
            :show-selection-count=false
          />
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isEditModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[1200px] min-h-[261px] p-[24px] flex flex-col"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-border pb-4 mb-6">
              <h3 class="text-lg font-bold">Edit {{ activeTab }}</h3>
              <button @click="closeEditModal" class="text-muted-foreground hover:text-foreground transition-colors">
                <X class="w-5 h-5" />
              </button>
            </div>
            
            <!-- Edit: Subkategori -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow" v-if="activeTab === 'Subkategori'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kategori Induk</label>
                <input type="text" :value="editingItem?.category?.name ?? ''" disabled
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Subkategori (tidak dapat diubah)</label>
                <input type="text" :value="editingItem?.code" disabled
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Subkategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="editSubcategoryForm.name" maxlength="255"
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="editSubcategoryForm.errors.name" class="text-destructive text-xs mt-1">{{ editSubcategoryForm.errors.name }}</div>
              </div>
            </div>

            <!-- Edit: Kategori -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 flex-grow" v-else-if="activeTab === 'Kategori'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Kategori (tidak dapat diubah)</label>
                <input type="text" v-model="editCategoryForm.code" disabled
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                <div v-if="editCategoryForm.errors.code" class="text-destructive text-xs mt-1">{{ editCategoryForm.errors.code }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Kategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="editCategoryForm.name" maxlength="255"
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="editCategoryForm.errors.name" class="text-destructive text-xs mt-1">{{ editCategoryForm.errors.name }}</div>
              </div>
            </div>

            <!-- Edit: Satuan / Merek / Organizer / Vendor / Lokasi (name-only) -->
            <div class="mb-8 flex-grow" v-else>
              <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
              <input type="text" v-model="(activeEditForm as any).name" maxlength="255"
                :placeholder="`${activeTab} sekarang`"
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
              <div v-if="(activeEditForm as any).errors?.name" class="text-destructive text-xs mt-1">{{ (activeEditForm as any).errors.name }}</div>
            </div>

            <div class="flex items-center justify-between mt-auto">
              <span class="text-sm text-destructive italic">*Wajib diisi</span>
              <div class="flex items-center gap-3">
                <button @click="closeEditModal" class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-accent transition-colors">
                  Batal
                </button>
                <button @click="submitUpdate" :disabled="(activeEditForm as any).processing"
                  class="px-4 py-2 text-sm font-medium text-primary-foreground bg-primary rounded-[14px] hover:bg-primary/90 transition-colors disabled:opacity-50">
                  {{ (activeEditForm as any).processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Create Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isCreateModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full max-w-[1200px] min-h-[261px] p-[24px] flex flex-col"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-border pb-4 mb-6">
              <h3 class="text-lg font-bold">Pembuatan {{ activeTab }} Baru</h3>
              <button @click="closeCreateModal" class="text-muted-foreground hover:text-foreground transition-colors">
                <X class="w-5 h-5" />
              </button>
            </div>
            
            <!-- Create: Subkategori -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow" v-if="activeTab === 'Subkategori'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kategori Induk<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" class="w-full justify-between rounded-[14px] font-normal">
                      {{ subcategoryForm.category_id ? (props.categories.find(c => c.id === subcategoryForm.category_id)?.name || 'Pilih Kategori Induk') : 'Pilih Kategori Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[200px] rounded-[14px]">
                    <DropdownMenuItem v-for="cat in props.categories" :key="cat.id" @select="subcategoryForm.category_id = cat.id">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="subcategoryForm.errors.category_id" class="text-destructive text-xs mt-1">{{ subcategoryForm.errors.category_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Subkategori<span class="text-destructive">*</span></label>
                <div class="flex rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors"
                  :class="{ 'opacity-50 bg-muted/50': !subcategoryForm.category_id }">
                  <span class="pl-3 py-2 text-sm text-muted-foreground flex items-center bg-transparent select-none whitespace-nowrap">
                    {{ subcategoryForm.category_id ? (props.categories.find(c => c.id === subcategoryForm.category_id)?.code ?? 'KOD') + '-' : 'KOD-' }}
                  </span>
                  <input type="text" v-model="subcategoryForm.code"
                    @input="subcategoryForm.code = subcategoryForm.code.replace(/[^A-Za-z]/g, '').toUpperCase()"
                    maxlength="3" :disabled="!subcategoryForm.category_id" placeholder="3 huruf kapital..."
                    class="w-full pr-3 py-2 text-sm bg-transparent border-none focus:ring-0 focus:outline-none"
                    :class="{ 'cursor-not-allowed': !subcategoryForm.category_id }" />
                </div>
                <div v-if="subcategoryForm.errors.code" class="text-destructive text-xs mt-1">{{ subcategoryForm.errors.code }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Subkategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="subcategoryForm.name" maxlength="255" placeholder="Input nama subkategorinya di sini..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="subcategoryForm.errors.name" class="text-destructive text-xs mt-1">{{ subcategoryForm.errors.name }}</div>
              </div>
            </div>

            <!-- Create: Kategori -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 flex-grow" v-else-if="activeTab === 'Kategori'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Kategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="categoryForm.code"
                  @input="categoryForm.code = categoryForm.code.replace(/[^A-Za-z]/g, '').toUpperCase()"
                  maxlength="3" placeholder="Contoh: ATK, FUR, ELE..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="categoryForm.errors.code" class="text-destructive text-xs mt-1">{{ categoryForm.errors.code }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Kategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="categoryForm.name" maxlength="255" placeholder="Input nama kategorinya di sini..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="categoryForm.errors.name" class="text-destructive text-xs mt-1">{{ categoryForm.errors.name }}</div>
              </div>
            </div>

            <!-- Create: Satuan / Merek / Organizer / Vendor / Lokasi (name-only) -->
            <div class="mb-8 flex-grow" v-else>
              <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
              <input type="text" v-model="(activeCreateForm as any).name" maxlength="255"
                :placeholder="`Input nama ${activeTab}nya di sini...`"
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
              <div v-if="(activeCreateForm as any).errors?.name" class="text-destructive text-xs mt-1">{{ (activeCreateForm as any).errors.name }}</div>
            </div>

            <div class="flex items-center justify-between mt-auto">
              <span class="text-sm text-destructive italic">*Wajib diisi</span>
              <div class="flex items-center gap-3">
                <button @click="closeCreateModal" class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-accent transition-colors">
                  Batal
                </button>
                <button @click="submitCreate" :disabled="(activeCreateForm as any).processing"
                  class="px-4 py-2 text-sm font-medium text-primary-foreground bg-primary rounded-[14px] hover:bg-primary/90 transition-colors disabled:opacity-50">
                  {{ (activeCreateForm as any).processing ? 'Memproses...' : `Buat ${activeTab}` }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <DeleteConfirmationModal 
      :is-open="isDeleteModalOpen"
      :item-count="1"
      :item-name="activeTab"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />
  </AppLayout>
</template>
