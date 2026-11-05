<script setup lang="ts">
/**
 * Daftar Habis Pakai Tab component managing consumable inventory catalog, low stock notifications, and nested lot views.
 * Componentized with useModelCatalogTable (<500 LOC).
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
  Breadcrumb,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import TableSearch from '@/Components/TableSearch.vue';
import DataTable from '@/Components/DataTable.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import Combobox from '@/Components/Combobox.vue';
import InputNewStockModal from '../Modals/InputNewStockModal.vue';
import EditModelModal from '../Modals/EditModelModal.vue';
import Tabs from '@/Components/Tabs.vue';
import ModelDetailTab from './ModelDetailTab.vue';
import LotListTab from './LotListTab.vue';
import InventoryAuditCard from './components/InventoryAuditCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useModelCatalogTable } from '../composables/useModelCatalogTable';

interface Props {
  barangs?: any[];
  categories?: any[];
  subcategories?: any[];
  brands?: any[];
  uoms?: any[];
  lots?: any[];
  organizers?: any[];
  vendors?: any[];
  locations?: any[];
  projects?: any[];
  inventoryLogs?: any[];
  selectedBarangCode?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  barangs: () => [],
  categories: () => [],
  subcategories: () => [],
  brands: () => [],
  uoms: () => [],
  lots: () => [],
  organizers: () => [],
  vendors: () => [],
  locations: () => [],
  projects: () => [],
  inventoryLogs: () => [],
  selectedBarangCode: null,
});

const { t } = useI18n();
const page = usePage();
const { can } = usePermissions();

// Selected Barang for LOT view
const selectedBarang = ref<any | null>(null);
const detailTabs = computed(() => [
  { id: 'Detail', label: t('inventory.typeDetail') },
  { id: 'Daftar LOT', label: t('inventory.lotList') },
  { id: 'Audit Manajemen Stok', label: t('admin.inventoryAuditTitle') },
]);
const activeDetailTab = ref('Detail');

const activeBarang = computed(() => {
  if (!selectedBarang.value) return null;
  return props.barangs.find((b) => String(b.id) === String(selectedBarang.value.id)) || selectedBarang.value;
});

const lotsForActiveBarang = computed(() => {
  if (!activeBarang.value) return [];
  return props.lots.filter((lot) => String(lot.barang_id) === String(activeBarang.value.id));
});

// Filters for Barang list
const searchQuery = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const stockStatusFilter = ref('');
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
const dataTableRef = ref<any>(null);

const syncFromPropsOrUrl = () => {
  const url = new URL(page.url, window.location.origin);
  const searchParam = url.searchParams.get('search');
  if (searchParam) {
    searchQuery.value = searchParam;
  }

  const normalizeCode = (val: any) =>
    String(val ?? '')
      .replace(/[^a-zA-Z0-9]/g, '')
      .toUpperCase();

  const findBarangByCode = (rawVal: string) => {
    const targetNorm = normalizeCode(rawVal);
    return props.barangs.find(
      (b) =>
        String(b.id) === String(rawVal) ||
        normalizeCode(b.code) === targetNorm ||
        normalizeCode(b.number) === targetNorm
    );
  };

  if (props.selectedBarangCode) {
    const found = findBarangByCode(String(props.selectedBarangCode));
    if (found) {
      selectedBarang.value = found;
      return;
    }
  }

  const pathMatch = url.pathname.match(/\/smart\/stock-management\/consumables\/([^/?#]+)/);
  if (pathMatch && pathMatch[1]) {
    const found = findBarangByCode(decodeURIComponent(pathMatch[1]));
    if (found) {
      selectedBarang.value = found;
      return;
    }
  }

  const barangIdParam = url.searchParams.get('barang_id') || url.searchParams.get('code');
  if (barangIdParam) {
    const found = findBarangByCode(barangIdParam);
    if (found) {
      selectedBarang.value = found;
      return;
    }
  }

  selectedBarang.value = null;
};

watch(selectedBarang, (newVal) => {
  if (newVal) {
    activeDetailTab.value = 'Detail';
  }
});

const getStockStatusLabel = (val: string) => {
  if (val === 'tipis') return t('inventory.lowStock');
  if (val === 'habis') return t('inventory.outOfStock');
  return t('inventory.allStockStatuses');
};

const hasActiveFilters = computed(() => {
  return !!(categoryFilter.value || subcategoryFilter.value || brandFilter.value || stockStatusFilter.value || searchQuery.value);
});

const clearFilters = () => {
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  stockStatusFilter.value = '';
  searchQuery.value = '';
};

const filteredBarangs = computed(() => {
  let list = props.barangs || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(
      (item) =>
        (item.name && item.name.toLowerCase().includes(q)) ||
        (item.code && item.code.toLowerCase().includes(q)) ||
        (item.specification && item.specification.toLowerCase().includes(q))
    );
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

  if (stockStatusFilter.value === 'habis') {
    list = list.filter((item) => Number(item.amount ?? 0) === 0);
  } else if (stockStatusFilter.value === 'tipis') {
    list = list.filter((item) => {
      const remainingStock = Number(item.amount ?? 0);
      const threshold =
        item.min_stock_threshold !== null && item.min_stock_threshold !== undefined
          ? Number(item.min_stock_threshold)
          : null;
      return remainingStock > 0 && threshold !== null && remainingStock <= threshold;
    });
  }

  return list;
});

const filteredCategories = computed(() => props.categories || []);

const filteredSubcategories = computed(() => {
  const cat = filteredCategories.value.find((c) => c.name === categoryFilter.value);
  if (!cat) return props.subcategories.map((s) => s.name);
  return props.subcategories.filter((s) => s.category_id == cat.id).map((s) => s.name);
});

const filteredBrands = computed(() => props.brands.map((b) => b.name));

watch(categoryFilter, () => {
  subcategoryFilter.value = '';
});

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'all' || (rowsPerPage.value as any) === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

const handleViewDetail = (item: any) => {
  const rawCode = item.code || item.number || '';
  const cleanCode = String(rawCode).replace(/[^a-zA-Z0-9]/g, '');
  const identifier = cleanCode || item.id;
  router.get(`/smart/stock-management/consumables/${identifier}`);
};

const {
  columns,
  isCreateModalOpen,
  openCreateModal,
  isBulkEditModalOpen,
  selectedItemsForEdit,
  openBulkEditModal,
  openEditModal,
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
  mode: 'consumable',
  lots: () => props.lots,
  dataTableRef,
  t,
  canManage: () => can('inventory.manage'),
  onViewDetail: handleViewDetail,
  onDeleteRequest: (item) => openDeleteModal(item),
  selectedBarang,
  activeBarang,
});

const getRowClass = (item: any) => {
  const remainingStock = Number(item.amount ?? 0);
  const threshold =
    item.min_stock_threshold !== null && item.min_stock_threshold !== undefined
      ? Number(item.min_stock_threshold)
      : null;

  if (remainingStock === 0) {
    return 'bg-red-100 hover:bg-red-200 text-black font-medium border-red-200';
  } else if (threshold !== null && remainingStock <= threshold) {
    return 'bg-amber-100 hover:bg-amber-200 text-black font-medium border-amber-200';
  }
  return '';
};

onMounted(() => {
  syncFromPropsOrUrl();
  document.addEventListener('keydown', closeOnEscape);
});

watch(
  () => [props.barangs, props.selectedBarangCode, page.url],
  () => {
    syncFromPropsOrUrl();
  },
  { immediate: true }
);

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <div>
    <!-- Adjusted Breadcrumb -->
    <Breadcrumb class="no-print mb-3">
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink
            :href="selectedBarang ? '/smart/stock-management/consumables' : undefined"
            :class="selectedBarang ? 'cursor-pointer hover:text-foreground' : ''"
          >
            {{ t('inventory.consumableList') }}
          </BreadcrumbLink>
        </BreadcrumbItem>
        <template v-if="selectedBarang && activeBarang">
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <span class="text-muted-foreground">{{ activeBarang.code }}</span>
          </BreadcrumbItem>
        </template>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Detail View: DetailBarangTab / DaftarLOTTab with Tabs pills -->
    <div v-if="selectedBarang && activeBarang">
      <!-- Top Action Bar -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-2 no-print">
        <Tabs v-model="activeDetailTab" :tabs="detailTabs" />

        <div v-if="can('inventory.manage')" class="flex items-center gap-3">
          <Button v-if="can('inventory.manage')" @click="openEditModal" variant="primary" size="lg">
            {{ t('inventory.editTypeDetail') }}
          </Button>
          <Button v-if="can('inventory.manage')" @click="openDeleteModal" variant="destructive" size="lg">
            {{ t('inventory.deleteType') }}
          </Button>
        </div>
      </div>

      <ModelDetailTab
        v-if="activeDetailTab === 'Detail'"
        :barang="activeBarang"
        :lots="lotsForActiveBarang"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :locations="props.locations"
        :projects="props.projects"
      />

      <LotListTab
        v-else-if="activeDetailTab === 'Daftar LOT'"
        :barang="activeBarang"
        :lots="lotsForActiveBarang"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :locations="props.locations"
        :projects="props.projects"
      />

      <InventoryAuditCard
        v-else-if="activeDetailTab === 'Audit Manajemen Stok'"
        :logs="props.inventoryLogs"
        :hide-barang-column="true"
      />
    </div>

    <!-- Table View: Consumable Barangs list -->
    <div v-else class="space-y-4">
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">{{ t('inventory.consumableList') }}</h2>

          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <!-- Row 1: Filters & Rows Per Page -->
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div class="flex flex-wrap items-end gap-3 flex-1">
                <!-- Search -->
                <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                  <label for="search-habis-pakai" class="text-xs text-muted-foreground font-medium block">{{ t('inventory.filter') }}</label>
                  <TableSearch
                    id="search-habis-pakai"
                    name="search"
                    v-model="searchQuery"
                    :placeholder="t('inventory.searchItemPlaceholder')"
                  />
                </div>

                <!-- Merek Combobox -->
                <Combobox
                  v-model="brandFilter"
                  :options="filteredBrands"
                  :search-placeholder="t('inventory.searchBrandPlaceholder')"
                  :default-label="t('inventory.allBrands')"
                />

                <!-- Kategori Dropdown -->
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

                <!-- Subkategori Combobox -->
                <Combobox
                  v-model="subcategoryFilter"
                  :options="filteredSubcategories"
                  :search-placeholder="t('inventory.searchSubcategoryPlaceholder')"
                  :default-label="t('inventory.allSubcategories')"
                />

                <!-- Status Stok Dropdown -->
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !stockStatusFilter ? 'text-muted-foreground' : 'text-foreground']">
                      <span class="truncate">{{ stockStatusFilter ? getStockStatusLabel(stockStatusFilter) : t('inventory.allStockStatuses') }}</span>
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="stockStatusFilter = ''">{{ t('inventory.allStockStatuses') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="stockStatusFilter = 'tipis'">{{ t('inventory.lowStock') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="stockStatusFilter = 'habis'">{{ t('inventory.outOfStock') }}</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

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
                    <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
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
            <div v-if="can('inventory.manage')" class="flex flex-wrap items-end justify-between gap-4 pt-2">
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
            :page-size="pageSizeNumber"
            :default-sorting="[{ id: 'lastUpdate', desc: true }]"
            :row-class="getRowClass"
          />
        </div>
      </div>
    </div>

    <!-- Modals -->
    <InputNewStockModal
      v-model:open="isCreateModalOpen"
      :categories="props.categories"
      :types="props.subcategories"
      :subcategories="props.subcategories"
      :uoms="props.uoms"
      :brands="props.brands"
      :models="props.barangs"
      :barangs="props.barangs"
      :lots="props.lots"
      :organizers="props.organizers"
      :locations="props.locations"
      :projects="props.projects"
    />

    <EditModelModal
      v-model:open="isBulkEditModalOpen"
      :items="selectedItemsForEdit"
      :uoms="props.uoms"
      :brands="props.brands"
      :lots="props.lots"
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
      @confirm="handleConfirmDelete('/smart/stock-management/consumables')"
    />

    <DeleteErrorModal
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="closeErrorModal"
    />
  </div>
</template>
