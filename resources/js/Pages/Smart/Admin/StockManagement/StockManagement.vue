<script setup lang="ts">
/**
 * Admin Inventory Management Page component for high-level stock overview, category filtering, and item CRUD operations.
 * Componentized with useModelCatalogTable (<500 LOC).
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Plus, Trash2, Pencil } from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import Combobox from '@/Components/Combobox.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import { printManajemenStok } from '@/utils/printManajemenStok';
import { exportCSV } from '@/utils/exportCSV';
import { exportExcel } from '@/utils/exportExcel';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';
import DataTable from '@/Components/DataTable.vue';
import InputNewStockModal from './Modals/InputNewStockModal.vue';
import EditModelModal from './Modals/EditModelModal.vue';
import { useModelCatalogTable } from './composables/useModelCatalogTable';

interface Category {
  id: number;
  code: string;
  name: string;
}
interface Subcategory {
  id: number;
  code: string;
  name: string;
  category_id: number;
  is_consumable: boolean;
  category: Category;
}
interface SimpleItem {
  id: number;
  name: string;
}

interface Props {
  user: { name: string; email: string };
  categories: Category[];
  subcategories?: Subcategory[];
  types?: Subcategory[];
  uoms: SimpleItem[];
  brands: SimpleItem[];
  barangs?: any[];
  models?: any[];
  organizers?: SimpleItem[];
  vendors?: SimpleItem[];
  locations?: any[];
  projects?: any[];
  lots?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  categories: () => [],
  subcategories: () => [],
  types: () => [],
  uoms: () => [],
  brands: () => [],
  barangs: () => [],
  models: () => [],
  organizers: () => [],
  vendors: () => [],
  locations: () => [],
  projects: () => [],
  lots: () => [],
});

const { t } = useI18n();

const searchQuery = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const typeFilter = ref('');
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
const dataTableRef = ref<any>(null);

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'all' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

const hasActiveFilters = computed(() => {
  return !!(categoryFilter.value || subcategoryFilter.value || brandFilter.value || typeFilter.value || searchQuery.value);
});

const clearFilters = () => {
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  typeFilter.value = '';
  searchQuery.value = '';
};

const handleViewDetail = (item: any) => {
  const rawCode = item.code || item.number || '';
  const cleanCode = String(rawCode).replace(/[^a-zA-Z0-9]/g, '');
  const identifier = cleanCode || item.id;
  router.get(`/smart/stock-management/${identifier}`);
};

const {
  columns,
  isCreateModalOpen,
  openCreateModal,
  isBulkEditModalOpen,
  selectedItemsForEdit,
  openBulkEditModal,
  handleEditSuccess,
  isDeleteModalOpen,
  itemsToDelete,
  processing,
  openDeleteModal,
  closeDeleteModal,
  handleConfirmDelete,
  isErrorModalOpen,
  errorModalMessage,
  closeErrorModal,
  closeOnEscape,
} = useModelCatalogTable({
  mode: 'hierarchical',
  lots: () => props.lots,
  dataTableRef,
  t,
  canManage: () => true,
  onViewDetail: handleViewDetail,
  onDeleteRequest: (item) => openDeleteModal(item),
});

const getExportData = () => {
  if (!dataTableRef.value) return filteredBarangs.value;
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const getExportPayload = () => {
  const data = getExportData();
  const headers = [
    t('inventory.code'),
    t('inventory.categoryType'),
    t('inventory.brand'),
    t('inventory.name'),
    t('inventory.specification'),
    t('inventory.total'),
  ];
  const rows = data.map((item: any) => [
    item.code,
    item.subcategory ? `${item.category} (${item.subcategory})` : (item.category || '-'),
    item.brand,
    item.name,
    item.specification,
    item.amount,
  ]);
  return { headers, rows };
};

const handleExportCSV = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportCSV(headers, rows, 'inventory_export');
};

const handleExportExcel = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportExcel(headers, rows, 'inventory_export');
};

const handlePrint = () => {
  const data = getExportData();
  if (!data || data.length === 0) return;
  printManajemenStok(data, {});
};

const filteredBarangs = computed(() => {
  let list = props.barangs || [];
  if (typeFilter.value === 'Habis pakai') {
    list = list.filter((item) => item.is_consumable === true);
  } else if (typeFilter.value === 'Aset') {
    list = list.filter((item) => item.is_consumable === false);
  }
  if (categoryFilter.value) {
    list = list.filter((item) => item.category === categoryFilter.value);
  }
  if (subcategoryFilter.value) {
    list = list.filter((item) => item.subcategory === subcategoryFilter.value);
  }
  if (brandFilter.value) {
    list = list.filter((item) => item.brand === brandFilter.value);
  }
  return list;
});

const filteredCategories = computed(() => {
  const list = props.categories || [];
  if (typeFilter.value === 'Habis pakai') {
    const consumableCatIds = new Set(props.subcategories.filter((s) => s.is_consumable).map((s) => s.category_id));
    return list.filter((c) => consumableCatIds.has(c.id));
  } else if (typeFilter.value === 'Aset') {
    const assetCatIds = new Set(props.subcategories.filter((s) => !s.is_consumable).map((s) => s.category_id));
    return list.filter((c) => assetCatIds.has(c.id));
  }
  return list;
});

const mainFilteredSubcategories = computed(() => {
  let subs = props.subcategories || [];
  if (typeFilter.value === 'Habis pakai') {
    subs = subs.filter((s) => s.is_consumable);
  } else if (typeFilter.value === 'Aset') {
    subs = subs.filter((s) => !s.is_consumable);
  }
  const cat = filteredCategories.value.find((c) => c.name === categoryFilter.value);
  if (!cat) return subs.map((s) => s.name);
  return subs.filter((s) => s.category_id == cat.id).map((s) => s.name);
});

const mainFilteredBrands = computed(() => {
  return props.brands.map((b) => b.name);
});

watch(typeFilter, () => {
  categoryFilter.value = '';
});

watch(categoryFilter, () => {
  subcategoryFilter.value = '';
  brandFilter.value = '';
});

watch(subcategoryFilter, () => {
  brandFilter.value = '';
});

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <AppLayout :title="t('inventory.inventoryManagement')">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/stock-management">{{ t('inventory.inventoryManagement') }}</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-4">
      <!-- Main Card -->
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">{{ t('inventory.hierarchicalManagement') }}</h2>

          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                  <label for="search-barang" class="text-xs text-muted-foreground font-medium block">{{ t('inventory.filter') }}</label>
                  <TableSearch
                    id="search-barang"
                    name="search"
                    v-model="searchQuery"
                    :placeholder="t('inventory.searchItemPlaceholder')"
                  />
                </div>

                <!-- Types (models:type / Subcategory) Combobox -->
                <Combobox
                  v-model="subcategoryFilter"
                  :options="mainFilteredSubcategories"
                  :search-placeholder="t('inventory.searchSubcategoryPlaceholder')"
                  :default-label="t('inventory.allSubcategories')"
                />

                <!-- Category Dropdown -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !categoryFilter ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ categoryFilter || t('inventory.allCategories') }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="categoryFilter = ''">{{ t('inventory.allCategories') }}</DropdownMenuItem>
                    <DropdownMenuItem v-for="cat in filteredCategories" :key="cat.id" @select="categoryFilter = cat.name">
                      {{ cat.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Kinds (Consumables, Asset) Dropdown -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !typeFilter ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ typeFilter ? (typeFilter === 'Habis pakai' ? t('inventory.consumable') : t('inventory.asset')) : t('inventory.allKinds') }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="typeFilter = ''">{{ t('inventory.allKinds') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="typeFilter = 'Habis pakai'">{{ t('inventory.consumable') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="typeFilter = 'Aset'">{{ t('inventory.asset') }}</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <!-- Brand Combobox -->
                <Combobox
                  v-model="brandFilter"
                  :options="mainFilteredBrands"
                  :search-placeholder="t('inventory.searchBrandPlaceholder')"
                  :default-label="t('inventory.allBrands')"
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
                <span class="whitespace-nowrap text-right">{{ t('inventory.rowsPerPage') }}</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[100px] justify-between rounded-[14px] font-normal', rowsPerPage === 'all' ? 'text-muted-foreground' : 'text-foreground']">
                      {{ rowsPerPage === 'all' ? t('inventory.allRows') : rowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="rowsPerPage = 'all'">{{ t('inventory.allRows') }}</DropdownMenuItem>
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
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.selectedActions') }}</label>
                <div class="flex flex-wrap gap-2">
                  <Button
                    @click="openBulkEditModal"
                    :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                    variant="more-round-warning"
                  >
                    <Pencil class="w-4 h-4" />
                    <span class="hidden sm:inline">{{ t('inventory.editSelected') }}</span>
                  </Button>
                  <Button
                    @click="openDeleteModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
                    :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
                    variant="destructive"
                  >
                    <Trash2 class="w-4 h-4" />
                    <span class="hidden sm:inline">{{ t('inventory.deleteSelected') }}</span>
                  </Button>
                </div>
              </div>

              <Button
                @click="openCreateModal"
                variant="primary"
                size="lg"
              >
                <Plus class="w-4 h-4" />
                <span>{{ t('inventory.inputNewStock') }}</span>
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
            :page-size="pageSizeNumber"
            :default-sorting="[{ id: 'name', desc: false }]"
          />
        </div>
      </div>
    </div>

    <InputNewStockModal
      v-model:open="isCreateModalOpen"
      :categories="props.categories"
      :types="props.types || props.subcategories"
      :subcategories="props.subcategories || props.types"
      :uoms="props.uoms"
      :brands="props.brands"
      :models="props.models || props.barangs"
      :barangs="props.barangs || props.models"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :projects="props.projects"
      :lots="props.lots"
    />

    <EditModelModal
      v-model:open="isBulkEditModalOpen"
      :items="selectedItemsForEdit"
      :uoms="props.uoms"
      :brands="props.brands"
      @success="handleEditSuccess"
    />
    <DeleteConfirmationModal
      :is-open="isDeleteModalOpen"
      :item-count="itemsToDelete.length"
      :item-name="t('inventory.itemType')"
      :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : itemsToDelete"
      :max-width-class="itemsToDelete.length === 1 ? 'max-w-2xl' : undefined"
      :processing="processing"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete()"
    />
    <DeleteErrorModal
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </AppLayout>
</template>
