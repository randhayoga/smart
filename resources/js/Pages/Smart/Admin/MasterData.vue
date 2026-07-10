<script setup lang="ts">
import { ref, computed, watch, h, onMounted, onUnmounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ArrowUpDown, 
  Plus, 
  X,
  Trash2,
  Pencil,
  Loader2
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
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import Heading from '@/Components/Heading.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Tabs from '@/Components/Tabs.vue';

interface Category    { id: number; code: string; name: string; is_consumable: boolean; }
interface Subcategory { id: number; code: string; name: string; category_id: number; category: Category; }
interface SimpleItem  { id: number; name: string; description?: string; }
interface VendorItem  {
  id: number;
  code: string;
  name: string;
  address: string;
  phone_number: string;
  email?: string;
  description?: string;
  contact_person_1?: string;
  cp_email_1?: string;
  cp_phone_1?: string;
  contact_person_2?: string;
  cp_email_2?: string;
  cp_phone_2?: string;
}
interface Floor       { id: number; name: string; location_id: number; location: SimpleItem; }
interface Room        { id: number; name: string; floor_id: number; floor: Floor; }

interface Props {
  user: { name: string; email: string; };
  categories:    Category[];
  subcategories: Subcategory[];
  uoms:          SimpleItem[];
  brands:        SimpleItem[];
  organizers:    SimpleItem[];
  vendors:       VendorItem[];
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
const subcategoryForm = useForm({ category_id: null as number | null, code: '', name: '', description: '' });
const uomForm         = useForm({ name: '' });
const brandForm       = useForm({ name: '', description: '' });
const organizerForm   = useForm({ name: '' });
const vendorForm      = useForm({
  code: '',
  name: '',
  address: '',
  phone_number: '',
  email: '',
  description: '',
  contact_person_1: '',
  cp_email_1: '',
  cp_phone_1: '',
  contact_person_2: '',
  cp_email_2: '',
  cp_phone_2: '',
});
const locationForm    = useForm({ name: '' });
const floorForm       = useForm({ location_id: null as number | null, name: '' });
const roomForm        = useForm({ location_id: null as number | null, floor_id: null as number | null, name: '' });

// ── Edit forms ──────────────────────────────────────────────────
const editCategoryForm    = useForm({ id: null as number | null, code: '', name: '', is_consumable: '1' });
const editSubcategoryForm = useForm({ id: null as number | null, name: '', description: '' });
const editUomForm         = useForm({ id: null as number | null, name: '' });
const editBrandForm       = useForm({ id: null as number | null, name: '', description: '' });
const editOrganizerForm   = useForm({ id: null as number | null, name: '' });
const editVendorForm      = useForm({
  id: null as number | null,
  code: '',
  name: '',
  address: '',
  phone_number: '',
  email: '',
  description: '',
  contact_person_1: '',
  cp_email_1: '',
  cp_phone_1: '',
  contact_person_2: '',
  cp_email_2: '',
  cp_phone_2: '',
});
const editLocationForm    = useForm({ id: null as number | null, name: '' });
const editFloorForm       = useForm({ id: null as number | null, location_id: null as number | null, name: '' });
const editRoomForm        = useForm({ id: null as number | null, location_id: null as number | null, floor_id: null as number | null, name: '' });

// ── Error refs (decoupled from Inertia) ────────────────────────
const createFormErrors = ref({
  code: '',
  name: '',
  description: '',
  address: '',
  phone_number: '',
  email: '',
  contact_person_1: '',
  cp_email_1: '',
  cp_phone_1: '',
  contact_person_2: '',
  cp_email_2: '',
  cp_phone_2: '',
  category_id: '',
  location_id: '',
  floor_id: '',
});

const editFormErrors = ref({
  code: '',
  name: '',
  description: '',
  address: '',
  phone_number: '',
  email: '',
  contact_person_1: '',
  cp_email_1: '',
  cp_phone_1: '',
  contact_person_2: '',
  cp_email_2: '',
  cp_phone_2: '',
  location_id: '',
  floor_id: '',
});

const resetCreateFormErrors = () => {
  createFormErrors.value = {
    code: '',
    name: '',
    description: '',
    address: '',
    phone_number: '',
    email: '',
    contact_person_1: '',
    cp_email_1: '',
    cp_phone_1: '',
    contact_person_2: '',
    cp_email_2: '',
    cp_phone_2: '',
    category_id: '',
    location_id: '',
    floor_id: '',
  };
};

const resetEditFormErrors = () => {
  editFormErrors.value = {
    code: '',
    name: '',
    description: '',
    address: '',
    phone_number: '',
    email: '',
    contact_person_1: '',
    cp_email_1: '',
    cp_phone_1: '',
    contact_person_2: '',
    cp_email_2: '',
    cp_phone_2: '',
    location_id: '',
    floor_id: '',
  };
};

// --- Reactive error clearing (create forms) ---
watch(() => categoryForm.code,    (v) => { if (v && createFormErrors.value.code) createFormErrors.value.code = ''; });
watch(() => categoryForm.name,    (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => subcategoryForm.category_id, (v) => { if (v && createFormErrors.value.category_id) createFormErrors.value.category_id = ''; });
watch(() => subcategoryForm.code, (v) => { if (v && createFormErrors.value.code) createFormErrors.value.code = ''; });
watch(() => subcategoryForm.name, (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => floorForm.location_id, (v) => { if (v && createFormErrors.value.location_id) createFormErrors.value.location_id = ''; });
watch(() => floorForm.name,       (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => roomForm.location_id, (v) => { if (v && createFormErrors.value.location_id) createFormErrors.value.location_id = ''; });
watch(() => roomForm.floor_id,    (v) => { if (v && createFormErrors.value.floor_id) createFormErrors.value.floor_id = ''; });
watch(() => roomForm.name,        (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => uomForm.name,         (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => brandForm.name,       (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => organizerForm.name,   (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => vendorForm.name,      (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => locationForm.name,    (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
// --- Reactive error clearing (edit forms) ---
watch(() => editCategoryForm.code,    (v) => { if (v && editFormErrors.value.code) editFormErrors.value.code = ''; });
watch(() => editCategoryForm.name,    (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editSubcategoryForm.name, (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editFloorForm.location_id, (v) => { if (v && editFormErrors.value.location_id) editFormErrors.value.location_id = ''; });
watch(() => editFloorForm.name,       (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editRoomForm.location_id, (v) => { if (v && editFormErrors.value.location_id) editFormErrors.value.location_id = ''; });
watch(() => editRoomForm.floor_id,    (v) => { if (v && editFormErrors.value.floor_id) editFormErrors.value.floor_id = ''; });
watch(() => editRoomForm.name,        (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editUomForm.name,         (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editBrandForm.name,       (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editOrganizerForm.name,   (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editVendorForm.name,      (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editLocationForm.name,    (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });

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
  resetEditFormErrors();
  form.id   = item.id;
  form.name = item.name;
  if (activeTab.value === 'Subkategori' || activeTab.value === 'Merek') {
    form.description = item.description ?? '';
  }
  if (activeTab.value === 'Vendor') {
    form.code = item.code ?? '';
    form.address = item.address ?? '';
    form.phone_number = item.phone_number ?? '';
    form.email = item.email ?? '';
    form.description = item.description ?? '';
    form.contact_person_1 = item.contact_person_1 ?? '';
    form.cp_email_1 = item.cp_email_1 ?? '';
    form.cp_phone_1 = item.cp_phone_1 ?? '';
    form.contact_person_2 = item.contact_person_2 ?? '';
    form.cp_email_2 = item.cp_email_2 ?? '';
    form.cp_phone_2 = item.cp_phone_2 ?? '';
  }
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
    editCategoryForm.reset();
    editSubcategoryForm.reset();
    editUomForm.reset();
    editBrandForm.reset();
    editOrganizerForm.reset();
    editVendorForm.reset();
    editLocationForm.reset();
    editFloorForm.reset();
    editRoomForm.reset();
    resetEditFormErrors();
  }, 200);
};

const generateVendorCode = () => {
  const existingVendors = props.vendors || [];
  let nextNumber = 0;
  if (existingVendors.length > 0) {
    const numbers = existingVendors.map(vendor => {
      const match = vendor.code.match(/^VN(\d{4})$/);
      return match ? parseInt(match[1]) : -1;
    });
    nextNumber = Math.max(...numbers) + 1;
    if (nextNumber < 0) nextNumber = 0;
  }
  const formattedNumber = nextNumber.toString().padStart(4, '0');
  vendorForm.code = `VN${formattedNumber}`;
  if (createFormErrors.value.code) {
    createFormErrors.value.code = '';
  }
};

const openCreateModal = () => {
  categoryForm.reset();
  subcategoryForm.reset();
  uomForm.reset();
  brandForm.reset();
  organizerForm.reset();
  vendorForm.reset();
  locationForm.reset();
  floorForm.reset();
  roomForm.reset();
  resetCreateFormErrors();
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
  categoryForm.reset();
  subcategoryForm.reset();
  uomForm.reset();
  brandForm.reset();
  organizerForm.reset();
  vendorForm.reset();
  locationForm.reset();
  floorForm.reset();
  roomForm.reset();
  resetCreateFormErrors();
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
  if (!['Satuan', 'Merek', 'Organizer', 'Lokasi', 'Lantai', 'Ruangan'].includes(activeTab.value)) {
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
            ? 'inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-800' 
            : 'inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-purple-100 text-purple-800'
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

  // Address & Phone & Email & Contact Person columns (Vendor only)
  if (activeTab.value === 'Vendor') {
    cols.push({
      accessorKey: 'address',
      header: () => h('span', { class: 'pl-2 font-semibold text-foreground' }, 'Alamat'),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('address')),
    });
    cols.push({
      accessorKey: 'phone_number',
      header: () => h('span', { class: 'pl-2 font-semibold text-foreground' }, 'No. Telepon'),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('phone_number')),
    });
    cols.push({
      accessorKey: 'email',
      header: () => h('span', { class: 'pl-2 font-semibold text-foreground' }, 'Email'),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('email') || '-'),
    });
    cols.push({
      accessorKey: 'contact_person_1',
      header: () => h('span', { class: 'pl-2 font-semibold text-foreground' }, 'Contact Person'),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('contact_person_1') || '-'),
    });
  }

  // Description column (Subkategori & Merek & Vendor)
  if (['Subkategori', 'Merek', 'Vendor'].includes(activeTab.value)) {
    cols.push({
      accessorKey: 'description',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          'Deskripsi',
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('description') || '-'),
    });
  }

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
    header: () => h('div', { class: 'text-right' }, 'Aksi'),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-end gap-2' }, [
        h(Button, {
          variant: 'table-edit',
          size: 'icon-sm',
          title: 'Edit',
          onClick: () => openEditModal(item),
        }, () => [
          h(Pencil),
          h('span', { class: 'sr-only' }, 'Edit')
        ]),
        h(Button, {
          variant: 'table-destructive',
          size: 'icon-sm',
          title: 'Hapus',
          onClick: () => openDeleteModal(item),
        }, () => [
          h(Trash2),
          h('span', { class: 'sr-only' }, 'Hapus')
        ])
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
  const form = activeCreateForm.value as any;
  resetCreateFormErrors();
  let hasError = false;

  if (activeTab.value === 'Kategori') {
    if (!form.code || !form.code.trim()) {
      createFormErrors.value.code = 'Kode Kategori belum diisi';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = 'Nama Kategori belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Subkategori') {
    if (!form.category_id) {
      createFormErrors.value.category_id = 'Kategori Induk belum dipilih';
      hasError = true;
    }
    if (!form.code || !form.code.trim()) {
      createFormErrors.value.code = 'Kode Subkategori belum diisi';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = 'Nama Subkategori belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Vendor') {
    if (!form.code || !form.code.trim()) {
      createFormErrors.value.code = 'Kode Vendor belum diisi';
      hasError = true;
    } else if (!/^VN\d{4}$/.test(form.code)) {
      createFormErrors.value.code = 'Format Kode Vendor harus VN#### (contoh: VN0001)';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = 'Nama Vendor belum diisi';
      hasError = true;
    }
    if (!form.address || !form.address.trim()) {
      createFormErrors.value.address = 'Alamat Vendor belum diisi';
      hasError = true;
    }
    if (!form.phone_number || !form.phone_number.trim()) {
      createFormErrors.value.phone_number = 'Nomor Telepon Vendor belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Lantai') {
    if (!form.location_id) {
      createFormErrors.value.location_id = 'Lokasi Induk belum dipilih';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = 'Nama Lantai belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Ruangan') {
    if (!form.location_id) {
      createFormErrors.value.location_id = 'Lokasi belum dipilih';
      hasError = true;
    }
    if (!form.floor_id) {
      createFormErrors.value.floor_id = 'Lantai belum dipilih';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = 'Nama Ruangan belum diisi';
      hasError = true;
    }
  } else {
    // Satuan, Merek, Organizer, Lokasi
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = `Nama ${activeTab.value} belum diisi`;
      hasError = true;
    }
  }

  if (hasError) return;

  let submitForm = form;
  if (activeTab.value === 'Subkategori') {
    const category = props.categories.find(c => c.id === form.category_id);
    const prefix = category ? category.code + '-' : '';
    submitForm = form.transform((data: any) => ({
      ...data,
      code: prefix + data.code,
    }));
  }

  submitForm.post(route(storeRouteMap[activeTab.value]), {
    onSuccess: () => closeCreateModal(),
  });
};

const submitUpdate = () => {
  const form = activeEditForm.value as any;
  if (!form.id) return;
  resetEditFormErrors();
  let hasError = false;

  if (activeTab.value === 'Kategori') {
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = 'Nama Kategori belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Subkategori') {
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = 'Nama Subkategori belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Vendor') {
    if (!form.code || !form.code.trim()) {
      editFormErrors.value.code = 'Kode Vendor belum diisi';
      hasError = true;
    } else if (!/^VN\d{4}$/.test(form.code)) {
      editFormErrors.value.code = 'Format Kode Vendor harus VN#### (contoh: VN0001)';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = 'Nama Vendor belum diisi';
      hasError = true;
    }
    if (!form.address || !form.address.trim()) {
      editFormErrors.value.address = 'Alamat Vendor belum diisi';
      hasError = true;
    }
    if (!form.phone_number || !form.phone_number.trim()) {
      editFormErrors.value.phone_number = 'Nomor Telepon Vendor belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Lantai') {
    if (!form.location_id) {
      editFormErrors.value.location_id = 'Lokasi Induk belum dipilih';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = 'Nama Lantai belum diisi';
      hasError = true;
    }
  } else if (activeTab.value === 'Ruangan') {
    if (!form.location_id) {
      editFormErrors.value.location_id = 'Lokasi belum dipilih';
      hasError = true;
    }
    if (!form.floor_id) {
      editFormErrors.value.floor_id = 'Lantai belum dipilih';
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = 'Nama Ruangan belum diisi';
      hasError = true;
    }
  } else {
    // Satuan, Merek, Organizer, Lokasi
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = `Nama ${activeTab.value} belum diisi`;
      hasError = true;
    }
  }

  if (hasError) return;

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

watch(flashSuccess, (newVal) => {
  if (newVal) {
    toast.success(newVal);
    if ((page.props as any).flash) {
      (page.props as any).flash.success = null;
    }
  }
}, { immediate: true });

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

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isEditModalOpen.value) {
      closeEditModal();
    } else if (isCreateModalOpen.value) {
      closeCreateModal();
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
      <Tabs v-model="activeTab" :tabs="tabs" />

      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3">
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
            <div class="flex flex-wrap items-center justify-end gap-3 w-full sm:w-auto sm:ml-auto">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <span class="text-right">Baris per halaman</span>
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

              <Button @click="openCreateModal" variant="primary">
                <Plus class="w-4 h-4" />
                <span>{{ activeTab }} Baru</span>
              </Button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="pb-5">
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
        <div v-if="isEditModalOpen" @click="closeEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            :class="[
              'bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[261px] overflow-hidden flex flex-col',
              !['Subkategori', 'Lantai', 'Ruangan', 'Kategori', 'Vendor'].includes(activeTab) ? 'max-w-[600px]' : 'max-w-[1200px]'
            ]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">Edit {{ activeTab }}</h3>
              <button @click="closeEditModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 flex-grow">
              <!-- Edit: Subkategori -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-if="activeTab === 'Subkategori'">
                <Field data-disabled="true">
                  <FieldLabel>Kategori Induk</FieldLabel>
                  <FieldContent>
                    <input type="text" :value="editingItem?.category?.name ?? ''" disabled
                      class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                  </FieldContent>
                </Field>
                <Field data-disabled="true">
                  <FieldLabel>Kode Subkategori (tidak dapat diubah)</FieldLabel>
                  <FieldContent>
                    <input type="text" :value="editingItem?.code" disabled
                      class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                  </FieldContent>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>Nama Subkategori<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editSubcategoryForm.name" maxlength="255"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field>
                  <FieldLabel><span>Deskripsi</span></FieldLabel>
                  <FieldContent>
                    <textarea v-model="editSubcategoryForm.description" placeholder="Deskripsi subkategori (opsional)..." rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                </Field>
              </div>

              <!-- Edit: Lantai -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-else-if="activeTab === 'Lantai'">
                <Field :data-invalid="!!editFormErrors.location_id || undefined">
                  <FieldLabel><span>Lokasi Induk<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !editFloorForm.location_id ? 'text-muted-foreground' : 'text-foreground', editFormErrors.location_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="editFormErrors.location_id">{{ editFormErrors.location_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined" :data-disabled="!editFloorForm.location_id || undefined">
                  <FieldLabel><span>Nama Lantai<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editFloorForm.name" maxlength="255" placeholder="Nama lantai..." :disabled="!editFloorForm.location_id"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
              </div>

              <!-- Edit: Ruangan -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6" v-else-if="activeTab === 'Ruangan'">
                <Field :data-invalid="!!editFormErrors.location_id || undefined">
                  <FieldLabel><span>Lokasi<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !editRoomForm.location_id ? 'text-muted-foreground' : 'text-foreground', editFormErrors.location_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="editFormErrors.location_id">{{ editFormErrors.location_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.floor_id || undefined" :data-disabled="!editRoomForm.location_id || undefined">
                  <FieldLabel><span>Lantai<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger :disabled="!editRoomForm.location_id" asChild>
                        <Button :disabled="!editRoomForm.location_id" variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !editRoomForm.floor_id ? 'text-muted-foreground' : 'text-foreground', editFormErrors.floor_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="editFormErrors.floor_id">{{ editFormErrors.floor_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined" :data-disabled="!editRoomForm.floor_id || undefined">
                  <FieldLabel><span>Nama Ruangan<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editRoomForm.name" maxlength="255" placeholder="Nama ruangan..." :disabled="!editRoomForm.floor_id"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
              </div>

              <!-- Edit: Kategori -->
              <div v-else-if="activeTab === 'Kategori'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!editFormErrors.code || undefined" data-disabled="true">
                  <FieldLabel>Kode Kategori (tidak dapat diubah)</FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editCategoryForm.code" disabled
                      class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.code">{{ editFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>Nama Kategori<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editCategoryForm.name" maxlength="255"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field data-disabled="true">
                  <FieldLabel><span>Klasifikasi<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <RadioGroup v-model="editCategoryForm.is_consumable" disabled class="flex gap-6">
                      <div class="flex items-center space-x-2 opacity-60">
                        <RadioGroupItem id="edit-consumable-true" value="1" class="cursor-not-allowed" />
                        <Label for="edit-consumable-true" class="font-normal cursor-not-allowed">Habis Pakai</Label>
                      </div>
                      <div class="flex items-center space-x-2 opacity-60">
                        <RadioGroupItem id="edit-consumable-false" value="0" class="cursor-not-allowed" />
                        <Label for="edit-consumable-false" class="font-normal cursor-not-allowed">Aset</Label>
                      </div>
                    </RadioGroup>
                  </FieldContent>
                </Field>
              </div>

              <!-- Edit: Merek -->
              <div v-else-if="activeTab === 'Merek'" class="grid grid-cols-1 gap-6">
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>Nama Merek<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editBrandForm.name" maxlength="255"
                      placeholder="Nama merek..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.description || undefined">
                  <FieldLabel>Deskripsi</FieldLabel>
                  <FieldContent>
                    <textarea v-model="editBrandForm.description" placeholder="Deskripsi merek..." rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.description">{{ editFormErrors.description }}</FieldError>
                </Field>
              </div>

              <!-- Edit: Vendor -->
              <div v-else-if="activeTab === 'Vendor'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!editFormErrors.code || undefined">
                  <FieldLabel><span>Kode Vendor<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.code"
                      disabled
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                      :class="[editFormErrors.code ? 'border-destructive' : 'border-input']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.code">{{ editFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined" class="md:col-span-2">
                  <FieldLabel><span>Nama Vendor<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.name" maxlength="255"
                      placeholder="Nama vendor..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.phone_number || undefined">
                  <FieldLabel><span>No. Telepon<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.phone_number" maxlength="255"
                      placeholder="Nomor telepon..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.phone_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.phone_number">{{ editFormErrors.phone_number }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.address || undefined" class="md:col-span-2">
                  <FieldLabel><span>Alamat<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.address" maxlength="255"
                      placeholder="Alamat lengkap..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.address ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.address">{{ editFormErrors.address }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.email || undefined">
                  <FieldLabel>Email</FieldLabel>
                  <FieldContent>
                    <input type="email" v-model="editVendorForm.email" maxlength="255"
                      placeholder="Alamat email..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.email">{{ editFormErrors.email }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.description || undefined" class="md:col-span-2">
                  <FieldLabel>Deskripsi</FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.description" maxlength="255"
                      placeholder="Keterangan singkat..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.description">{{ editFormErrors.description }}</FieldError>
                </Field>

                <!-- Contact Person 1 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">Contact Person 1</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!editFormErrors.contact_person_1 || undefined">
                      <FieldLabel>Nama CP 1</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.contact_person_1" maxlength="255"
                          placeholder="Nama CP 1..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.contact_person_1">{{ editFormErrors.contact_person_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_email_1 || undefined">
                      <FieldLabel>Email CP 1</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="editVendorForm.cp_email_1" maxlength="255"
                          placeholder="Email CP 1..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_email_1">{{ editFormErrors.cp_email_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_phone_1 || undefined">
                      <FieldLabel>Telepon CP 1</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.cp_phone_1" maxlength="255"
                          placeholder="Telepon CP 1..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_phone_1">{{ editFormErrors.cp_phone_1 }}</FieldError>
                    </Field>
                  </div>
                </div>

                <!-- Contact Person 2 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">Contact Person 2</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!editFormErrors.contact_person_2 || undefined">
                      <FieldLabel>Nama CP 2</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.contact_person_2" maxlength="255"
                          placeholder="Nama CP 2..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.contact_person_2">{{ editFormErrors.contact_person_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_email_2 || undefined">
                      <FieldLabel>Email CP 2</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="editVendorForm.cp_email_2" maxlength="255"
                          placeholder="Email CP 2..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_email_2">{{ editFormErrors.cp_email_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_phone_2 || undefined">
                      <FieldLabel>Telepon CP 2</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.cp_phone_2" maxlength="255"
                          placeholder="Telepon CP 2..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_phone_2">{{ editFormErrors.cp_phone_2 }}</FieldError>
                    </Field>
                  </div>
                </div>
              </div>

              <!-- Edit: Satuan / Merek / Organizer / Vendor / Lokasi (name-only) -->
              <div v-else>
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>Nama {{ activeTab }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="(activeEditForm as any).name" maxlength="255"
                      :placeholder="`${activeTab} sekarang`"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
              <div class="flex items-center gap-3">
                <Button @click="closeEditModal" variant="white" size="xl">
                  Batal
                </Button>
                <Button @click="submitUpdate" :disabled="(activeEditForm as any).processing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="(activeEditForm as any).processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': (activeEditForm as any).processing }">
                    Simpan Perubahan
                  </span>
                </Button>
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
        <div v-if="isCreateModalOpen" @click="closeCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
          <div 
            :class="[
              'bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[261px] overflow-hidden flex flex-col',
              !['Subkategori', 'Lantai', 'Ruangan', 'Kategori', 'Vendor'].includes(activeTab) ? 'max-w-[600px]' : 'max-w-[1200px]'
            ]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">Pembuatan {{ activeTab }} Baru</h3>
              <button @click="closeCreateModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 flex-grow">
              <!-- Create: Subkategori -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-if="activeTab === 'Subkategori'">
                <Field :data-invalid="!!createFormErrors.category_id || undefined">
                  <FieldLabel><span>Kategori Induk<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !subcategoryForm.category_id ? 'text-muted-foreground' : 'text-foreground', createFormErrors.category_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="createFormErrors.category_id">{{ createFormErrors.category_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.code || undefined" :data-disabled="!subcategoryForm.category_id || undefined">
                  <FieldLabel><span>Kode Subkategori<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <div class="flex rounded-[14px] border bg-background focus-within:ring-2 transition-colors"
                      :class="[
                        { 'opacity-50 bg-muted/50': !subcategoryForm.category_id },
                        createFormErrors.code ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive' : 'border-input focus-within:ring-primary/20 focus-within:border-primary'
                      ]">
                      <span class="pl-3 py-2 text-sm text-muted-foreground flex items-center bg-transparent select-none whitespace-nowrap">
                        {{ subcategoryForm.category_id ? (props.categories.find(c => c.id === subcategoryForm.category_id)?.code ?? 'KOD') + '-' : 'KOD-' }}
                      </span>
                      <input type="text" v-model="subcategoryForm.code"
                         @input="subcategoryForm.code = subcategoryForm.code.replace(/[^A-Za-z]/g, '').toUpperCase()"
                        maxlength="4" :disabled="!subcategoryForm.category_id" placeholder="4 huruf kapital..."
                        class="w-full pr-3 py-2 text-sm bg-transparent border-none focus:ring-0 focus:outline-none"
                        :class="{ 'cursor-not-allowed': !subcategoryForm.category_id }" />
                    </div>
                  </FieldContent>
                  <FieldError v-if="createFormErrors.code">{{ createFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>Nama Subkategori<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="subcategoryForm.name" maxlength="255" placeholder="Nama subkategori..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field>
                  <FieldLabel><span>Deskripsi</span></FieldLabel>
                  <FieldContent>
                    <textarea v-model="subcategoryForm.description" placeholder="Deskripsi subkategori (opsional)..." rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                </Field>
              </div>

              <!-- Create: Lantai -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-else-if="activeTab === 'Lantai'">
                <Field :data-invalid="!!createFormErrors.location_id || undefined">
                  <FieldLabel><span>Lokasi Induk<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !floorForm.location_id ? 'text-muted-foreground' : 'text-foreground', createFormErrors.location_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="createFormErrors.location_id">{{ createFormErrors.location_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined" :data-disabled="!floorForm.location_id || undefined">
                  <FieldLabel><span>Nama Lantai<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="floorForm.name" maxlength="255" placeholder="Nama lantai..." :disabled="!floorForm.location_id"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
              </div>

              <!-- Create: Ruangan -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6" v-else-if="activeTab === 'Ruangan'">
                <Field :data-invalid="!!createFormErrors.location_id || undefined">
                  <FieldLabel><span>Lokasi<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !roomForm.location_id ? 'text-muted-foreground' : 'text-foreground', createFormErrors.location_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="createFormErrors.location_id">{{ createFormErrors.location_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.floor_id || undefined" :data-disabled="!roomForm.location_id || undefined">
                  <FieldLabel><span>Lantai<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger :disabled="!roomForm.location_id" asChild>
                        <Button :disabled="!roomForm.location_id" variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !roomForm.floor_id ? 'text-muted-foreground' : 'text-foreground', createFormErrors.floor_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
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
                  </FieldContent>
                  <FieldError v-if="createFormErrors.floor_id">{{ createFormErrors.floor_id }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined" :data-disabled="!roomForm.floor_id || undefined">
                  <FieldLabel><span>Nama Ruangan<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="roomForm.name" maxlength="255" placeholder="Nama ruangan..." :disabled="!roomForm.floor_id"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
              </div>

              <!-- Create: Kategori -->
              <div v-else-if="activeTab === 'Kategori'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!createFormErrors.code || undefined">
                  <FieldLabel><span>Kode Kategori<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="categoryForm.code"
                      @input="categoryForm.code = categoryForm.code.replace(/[^A-Za-z0-9]/g, '').toUpperCase()"
                      maxlength="4" placeholder="Contoh: ATK, FUR, COMP..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.code ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.code">{{ createFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>Nama Kategori<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="categoryForm.name" maxlength="255" placeholder="Nama kategori..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field>
                  <FieldLabel><span>Klasifikasi<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <RadioGroup v-model="categoryForm.is_consumable" class="flex gap-6">
                      <div class="flex items-center space-x-2">
                        <RadioGroupItem id="consumable-true" value="1" class="cursor-pointer" />
                        <Label for="consumable-true" class="font-normal cursor-pointer">Habis Pakai</Label>
                      </div>
                      <div class="flex items-center space-x-2">
                        <RadioGroupItem id="consumable-false" value="0" class="cursor-pointer" />
                        <Label for="consumable-false" class="font-normal cursor-pointer">Aset</Label>
                      </div>
                    </RadioGroup>
                  </FieldContent>
                </Field>
              </div>

              <!-- Create: Merek -->
              <div v-else-if="activeTab === 'Merek'" class="grid grid-cols-1 gap-6">
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>Nama Merek<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="brandForm.name" maxlength="255"
                      placeholder="Nama merek..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.description || undefined">
                  <FieldLabel>Deskripsi</FieldLabel>
                  <FieldContent>
                    <textarea v-model="brandForm.description" placeholder="Deskripsi merek..." rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.description">{{ createFormErrors.description }}</FieldError>
                </Field>
              </div>

              <!-- Create: Vendor -->
              <div v-else-if="activeTab === 'Vendor'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!createFormErrors.code || undefined">
                  <FieldLabel><span>Kode Vendor<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <div class="flex gap-2 w-full">
                      <input 
                        type="text" 
                        v-model="vendorForm.code"
                        disabled
                        placeholder="Kode Vendor belum di-generate" 
                        class="flex-grow px-4 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                        :class="[createFormErrors.code ? 'border-destructive' : 'border-input']"
                      />
                      <Button
                        type="button"
                        @click="generateVendorCode"
                        size="lg"                       
                      >
                        Generate
                      </Button>
                    </div>
                  </FieldContent>
                  <FieldError v-if="createFormErrors.code">{{ createFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined" class="md:col-span-2">
                  <FieldLabel><span>Nama Vendor<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.name" maxlength="255"
                      placeholder="Nama vendor..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.phone_number || undefined">
                  <FieldLabel><span>No. Telepon<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.phone_number" maxlength="255"
                      placeholder="Nomor telepon..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.phone_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.phone_number">{{ createFormErrors.phone_number }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.address || undefined" class="md:col-span-2">
                  <FieldLabel><span>Alamat<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.address" maxlength="255"
                      placeholder="Alamat lengkap..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.address ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.address">{{ createFormErrors.address }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.email || undefined">
                  <FieldLabel>Email</FieldLabel>
                  <FieldContent>
                    <input type="email" v-model="vendorForm.email" maxlength="255"
                      placeholder="Alamat email..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.email">{{ createFormErrors.email }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.description || undefined" class="md:col-span-2">
                  <FieldLabel>Deskripsi</FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.description" maxlength="255"
                      placeholder="Keterangan singkat..."
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.description">{{ createFormErrors.description }}</FieldError>
                </Field>

                <!-- Contact Person 1 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">Contact Person 1</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!createFormErrors.contact_person_1 || undefined">
                      <FieldLabel>Nama CP 1</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.contact_person_1" maxlength="255"
                          placeholder="Nama CP 1..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.contact_person_1">{{ createFormErrors.contact_person_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_email_1 || undefined">
                      <FieldLabel>Email CP 1</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="vendorForm.cp_email_1" maxlength="255"
                          placeholder="Email CP 1..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_email_1">{{ createFormErrors.cp_email_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_phone_1 || undefined">
                      <FieldLabel>Telepon CP 1</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.cp_phone_1" maxlength="255"
                          placeholder="Telepon CP 1..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_phone_1">{{ createFormErrors.cp_phone_1 }}</FieldError>
                    </Field>
                  </div>
                </div>

                <!-- Contact Person 2 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">Contact Person 2</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!createFormErrors.contact_person_2 || undefined">
                      <FieldLabel>Nama CP 2</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.contact_person_2" maxlength="255"
                          placeholder="Nama CP 2..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.contact_person_2">{{ createFormErrors.contact_person_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_email_2 || undefined">
                      <FieldLabel>Email CP 2</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="vendorForm.cp_email_2" maxlength="255"
                          placeholder="Email CP 2..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_email_2">{{ createFormErrors.cp_email_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_phone_2 || undefined">
                      <FieldLabel>Telepon CP 2</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.cp_phone_2" maxlength="255"
                          placeholder="Telepon CP 2..."
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_phone_2">{{ createFormErrors.cp_phone_2 }}</FieldError>
                    </Field>
                  </div>
                </div>
              </div>

              <!-- Create: Satuan / Merek / Organizer / Lokasi (name-only) -->
              <div v-else>
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>Nama {{ activeTab }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="(activeCreateForm as any).name" maxlength="255"
                      :placeholder="`Nama ${activeTab}...`"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
              <div class="flex items-center gap-3">
                <Button @click="closeCreateModal" variant="white" size="xl">
                  Batal
                </Button>
                <Button @click="submitCreate" variant="primary" :disabled="(activeCreateForm as any).processing" size="xl" class="relative">
                  <Loader2 v-if="(activeCreateForm as any).processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': (activeCreateForm as any).processing }">
                    Buat {{ activeTab }}
                  </span>
                </Button>
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
      :processing="deleteForm.processing"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <!-- Cannot Delete Warning Modal -->
    <DeleteErrorModal 
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </AppLayout>
</template>
