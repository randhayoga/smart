<script setup lang="ts">
/**
 * Master Data Management Page component managing categories, subcategories, UOMs, brands, organizers, vendors, locations, floors, and rooms.
 */
import { ref, computed, watch, h, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ChevronRight, 
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
import Switch from "@/Components/ui/switch/Switch.vue";
import LocationCombobox from "@/Components/LocationCombobox.vue";
import Heading from '@/Components/Heading.vue';

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
interface LocationItem {
  id: number;
  name: string;
  parent_id: number | null;
  is_active: boolean;
  parent?: { id: number; name: string } | null;
  children_count?: number;
  full_name?: string;
}

interface Props {
  user: { name: string; email: string; };
  categories:    Category[];
  subcategories: Subcategory[];
  uoms:          SimpleItem[];
  brands:        SimpleItem[];
  organizers:    SimpleItem[];
  vendors:       VendorItem[];
  locations:     LocationItem[];
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

const { t } = useI18n();

type MasterDataTabKey = 'categories' | 'subcategories' | 'uoms' | 'brands' | 'organizers' | 'vendors' | 'locations';

const tabKeys: MasterDataTabKey[] = [
  'categories', 'subcategories', 'uoms', 'brands', 'organizers', 'vendors', 'locations'
];

const activeTab = ref<MasterDataTabKey>('categories');

const getTabLabel = (key: MasterDataTabKey): string => t(`masterData.tabs.${key}`);
const tabLabels = computed(() => tabKeys.map(k => getTabLabel(k)));

const currentTabLabel = computed({
  get: () => getTabLabel(activeTab.value),
  set: (label: string) => {
    const found = tabKeys.find(k => getTabLabel(k) === label);
    if (found) {
      activeTab.value = found;
    }
  }
});

const currentTabSingular = computed(() => t(`masterData.tabSingular.${activeTab.value}`));

const searchQuery = ref('');
const parentFilter = ref('');
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');

// Map subcategories to include a `parent` string for display/filter
const subcategoryRows = computed(() =>
  props.subcategories.map(s => ({
    ...s,
    parent:     s.category?.name ?? '',
    parentCode: s.category?.code ?? '',
  }))
);

const collapsedLocationIds = ref<Set<number>>(new Set());

function toggleLocationExpand(id: number, e?: Event) {
  if (e) e.stopPropagation();
  const next = new Set(collapsedLocationIds.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  collapsedLocationIds.value = next;
}

watch(searchQuery, (newVal, oldVal) => {
  if (newVal !== oldVal) {
    collapsedLocationIds.value = new Set();
  }
});

function getDescendantIds(locId: number): number[] {
  const result: number[] = [];
  const directChildren = props.locations.filter(l => l.parent_id === locId);
  directChildren.forEach(child => {
    result.push(child.id);
    result.push(...getDescendantIds(child.id));
  });
  return result;
}

interface LocationRow extends LocationItem {
  depth: number;
  hasChildren: boolean;
  isCollapsed: boolean;
  childrenList: LocationRow[];
}

const locationRows = computed<LocationRow[]>(() => {
  const childrenMap = new Map<number | 'root', LocationItem[]>();
  props.locations.forEach(loc => {
    const pid = loc.parent_id ?? 'root';
    if (!childrenMap.has(pid)) {
      childrenMap.set(pid, []);
    }
    childrenMap.get(pid)!.push(loc);
  });

  const roots = childrenMap.get('root') || [];

  function buildTree(items: LocationItem[], depth: number): LocationRow[] {
    return items.map(item => {
      const childItems = childrenMap.get(item.id) || [];
      const childrenNodes = buildTree(childItems, depth + 1);
      return {
        ...item,
        depth,
        hasChildren: childrenNodes.length > 0,
        isCollapsed: collapsedLocationIds.value.has(item.id),
        childrenList: childrenNodes,
      };
    });
  }

  return buildTree(roots, 0);
});

const flattenedLocationDisplay = computed<LocationRow[]>(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const matchedIds = new Set<number>();

  if (q) {
    function addAllDescendants(node: LocationRow) {
      matchedIds.add(node.id);
      node.childrenList.forEach(c => addAllDescendants(c));
    }

    function checkMatch(node: LocationRow): boolean {
      const selfMatch = node.name.toLowerCase().includes(q) || (node.full_name ?? '').toLowerCase().includes(q);
      let childMatch = false;
      node.childrenList.forEach(c => {
        if (checkMatch(c)) childMatch = true;
      });

      if (selfMatch) {
        addAllDescendants(node);
        return true;
      }

      if (childMatch) {
        matchedIds.add(node.id);
        return true;
      }

      return false;
    }
    locationRows.value.forEach(r => checkMatch(r));
  }

  const result: LocationRow[] = [];
  function traverse(nodes: LocationRow[]) {
    nodes.forEach(node => {
      if (q && !matchedIds.has(node.id)) return;
      const isCollapsed = collapsedLocationIds.value.has(node.id);
      result.push({
        ...node,
        isCollapsed,
      });
      if (node.hasChildren && !isCollapsed) {
        traverse(node.childrenList);
      }
    });
  }

  traverse(locationRows.value);
  return result;
});

const displayData = computed(() => {
  if (activeTab.value === 'categories')    return props.categories;
  if (activeTab.value === 'subcategories') return subcategoryRows.value;
  if (activeTab.value === 'uoms')          return props.uoms;
  if (activeTab.value === 'brands')        return props.brands;
  if (activeTab.value === 'organizers')    return props.organizers;
  if (activeTab.value === 'vendors')       return props.vendors;
  if (activeTab.value === 'locations')     return flattenedLocationDisplay.value;
  return [];
});

const isEditModalOpen   = ref(false);
const isCreateModalOpen = ref(false);
const editingItem       = ref<any>(null);

const isAnyModalOpen = computed(() => isEditModalOpen.value || isCreateModalOpen.value);
useModalLock(isAnyModalOpen);

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
const locationForm    = useForm({ name: '', parent_id: null as number | null, is_active: true });

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
const editLocationForm    = useForm({ id: null as number | null, name: '', parent_id: null as number | null, is_active: true });

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
  parent_id: '',
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
  parent_id: '',
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
    parent_id: '',
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
    parent_id: '',
  };
};

// --- Reactive error clearing (create forms) ---
watch(() => categoryForm.code,    (v) => { if (v && createFormErrors.value.code) createFormErrors.value.code = ''; });
watch(() => categoryForm.name,    (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => subcategoryForm.category_id, (v) => { if (v && createFormErrors.value.category_id) createFormErrors.value.category_id = ''; });
watch(() => subcategoryForm.code, (v) => { if (v && createFormErrors.value.code) createFormErrors.value.code = ''; });
watch(() => subcategoryForm.name, (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => uomForm.name,         (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => brandForm.name,       (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => organizerForm.name,   (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => vendorForm.name,      (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });
watch(() => locationForm.name,    (v) => { if (v && createFormErrors.value.name) createFormErrors.value.name = ''; });

// --- Reactive error clearing (edit forms) ---
watch(() => editCategoryForm.code,    (v) => { if (v && editFormErrors.value.code) editFormErrors.value.code = ''; });
watch(() => editCategoryForm.name,    (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editSubcategoryForm.name, (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editUomForm.name,         (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editBrandForm.name,       (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editOrganizerForm.name,   (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editVendorForm.name,      (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });
watch(() => editLocationForm.name,    (v) => { if (v && editFormErrors.value.name) editFormErrors.value.name = ''; });

// Helper: active edit form
const activeEditForm = computed(() => {
  switch (activeTab.value) {
    case 'categories':    return editCategoryForm;
    case 'subcategories': return editSubcategoryForm;
    case 'uoms':          return editUomForm;
    case 'brands':        return editBrandForm;
    case 'organizers':    return editOrganizerForm;
    case 'vendors':       return editVendorForm;
    case 'locations':     return editLocationForm;
    default:              return editCategoryForm;
  }
});

// Helper: active create form
const activeCreateForm = computed(() => {
  switch (activeTab.value) {
    case 'categories':    return categoryForm;
    case 'subcategories': return subcategoryForm;
    case 'uoms':          return uomForm;
    case 'brands':        return brandForm;
    case 'organizers':    return organizerForm;
    case 'vendors':       return vendorForm;
    case 'locations':     return locationForm;
    default:              return categoryForm;
  }
});

const openEditModal = (item: any) => {
  editingItem.value = { ...item };
  const form = activeEditForm.value as any;
  resetEditFormErrors();
  form.id   = item.id;
  form.name = item.name;
  if (activeTab.value === 'subcategories' || activeTab.value === 'brands') {
    form.description = item.description ?? '';
  }
  if (activeTab.value === 'vendors') {
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
  if (activeTab.value === 'categories') {
    form.code = item.code;
    form.is_consumable = item.is_consumable ? '1' : '0';
  }
  if (activeTab.value === 'locations') {
    form.parent_id = item.parent_id ?? null;
    form.is_active = Boolean(item.is_active);
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
  locationForm.parent_id = null;
  locationForm.is_active = true;
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
  resetCreateFormErrors();
};

function toggleLocationActive(item: LocationItem) {
  router.patch(route('smart.master.locations.toggle-active', item.id), {}, {
    preserveScroll: true,
  });
}

// Reset filters when tab changes
watch(activeTab, () => {
  searchQuery.value = '';
  parentFilter.value = '';
});

const columns = computed<ColumnDef<any>[]>(() => {
  const cols: ColumnDef<any>[] = [];

  // Code column (if applicable)
  if (!['uoms', 'brands', 'organizers', 'locations'].includes(activeTab.value)) {
    cols.push({
      accessorKey: 'code',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'px-2 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('masterData.columns.codeWithTab', { tab: currentTabSingular.value }),
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground font-mono text-sm truncate' }, row.getValue('code')),
    });
  }

  // Classification column (categories only)
  if (activeTab.value === 'categories') {
    cols.push({
      accessorKey: 'is_consumable',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('masterData.columns.classification'),
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'pl-2' }, [
        h('span', { 
          class: row.original.is_consumable 
            ? 'inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-800' 
            : 'inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-purple-100 text-purple-800'
        }, row.original.is_consumable ? t('masterData.classification.consumable') : t('masterData.classification.asset'))
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
        t('masterData.columns.nameWithTab', { tab: currentTabSingular.value }),
        h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
      ])
    },
    cell: ({ row }) => {
      if (activeTab.value === 'locations') {
        const item = row.original as LocationRow;
        const depth = item.depth || 0;
        const childrenElements: any[] = [];

        if (item.hasChildren) {
          childrenElements.push(
            h('button', {
              type: 'button',
              class: 'p-1 -ml-1 mr-1 text-muted-foreground hover:text-foreground rounded transition-colors inline-flex items-center justify-center cursor-pointer',
              onClick: (e: Event) => toggleLocationExpand(item.id, e),
            }, [
              h(item.isCollapsed ? ChevronRight : ChevronDown, { class: 'h-4 w-4' }),
            ])
          );
        } else {
          childrenElements.push(
            h('span', { class: 'inline-block w-6' })
          );
        }

        childrenElements.push(
          h('span', { class: 'font-medium text-foreground' }, item.name)
        );

        if (!item.is_active) {
          childrenElements.push(
            h('span', {
              class: 'ml-2.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }, t('masterData.status.inactive'))
          );
        }

        return h('div', {
          class: 'flex items-center text-foreground truncate',
          style: { paddingLeft: `${depth * 24 + 8}px` }
        }, childrenElements);
      }

      return h('div', { class: 'pl-2 text-foreground truncate' }, row.getValue('name'));
    },
  });

  // Address & Phone & Email & Contact Person columns (Vendor only)
  if (activeTab.value === 'vendors') {
    cols.push({
      accessorKey: 'address',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.address')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('address')),
    });
    cols.push({
      accessorKey: 'phone_number',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.phone')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('phone_number')),
    });
    cols.push({
      accessorKey: 'email',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.email')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('email') || '-'),
    });
    cols.push({
      accessorKey: 'contact_person_1',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.contactPerson')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('contact_person_1') || '-'),
    });
  }

  // Description column (Subkategori & Merek & Vendor)
  if (['subcategories', 'brands', 'vendors'].includes(activeTab.value)) {
    cols.push({
      accessorKey: 'description',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.description')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('description') || '-'),
    });
  }

  // Parent column (Subkategori)
  if (activeTab.value === 'subcategories') {
    cols.push({
      accessorKey: 'parent',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('masterData.columns.parentCategory'),
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ])
      },
      cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('parent')),
      filterFn: (row, id, value) => {
        if (!value) return true;
        return row.original.parentCode === value;
      }
    });
  }

  // Active column for Lokasi
  if (activeTab.value === 'locations') {
    cols.push({
      accessorKey: 'is_active',
      size: 90,
      header: () => h('div', { class: 'text-center font-semibold text-foreground leading-tight' }, t('masterData.columns.active')),
      cell: ({ row }) => {
        const item = row.original as LocationRow;
        return h('div', { class: 'flex items-center justify-center' }, [
          h(Switch, {
            modelValue: Boolean(item.is_active),
            class: 'data-[state=checked]:!bg-emerald-600 data-[state=unchecked]:!bg-slate-300 dark:data-[state=unchecked]:!bg-slate-700',
            title: item.is_active ? t('masterData.actions.deactivate') : t('masterData.actions.activate'),
            'onUpdate:modelValue': () => toggleLocationActive(item),
          })
        ]);
      }
    });
  }

  // Actions column
  cols.push({
    id: 'actions',
    size: 84,
    header: () => h('div', { class: 'text-right' }, t('masterData.columns.actions')),
    cell: ({ row }) => {
      const item = row.original;
      const actionButtons: any[] = [];

      // Edit button
      actionButtons.push(
        h(Button, {
          variant: 'table-edit',
          size: 'icon-sm',
          title: t('common.edit'),
          onClick: () => openEditModal(item),
        }, () => [
          h(Pencil),
          h('span', { class: 'sr-only' }, t('common.edit'))
        ])
      );

      // Delete button
      const isDeleteDisabled = activeTab.value === 'locations' && Boolean(item.hasChildren);
      actionButtons.push(
        h(Button, {
          variant: 'table-destructive',
          size: 'icon-sm',
          title: isDeleteDisabled ? t('masterData.actions.cannotDeleteWithChildren') : t('common.delete'),
          disabled: isDeleteDisabled,
          class: isDeleteDisabled ? 'opacity-40 cursor-not-allowed' : '',
          onClick: () => {
            if (!isDeleteDisabled) {
              openDeleteModal(item);
            }
          },
        }, () => [
          h(Trash2),
          h('span', { class: 'sr-only' }, t('common.delete'))
        ])
      );

      return h('div', { class: 'flex items-center justify-end gap-2' }, actionButtons);
    },
  });

  return cols;
});

const dataTableRef = ref<any>(null);

// Sync parentFilter with the parent column filter in DataTable
watch(parentFilter, (val) => {
  if (activeTab.value === 'subcategories' && dataTableRef.value) {
    dataTableRef.value.table.getColumn('parent')?.setFilterValue(val);
  }
});

// Delete Logic
const isDeleteModalOpen = ref(false);
const itemToDelete = ref<any>(null);

const openDeleteModal = (item: any) => {
  const itemData = { ...item };
  if (activeTab.value === 'locations' && itemData.parent_id && !itemData.parent) {
    const parentLoc = props.locations.find(l => l.id === itemData.parent_id);
    if (parentLoc) {
      itemData.parent = parentLoc;
    }
  }
  itemToDelete.value = itemData;
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  itemToDelete.value = null;
};

const deleteForm = useForm({});

const routeMap: Record<MasterDataTabKey, string> = {
  categories:    'smart.master.categories.destroy',
  subcategories: 'smart.master.subcategories.destroy',
  uoms:          'smart.master.uoms.destroy',
  brands:        'smart.master.brands.destroy',
  organizers:    'smart.master.organizers.destroy',
  vendors:       'smart.master.vendors.destroy',
  locations:     'smart.master.locations.destroy',
};

const storeRouteMap: Record<MasterDataTabKey, string> = {
  categories:    'smart.master.categories.store',
  subcategories: 'smart.master.subcategories.store',
  uoms:          'smart.master.uoms.store',
  brands:        'smart.master.brands.store',
  organizers:    'smart.master.organizers.store',
  vendors:       'smart.master.vendors.store',
  locations:     'smart.master.locations.store',
};

const updateRouteMap: Record<MasterDataTabKey, string> = {
  categories:    'smart.master.categories.update',
  subcategories: 'smart.master.subcategories.update',
  uoms:          'smart.master.uoms.update',
  brands:        'smart.master.brands.update',
  organizers:    'smart.master.organizers.update',
  vendors:       'smart.master.vendors.update',
  locations:     'smart.master.locations.update',
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

  if (activeTab.value === 'categories') {
    if (!form.code || !form.code.trim()) {
      createFormErrors.value.code = t('masterData.validation.categoryCodeRequired');
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = t('masterData.validation.categoryNameRequired');
      hasError = true;
    }
  } else if (activeTab.value === 'subcategories') {
    if (!form.category_id) {
      createFormErrors.value.category_id = t('masterData.validation.parentCategoryRequired');
      hasError = true;
    }
    if (!form.code || !form.code.trim()) {
      createFormErrors.value.code = t('masterData.validation.subcategoryCodeRequired');
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = t('masterData.validation.subcategoryNameRequired');
      hasError = true;
    }
  } else if (activeTab.value === 'vendors') {
    if (!form.code || !form.code.trim()) {
      createFormErrors.value.code = t('masterData.validation.vendorCodeRequired');
      hasError = true;
    } else if (!/^VN\d{4}$/.test(form.code)) {
      createFormErrors.value.code = t('masterData.validation.vendorCodeFormat');
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = t('masterData.validation.vendorNameRequired');
      hasError = true;
    }
    if (!form.address || !form.address.trim()) {
      createFormErrors.value.address = t('masterData.validation.vendorAddressRequired');
      hasError = true;
    }
    if (!form.phone_number || !form.phone_number.trim()) {
      createFormErrors.value.phone_number = t('masterData.validation.vendorPhoneRequired');
      hasError = true;
    }
  } else {
    // uoms, brands, organizers, locations
    if (!form.name || !form.name.trim()) {
      createFormErrors.value.name = t('masterData.validation.nameRequired', { tab: currentTabSingular.value });
      hasError = true;
    }
  }

  if (hasError) return;

  let submitForm = form;
  if (activeTab.value === 'subcategories') {
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

  if (activeTab.value === 'categories') {
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = t('masterData.validation.categoryNameRequired');
      hasError = true;
    }
  } else if (activeTab.value === 'subcategories') {
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = t('masterData.validation.subcategoryNameRequired');
      hasError = true;
    }
  } else if (activeTab.value === 'vendors') {
    if (!form.code || !form.code.trim()) {
      editFormErrors.value.code = t('masterData.validation.vendorCodeRequired');
      hasError = true;
    } else if (!/^VN\d{4}$/.test(form.code)) {
      editFormErrors.value.code = t('masterData.validation.vendorCodeFormat');
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = t('masterData.validation.vendorNameRequired');
      hasError = true;
    }
    if (!form.address || !form.address.trim()) {
      editFormErrors.value.address = t('masterData.validation.vendorAddressRequired');
      hasError = true;
    }
    if (!form.phone_number || !form.phone_number.trim()) {
      editFormErrors.value.phone_number = t('masterData.validation.vendorPhoneRequired');
      hasError = true;
    }
  } else {
    // uoms, brands, organizers, locations
    if (!form.name || !form.name.trim()) {
      editFormErrors.value.name = t('masterData.validation.nameRequired', { tab: currentTabSingular.value });
      hasError = true;
    }
  }

  if (hasError) return;

  form.put(route(updateRouteMap[activeTab.value], form.id), {
    onSuccess: () => closeEditModal(),
  });
};
const pageSize = computed(() => {
  if (activeTab.value === 'locations') return 999999;
  if (rowsPerPage.value === 'all') return 999999;
  return parseInt(rowsPerPage.value);
});

// Flash Notifications
const page = usePage();
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
  <AppLayout :title="t('masterData.title')">
    <div class="space-y-1">
      <!-- Tabs -->
      <Tabs v-model="currentTabLabel" :tabs="tabLabels" />

      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3">
          <Heading as="h2">{{ t('masterData.listTitle', { tab: currentTabSingular }) }}</Heading>
          
          <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <!-- Search -->
            <div class="flex items-end gap-3 w-full max-w-xl">
              <div class="space-y-1.5 flex-1 max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block">{{ t('masterData.filterLabel') }}</label>
                <TableSearch 
                  v-model="searchQuery"
                  :placeholder="t('masterData.searchPlaceholder', { tab: currentTabSingular })" 
                />
              </div>
              <div v-if="activeTab === 'subcategories'" class="flex-1 max-w-[200px]">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !parentFilter ? 'text-muted-foreground' : 'text-foreground']">
                      {{ parentFilter ? (props.categories.find(c => c.code === parentFilter)?.name || t('masterData.allParentCategories')) : t('masterData.allParentCategories') }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                    <DropdownMenuItem @select="parentFilter = ''">{{ t('masterData.allParentCategories') }}</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in props.categories" :key="cat.code" @select="parentFilter = cat.code">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <!-- Right Actions -->
            <div class="flex flex-wrap items-center justify-end gap-3 w-full sm:w-auto sm:ml-auto">
              <div v-if="activeTab !== 'locations'" class="flex items-center gap-2 text-sm text-muted-foreground">
                <span class="text-right">{{ t('masterData.rowsPerPage') }}</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', rowsPerPage === 'all' ? 'text-muted-foreground' : 'text-foreground']">
                      {{ rowsPerPage === 'all' ? t('masterData.allRows') : rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                    <DropdownMenuItem @select="rowsPerPage = 'all'">{{ t('masterData.allRows') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                    <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <Button @click="openCreateModal" variant="primary">
                <Plus class="w-4 h-4" />
                <span>{{ t('masterData.newButton', { tab: currentTabSingular }) }}</span>
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
            :filter-value="activeTab === 'locations' ? '' : searchQuery"
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
        <div v-if="isEditModalOpen" @click="closeEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 overscroll-contain">
          <div 
            :class="[
              'bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[261px] max-h-[90vh] overflow-hidden flex flex-col',
              !['subcategories', 'locations', 'categories', 'vendors'].includes(activeTab) ? 'max-w-[600px]' : 'max-w-[1200px]'
            ]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">{{ t('masterData.editTitle', { tab: currentTabSingular }) }}</h3>
              <button @click="closeEditModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 flex-grow overflow-y-auto overscroll-contain">
              <!-- Edit: Subkategori -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-if="activeTab === 'subcategories'">
                <Field data-disabled="true">
                  <FieldLabel>{{ t('masterData.fields.parentCategory') }}</FieldLabel>
                  <FieldContent>
                    <input type="text" :value="editingItem?.category?.name ?? ''" disabled
                      class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                  </FieldContent>
                </Field>
                <Field data-disabled="true">
                  <FieldLabel>{{ t('masterData.fields.subcategoryCodeReadOnly') }}</FieldLabel>
                  <FieldContent>
                    <input type="text" :value="editingItem?.code" disabled
                      class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                  </FieldContent>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.subcategoryName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editSubcategoryForm.name" maxlength="255"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field>
                  <FieldLabel><span>{{ t('masterData.fields.description') }}</span></FieldLabel>
                  <FieldContent>
                    <textarea v-model="editSubcategoryForm.description" :placeholder="t('masterData.placeholders.subcategoryDescription')" rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                </Field>
              </div>

              <!-- Edit: Lokasi -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-else-if="activeTab === 'locations'">
                <Field>
                  <FieldLabel>{{ t('masterData.fields.parentLocation') }}</FieldLabel>
                  <FieldContent>
                    <LocationCombobox
                      v-model="editLocationForm.parent_id"
                      :locations="props.locations"
                      :exclude-ids="editingItem ? [editingItem.id, ...getDescendantIds(editingItem.id)] : []"
                      :placeholder="t('masterData.placeholders.selectParentLocation')"
                      :clearable="true"
                    />
                  </FieldContent>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.locationName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editLocationForm.name" maxlength="255" :placeholder="t('masterData.placeholders.locationName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <div class="md:col-span-2 flex justify-end items-center pt-2">
                  <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-foreground cursor-pointer select-none" @click="editLocationForm.is_active = !editLocationForm.is_active">
                      {{ editLocationForm.is_active ? t('masterData.status.locationActive') : t('masterData.status.locationInactive') }}
                    </span>
                    <Switch
                      v-model="editLocationForm.is_active"
                      class="data-[state=checked]:!bg-emerald-600 data-[state=unchecked]:!bg-slate-300 dark:data-[state=unchecked]:!bg-slate-700"
                    />
                  </div>
                </div>
              </div>

              <!-- Edit: Kategori -->
              <div v-else-if="activeTab === 'categories'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!editFormErrors.code || undefined" data-disabled="true">
                  <FieldLabel>{{ t('masterData.fields.categoryCodeReadOnly') }}</FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editCategoryForm.code" disabled
                      class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.code">{{ editFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.categoryName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editCategoryForm.name" maxlength="255"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field data-disabled="true">
                  <FieldLabel><span>{{ t('masterData.fields.classification') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <RadioGroup v-model="editCategoryForm.is_consumable" disabled class="flex gap-6">
                      <div class="flex items-center space-x-2 opacity-60">
                        <RadioGroupItem id="edit-consumable-true" value="1" class="cursor-not-allowed" />
                        <Label for="edit-consumable-true" class="font-normal cursor-not-allowed">{{ t('masterData.classification.consumable') }}</Label>
                      </div>
                      <div class="flex items-center space-x-2 opacity-60">
                        <RadioGroupItem id="edit-consumable-false" value="0" class="cursor-not-allowed" />
                        <Label for="edit-consumable-false" class="font-normal cursor-not-allowed">{{ t('masterData.classification.asset') }}</Label>
                      </div>
                    </RadioGroup>
                  </FieldContent>
                </Field>
              </div>

              <!-- Edit: Merek -->
              <div v-else-if="activeTab === 'brands'" class="grid grid-cols-1 gap-6">
                <Field :data-invalid="!!editFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.brandName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editBrandForm.name" maxlength="255"
                      :placeholder="t('masterData.placeholders.brandName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.description || undefined">
                  <FieldLabel>{{ t('masterData.fields.description') }}</FieldLabel>
                  <FieldContent>
                    <textarea v-model="editBrandForm.description" :placeholder="t('masterData.placeholders.brandDescription')" rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.description">{{ editFormErrors.description }}</FieldError>
                </Field>
              </div>

              <!-- Edit: Vendor -->
              <div v-else-if="activeTab === 'vendors'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!editFormErrors.code || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.vendorCode') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.code"
                      disabled
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                      :class="[editFormErrors.code ? 'border-destructive' : 'border-input']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.code">{{ editFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.name || undefined" class="md:col-span-2">
                  <FieldLabel><span>{{ t('masterData.fields.vendorName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.name" maxlength="255"
                      :placeholder="t('masterData.placeholders.vendorName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.phone_number || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.phoneNumber') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.phone_number" maxlength="255"
                      :placeholder="t('masterData.placeholders.phoneNumber')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.phone_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.phone_number">{{ editFormErrors.phone_number }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.address || undefined" class="md:col-span-2">
                  <FieldLabel><span>{{ t('masterData.fields.address') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.address" maxlength="255"
                      :placeholder="t('masterData.placeholders.address')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.address ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.address">{{ editFormErrors.address }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.email || undefined">
                  <FieldLabel>{{ t('masterData.fields.email') }}</FieldLabel>
                  <FieldContent>
                    <input type="email" v-model="editVendorForm.email" maxlength="255"
                      :placeholder="t('masterData.placeholders.email')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.email">{{ editFormErrors.email }}</FieldError>
                </Field>
                <Field :data-invalid="!!editFormErrors.description || undefined" class="md:col-span-2">
                  <FieldLabel>{{ t('masterData.fields.description') }}</FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="editVendorForm.description" maxlength="255"
                      :placeholder="t('masterData.placeholders.vendorDescription')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.description">{{ editFormErrors.description }}</FieldError>
                </Field>

                <!-- Contact Person 1 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">{{ t('masterData.fields.contactPerson1') }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!editFormErrors.contact_person_1 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp1Name') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.contact_person_1" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp1Name')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.contact_person_1">{{ editFormErrors.contact_person_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_email_1 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp1Email') }}</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="editVendorForm.cp_email_1" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp1Email')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_email_1">{{ editFormErrors.cp_email_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_phone_1 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp1Phone') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.cp_phone_1" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp1Phone')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_phone_1">{{ editFormErrors.cp_phone_1 }}</FieldError>
                    </Field>
                  </div>
                </div>

                <!-- Contact Person 2 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">{{ t('masterData.fields.contactPerson2') }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!editFormErrors.contact_person_2 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp2Name') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.contact_person_2" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp2Name')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.contact_person_2">{{ editFormErrors.contact_person_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_email_2 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp2Email') }}</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="editVendorForm.cp_email_2" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp2Email')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="editFormErrors.cp_email_2">{{ editFormErrors.cp_email_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!editFormErrors.cp_phone_2 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp2Phone') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="editVendorForm.cp_phone_2" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp2Phone')"
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
                  <FieldLabel><span>{{ t('masterData.fields.nameWithTab', { tab: currentTabSingular }) }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="(activeEditForm as any).name" maxlength="255"
                      :placeholder="t('masterData.placeholders.currentName', { tab: currentTabSingular })"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
                </Field>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('masterData.fields.requiredMarker') }}</p>
              <div class="flex items-center gap-3">
                <Button @click="closeEditModal" variant="white" size="xl">
                  {{ t('common.cancel') }}
                </Button>
                <Button @click="submitUpdate" :disabled="(activeEditForm as any).processing" variant="primary" size="xl" class="relative">
                  <Loader2 v-if="(activeEditForm as any).processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': (activeEditForm as any).processing }">
                    {{ t('masterData.saveChanges') }}
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
        <div v-if="isCreateModalOpen" @click="closeCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 overscroll-contain">
          <div 
            :class="[
              'bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[261px] max-h-[90vh] overflow-hidden flex flex-col',
              !['subcategories', 'locations', 'categories', 'vendors'].includes(activeTab) ? 'max-w-[600px]' : 'max-w-[1200px]'
            ]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">{{ t('masterData.createTitle', { tab: currentTabSingular }) }}</h3>
              <button @click="closeCreateModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 flex-grow overflow-y-auto overscroll-contain">
              <!-- Create: Subkategori -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-if="activeTab === 'subcategories'">
                <Field :data-invalid="!!createFormErrors.category_id || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.parentCategory') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', !subcategoryForm.category_id ? 'text-muted-foreground' : 'text-foreground', createFormErrors.category_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
                          {{ subcategoryForm.category_id ? (props.categories.find(c => c.id === subcategoryForm.category_id)?.name || t('masterData.placeholders.selectParentCategory')) : t('masterData.placeholders.selectParentCategory') }}
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
                  <FieldLabel><span>{{ t('masterData.columns.codeWithTab', { tab: currentTabSingular }) }}<span class="text-destructive">*</span></span></FieldLabel>
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
                        maxlength="4" :disabled="!subcategoryForm.category_id" :placeholder="t('masterData.placeholders.fourUppercaseLetters')"
                        class="w-full pr-3 py-2 text-sm bg-transparent border-none focus:ring-0 focus:outline-none"
                        :class="{ 'cursor-not-allowed': !subcategoryForm.category_id }" />
                    </div>
                  </FieldContent>
                  <FieldError v-if="createFormErrors.code">{{ createFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.subcategoryName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="subcategoryForm.name" maxlength="255" :placeholder="t('masterData.placeholders.subcategoryName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field>
                  <FieldLabel><span>{{ t('masterData.fields.description') }}</span></FieldLabel>
                  <FieldContent>
                    <textarea v-model="subcategoryForm.description" :placeholder="t('masterData.placeholders.subcategoryDescription')" rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                </Field>
              </div>

              <!-- Create: Lokasi -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-else-if="activeTab === 'locations'">
                <Field>
                  <FieldLabel>{{ t('masterData.fields.parentLocation') }}</FieldLabel>
                  <FieldContent>
                    <LocationCombobox
                      v-model="locationForm.parent_id"
                      :locations="props.locations"
                      :placeholder="t('masterData.placeholders.selectParentLocation')"
                      :clearable="true"
                    />
                  </FieldContent>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.locationName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="locationForm.name" maxlength="255" :placeholder="t('masterData.placeholders.locationName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <div class="md:col-span-2 flex justify-end items-center pt-2">
                  <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-foreground cursor-pointer select-none" @click="locationForm.is_active = !locationForm.is_active">
                      {{ locationForm.is_active ? t('masterData.status.locationActive') : t('masterData.status.locationInactive') }}
                    </span>
                    <Switch
                      v-model="locationForm.is_active"
                      class="data-[state=checked]:!bg-emerald-600 data-[state=unchecked]:!bg-slate-300 dark:data-[state=unchecked]:!bg-slate-700"
                    />
                  </div>
                </div>
              </div>

              <!-- Create: Kategori -->
              <div v-else-if="activeTab === 'categories'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!createFormErrors.code || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.categoryCode') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="categoryForm.code"
                      @input="categoryForm.code = categoryForm.code.replace(/[^A-Za-z0-9]/g, '').toUpperCase()"
                      maxlength="4" :placeholder="t('masterData.placeholders.categoryCode')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.code ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.code">{{ createFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.categoryName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="categoryForm.name" maxlength="255" :placeholder="t('masterData.placeholders.categoryName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field>
                  <FieldLabel><span>{{ t('masterData.fields.classification') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <RadioGroup v-model="categoryForm.is_consumable" class="flex gap-6">
                      <div class="flex items-center space-x-2">
                        <RadioGroupItem id="consumable-true" value="1" class="cursor-pointer" />
                        <Label for="consumable-true" class="font-normal cursor-pointer">{{ t('masterData.classification.consumable') }}</Label>
                      </div>
                      <div class="flex items-center space-x-2">
                        <RadioGroupItem id="consumable-false" value="0" class="cursor-pointer" />
                        <Label for="consumable-false" class="font-normal cursor-pointer">{{ t('masterData.classification.asset') }}</Label>
                      </div>
                    </RadioGroup>
                  </FieldContent>
                </Field>
              </div>

              <!-- Create: Merek -->
              <div v-else-if="activeTab === 'brands'" class="grid grid-cols-1 gap-6">
                <Field :data-invalid="!!createFormErrors.name || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.brandName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="brandForm.name" maxlength="255"
                      :placeholder="t('masterData.placeholders.brandName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.description || undefined">
                  <FieldLabel>{{ t('masterData.fields.description') }}</FieldLabel>
                  <FieldContent>
                    <textarea v-model="brandForm.description" :placeholder="t('masterData.placeholders.brandDescription')" rows="3"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.description">{{ createFormErrors.description }}</FieldError>
                </Field>
              </div>

              <!-- Create: Vendor -->
              <div v-else-if="activeTab === 'vendors'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Field :data-invalid="!!createFormErrors.code || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.vendorCode') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <div class="flex gap-2 w-full">
                      <input 
                        type="text" 
                        v-model="vendorForm.code"
                        disabled
                        :placeholder="t('masterData.placeholders.vendorCodeNotGenerated')" 
                        class="flex-grow px-4 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                        :class="[createFormErrors.code ? 'border-destructive' : 'border-input']"
                      />
                      <Button
                        type="button"
                        @click="generateVendorCode"
                        size="lg"                       
                      >
                        {{ t('masterData.actions.generate') }}
                      </Button>
                    </div>
                  </FieldContent>
                  <FieldError v-if="createFormErrors.code">{{ createFormErrors.code }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.name || undefined" class="md:col-span-2">
                  <FieldLabel><span>{{ t('masterData.fields.vendorName') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.name" maxlength="255"
                      :placeholder="t('masterData.placeholders.vendorName')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.phone_number || undefined">
                  <FieldLabel><span>{{ t('masterData.fields.phoneNumber') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.phone_number" maxlength="255"
                      :placeholder="t('masterData.placeholders.phoneNumber')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.phone_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.phone_number">{{ createFormErrors.phone_number }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.address || undefined" class="md:col-span-2">
                  <FieldLabel><span>{{ t('masterData.fields.address') }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.address" maxlength="255"
                      :placeholder="t('masterData.placeholders.address')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.address ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.address">{{ createFormErrors.address }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.email || undefined">
                  <FieldLabel>{{ t('masterData.fields.email') }}</FieldLabel>
                  <FieldContent>
                    <input type="email" v-model="vendorForm.email" maxlength="255"
                      :placeholder="t('masterData.placeholders.email')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.email">{{ createFormErrors.email }}</FieldError>
                </Field>
                <Field :data-invalid="!!createFormErrors.description || undefined" class="md:col-span-2">
                  <FieldLabel>{{ t('masterData.fields.description') }}</FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="vendorForm.description" maxlength="255"
                      :placeholder="t('masterData.placeholders.vendorDescription')"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.description">{{ createFormErrors.description }}</FieldError>
                </Field>

                <!-- Contact Person 1 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">{{ t('masterData.fields.contactPerson1') }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!createFormErrors.contact_person_1 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp1Name') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.contact_person_1" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp1Name')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.contact_person_1">{{ createFormErrors.contact_person_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_email_1 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp1Email') }}</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="vendorForm.cp_email_1" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp1Email')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_email_1">{{ createFormErrors.cp_email_1 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_phone_1 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp1Phone') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.cp_phone_1" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp1Phone')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_phone_1">{{ createFormErrors.cp_phone_1 }}</FieldError>
                    </Field>
                  </div>
                </div>

                <!-- Contact Person 2 Section -->
                <div class="md:col-span-3 border-t pt-4 mt-2">
                  <h4 class="text-sm font-semibold text-foreground mb-4">{{ t('masterData.fields.contactPerson2') }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field :data-invalid="!!createFormErrors.contact_person_2 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp2Name') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.contact_person_2" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp2Name')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.contact_person_2">{{ createFormErrors.contact_person_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_email_2 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp2Email') }}</FieldLabel>
                      <FieldContent>
                        <input type="email" v-model="vendorForm.cp_email_2" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp2Email')"
                          class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary" />
                      </FieldContent>
                      <FieldError v-if="createFormErrors.cp_email_2">{{ createFormErrors.cp_email_2 }}</FieldError>
                    </Field>
                    <Field :data-invalid="!!createFormErrors.cp_phone_2 || undefined">
                      <FieldLabel>{{ t('masterData.fields.cp2Phone') }}</FieldLabel>
                      <FieldContent>
                        <input type="text" v-model="vendorForm.cp_phone_2" maxlength="255"
                          :placeholder="t('masterData.placeholders.cp2Phone')"
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
                  <FieldLabel><span>{{ t('masterData.fields.nameWithTab', { tab: currentTabSingular }) }}<span class="text-destructive">*</span></span></FieldLabel>
                  <FieldContent>
                    <input type="text" v-model="(activeCreateForm as any).name" maxlength="255"
                      :placeholder="t('masterData.placeholders.nameWithTab', { tab: currentTabSingular })"
                      class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                      :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']" />
                  </FieldContent>
                  <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
                </Field>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('masterData.fields.requiredMarker') }}</p>
              <div class="flex items-center gap-3">
                <Button @click="closeCreateModal" variant="white" size="xl">
                  {{ t('common.cancel') }}
                </Button>
                <Button @click="submitCreate" variant="primary" :disabled="(activeCreateForm as any).processing" size="xl" class="relative">
                  <Loader2 v-if="(activeCreateForm as any).processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': (activeCreateForm as any).processing }">
                    {{ t('masterData.createButton', { tab: currentTabSingular }) }}
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
      :item-name="currentTabSingular"
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
