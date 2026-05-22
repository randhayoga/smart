<script setup lang="ts">
import { ref, computed, watch, h } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  X,
  CheckCircle2,
  AlertTriangle
} from 'lucide-vue-next';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Label } from '@/Components/ui/label';
import Heading from '@/Components/Heading.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteTableButton from '@/Components/DeleteTableButton.vue';
import EditTableButton from '@/Components/EditTableButton.vue';

interface Category    { id: number; code: string; name: string; is_consumable: boolean; }
interface Subcategory { id: number; code: string; name: string; category_id: number; category: Category; }
interface SimpleItem  { id: number; name: string; }
interface Floor       { id: number; name: string; location_id: number; location: SimpleItem; }
interface Room        { id: number; name: string; floor_id: number; floor: Floor; }

interface Props {
  user: { name: string; email: string; };
  categories:    Category[];
  subcategories: Subcategory[];
  uoms:          SimpleItem[];
  brands:        SimpleItem[];
  organizers:    SimpleItem[];
  vendors:       SimpleItem[];
  locations:     SimpleItem[];
  floors:        Floor[];
  rooms:         Room[];
}

const props = withDefaults(defineProps<Props>(), {
  categories:    () => [],
  subcategories: () => [],
  uoms:          () => [],
  brands:        () => [],
  organizers:    () => [],
  vendors:       () => [],
  locations:     () => [],
  floors:        () => [],
  rooms:         () => [],
});

const tabs = [
  'Kategori', 'Subkategori', 'Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi', 'Lantai', 'Ruangan'
];

const activeTab = ref('Kategori');

const searchQuery = ref('');
const parentFilter = ref('');
const locationFilter = ref('');
const floorFilter = ref('');
const rowsPerPage = ref('Semua baris');

const filteredFloorsForFilter = computed(() => {
  if (!locationFilter.value) return props.floors;
  return props.floors.filter(f => f.location_id.toString() === locationFilter.value);
});

// Map subcategories to include a `parent` string for display/filter
const subcategoryRows = computed(() =>
  props.subcategories.map(s => ({
    ...s,
    parent:     s.category?.name ?? '',
    parentCode: s.category?.code ?? '',
  }))
);

// Map floors to include parent location name for display/filter
const floorRows = computed(() =>
  props.floors.map(f => ({
    ...f,
    parent:     f.location?.name ?? '',
    parentCode: f.location_id?.toString() ?? '',
  }))
);

// Map rooms to include parent floor name for display/filter
const roomRows = computed(() =>
  props.rooms.map(r => ({
    ...r,
    floorName:    r.floor?.name ?? '',
    locationName: r.floor?.location?.name ?? '',
    parent:       r.floor ? `${r.floor.location?.name ?? ''} - ${r.floor.name}` : '',
    parentCode:   r.floor_id?.toString() ?? '',
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
  if (activeTab.value === 'Lantai')      return floorRows.value;
  if (activeTab.value === 'Ruangan')     return roomRows.value;
  return [];
});

const isEditModalOpen   = ref(false);
const isCreateModalOpen = ref(false);
const editingItem       = ref<any>(null);

// ── Create forms ────────────────────────────────────────────────
const categoryForm    = useForm({ code: '', name: '', is_consumable: '1' });
const subcategoryForm = useForm({ category_id: null as number | null, code: '', name: '' });
const uomForm         = useForm({ name: '' });
const brandForm       = useForm({ name: '' });
const organizerForm   = useForm({ name: '' });
const vendorForm      = useForm({ name: '' });
const locationForm    = useForm({ name: '' });
const floorForm       = useForm({ location_id: null as number | null, name: '' });
const roomForm        = useForm({ location_id: null as number | null, floor_id: null as number | null, name: '' });

// ── Edit forms ──────────────────────────────────────────────────
const editCategoryForm    = useForm({ id: null as number | null, code: '', name: '', is_consumable: '1' });
const editSubcategoryForm = useForm({ id: null as number | null, name: '' });
const editUomForm         = useForm({ id: null as number | null, name: '' });
const editBrandForm       = useForm({ id: null as number | null, name: '' });
const editOrganizerForm   = useForm({ id: null as number | null, name: '' });
const editVendorForm      = useForm({ id: null as number | null, name: '' });
const editLocationForm    = useForm({ id: null as number | null, name: '' });
const editFloorForm       = useForm({ id: null as number | null, location_id: null as number | null, name: '' });
const editRoomForm        = useForm({ id: null as number | null, location_id: null as number | null, floor_id: null as number | null, name: '' });

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
    case 'Lantai':      return editFloorForm;
    case 'Ruangan':     return editRoomForm;
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
    case 'Lantai':      return floorForm;
    case 'Ruangan':     return roomForm;
    default:            return categoryForm;
  }
});

const openEditModal = (item: any) => {
  editingItem.value = { ...item };
  const form = activeEditForm.value as any;
  form.id   = item.id;
  form.name = item.name;
  if (activeTab.value === 'Kategori') {
    form.code = item.code;
    form.is_consumable = item.is_consumable ? '1' : '0';
  }
  if (activeTab.value === 'Lantai') {
    form.location_id = item.location_id ?? null;
  }
  if (activeTab.value === 'Ruangan') {
    form.location_id = item.floor?.location_id ?? null;
    form.floor_id = item.floor_id ?? null;
  }
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  isEditModalOpen.value = false;
  setTimeout(() => {
    editingItem.value = null;
    editCategoryForm.reset();    editSubcategoryForm.reset();
    editUomForm.reset();         editBrandForm.reset();
    editOrganizerForm.reset();   editVendorForm.reset();
    editLocationForm.reset();    editFloorForm.reset();
    editRoomForm.reset();
  }, 200);
};

const openCreateModal = () => {
  categoryForm.reset();    subcategoryForm.reset();
  uomForm.reset();         brandForm.reset();
  organizerForm.reset();   vendorForm.reset();
  locationForm.reset();    floorForm.reset();
  roomForm.reset();
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
  categoryForm.reset();    subcategoryForm.reset();
  uomForm.reset();         brandForm.reset();
  organizerForm.reset();   vendorForm.reset();
  locationForm.reset();    floorForm.reset();
  roomForm.reset();
};

// Reset filters when tab changes
watch(activeTab, () => {
  searchQuery.value = '';
  parentFilter.value = '';
  locationFilter.value = '';
  floorFilter.value = '';
});

const columns = computed<ColumnDef<any>[]>(() => {
  const cols: ColumnDef<any>[] = [];

  // Code column (if applicable)
  if (!['Satuan', 'Merek', 'Organizer', 'Vendor', 'Lokasi', 'Lantai', 'Ruangan'].includes(activeTab.value)) {
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

  // Klasifikasi column (Kategori only)
  if (activeTab.value === 'Kategori') {
    cols.push({
      accessorKey: 'is_consumable',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          'Klasifikasi',
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'pl-2' }, [
        h('span', { 
          class: row.original.is_consumable 
            ? 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800' 
            : 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800'
        }, row.original.is_consumable ? 'Habis Pakai' : 'Aset')
      ]),
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

  // Parent column (Subkategori, Lantai)
  if (['Subkategori', 'Lantai'].includes(activeTab.value)) {
    let headerText = 'Kategori Induk';
    if (activeTab.value === 'Lantai') headerText = 'Lokasi';

    cols.push({
      accessorKey: 'parent',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          headerText,
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

  // Separate columns for Ruangan (Lantai & Lokasi)
  if (activeTab.value === 'Ruangan') {
    // Lantai
    cols.push({
      accessorKey: 'floorName',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          'Lantai',
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('floorName')),
      filterFn: (row, id, value) => {
        if (!value) return true;
        return row.original.floor_id?.toString() === value;
      }
    });

    // Lokasi
    cols.push({
      accessorKey: 'locationName',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          'Lokasi',
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('locationName')),
      filterFn: (row, id, value) => {
        if (!value) return true;
        return row.original.floor?.location_id?.toString() === value;
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
        h(EditTableButton, {
          onClick: () => openEditModal(item),
        }),
        h(DeleteTableButton, {
          onClick: () => openDeleteModal(item),
        })
      ]);
    },
  });

  return cols;
});

const dataTableRef = ref<any>(null);

// Sync parentFilter with the parent column filter in DataTable
watch(parentFilter, (val) => {
  if (['Subkategori', 'Lantai'].includes(activeTab.value) && dataTableRef.value) {
    dataTableRef.value.table.getColumn('parent')?.setFilterValue(val);
  }
});

// Sync room filters with columns in DataTable
watch(locationFilter, (val) => {
  if (activeTab.value === 'Ruangan' && dataTableRef.value) {
    dataTableRef.value.table.getColumn('locationName')?.setFilterValue(val);
  }
});

watch(floorFilter, (val) => {
  if (activeTab.value === 'Ruangan' && dataTableRef.value) {
    dataTableRef.value.table.getColumn('floorName')?.setFilterValue(val);
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
  'Lantai':      'smart.master.floors.destroy',
  'Ruangan':     'smart.master.rooms.destroy',
};

const storeRouteMap: Record<string, string> = {
  'Kategori':    'smart.master.categories.store',
  'Subkategori': 'smart.master.subcategories.store',
  'Satuan':      'smart.master.uoms.store',
  'Merek':       'smart.master.brands.store',
  'Organizer':   'smart.master.organizers.store',
  'Vendor':      'smart.master.vendors.store',
  'Lokasi':      'smart.master.locations.store',
  'Lantai':      'smart.master.floors.store',
  'Ruangan':     'smart.master.rooms.store',
};

const updateRouteMap: Record<string, string> = {
  'Kategori':    'smart.master.categories.update',
  'Subkategori': 'smart.master.subcategories.update',
  'Satuan':      'smart.master.uoms.update',
  'Merek':       'smart.master.brands.update',
  'Organizer':   'smart.master.organizers.update',
  'Vendor':      'smart.master.vendors.update',
  'Lokasi':      'smart.master.locations.update',
  'Lantai':      'smart.master.floors.update',
  'Ruangan':     'smart.master.rooms.update',
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
const pageSize = computed(() => {
  if (rowsPerPage.value === 'Semua baris') return 999999;
  return parseInt(rowsPerPage.value);
});

// Flash Notifications
const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);
const flashError = computed(() => (page.props as any).flash?.error);

const showSuccessAlert = ref(true);

watch(() => (page.props as any).flash, () => {
  showSuccessAlert.value = true;
}, { deep: true });

// Error Modal for Deletion Block
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
  <AppLayout title="Master Data">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/master">Master Data</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Flash Notifications -->
    <div class="mb-4 space-y-2">
      <Transition
        enter-active-class="transition ease-out duration-300 transform"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="flashSuccess && showSuccessAlert" class="flex items-center justify-between p-4 rounded-[14px] border border-emerald-500/20 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400">
          <div class="flex items-center gap-2.5">
            <CheckCircle2 class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            <span class="text-sm font-medium">{{ flashSuccess }}</span>
          </div>
          <button @click="showSuccessAlert = false" class="text-emerald-500 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
            <X class="w-4 h-4" />
          </button>
        </div>
      </Transition>
    </div>
    
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
            <div class="flex items-end gap-3 w-full" :class="[activeTab === 'Ruangan' ? 'max-w-2xl' : 'max-w-xl']">
              <div class="space-y-1.5 flex-1 max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block">Filter</label>
                <TableSearch 
                  v-model="searchQuery"
                  :placeholder="`Cari ${activeTab}...`" 
                />
              </div>
              <div v-if="activeTab === 'Subkategori'" class="flex-1 max-w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !parentFilter ? 'text-muted-foreground' : 'text-foreground']">
                      {{ parentFilter ? (props.categories.find(c => c.code === parentFilter)?.name || 'Semua Kategori Induk') : 'Semua Kategori Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                    <DropdownMenuItem @select="parentFilter = ''">Semua Kategori Induk</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in props.categories" :key="cat.code" @select="parentFilter = cat.code">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
              <div v-if="activeTab === 'Lantai'" class="flex-1 max-w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !parentFilter ? 'text-muted-foreground' : 'text-foreground']">
                      {{ parentFilter ? (props.locations.find(l => l.id.toString() === parentFilter)?.name || 'Semua Lokasi') : 'Semua Lokasi' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                    <DropdownMenuItem @select="parentFilter = ''">Semua Lokasi</DropdownMenuItem>
                    <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="parentFilter = loc.id.toString()">
                      {{ loc.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
              <template v-if="activeTab === 'Ruangan'">
                <div class="flex-1 max-w-[200px]">
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !locationFilter ? 'text-muted-foreground' : 'text-foreground']">
                        {{ locationFilter ? (props.locations.find(l => l.id.toString() === locationFilter)?.name || 'Semua Lokasi') : 'Semua Lokasi' }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                      <DropdownMenuItem @select="locationFilter = ''; floorFilter = ''">Semua Lokasi</DropdownMenuItem>
                      <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="locationFilter = loc.id.toString(); floorFilter = ''">
                        {{ loc.name }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
                <div class="flex-1 max-w-[200px]">
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !floorFilter ? 'text-muted-foreground' : 'text-foreground']">
                        {{ floorFilter ? (props.floors.find(f => f.id.toString() === floorFilter)?.name || 'Semua Lantai') : 'Semua Lantai' }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                      <DropdownMenuItem @select="floorFilter = ''">Semua Lantai</DropdownMenuItem>
                      <DropdownMenuItem v-for="fl in filteredFloorsForFilter" :key="fl.id" @select="floorFilter = fl.id.toString()">
                        {{ fl.name }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </template>
            </div>

            <!-- Right Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <span>Baris per halaman</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                      {{ rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                    <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <button @click="openCreateModal" class="flex items-center gap-1.5 bg-gradient-primary hover:opacity-90 text-primary-foreground px-4 py-2 rounded-[14px] text-sm font-medium transition-colors shadow-sm">
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
            :page-size="pageSize"
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
        <div v-if="isEditModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
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

            <!-- Edit: Lantai -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 flex-grow" v-else-if="activeTab === 'Lantai'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Lokasi Induk<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !editFloorForm.location_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ editFloorForm.location_id ? (props.locations.find(l => l.id === editFloorForm.location_id)?.name || 'Pilih Lokasi Induk') : 'Pilih Lokasi Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="editFloorForm.location_id = loc.id">
                      {{ loc.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="editFloorForm.errors.location_id" class="text-destructive text-xs mt-1">{{ editFloorForm.errors.location_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Lantai<span class="text-destructive">*</span></label>
                <input type="text" v-model="editFloorForm.name" maxlength="255" placeholder="Nama lantai..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="editFloorForm.errors.name" class="text-destructive text-xs mt-1">{{ editFloorForm.errors.name }}</div>
              </div>
            </div>

            <!-- Edit: Ruangan -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow" v-else-if="activeTab === 'Ruangan'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Lokasi<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !editRoomForm.location_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ editRoomForm.location_id ? (props.locations.find(l => l.id == editRoomForm.location_id)?.name || 'Pilih Lokasi') : 'Pilih Lokasi' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="editRoomForm.location_id = loc.id; editRoomForm.floor_id = null">
                      {{ loc.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="editRoomForm.errors.location_id" class="text-destructive text-xs mt-1">{{ editRoomForm.errors.location_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Lantai<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger :disabled="!editRoomForm.location_id" asChild>
                    <Button :disabled="!editRoomForm.location_id" variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !editRoomForm.floor_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ editRoomForm.floor_id ? (props.floors.find(f => f.id == editRoomForm.floor_id)?.name || 'Pilih Lantai') : 'Pilih Lantai' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem v-for="fl in props.floors.filter(f => f.location_id == editRoomForm.location_id)" :key="fl.id" @select="editRoomForm.floor_id = fl.id">
                      {{ fl.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="editRoomForm.errors.floor_id" class="text-destructive text-xs mt-1">{{ editRoomForm.errors.floor_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Ruangan<span class="text-destructive">*</span></label>
                <input type="text" v-model="editRoomForm.name" maxlength="255" placeholder="Nama ruangan..." :disabled="!editRoomForm.floor_id"
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed" />
                <div v-if="editRoomForm.errors.name" class="text-destructive text-xs mt-1">{{ editRoomForm.errors.name }}</div>
              </div>
            </div>

            <!-- Edit: Kategori -->
            <div v-else-if="activeTab === 'Kategori'" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow">
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
              <div>
                <label class="block text-sm font-medium text-foreground mb-3">Klasifikasi<span class="text-destructive">*</span></label>
                <RadioGroup v-model="editCategoryForm.is_consumable" class="flex gap-6">
                  <div class="flex items-center space-x-2">
                    <RadioGroupItem id="edit-consumable-true" value="1" />
                    <Label for="edit-consumable-true" class="font-normal cursor-pointer">Habis Pakai</Label>
                  </div>
                  <div class="flex items-center space-x-2">
                    <RadioGroupItem id="edit-consumable-false" value="0" />
                    <Label for="edit-consumable-false" class="font-normal cursor-pointer">Aset</Label>
                  </div>
                </RadioGroup>
                <div v-if="editCategoryForm.errors.is_consumable" class="text-destructive text-xs mt-1">{{ editCategoryForm.errors.is_consumable }}</div>
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
                <button @click="closeEditModal" class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-muted transition-colors">
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
        <div v-if="isCreateModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
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
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !subcategoryForm.category_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ subcategoryForm.category_id ? (props.categories.find(c => c.id === subcategoryForm.category_id)?.name || 'Pilih Kategori Induk') : 'Pilih Kategori Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
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
                    @input="subcategoryForm.code = subcategoryForm.code.replace(/[^A-Za-z0-9]/g, '').toUpperCase()"
                    maxlength="3" :disabled="!subcategoryForm.category_id" placeholder="3 huruf kapital/angka..."
                    class="w-full pr-3 py-2 text-sm bg-transparent border-none focus:ring-0 focus:outline-none"
                    :class="{ 'cursor-not-allowed': !subcategoryForm.category_id }" />
                </div>
                <div v-if="subcategoryForm.errors.code" class="text-destructive text-xs mt-1">{{ subcategoryForm.errors.code }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Subkategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="subcategoryForm.name" maxlength="255" placeholder="Nama subkategori..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="subcategoryForm.errors.name" class="text-destructive text-xs mt-1">{{ subcategoryForm.errors.name }}</div>
              </div>
            </div>

            <!-- Create: Lantai -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 flex-grow" v-else-if="activeTab === 'Lantai'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Lokasi Induk<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !floorForm.location_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ floorForm.location_id ? (props.locations.find(l => l.id === floorForm.location_id)?.name || 'Pilih Lokasi Induk') : 'Pilih Lokasi Induk' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="floorForm.location_id = loc.id">
                      {{ loc.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="floorForm.errors.location_id" class="text-destructive text-xs mt-1">{{ floorForm.errors.location_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Lantai<span class="text-destructive">*</span></label>
                <input type="text" v-model="floorForm.name" maxlength="255" placeholder="Nama lantai..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="floorForm.errors.name" class="text-destructive text-xs mt-1">{{ floorForm.errors.name }}</div>
              </div>
            </div>

            <!-- Create: Ruangan -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow" v-else-if="activeTab === 'Ruangan'">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Lokasi<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !roomForm.location_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ roomForm.location_id ? (props.locations.find(l => l.id == roomForm.location_id)?.name || 'Pilih Lokasi') : 'Pilih Lokasi' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem v-for="loc in props.locations" :key="loc.id" @select="roomForm.location_id = loc.id; roomForm.floor_id = null">
                      {{ loc.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="roomForm.errors.location_id" class="text-destructive text-xs mt-1">{{ roomForm.errors.location_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Lantai<span class="text-destructive">*</span></label>
                <DropdownMenu>
                  <DropdownMenuTrigger :disabled="!roomForm.location_id" asChild>
                    <Button :disabled="!roomForm.location_id" variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !roomForm.floor_id ? 'text-muted-foreground' : 'text-foreground']">
                      {{ roomForm.floor_id ? (props.floors.find(f => f.id == roomForm.floor_id)?.name || 'Pilih Lantai') : 'Pilih Lantai' }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem v-for="fl in props.floors.filter(f => f.location_id == roomForm.location_id)" :key="fl.id" @select="roomForm.floor_id = fl.id">
                      {{ fl.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <div v-if="roomForm.errors.floor_id" class="text-destructive text-xs mt-1">{{ roomForm.errors.floor_id }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Ruangan<span class="text-destructive">*</span></label>
                <input type="text" v-model="roomForm.name" maxlength="255" placeholder="Nama ruangan..." :disabled="!roomForm.floor_id"
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed" />
                <div v-if="roomForm.errors.name" class="text-destructive text-xs mt-1">{{ roomForm.errors.name }}</div>
              </div>
            </div>

            <!-- Create: Kategori -->
            <div v-else-if="activeTab === 'Kategori'" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 flex-grow">
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Kode Kategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="categoryForm.code"
                  @input="categoryForm.code = categoryForm.code.replace(/[^A-Za-z0-9]/g, '').toUpperCase()"
                  maxlength="3" placeholder="Contoh: ATK, FUR, ELE..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="categoryForm.errors.code" class="text-destructive text-xs mt-1">{{ categoryForm.errors.code }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-foreground mb-2">Nama Kategori<span class="text-destructive">*</span></label>
                <input type="text" v-model="categoryForm.name" maxlength="255" placeholder="Nama kategori..."
                  class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
                <div v-if="categoryForm.errors.name" class="text-destructive text-xs mt-1">{{ categoryForm.errors.name }}</div>
              </div>

              <div>
                <label class="block text-sm font-medium text-foreground mb-3">Klasifikasi<span class="text-destructive">*</span></label>
                <RadioGroup v-model="categoryForm.is_consumable" class="flex gap-6">
                  <div class="flex items-center space-x-2">
                    <RadioGroupItem id="consumable-true" value="1" />
                    <Label for="consumable-true" class="font-normal cursor-pointer">Habis Pakai</Label>
                  </div>
                  <div class="flex items-center space-x-2">
                    <RadioGroupItem id="consumable-false" value="0" />
                    <Label for="consumable-false" class="font-normal cursor-pointer">Aset</Label>
                  </div>
                </RadioGroup>
                <div v-if="categoryForm.errors.is_consumable" class="text-destructive text-xs mt-1">{{ categoryForm.errors.is_consumable }}</div>
              </div>
            </div>

            <!-- Create: Satuan / Merek / Organizer / Vendor / Lokasi (name-only) -->
            <div class="mb-8 flex-grow" v-else>
              <label class="block text-sm font-medium text-foreground mb-2">Nama {{ activeTab }}<span class="text-destructive">*</span></label>
              <input type="text" v-model="(activeCreateForm as any).name" maxlength="255"
                :placeholder="`Nama ${activeTab}...`"
                class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors" />
              <div v-if="(activeCreateForm as any).errors?.name" class="text-destructive text-xs mt-1">{{ (activeCreateForm as any).errors.name }}</div>
            </div>

            <div class="flex items-center justify-between mt-auto">
              <span class="text-sm text-destructive italic">*Wajib diisi</span>
              <div class="flex items-center gap-3">
                <button @click="closeCreateModal" class="px-4 py-2 text-sm font-medium border border-input rounded-[14px] hover:bg-muted transition-colors">
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
      :item-data="itemToDelete"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <!-- Cannot Delete Warning Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isErrorModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div 
              class="bg-card w-full max-w-[512px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col"
              @click.stop
            >
              <!-- Modal Header -->
              <div class="flex items-center p-1 justify-between border-b border-border">
                <h3 class="text-lg font-bold text-foreground p-2">Pemberitahuan</h3>
                <button @click="closeErrorModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                  <X class="w-5 h-5 text-muted-foreground" />
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-6 flex flex-col items-center text-center space-y-4 flex-grow">
                <div class="w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/20 flex items-center justify-center text-[#CC0000]">
                  <AlertTriangle class="w-6 h-6" />
                </div>
                <div class="space-y-2">
                  <h4 class="text-destructive font-bold text-base">Gagal Menghapus Item</h4>
                  <p class="text-sm text-muted-foreground leading-relaxed">
                    {{ errorModalMessage }}
                  </p>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="p-4 bg-muted/30 border-t border-border flex items-center justify-center">
                <button 
                  @click="closeErrorModal"
                  class="w-full py-2.5 border border-input rounded-[14px] hover:bg-muted transition-colors shadow-button active:scale-[0.98]"
                >
                  Mengerti
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
