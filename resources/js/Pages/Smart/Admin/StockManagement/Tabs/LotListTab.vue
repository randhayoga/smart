<script setup lang="ts">
/**
 * Daftar LOT Tab component rendering batch/procurement lot tables, quantity metrics, and batch actions.
 * Componentized with useLotTableColumns (<500 LOC).
 */
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import TableSearch from '@/Components/TableSearch.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import DataTable from '@/Components/DataTable.vue';
import { formatDate } from '@/lib/utils';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import ConsumableLotDetail from '../ConsumableLotDetail.vue';
import CreateLotModal from '../Modals/CreateLotModal.vue';
import EditLotModal from '../Modals/EditLotModal.vue';
import { printManajemenStok } from '@/utils/printManajemenStok';
import { exportCSV } from '@/utils/exportCSV';
import { exportExcel } from '@/utils/exportExcel';
import { usePermissions } from '@/composables/usePermissions';
import { useLotTableColumns } from './composables/useLotTableColumns';
import { buildLocationTargetSetMap } from './composables/useNonConsumableColumns';

interface Props {
  barang: {
    id: number;
    code: string;
    category: string;
    subcategory: string;
    brand: string;
    name: string;
    specification: string;
    lastUpdate: string;
    amount: number;
    image_url: string | null;
    uom: string;
    subcategory_id: number;
    category_id: number;
    category_code?: string;
    brand_id: number;
    uom_id: number;
    is_consumable: boolean;
  };
  lots: {
    id: number;
    number: string;
    po_number?: string | null;
    date_of_receipt: string;
    organizer: string;
    organizer_id: number;
    vendor: string;
    vendor_id?: number | null;
    legacy_vendor_id?: number | null;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    unitPrice: number | string;
    imageUrl: string;
    assetCount: number;
    availableAssetCount: number;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    burden?: string;
    updated_at: string;
  }[];
  organizers: { id: number; name: string }[];
  vendors: { id: number; name: string }[];
  locations: any[];
  floors?: any[];
  rooms?: any[];
  projects: { id: number; no_project: string; project_name: string; client_id: string }[];
  initialLotId?: number | null;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();
const { can } = usePermissions();

const searchQuery = ref('');
const entryTypeFilter = ref('');
const timeFilter = ref('');
const organizerFilter = ref('');
const locationFilter = ref('');
const rowsPerPage = ref<'all' | '10' | '25' | '50'>('50');
const dataTableRef = ref<any>(null);

const locationTargetSetMap = computed<Map<number, Set<number>>>(() =>
  buildLocationTargetSetMap(props.locations || [])
);

const isAnyFilterActive = computed(() => {
  return searchQuery.value !== '' ||
    entryTypeFilter.value !== '' ||
    timeFilter.value !== '' ||
    organizerFilter.value !== '' ||
    locationFilter.value !== '';
});

const resetFilters = () => {
  searchQuery.value = '';
  entryTypeFilter.value = '';
  timeFilter.value = '';
  organizerFilter.value = '';
  locationFilter.value = '';
};

// Lot Modal Setup
const isCreateLotModalOpen = ref(false);
const isBulkEditModalOpen = ref(false);
const selectedLotsForEdit = ref<any[]>([]);

const isDetailConsumablesOpen = ref(false);
const selectedLotForDetail = ref<number | null>(null);

// Delete LOT Modal Logic
const isDeleteModalOpen = ref(false);
const itemsToDelete = ref<any[]>([]);
const processing = ref(false);

const openDetailLOTConsumables = (lot: any) => {
  selectedLotForDetail.value = lot.id;
  isDetailConsumablesOpen.value = true;
};

const openCreateLotModal = () => {
  isCreateLotModalOpen.value = true;
};

const openEditLotModal = (lot: any) => {
  selectedLotsForEdit.value = [{ ...lot, barang_id: lot.barang_id || props.barang.id }];
  isBulkEditModalOpen.value = true;
};

const handleEditSuccess = () => {
  selectedLotsForEdit.value = [];
  if (dataTableRef.value) {
    dataTableRef.value.table.resetRowSelection();
  }
};

const openDeleteLotModal = (lots: any | any[]) => {
  const rawLots = Array.isArray(lots) ? lots : [lots];
  itemsToDelete.value = rawLots.map((lot: any) => ({
    ...lot,
    barang_code: lot.barang_code || props.barang.code,
    barang_brand: lot.barang_brand || props.barang.brand,
    barang_nama: lot.barang_nama || props.barang.name,
    barang_specification: lot.barang_specification || props.barang.specification,
    barang_category: lot.barang_category || props.barang.category,
    barang_subcategory: lot.barang_subcategory || props.barang.subcategory,
    barang_uom: lot.barang_uom || props.barang.uom,
    is_consumable: lot.is_consumable !== undefined ? lot.is_consumable : props.barang.is_consumable,
  }));
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false;
  itemsToDelete.value = [];
};

const { columns, deleteFields } = useLotTableColumns({
  barang: () => props.barang,
  locale,
  t,
  canManage: () => can('inventory.manage'),
  openDetailLOTConsumables,
  openDeleteLotModal,
  itemsToDelete,
  locations: () => props.locations || [],
});

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'all' || (rowsPerPage.value as any) === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

const filteredLots = computed(() => {
  let list = props.lots || [];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(
      (lot) =>
        (lot.number && lot.number.toLowerCase().includes(q)) ||
        (lot.po_number && lot.po_number.toLowerCase().includes(q))
    );
  }

  if (entryTypeFilter.value) {
    list = list.filter((lot: any) => (lot.entry_type || 'Procurement') === entryTypeFilter.value);
  }

  if (organizerFilter.value) {
    list = list.filter((lot) => lot.organizer === organizerFilter.value);
  }

  if (props.barang.is_consumable && locationFilter.value) {
    const locId = Number(locationFilter.value);
    const targetSet = locationTargetSetMap.value.get(locId) || new Set([locId]);
    list = list.filter((lot: any) => lot.location_id != null && targetSet.has(Number(lot.location_id)));
  }

  if (timeFilter.value) {
    const today = new Date();
    list = list.filter((lot) => {
      if (!lot.date_of_receipt) return false;
      const entryDateObj = new Date(lot.date_of_receipt);
      if (isNaN(entryDateObj.getTime())) return false;

      if (timeFilter.value === 'Hari ini' || timeFilter.value === 'today') {
        return entryDateObj.toDateString() === today.toDateString();
      } else if (timeFilter.value === 'Bulan ini' || timeFilter.value === 'thisMonth') {
        return entryDateObj.getMonth() === today.getMonth() && entryDateObj.getFullYear() === today.getFullYear();
      }
      return true;
    });
  }

  return list;
});

const uniqueOrganizers = computed(() => {
  const names = (props.lots || []).map((lot) => lot.organizer).filter(Boolean);
  return [...new Set(names)];
});

const getExportData = () => {
  if (!dataTableRef.value) return filteredLots.value;
  return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
};

const getExportPayload = () => {
  const data = getExportData();
  const headers = [
    t('inventory.lotCode'),
    t('inventory.poNumber'),
    t('inventory.registrationDate'),
    t('inventory.organizer'),
    t('inventory.stockCount'),
  ];
  const rows = data.map((item: any) => [
    item.number,
    item.po_number,
    formatDate(item.date_of_receipt),
    item.organizer,
    props.barang.is_consumable ? (item.current_quantity ?? 0) : item.assetCount,
  ]);
  return { headers, rows };
};

const handleExportCSV = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportCSV(headers, rows, 'lot_export');
};

const handleExportExcel = () => {
  const { headers, rows } = getExportPayload();
  if (rows.length === 0) return;
  exportExcel(headers, rows, 'lot_export');
};

const handlePrint = () => {
  const data = getExportData();
  if (!data || data.length === 0) return;
  printManajemenStok(data, {});
};

const handleEditTerpilih = () => {
  if (!dataTableRef.value || !dataTableRef.value.table) return;
  const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
  if (selectedRows.length === 1) {
    openEditLotModal(selectedRows[0].original);
  } else if (selectedRows.length > 1) {
    selectedLotsForEdit.value = selectedRows.map((row: any) => row.original);
    isBulkEditModalOpen.value = true;
  }
};

const handleConfirmDelete = () => {
  if (processing.value || itemsToDelete.value.length === 0) return;
  processing.value = true;

  const ids = itemsToDelete.value.map((item) => item.id);

  if (ids.length === 1) {
    router.delete(`/smart/stock-management/lots/${ids[0]}`, {
      onStart: () => {
        processing.value = true;
      },
      onFinish: () => {
        processing.value = false;
      },
      onSuccess: () => {
        closeDeleteModal();
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
      },
    });
  } else {
    router.delete('/smart/stock-management/lots/bulk', {
      data: { ids },
      onStart: () => {
        processing.value = true;
      },
      onFinish: () => {
        processing.value = false;
      },
      onSuccess: () => {
        closeDeleteModal();
        if (dataTableRef.value) {
          dataTableRef.value.table.resetRowSelection();
        }
      },
    });
  }
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isCreateLotModalOpen.value) {
      isCreateLotModalOpen.value = false;
    } else if (isBulkEditModalOpen.value) {
      isBulkEditModalOpen.value = false;
    } else if (isDeleteModalOpen.value) {
      closeDeleteModal();
    } else if (isDetailConsumablesOpen.value) {
      isDetailConsumablesOpen.value = false;
    }
  }
};

onMounted(() => {
  if (props.initialLotId) {
    selectedLotForDetail.value = Number(props.initialLotId);
    isDetailConsumablesOpen.value = true;
  }
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <!-- Daftar LOT Card -->
  <div class="bg-card rounded-xl border border-border px-4 py-3 shadow-sm overflow-hidden">
    <div class="no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">{{ t('inventory.lotList') }}</h2>

      <!-- Filters Row -->
      <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-1 flex-wrap items-end gap-3 min-w-0">
          <div class="space-y-1.5 flex-1 min-w-[260px] max-w-sm">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.filter') }}</label>
            <TableSearch
              v-model="searchQuery"
              :placeholder="t('inventory.searchLotPlaceholder')"
            />
          </div>

          <!-- Entry Type Filter -->
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !entryTypeFilter ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">{{ entryTypeFilter ? (entryTypeFilter === 'Procurement' ? t('inventory.procurement') : t('inventory.grant')) : t('inventory.allEntryTypes') }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="entryTypeFilter = ''">{{ t('inventory.allEntryTypes') }}</DropdownMenuItem>
              <DropdownMenuItem @select="entryTypeFilter = 'Procurement'">{{ t('inventory.procurement') }}</DropdownMenuItem>
              <DropdownMenuItem @select="entryTypeFilter = 'Hibah'">{{ t('inventory.grant') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">{{ timeFilter === 'today' ? t('inventory.today') : (timeFilter === 'thisMonth' ? t('inventory.thisMonth') : t('inventory.allTimeRanges')) }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="timeFilter = ''">{{ t('inventory.allTimeRanges') }}</DropdownMenuItem>
              <DropdownMenuItem @select="timeFilter = 'today'">{{ t('inventory.today') }}</DropdownMenuItem>
              <DropdownMenuItem @select="timeFilter = 'thisMonth'">{{ t('inventory.thisMonth') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !organizerFilter ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">{{ organizerFilter || t('inventory.allOrganizers') }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="organizerFilter = ''">{{ t('inventory.allOrganizers') }}</DropdownMenuItem>
              <DropdownMenuItem v-for="org in uniqueOrganizers" :key="org" @select="organizerFilter = org">
                {{ org }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Location Filter (Only for Consumables) -->
          <div v-if="props.barang.is_consumable" class="w-[320px]">
            <LocationCombobox
              :model-value="locationFilter"
              @update:model-value="locationFilter = String($event ?? '')"
              :locations="props.locations"
              :placeholder="t('inventory.allLocations')"
              :clearable="true"
              width-class="w-full"
            />
          </div>

          <!-- Reset Filter Button -->
          <ResetFilterButton v-if="isAnyFilterActive" @click="resetFilters" />
        </div>

        <div class="flex items-center gap-3 text-sm text-muted-foreground shrink-0 ml-auto pb-0.5">
          <span>{{ t('inventory.rowsPerPage') }}</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[100px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPage === 'all' ? t('inventory.allRows') : rowsPerPage }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[100px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'all'">{{ t('inventory.allRows') }}</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <!-- Actions Row -->
      <div v-if="can('inventory.manage')" class="mb-4 flex flex-wrap items-end justify-between gap-4 pt-2">
        <div class="space-y-2 flex-1 min-w-0">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.selectedActions') }}</label>
          <div class="flex flex-wrap gap-2">
            <Button
              @click="handleEditTerpilih"
              :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
              variant="more-round-warning"
            >
              <Pencil class="w-4 h-4" />
              <span class="hidden sm:inline">{{ t('inventory.editSelected') }}</span>
            </Button>
            <Button
              @click="openDeleteLotModal(dataTableRef.table.getFilteredRowModel().rows.filter((r: any) => r.getIsSelected()).map((r: any) => r.original))"
              :disabled="!dataTableRef || Object.keys(dataTableRef.table.getState().rowSelection).length === 0"
              variant="destructive"
            >
              <Trash2 class="w-4 h-4" />
              <span class="hidden sm:inline">{{ t('inventory.deleteSelected') }}</span>
            </Button>
          </div>
        </div>

        <Button @click="openCreateLotModal" variant="primary" size="lg">
          <Plus class="w-4 h-4" />
          <span>{{ t('inventory.newLot') }}</span>
        </Button>
      </div>
    </div>

    <!-- Table -->
    <div class="pb-2">
      <DataTable
        ref="dataTableRef"
        :columns="columns"
        :data="filteredLots"
        :page-size="pageSizeNumber"
        :default-sorting="[{ id: 'number', desc: true }]"
      />
    </div>
  </div>

  <CreateLotModal
    v-model:open="isCreateLotModalOpen"
    :barang="props.barang"
    :lots="props.lots"
    :organizers="props.organizers"
    :vendors="props.vendors"
    :locations="props.locations"
    :projects="props.projects"
  />

  <EditLotModal
    v-model:open="isBulkEditModalOpen"
    :items="selectedLotsForEdit"
    :isConsumable="props.barang.is_consumable"
    :uom="props.barang.uom"
    :parentImageUrl="props.barang.image_url"
    :organizers="props.organizers"
    :vendors="props.vendors"
    :locations="props.locations"
    :projects="props.projects"
    @success="handleEditSuccess"
  />

  <DeleteConfirmationModal
    :is-open="isDeleteModalOpen"
    :item-count="itemsToDelete.length"
    :item-name="t('inventory.lotItem')"
    :item-data="itemsToDelete.length === 1 ? itemsToDelete[0] : itemsToDelete"
    :fields="deleteFields"
    :max-width-class="itemsToDelete.length === 1 ? 'max-w-2xl' : undefined"
    :processing="processing"
    @close="closeDeleteModal"
    @confirm="handleConfirmDelete"
  />

  <ConsumableLotDetail
    :is-open="isDetailConsumablesOpen"
    :lot-id="selectedLotForDetail"
    @close="isDetailConsumablesOpen = false"
    @edit="openEditLotModal"
    @delete="openDeleteLotModal"
  />
</template>
