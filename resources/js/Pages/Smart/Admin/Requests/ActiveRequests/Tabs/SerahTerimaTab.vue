<script setup lang="ts">
/**
 * Serah Terima Tab Component for Permintaan Aktif
 * Lists scheduled handovers and links to detail page.
 */
import { ref, computed, watch, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { 
  ChevronDown, 
  ArrowUpDown,
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
import ViewTableButton from '@/Components/ViewTableButton.vue';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';

interface Props {
  handovers: any[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const dummyHandovers = computed(() => props.handovers);

const searchQuery = ref('');
const timeFilter = ref('');
const methodFilter = ref('');
const rowsPerPage = ref('10');

const timeFilterLabel = computed(() => {
  if (timeFilter.value === 'Hari ini') return t('fulfillment.today');
  if (timeFilter.value === 'Minggu ini') return t('fulfillment.thisWeek');
  if (timeFilter.value === 'Bulan ini') return t('fulfillment.thisMonth');
  return t('fulfillment.allTimeRanges');
});

const methodFilterLabel = computed(() => {
  if (methodFilter.value === 'Diambil sendiri') return t('fulfillment.selfPickup');
  if (methodFilter.value === 'Diantar') return t('fulfillment.delivered');
  return t('fulfillment.allMethods');
});

const rowsPerPageLabel = computed(() => {
  if (rowsPerPage.value === 'Semua baris') return t('fulfillment.allRows');
  return rowsPerPage.value;
});

const hasActiveFilters = computed(() => {
  return !!(
    searchQuery.value ||
    timeFilter.value ||
    methodFilter.value
  );
});

const clearFilters = () => {
  searchQuery.value = '';
  timeFilter.value = '';
  methodFilter.value = '';
};

const dataTableRef = ref<any>(null);

const handleViewDetail = (item: any) => {
  const url = `/smart/handover/${item.id}`;
  router.get(url);
};

const columns = computed<ColumnDef<any>[]>(() => [
  {
    id: 'select',
    size: 50,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
      })
    ]),
  },
  {
    accessorKey: 'number',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.number'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate' }, row.getValue('number')),
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.requesterName'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('requester')),
  },
  {
    accessorKey: 'method',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.method'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => {
      const val = row.getValue('method') as string;
      const label = val === 'Diambil sendiri' ? t('fulfillment.selfPickup') : (val === 'Diantar' ? t('fulfillment.delivered') : val);
      return h('div', { class: 'pl-0' }, label);
    },
  },
  {
    accessorKey: 'time',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.time'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('time')),
  },
  {
    accessorKey: 'location',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.location'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('location')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('fulfillment.actions')),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center no-print' }, [
      h(ViewTableButton, {
        onClick: () => handleViewDetail(row.original)
      })
    ]),
  },
]);

// Watchers for filters
watch(methodFilter, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.getColumn('method')?.setFilterValue(val);
  }
});

watch(rowsPerPage, (val) => {
  if (dataTableRef.value && dataTableRef.value.table) {
    if (val === 'Semua baris' || !val) {
      dataTableRef.value.table.setPageSize(999999);
    } else {
      dataTableRef.value.table.setPageSize(Number(val));
    }
  }
});

onMounted(() => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.setPageSize(Number(rowsPerPage.value));
  }
});
</script>

<template>
  <div>
    <!-- Filters Row -->
    <div class="space-y-4 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-md">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('fulfillment.filter') }}</label>
          <TableSearch 
            v-model="searchQuery"
            :placeholder="t('fulfillment.searchHandoverPlaceholder')" 
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[220px] justify-between rounded-[14px] font-normal', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ timeFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="timeFilter = ''">{{ t('fulfillment.allTimeRanges') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'Hari ini'">{{ t('fulfillment.today') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'Minggu ini'">{{ t('fulfillment.thisWeek') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'Bulan ini'">{{ t('fulfillment.thisMonth') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[220px] justify-between rounded-[14px] font-normal', !methodFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ methodFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="methodFilter = ''">{{ t('fulfillment.allMethods') }}</DropdownMenuItem>
            <DropdownMenuItem @select="methodFilter = 'Diambil sendiri'">{{ t('fulfillment.selfPickup') }}</DropdownMenuItem>
            <DropdownMenuItem @select="methodFilter = 'Diantar'">{{ t('fulfillment.delivered') }}</DropdownMenuItem>
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
              <Button variant="outline" :class="['w-[160px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPageLabel }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">{{ t('fulfillment.allRows') }}</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
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
        :data="dummyHandovers" 
        :filter-value="searchQuery"
      />
    </div>
  </div>
</template>
