<script setup lang="ts">
/**
 * Master Data Management Page component managing categories, subcategories, UOMs, brands, organizers, vendors, locations, floors, and rooms.
 * Componentized with useMasterDataColumns (<500 LOC).
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronDown, Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Heading from '@/Components/Heading.vue';
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
import { useMasterDataColumns } from './composables/useMasterDataColumns';

interface Props {
  user?: { name: string; email: string };
  categories?: Category[];
  subcategories?: Subcategory[];
  uoms?: SimpleItem[];
  brands?: SimpleItem[];
  organizers?: SimpleItem[];
  vendors?: VendorItem[];
  locations?: LocationItem[];
  departments?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  user: () => ({ name: '', email: '' }),
  categories: () => [],
  subcategories: () => [],
  uoms: () => [],
  brands: () => [],
  organizers: () => [],
  vendors: () => [],
  locations: () => [],
  departments: () => [],
});

const { t } = useI18n();

const tabKeys: MasterDataTabKey[] = [
  'categories',
  'subcategories',
  'uoms',
  'brands',
  'organizers',
  'vendors',
  'locations',
];

const activeTab = ref<MasterDataTabKey>('categories');

const getTabLabel = (key: MasterDataTabKey): string => t(`masterData.tabs.${key}`);
const tabLabels = computed(() => tabKeys.map((k) => getTabLabel(k)));

const currentTabLabel = computed({
  get: () => getTabLabel(activeTab.value),
  set: (label: string) => {
    const found = tabKeys.find((k) => getTabLabel(k) === label);
    if (found) {
      activeTab.value = found;
    }
  },
});

const currentTabSingular = computed(() => t(`masterData.tabSingular.${activeTab.value}`));

const searchQuery = ref('');
const parentFilter = ref('');
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
const dataTableRef = ref<any>(null);

const subcategoryRows = computed(() =>
  props.subcategories.map((s) => ({
    ...s,
    parent: s.category?.name ?? '',
    parentCode: s.category?.code ?? '',
  }))
);

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

// Delete Logic
const isDeleteModalOpen = ref(false);
const itemToDelete = ref<any>(null);

const openDeleteModal = (item: any) => {
  const itemData = { ...item };
  if (activeTab.value === 'locations' && itemData.parent_id && !itemData.parent) {
    const parentLoc = props.locations.find((l) => l.id === itemData.parent_id);
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

const { columns, flattenedLocationDisplay } = useMasterDataColumns({
  activeTab,
  currentTabSingular,
  searchQuery,
  locations: () => props.locations,
  t,
  openEditModal,
  openDeleteModal,
});

const displayData = computed(() => {
  if (activeTab.value === 'categories') return props.categories;
  if (activeTab.value === 'subcategories') return subcategoryRows.value;
  if (activeTab.value === 'uoms') return props.uoms;
  if (activeTab.value === 'brands') return props.brands;
  if (activeTab.value === 'organizers') return props.organizers;
  if (activeTab.value === 'vendors') return props.vendors;
  if (activeTab.value === 'locations') return flattenedLocationDisplay.value;
  return [];
});

watch(activeTab, () => {
  searchQuery.value = '';
  parentFilter.value = '';
});

watch(parentFilter, (val) => {
  if (activeTab.value === 'subcategories' && dataTableRef.value) {
    dataTableRef.value.table.getColumn('parent')?.setFilterValue(val);
  }
});

const deleteForm = useForm({});

const routeMap: Record<MasterDataTabKey, string> = {
  categories: 'smart.master.categories.destroy',
  subcategories: 'smart.master.types.destroy',
  uoms: 'smart.master.uoms.destroy',
  brands: 'smart.master.brands.destroy',
  organizers: 'smart.master.organizers.destroy',
  vendors: 'smart.master.vendors.destroy',
  locations: 'smart.master.locations.destroy',
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

watch(
  flashSuccess,
  (newVal) => {
    if (newVal && (page.props as any).flash?.success) {
      toast.success(newVal);
      if ((page.props as any).flash) {
        (page.props as any).flash.success = null;
      }
    }
  },
  { immediate: true }
);

const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

watch(
  flashError,
  (newVal) => {
    if (newVal) {
      errorModalMessage.value = newVal;
      isErrorModalOpen.value = true;
    }
  },
  { immediate: true }
);

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
