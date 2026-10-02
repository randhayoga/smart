<script setup lang="ts">
/**
 * Master Data Management Page component managing categories, subcategories, UOMs, brands, organizers, vendors, locations, floors, and rooms.
 */
import { ref, computed, watch, h, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
  ChevronDown, 
  ChevronRight, 
  ArrowUpDown, 
  Plus, 
  Trash2, 
  Pencil 
} from 'lucide-vue-next';

import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import Switch from "@/Components/ui/switch/Switch.vue";
import Heading from '@/Components/Heading.vue';

import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Tabs from '@/Components/Tabs.vue';
import MasterDataModal, {
  type MasterDataTabKey,
  type Category,
  type Subcategory,
  type SimpleItem,
  type VendorItem,
  type LocationItem,
} from './Modals/MasterDataModal.vue';

interface Props {
  user?: { name: string; email: string; };
  categories?:    Category[];
  subcategories?: Subcategory[];
  uoms?:          SimpleItem[];
  brands?:        SimpleItem[];
  organizers?:    SimpleItem[];
  vendors?:       VendorItem[];
  locations?:     LocationItem[];
  departments?:   any[];
}

const props = withDefaults(defineProps<Props>(), {
  user:          () => ({ name: '', email: '' }),
  categories:    () => [],
  subcategories: () => [],
  uoms:          () => [],
  brands:        () => [],
  organizers:    () => [],
  vendors:       () => [],
  locations:     () => [],
  departments:   () => [],
});

const { t } = useI18n();

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
      const dept = node.related_department;
      const deptMatch = dept ? (
        Boolean(dept.org_name?.toLowerCase().includes(q)) ||
        Boolean(dept.org_code && dept.org_code.toLowerCase().includes(q))
      ) : false;
      const selfMatch = node.name.toLowerCase().includes(q) || (node.full_name ?? '').toLowerCase().includes(q) || deptMatch;
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

// Modal State
const isModalOpen = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedItem = ref<any>(null);

const openCreateModal = () => {
  modalMode.value = 'create';
  selectedItem.value = null;
  isModalOpen.value = true;
};

const openEditModal = (item: any) => {
  modalMode.value = 'edit';
  selectedItem.value = { ...item };
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  selectedItem.value = null;
};

watch(isModalOpen, (isOpen) => {
  if (!isOpen) {
    selectedItem.value = null;
  }
});

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

  // Classification column (subcategories only)
  if (activeTab.value === 'subcategories') {
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
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight max-w-[200px]' }, t('masterData.columns.address')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate max-w-[200px]' }, row.getValue('address') || '-'),
    });
    cols.push({
      accessorKey: 'phone_number',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.phone')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('phone_number') || '-'),
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
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight max-w-[200px]' }, t('masterData.columns.description')),
      cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate max-w-[200px]' }, row.getValue('description') || '-'),
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

  // Related Department column for Lokasi
  if (activeTab.value === 'locations') {
    cols.push({
      accessorKey: 'related_departement',
      header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.relatedDepartment')),
      cell: ({ row }) => {
        const item = row.original as LocationRow;
        const dept = item.related_department;
        if (!dept) {
          return h('div', { class: 'pl-2 text-muted-foreground' }, '-');
        }
        const deptLabel = dept.org_code ? `${dept.org_code} - ${dept.org_name}` : dept.org_name;
        return h('div', { class: 'pl-2 text-foreground font-medium truncate', title: deptLabel }, deptLabel);
      },
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

const handleConfirmDelete = () => {
  if (!itemToDelete.value) return;
  deleteForm.delete(route(routeMap[activeTab.value], itemToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => closeDeleteModal(),
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
    if (isDeleteModalOpen.value) {
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
            :show-selection-count="false"
          />
        </div>
      </div>
    </div>
  </AppLayout>

  <!-- Unified Create & Edit Modal -->
  <MasterDataModal
    v-model:open="isModalOpen"
    :mode="modalMode"
    :active-tab="activeTab"
    :item="selectedItem"
    :categories="props.categories"
    :locations="props.locations"
    :vendors="props.vendors"
    :departments="props.departments"
    @close="closeModal"
  />

  <!-- Delete Confirmation Modal -->
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
</template>
