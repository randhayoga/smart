<script setup lang="ts">
/**
 * Daftar Tidak Habis Pakai Tab component providing non-consumable inventory data table, multi-column filters, export, and batch operations.
 * Componentized into NonConsumableFilterBar, useNonConsumableColumns, and useNonConsumableExport (<500 LOC).
 */
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ChevronDown, Pencil } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import DataTable from '@/Components/DataTable.vue';
import ExportButtonGroup from '@/Components/ExportButtonGroup.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import EditAssetModal from '../Modals/EditAssetModal.vue';
import DetailAssetModal from '../Modals/DetailAssetModal.vue';
import NonConsumableFilterBar from './components/NonConsumableFilterBar.vue';
import { useNonConsumableColumns, buildLocationTargetSetMap } from './composables/useNonConsumableColumns';
import { useNonConsumableExport } from './composables/useNonConsumableExport';
import { prepareUnitsBundle, type CompactUnitsPayload } from '../utils/hydrateCompactUnits';
import { parseAssetStatuses, hasAssetStatus } from '@/lib/assetStatus';
import { usePermissions } from '@/composables/usePermissions';

export type StatusScope =
  | 'standard'
  | 'unverified'
  | 'pending:bod/boc'
  | 'pending:manager'
  | 'all';

interface Props {
  units?: any[];
  unitsCompact?: CompactUnitsPayload;
  locations: any[];
  floors?: any[];
  rooms?: any[];
  organizers?: { id: number; name: string }[];
  vendors?: { id: number; name: string }[];
  projects?: { id: number; no_project: string; project_name: string; client_id?: string }[];
  users?: { id: number; name: string }[];
  hideBarangColumns?: boolean;
  hideStatusFilter?: boolean;
  hideConditionFilter?: boolean;
  hideActions?: boolean;
  customPrintHandler?: (items: any[]) => void;
  lot?: any;
  barang?: {
    category: string;
    category_code?: string;
  };
  filterVariant?: 'simple' | 'full';
  hideExport?: boolean;
  statusScope?: StatusScope;
  showErrorModal?: boolean;
  title?: string;
  cardless?: boolean;
  hideHeader?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  hideBarangColumns: false,
  hideStatusFilter: false,
  hideConditionFilter: false,
  hideActions: false,
  filterVariant: 'full',
  hideExport: false,
  statusScope: 'standard',
  showErrorModal: true,
  title: '',
  cardless: false,
  hideHeader: false,
});

const searchQuery = ref('');
const statusFilter = ref('');
const conditionFilter = ref('');
const categoryFilter = ref('');
const subcategoryFilter = ref('');
const brandFilter = ref('');
const locationFilter = ref('');
const organizerFilter = ref('');
const vendorFilter = ref('');
const showAdvancedFilters = ref(false);
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
const dataTableRef = ref<any>(null);

const { t, te, locale } = useI18n();
const page = usePage();
const { can } = usePermissions();

// Single-pass hydration + markRaw() + facet/status/search indexing
const unitsBundle = computed(() => prepareUnitsBundle(props.units, props.unitsCompact));
const resolvedUnits = computed(() => unitsBundle.value.units);

const isComp = computed(() => {
  const catCode = props.barang?.category || props.lot?.barang_category_code || props.lot?.barang_category || '';
  const catLower = String(catCode).toLowerCase();
  if (catCode === 'COMP' || catLower === 'computer' || catLower === 'comp') {
    return true;
  }
  return unitsBundle.value.facets.hasComp;
});

// View Asset Modal Setup
const isViewAssetModalOpen = ref(false);
const selectedAssetForView = ref<any>(null);
const openViewAssetModal = (asset: any) => {
  selectedAssetForView.value = asset;
  isViewAssetModalOpen.value = true;
  if ((!props.users || props.users.length === 0) && typeof router?.reload === 'function') {
    router.reload({ only: ['users'] });
  }
};

watch(
  resolvedUnits,
  (newUnits) => {
    if (selectedAssetForView.value && newUnits) {
      const updated = newUnits.find((u: any) => u.id === selectedAssetForView.value.id);
      if (updated) {
        selectedAssetForView.value = updated;
      }
    }
  }
);

// Edit Asset Modal Setup
const isEditAssetModalOpen = ref(false);
const selectedAssetsForEdit = ref<any[]>([]);

const ensureProjectsLoaded = () => {
  if ((!props.projects || props.projects.length === 0) && typeof router?.reload === 'function') {
    router.reload({ only: ['projects'] });
  }
};

const openEditAssetModal = (asset: any) => {
  if (!asset) return;
  selectedAssetsForEdit.value = [asset];
  isEditAssetModalOpen.value = true;
  ensureProjectsLoaded();
};

const handleEditTerpilih = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length === 1) {
    openEditAssetModal(selectedRows[0].original);
  } else if (selectedRows.length > 1) {
    selectedAssetsForEdit.value = selectedRows.map((row: any) => row.original);
    isEditAssetModalOpen.value = true;
    ensureProjectsLoaded();
  }
};

const handleAssetSuccess = () => {
  selectedAssetsForEdit.value = [];
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
};

const activeLotForEdit = computed(() => {
  if (props.lot) return props.lot;
  if (selectedAssetsForEdit.value.length === 1) {
    const item = selectedAssetsForEdit.value[0];
    return {
      id: item.lot_id,
      imageUrl: item.lot_imageUrl,
      unitPrice: item.lot_unitPrice,
    };
  }
  return null;
});

const activeBarangForEdit = computed(() => {
  if (props.barang) return props.barang;
  if (selectedAssetsForEdit.value.length === 1) {
    const item = selectedAssetsForEdit.value[0];
    return {
      category: item.barang_category,
      category_code: item.barang_category_code,
    };
  }
  return null;
});

// Flash Notifications & Error Modal
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
    if (props.showErrorModal && newVal) {
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

// Precomputed O(1) location descendant lookup map
const locationTargetSetMap = computed<Map<number, Set<number>>>(() =>
  buildLocationTargetSetMap(props.locations || [])
);

// Baseline Scoped Units based on statusScope prop
const scopedUnits = computed(() => {
  const units = resolvedUnits.value;
  switch (props.statusScope) {
    case 'unverified':
      return units.filter((u: any) => hasAssetStatus(u?.status, 'Belum Diverifikasi', u?.approval_status));
    case 'pending:bod/boc':
      return units.filter(
        (u: any) =>
          hasAssetStatus(u?.status, 'Pending:BoD/BoC', u?.approval_status) ||
          String(u?.status || '').trim().toLowerCase() === 'pending'
      );
    case 'pending:manager':
      return units.filter(
        (u: any) =>
          hasAssetStatus(u?.status, 'Pending:DM', u?.approval_status) ||
          hasAssetStatus(u?.status, 'Pending:Manager', u?.approval_status)
      );
    case 'all':
      return units;
    case 'standard':
    default:
      return unitsBundle.value.standardUnits;
  }
});

// Filtered Units list for Table
const filteredUnits = computed(() => {
  let list = scopedUnits.value;

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((u: any) =>
      u._searchText
        ? u._searchText.includes(q)
        : (u.number && u.number.toLowerCase().includes(q)) ||
          (u.barang_nama && u.barang_nama.toLowerCase().includes(q)) ||
          (u.legacy_number && u.legacy_number.toLowerCase().includes(q))
    );
  }

  if (categoryFilter.value) {
    const catLower = categoryFilter.value.toLowerCase();
    list = list.filter((u: any) => (u.barang_category || '').toLowerCase() === catLower);
  }

  if (subcategoryFilter.value) {
    const subLower = subcategoryFilter.value.toLowerCase();
    list = list.filter((u: any) => (u.barang_subcategory || '').toLowerCase() === subLower);
  }

  if (statusFilter.value) {
    const filterVal = statusFilter.value.toLowerCase();
    list = list.filter((u: any) => {
      const status = (u.status || '').toLowerCase();
      const proposed = (u.proposed_status || '').toLowerCase();
      return (
        hasAssetStatus(u.status, filterVal, u.approval_status) ||
        status === filterVal ||
        ((status === 'pending' || status === 'dihapus') && proposed === filterVal)
      );
    });
  }

  if (conditionFilter.value) {
    const condLower = conditionFilter.value.toLowerCase();
    list = list.filter((u: any) => (u.condition || '').toLowerCase() === condLower);
  }

  if (brandFilter.value) {
    const brandLower = brandFilter.value.toLowerCase();
    list = list.filter((u: any) => (u.barang_brand || '').toLowerCase() === brandLower);
  }

  if (locationFilter.value) {
    const locId = Number(locationFilter.value);
    const targetSet = locationTargetSetMap.value.get(locId) || new Set([locId]);
    list = list.filter((u: any) => u.location_id != null && targetSet.has(Number(u.location_id)));
  }

  if (organizerFilter.value) {
    const orgStr = String(organizerFilter.value);
    list = list.filter((u: any) => String(u.organizer_id) === orgStr);
  }

  if (vendorFilter.value) {
    const venStr = String(vendorFilter.value);
    list = list.filter((u: any) => String(u.vendor_id) === venStr || String(u.legacy_vendor_id) === venStr);
  }

  return list;
});

const isUnitSoft = (u: any): boolean => {
  if (!u) return false;
  const code = (u.barang_category_code || '').trim().toUpperCase();
  const cat = (u.barang_category || '').trim().toLowerCase();
  return code === 'SOFT' || cat === 'software' || cat === 'soft';
};

const isAllSoft = computed(() => {
  const parentCatCode = (props.barang?.category_code || props.lot?.barang_category_code || '').trim().toUpperCase();
  const parentCat = (props.barang?.category || props.lot?.barang_category || '').trim().toLowerCase();
  if (parentCatCode === 'SOFT' || parentCat === 'software' || parentCat === 'soft') {
    return true;
  }
  if (categoryFilter.value && categoryFilter.value.trim().toLowerCase() === 'software') {
    return true;
  }
  const list = filteredUnits.value;
  if (list.length === 0) return false;
  return list.every((u: any) => isUnitSoft(u));
});

watch(isAllSoft, (allSoft) => {
  if (allSoft) {
    if (conditionFilter.value) conditionFilter.value = '';
    if (locationFilter.value) locationFilter.value = '';
  }
});

const hasActiveFilters = computed(() => {
  return !!(
    statusFilter.value ||
    conditionFilter.value ||
    searchQuery.value ||
    categoryFilter.value ||
    subcategoryFilter.value ||
    brandFilter.value ||
    locationFilter.value ||
    organizerFilter.value ||
    vendorFilter.value
  );
});

const clearFilters = () => {
  statusFilter.value = '';
  conditionFilter.value = '';
  searchQuery.value = '';
  categoryFilter.value = '';
  subcategoryFilter.value = '';
  brandFilter.value = '';
  locationFilter.value = '';
  organizerFilter.value = '';
  vendorFilter.value = '';
};

// Single-pass facet computation over units (already precomputed in prepareUnitsBundle)
const availableCategories = computed<string[]>(() => unitsBundle.value.facets.categories);
const availableSubcategories = computed<string[]>(() => {
  if (categoryFilter.value) {
    const subSet = unitsBundle.value.facets.subByCat.get(categoryFilter.value);
    return subSet ? Array.from(subSet).sort() : [];
  }
  return unitsBundle.value.facets.allSubcategories;
});
const availableBrands = computed<string[]>(() => unitsBundle.value.facets.brands);

watch(categoryFilter, () => {
  subcategoryFilter.value = '';
});

const availableStatuses = computed(() => {
  if (!props.statusScope || props.statusScope === 'standard') {
    const std = unitsBundle.value.facets.standardStatuses;
    if (std.length > 0) return std;
    return ['Tersedia', 'Dipinjam (ST)', 'Di-assign (LT)', 'Tidak Aktif', 'Dihapus', 'Verifikasi Ditolak'];
  }
  const statusSet = new Set<string>();
  const list = scopedUnits.value;
  for (let i = 0; i < list.length; i++) {
    const tokens = list[i]._statusTokens || parseAssetStatuses(list[i].status, list[i].approval_status);
    for (let j = 0; j < tokens.length; j++) {
      if (tokens[j]) statusSet.add(tokens[j]);
    }
  }
  if (statusSet.size > 0) return Array.from(statusSet);
  return ['Tersedia', 'Dipinjam (ST)', 'Di-assign (LT)', 'Tidak Aktif', 'Dihapus', 'Verifikasi Ditolak'];
});

const availableConditions = ['Bagus', 'Rusak', 'Rusak Total', 'Hilang'];

const { columns, getStatusLabel, getConditionLabel } = useNonConsumableColumns({
  props,
  isComp,
  isAllSoft,
  t,
  te,
  locale,
  openViewAssetModal,
});

const { getExportPayload, handleExportCSV, handleExportExcel, handlePrint } = useNonConsumableExport({
  dataTableRef,
  filteredUnits,
  isAllSoft,
  locationFilter,
  locations: () => props.locations,
  customPrintHandler: props.customPrintHandler,
  t,
});

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'all' || (rowsPerPage.value as any) === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

const checkSearchParam = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search');
  if (searchParam) {
    searchQuery.value = searchParam;
  }
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isEditAssetModalOpen.value) {
      isEditAssetModalOpen.value = false;
    } else if (isViewAssetModalOpen.value) {
      isViewAssetModalOpen.value = false;
    } else if (isErrorModalOpen.value) {
      closeErrorModal();
    }
  }
};

onMounted(() => {
  checkSearchParam();
  document.addEventListener('keydown', closeOnEscape);
});

watch(
  () => page.url,
  (newUrl) => {
    if (newUrl) {
      const url = new URL(newUrl, window.location.origin);
      const searchParam = url.searchParams.get('search');
      if (searchParam) {
        searchQuery.value = searchParam;
      }
    }
  }
);

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

const totalAsetTerpilihCount = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return 0;
  return Object.keys(dataTableRef.value.table.getState().rowSelection).length;
});
</script>

<template>
  <div class="space-y-4">
    <!-- Main Card Container (Card frame omitted when cardless is true) -->
    <div :class="props.cardless ? '' : 'px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden'">
      <div :class="props.cardless ? 'mb-6 no-print' : 'py-3 no-print'">
        <h2 v-if="!props.hideHeader" class="text-lg font-bold text-foreground">{{ props.title || t('inventory.nonConsumableList') }}</h2>

        <!-- Filters & Actions -->
        <div :class="['flex flex-col space-y-4', props.hideHeader ? '' : 'mt-4']">
          <NonConsumableFilterBar
            v-model:search-query="searchQuery"
            v-model:category-filter="categoryFilter"
            v-model:subcategory-filter="subcategoryFilter"
            v-model:status-filter="statusFilter"
            v-model:condition-filter="conditionFilter"
            v-model:location-filter="locationFilter"
            v-model:brand-filter="brandFilter"
            v-model:organizer-filter="organizerFilter"
            v-model:vendor-filter="vendorFilter"
            v-model:show-advanced-filters="showAdvancedFilters"
            :has-active-filters="hasActiveFilters"
            :available-categories="availableCategories"
            :available-subcategories="availableSubcategories"
            :available-brands="availableBrands"
            :available-statuses="availableStatuses"
            :available-conditions="availableConditions"
            :locations="props.locations"
            :organizers="props.organizers"
            :vendors="props.vendors"
            :filter-variant="props.filterVariant"
            :hide-status-filter="props.hideStatusFilter"
            :hide-condition-filter="props.hideConditionFilter"
            :is-all-soft="isAllSoft"
            :cardless="props.cardless"
            :get-status-label="getStatusLabel"
            :get-condition-label="getConditionLabel"
            @clear="clearFilters"
          />

          <!-- Row 2: Bulk Actions & Rows Per Page -->
          <div class="flex flex-wrap items-end justify-between gap-4 pt-2">
            <div class="space-y-2 flex-1 min-w-0">
              <label v-if="(can('inventory.manage') && !props.hideActions) || !props.hideExport" class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.selectedActions') }}</label>
              <div class="flex flex-wrap gap-2">
                <Button
                  v-if="can('inventory.manage') && !props.hideActions"
                  @click="handleEditTerpilih()"
                  :disabled="totalAsetTerpilihCount === 0"
                  variant="more-round-warning"
                >
                  <Pencil class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ t('inventory.editSelected') }}</span>
                </Button>
                <ExportButtonGroup
                  v-if="!hideExport"
                  @print="handlePrint"
                  @export-pdf="handlePrint"
                  @export-excel="handleExportExcel"
                  @export-csv="handleExportCSV"
                />
              </div>
            </div>

            <!-- Right side: Rows Per Page & Extra Actions -->
            <div class="flex items-center gap-3 shrink-0 ml-auto pb-0.5">
              <div class="flex items-center gap-3 text-sm text-muted-foreground">
                <span class="whitespace-nowrap text-right">{{ t('inventory.rowsPerPage') }}</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[100px] justify-between rounded-[14px] font-normal', props.cardless ? 'bg-white' : '', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
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
              <slot v-if="can('inventory.manage') && !props.hideActions" name="extra-actions"></slot>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="pb-4">
        <DataTable
          ref="dataTableRef"
          :columns="columns"
          :data="filteredUnits"
          :page-size="pageSizeNumber"
          :show-selection-count="false"
          :preserve-initial-order="true"
        />

        <div v-if="!props.hideActions" class="text-xs text-muted-foreground pl-1 mt-3 no-print">
          {{ t('inventory.rowsSelected', { selected: totalAsetTerpilihCount, total: filteredUnits.length }) }}
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Asset Modal -->
  <EditAssetModal
    v-model:open="isEditAssetModalOpen"
    :items="selectedAssetsForEdit"
    :lot="activeLotForEdit"
    :barang="activeBarangForEdit"
    :locations="props.locations"
    :projects="props.projects"
    @success="handleAssetSuccess"
  />

  <!-- Delete Error Modal -->
  <DeleteErrorModal
    v-if="props.showErrorModal"
    :is-open="isErrorModalOpen"
    :error-message="errorModalMessage"
    @close="closeErrorModal"
  />

  <!-- Detail Asset Modal (Units) -->
  <DetailAssetModal
    v-model:open="isViewAssetModalOpen"
    :asset="selectedAssetForView"
    :lot="props.lot"
    :users="props.users"
    @edit="openEditAssetModal"
  />
</template>
