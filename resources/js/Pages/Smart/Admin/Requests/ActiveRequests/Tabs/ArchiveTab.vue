<script setup lang="ts">
/**
 * Archive Tab Component for Requests List (Daftar Permintaan)
 * Displays completed, rejected, and cancelled requests history.
 * Card structure and filters match the original standalone Archive page and BorrowedTab.
 */
import { ref, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { 
  ChevronDown, 
  ArrowUpDown,
  Eye,
} from 'lucide-vue-next';
import TableSearch from '@/Components/TableSearch.vue';
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
import { getRequestTypeBadgeClass, getRequestTypeLabel } from '@/lib/requestTypeBadge';

interface Props {
  archiveList: any[];
}

const props = withDefaults(defineProps<Props>(), {
  archiveList: () => [],
});

const { t } = useI18n();

const searchQuery = ref('');
const typeFilter = ref('');
const statusFilter = ref('');
const timeFilter = ref('');
const rowsPerPage = ref('50');
const dataTableRef = ref<any>(null);

const dummyArsip = computed(() => {
  let list = props.archiveList || [];
  if (typeFilter.value) {
    list = list.filter((item: any) => item.type === typeFilter.value);
  }
  if (statusFilter.value) {
    list = list.filter((item: any) => item.status === statusFilter.value);
  }
  return list;
});

const typeFilterLabel = computed(() => {
  if (typeFilter.value === 'consumable') return t('fulfillment.request');
  if (typeFilter.value === 'st') return t('fulfillment.manualRequest.typeSt');
  if (typeFilter.value === 'rent') return t('fulfillment.manualRequest.typeRent');
  if (typeFilter.value === 'lt') return t('fulfillment.manualRequest.typeLt');
  return t('fulfillment.allTypes');
});

const statusFilterLabel = computed(() => {
  if (statusFilter.value === 'Sukses') return t('fulfillment.success');
  if (statusFilter.value === 'Ditolak') return t('fulfillment.rejected');
  if (statusFilter.value === 'Dibatalkan') return t('fulfillment.cancelled');
  if (statusFilter.value === 'Pending') return t('fulfillment.pending');
  return t('fulfillment.allFinalStatuses');
});

const timeFilterLabel = computed(() => {
  if (timeFilter.value === 'Hari ini') return t('fulfillment.today');
  if (timeFilter.value === 'Minggu ini') return t('fulfillment.thisWeek');
  if (timeFilter.value === 'Bulan ini') return t('fulfillment.thisMonth');
  return t('fulfillment.allTimeRanges');
});

const rowsPerPageLabel = computed(() => {
  if (rowsPerPage.value === 'Semua baris' || rowsPerPage.value === 'all') return t('fulfillment.allRows');
  return rowsPerPage.value;
});

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'Semua baris' || rowsPerPage.value === 'all' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

const hasActiveFilters = computed(() => {
  return !!(searchQuery.value || typeFilter.value || statusFilter.value || timeFilter.value);
});

const clearFilters = () => {
  searchQuery.value = '';
  typeFilter.value = '';
  statusFilter.value = '';
  timeFilter.value = '';
};

const handleViewDetail = (item: any) => {
  const url = `/smart/requests/${item.id}`;
  router.get(url);
};

const columns = computed<ColumnDef<any>[]>(() => [
  {
    id: 'select',
    size: 50,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
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
    accessorKey: 'type',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.type'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => {
      const val = row.getValue('type') as string;
      const label = getRequestTypeLabel(val, t);
      const badgeClass = getRequestTypeBadgeClass(val);
      return h('span', {
        class: ['inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold', badgeClass]
      }, label);
    },
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.finalStatus'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => {
      const val = row.getValue('status') as string;
      let label = val;
      if (val === 'Sukses') label = t('fulfillment.success');
      else if (val === 'Ditolak') label = t('fulfillment.rejected');
      else if (val === 'Dibatalkan') label = t('fulfillment.cancelled');
      else if (val === 'Pending') label = t('fulfillment.pending');

      const isSuccess = val === 'Sukses' || val === 'success';
      const statusBadgeClass = isSuccess
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-700'
        : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-700';

      return h('span', {
        class: ['inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold', statusBadgeClass]
      }, label);
    },
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.employeeName'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0' }, row.getValue('requester')),
  },
  {
    accessorKey: 'startTime',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.startTime'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, row.getValue('startTime')),
  },
  {
    accessorKey: 'endTime',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      t('fulfillment.endTime'),
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
    ]),
    cell: ({ row }) => h('div', { class: 'pl-0 text-center text-muted-foreground' }, row.getValue('endTime')),
  },
  {
    id: 'actions',
    size: 80,
    header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('fulfillment.actions')),
    cell: ({ row }) => h('div', { class: 'flex items-center justify-center no-print' }, [
      h(Button, {
        variant: 'table-view',
        size: 'icon-sm',
        title: t('fulfillment.viewDetail'),
        onClick: () => handleViewDetail(row.original),
      }, () => [
        h(Eye, { class: 'w-4 h-4' }),
        h('span', { class: 'sr-only' }, t('fulfillment.viewDetail')),
      ]),
    ]),
  },
]);
</script>

<template>
  <div>
    <!-- Filters Row -->
    <div class="space-y-4 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 w-[310px]">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('fulfillment.filter') }}</label>
          <TableSearch 
            v-model="searchQuery"
            :placeholder="t('fulfillment.searchArchivePlaceholder')" 
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal', !typeFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ typeFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[180px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="typeFilter = ''">{{ t('fulfillment.allTypes') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'consumable'">{{ t('fulfillment.request') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'st'">{{ t('fulfillment.manualRequest.typeSt') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'rent'">{{ t('fulfillment.manualRequest.typeRent') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'lt'">{{ t('fulfillment.manualRequest.typeLt') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !statusFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ statusFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="statusFilter = ''">{{ t('fulfillment.allFinalStatuses') }}</DropdownMenuItem>
            <DropdownMenuItem @select="statusFilter = 'Sukses'">{{ t('fulfillment.success') }}</DropdownMenuItem>
            <DropdownMenuItem @select="statusFilter = 'Ditolak'">{{ t('fulfillment.rejected') }}</DropdownMenuItem>
            <DropdownMenuItem @select="statusFilter = 'Dibatalkan'">{{ t('fulfillment.cancelled') }}</DropdownMenuItem>
            <DropdownMenuItem @select="statusFilter = 'Pending'">{{ t('fulfillment.pending') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', !timeFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ timeFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="timeFilter = ''">{{ t('fulfillment.allTimeRanges') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'Hari ini'">{{ t('fulfillment.today') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'Minggu ini'">{{ t('fulfillment.thisWeek') }}</DropdownMenuItem>
            <DropdownMenuItem @select="timeFilter = 'Bulan ini'">{{ t('fulfillment.thisMonth') }}</DropdownMenuItem>
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
              <Button variant="outline" :class="['w-[100px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPageLabel }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[100px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">{{ t('fulfillment.allRows') }}</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="pb-4">
      <DataTable 
        ref="dataTableRef"
        :columns="columns" 
        :data="dummyArsip" 
        :filter-value="searchQuery"
        :page-size="pageSizeNumber"
      />
    </div>
  </div>
</template>
