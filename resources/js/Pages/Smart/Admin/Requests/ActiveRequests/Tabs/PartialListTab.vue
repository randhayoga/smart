<script setup lang="ts">
/**
 * Parsial Tab Component for Permintaan Aktif
 * Lists partially fulfilled requests awaiting completion and admin fulfillment.
 */
import { ref, computed, watch, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import {
  ArrowUpDown,
  ChevronDown,
  Eye
} from 'lucide-vue-next';
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
import { REQUEST_STATUS_PILL_BASE, getRequestStatusBadgeClass, getRequestStatusLabel, getRequestStatusBadges } from '@/lib/requestStatus';

interface SmartRequestListItem {
  id: number;
  uuid: string;
  number: string;
  requester: string;
  approver: string;
  type: string;
  typeLabel?: string;
  destination: string;
  pemanfaatan: string;
  pemanfaatanDetail?: string;
  total_items: number;
  total_requested: number;
  total_fulfilled: number;
  is_fully_fulfilled: boolean;
  status: string;
  raw_status: string;
  createdAt: string;
  created_at: string;
  durationStart?: string | null;
  durationEnd?: string | null;
}

interface Props {
  requests: SmartRequestListItem[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const requests = ref<SmartRequestListItem[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// --- Filter & Search State ---
const searchQuery = ref('');
const typeFilter = ref('all');
const utilizationFilter = ref('all');
const rowsPerPage = ref('all');

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
    return filteredRequests.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

const openShowPage = (item: SmartRequestListItem) => {
  router.visit(route('smart.fulfillment.show', { id: item.uuid || item.id, from: 'parsial' }));
};

const columns = computed<ColumnDef<SmartRequestListItem>[]>(() => [
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
    accessorKey: 'status',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.status'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      const badges = getRequestStatusBadges(item.raw_status || item.status);
      return h('div', { class: 'flex flex-wrap items-center gap-1.5 text-left' }, 
        badges.map(b => h('span', { class: b.pillClass }, b.label))
      );
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
    cell: ({ row }) => h('div', { class: 'text-muted-foreground' }, row.original.createdAt || row.getValue('created_at')),
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
          title: t('fulfillment.viewDetailAndFulfill'),
          onClick: () => openShowPage(item)
        }, () => [
          h(Eye, { class: 'w-4 h-4' }),
          h('span', { class: 'sr-only' }, t('fulfillment.viewDetail'))
        ]),
      ]);
    },
    enableSorting: false,
  }
]);

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
</script>

<template>
  <div>
    <!-- Filters & Rows per Page -->
    <div class="space-y-4 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('fulfillment.filter') }}</label>
          <TableSearch 
            v-model="searchQuery"
            :placeholder="t('fulfillment.searchConfirmedPlaceholder')" 
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
            </DropdownMenuContent>
          </DropdownMenu>
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
  </div>
</template>
