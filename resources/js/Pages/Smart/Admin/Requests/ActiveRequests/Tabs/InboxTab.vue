<script setup lang="ts">
/**
 * Inbox Tab Component for Permintaan Aktif
 * Lists user-submitted requests awaiting admin confirmation or rejection.
 */
import { ref, computed, watch, onMounted, onUnmounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import {
  ArrowUpDown,
  ChevronDown,
  Eye
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import AdminConfirmationModal from '@/Pages/Smart/Admin/Modals/AdminConfirmationModal.vue';
import { REQUEST_STATUS_PILL_BASE } from '@/lib/requestStatus';
import type { SmartRequestData } from '@/types/request';

interface Props {
  requests: SmartRequestData[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const requests = ref<SmartRequestData[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// --- Filter & Search State ---
const searchQuery = ref('');
const typeFilter = ref('all');
const utilizationFilter = ref('all');
const rowsPerPage = ref('50');

const typeFilterLabel = computed(() => {
  if (typeFilter.value === 'peminjaman') return t('fulfillment.loan');
  if (typeFilter.value === 'permintaan') return t('fulfillment.request');
  return t('fulfillment.allTypes');
});

const utilizationFilterLabel = computed(() => {
  if (utilizationFilter.value === 'corporate') return t('fulfillment.corporate');
  if (utilizationFilter.value === 'project') return t('fulfillment.project');
  return t('fulfillment.allUtilizations');
});

const rowsPerPageLabel = computed(() => {
  if (rowsPerPage.value === 'all') return t('fulfillment.allRows');
  return rowsPerPage.value;
});

const hasActiveFilters = computed(() => {
  return !!(
    searchQuery.value ||
    (typeFilter.value && typeFilter.value !== 'all') ||
    (utilizationFilter.value && utilizationFilter.value !== 'all')
  );
});

const clearFilters = () => {
  searchQuery.value = '';
  typeFilter.value = 'all';
  utilizationFilter.value = 'all';
};

const dataTableRef = ref<any>(null);

// Selection States
const selectedIds = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return [];
  return dataTableRef.value.table.getFilteredRowModel().rows
    .filter((r: any) => r.getIsSelected())
    .map((r: any) => r.original.id);
});

// Filtered data
const filteredRequests = computed(() => {
  let list = [...requests.value];

  if (typeFilter.value !== 'all') {
    list = list.filter(req => req.type === typeFilter.value);
  }

  if (utilizationFilter.value !== 'all') {
    list = list.filter(req => req.pemanfaatan === utilizationFilter.value);
  }

  // Pre-sort by id descending (newest first)
  list.sort((a, b) => b.id - a.id);

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'all') {
    return filteredRequests.value.length || 50;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

watch([typeFilter, utilizationFilter], () => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
});

const columns = computed<ColumnDef<SmartRequestData>[]>(() => [
  {
    id: 'select',
    size: 40,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
        'aria-label': 'Select all',
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
        'aria-label': 'Select row',
      })
    ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'number',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.number'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('number')),
  },
  {
    accessorKey: 'type',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.type'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground capitalize' }, row.getValue('type') === 'peminjaman' ? t('fulfillment.loan') : t('fulfillment.request')),
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.requester'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground font-semibold' }, row.getValue('requester')),
  },
  {
    accessorKey: 'pemanfaatan',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.utilization'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      const isCorporate = item.pemanfaatan === 'corporate';
      return h('div', { class: 'text-foreground' }, [
        h('span', { class: 'font-semibold' }, isCorporate ? `${t('fulfillment.corporate')} ` : `${t('fulfillment.project')} `),
        h('span', { class: 'font-normal text-muted-foreground' }, isCorporate ? `(${item.pemanfaatanDetail})` : item.pemanfaatanDetail)
      ]);
    }
  },
  {
    accessorKey: 'created_at',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.createdAt'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.getValue('created_at')),
  },
  {
    accessorKey: 'is_stock_sufficient',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.stockSufficiency'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const isSufficient = Boolean(row.original.is_stock_sufficient);
      if (isSufficient) {
        return h('div', { class: 'text-left' }, [
          h('span', { class: `${REQUEST_STATUS_PILL_BASE} bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300` }, t('fulfillment.sufficient'))
        ]);
      }
      return h('div', { class: 'text-left' }, [
        h('span', { class: `${REQUEST_STATUS_PILL_BASE} bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300` }, t('fulfillment.insufficient'))
      ]);
    },
  },
  {
    id: 'actions',
    size: 80,
    enableGlobalFilter: false,
    header: () => h('div', { class: 'text-right font-semibold text-foreground no-print' }, t('fulfillment.actions')),
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'flex items-center justify-end gap-1.5 no-print' }, [
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: t('fulfillment.viewDetailAndConfirm'),
          onClick: () => openDetailModal(item)
        }, () => [
          h(Eye, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, t('fulfillment.viewDetail'))
        ]),
      ]);
    },
    enableSorting: false,
  }
]);

// --- Confirmation Modal States ---
const isDetailModalOpen = ref(false);
const isBulkModalOpen = ref(false);
const selectedSingleRequest = ref<SmartRequestData | null>(null);
const processing = ref(false);

const openDetailModal = (item: SmartRequestData) => {
  selectedSingleRequest.value = item;
  isDetailModalOpen.value = true;
};

const closeDetailModal = () => {
  isDetailModalOpen.value = false;
  selectedSingleRequest.value = null;
};

const openBulkModal = () => {
  if (selectedIds.value.length === 0) {
    toast.error(t('fulfillment.selectAtLeastOne'));
    return;
  }
  isBulkModalOpen.value = true;
};

const closeBulkModal = () => {
  isBulkModalOpen.value = false;
};

const singleRequestList = computed(() => {
  return selectedSingleRequest.value ? [selectedSingleRequest.value] : [];
});

const bulkRequestsList = computed(() => {
  return requests.value.filter(req => selectedIds.value.includes(req.id));
});

const handleModalAction = ({ action, note }: { action: 'confirm' | 'reject'; note: string }, isBulk: boolean) => {
  const idsToProcess = isBulk
    ? selectedIds.value 
    : (selectedSingleRequest.value ? [selectedSingleRequest.value.id] : []);

  if (idsToProcess.length === 0) {
    toast.error(t('fulfillment.noRequestsSelected'));
    return;
  }

  router.post(route('smart.inbox.confirmation'), {
    ids: idsToProcess,
    action: action,
    note: note,
  }, {
    onStart: () => { processing.value = true; },
    onFinish: () => { processing.value = false; },
    onSuccess: () => {
      if (isBulk) {
        closeBulkModal();
      } else {
        closeDetailModal();
      }
      if (dataTableRef.value && dataTableRef.value.table) {
        dataTableRef.value.table.resetRowSelection();
      }
      toast.success(action === 'confirm' 
        ? t('fulfillment.confirmSuccess') 
        : t('fulfillment.rejectSuccess')
      );
    },
    onError: (errs) => {
      toast.error(Object.values(errs).join(', '));
    }
  });
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isDetailModalOpen.value) closeDetailModal();
    if (isBulkModalOpen.value) closeBulkModal();
  }
};

const page = usePage();

const checkSearchParam = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search');
  if (searchParam) {
    searchQuery.value = searchParam;
  }
};

onMounted(() => {
  checkSearchParam();
  document.addEventListener('keydown', closeOnEscape);
});

watch(() => page.url, (newUrl) => {
  if (newUrl) {
    const url = new URL(newUrl, window.location.origin);
    const searchParam = url.searchParams.get('search');
    if (searchParam) {
      searchQuery.value = searchParam;
    }
  }
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <div>
    <!-- Filters & Bulk Actions -->
    <div class="space-y-4 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('fulfillment.filter') }}</label>
          <TableSearch 
            v-model="searchQuery"
            :placeholder="t('fulfillment.searchInboxPlaceholder')" 
            bg-class="bg-white"
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!typeFilter || typeFilter === 'all') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ typeFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="typeFilter = 'all'">{{ t('fulfillment.allTypes') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'peminjaman'">{{ t('fulfillment.loan') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'permintaan'">{{ t('fulfillment.request') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!utilizationFilter || utilizationFilter === 'all') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ utilizationFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="utilizationFilter = 'all'">{{ t('fulfillment.allUtilizations') }}</DropdownMenuItem>
            <DropdownMenuItem @select="utilizationFilter = 'corporate'">{{ t('fulfillment.corporate') }}</DropdownMenuItem>
            <DropdownMenuItem @select="utilizationFilter = 'project'">{{ t('fulfillment.project') }}</DropdownMenuItem>
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

      <!-- Bulk Actions & Rows per Page -->
      <div class="space-y-2 flex-1 min-w-0 pt-2">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('fulfillment.selectedActions') }}</label>
        <div class="flex flex-wrap items-center gap-2">
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openBulkModal"
            variant="view"
            class="rounded-[14px]"
          >
            <Eye class="w-4 h-4" />
            <span class="hidden sm:inline">{{ t('fulfillment.confirmSelected', { count: selectedIds.length }) }}</span>
          </Button>
          <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
            <span>{{ t('fulfillment.rowsPerPage') }}</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                  {{ rowsPerPageLabel }}
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
                <DropdownMenuItem @select="rowsPerPage = 'all'">{{ t('fulfillment.allRows') }}</DropdownMenuItem>
                <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Display -->
    <div class="pb-4">
      <DataTable 
        ref="dataTableRef"
        :columns="columns" 
        :data="filteredRequests" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
      />
    </div>

    <!-- Single Detail & Confirmation Modal -->
    <AdminConfirmationModal
      :is-open="isDetailModalOpen"
      :requests="singleRequestList"
      :processing="processing"
      @close="closeDetailModal"
      @action="(payload) => handleModalAction(payload, false)"
    />

    <!-- Bulk Confirmation Modal -->
    <AdminConfirmationModal
      :is-open="isBulkModalOpen"
      :requests="bulkRequestsList"
      :processing="processing"
      @close="closeBulkModal"
      @action="(payload) => handleModalAction(payload, true)"
    />
  </div>
</template>
