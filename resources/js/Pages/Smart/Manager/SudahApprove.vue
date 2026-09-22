<script setup lang="ts">
/**
 * Sudah Diproses Page (Manager)
 * Displays historical records of requisition and borrow requests that have already been reviewed/decided by the manager.
 */
import { ref, computed, watch, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  ArrowUpDown,
  ChevronDown
} from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import { getRequestStatusPillClass, getRequestStatusLabel, getLocalizedRequestStatusLabel } from '@/lib/requestStatus';
import type { SmartRequestData } from '@/types/request';

// --- Data Types & Props ---
interface Props {
  user: any;
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
const decisionFilter = ref('all');
const rowsPerPage = ref('50');

const typeFilterLabel = computed(() => {
  if (typeFilter.value === 'peminjaman') return t('requests.loan');
  if (typeFilter.value === 'permintaan') return t('requests.request');
  return t('approvals.allTypes');
});

const decisionFilterLabel = computed(() => {
  if (decisionFilter.value === 'approve') return t('approvals.approve');
  if (decisionFilter.value === 'reject') return t('approvals.reject');
  return t('approvals.allDecisions');
});

const rowsPerPageLabel = computed(() => {
  if (rowsPerPage.value === 'all') return t('approvals.allRows');
  return rowsPerPage.value;
});

// Filtered data
const filteredRequests = computed(() => {
  let list = [...requests.value];

  if (typeFilter.value !== 'all') {
    list = list.filter(req => req.type === typeFilter.value);
  }

  if (decisionFilter.value !== 'all') {
    list = list.filter(req => {
      if (decisionFilter.value === 'approve') {
        return req.raw_status !== 'reject';
      } else if (decisionFilter.value === 'reject') {
        return req.raw_status === 'reject';
      }
      return true;
    });
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

const columns = computed<ColumnDef<SmartRequestData>[]>(() => [
  {
    accessorKey: 'number',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.number'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
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
        t('approvals.type'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const val = row.getValue('type') as string;
      const label = val === 'peminjaman' ? t('requests.loan') : t('requests.request');
      return h('div', { class: 'text-foreground capitalize' }, label);
    },
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.requester'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
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
        t('approvals.utilization'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      const isCorporate = item.pemanfaatan === 'corporate';
      const utilLabel = isCorporate ? `${t('requests.corporate')} ` : `${t('requests.project')} `;
      return h('div', { class: 'text-foreground' }, [
        h('span', { class: 'font-semibold' }, utilLabel),
        h('span', { class: 'text-muted-foreground font-normal' }, isCorporate ? `(${item.pemanfaatanDetail})` : item.pemanfaatanDetail)
      ]);
    }
  },
  {
    accessorKey: 'decision',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.decision'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      const isApproved = item.raw_status !== 'reject';
      const decisionPill = isApproved ? 'Di-approve' : 'Ditolak';
      const decisionLabel = isApproved ? t('approvals.approve') : t('approvals.reject');
      return h('div', { class: 'text-left' }, [
        h('span', { 
          class: getRequestStatusPillClass(decisionPill)
        }, decisionLabel)
      ]);
    }
  },
  {
    accessorKey: 'approval_at',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.decidedAt'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'text-left' }, [
        h('div', { class: 'font-medium text-foreground' }, item.approval_at || '-'),
        item.approval_by ? h('div', { class: 'text-[11px] text-muted-foreground mt-0.5' }, t('approvals.decidedBy', { name: item.approval_by })) : null
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
        t('approvals.latestStatus'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      const statusLabel = getRequestStatusLabel(item.status || item.raw_status);
      const localizedLabel = getLocalizedRequestStatusLabel(item.status || item.raw_status);
      return h('div', { class: 'text-left' }, [
        h('span', { 
          class: getRequestStatusPillClass(statusLabel) 
        }, localizedLabel)
      ]);
    }
  }
]);
</script>

<template>
  <Head :title="$t('nav.items.processed')" />

  <AppLayout :title="$t('nav.items.processed')">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">{{ $t('approvals.historyTitle') }}</h1>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <!-- Filters Row -->
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ $t('common.filter') }}</label>
          <TableSearch 
            v-model="searchQuery" 
            :placeholder="$t('approvals.searchPlaceholder')" 
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
            <DropdownMenuItem @select="typeFilter = 'all'">{{ $t('approvals.allTypes') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'peminjaman'">{{ $t('requests.loan') }}</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'permintaan'">{{ $t('requests.request') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!decisionFilter || decisionFilter === 'all') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ decisionFilterLabel }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="decisionFilter = 'all'">{{ $t('approvals.allDecisions') }}</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'approve'">{{ $t('approvals.approve') }}</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'reject'">{{ $t('approvals.reject') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>{{ $t('approvals.rowsPerPage') }}</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (rowsPerPage === 'all' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPageLabel }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'all'">{{ $t('approvals.allRows') }}</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>

    <!-- ── Table Display ── -->
    <div class="pb-4">
      <DataTable 
        :columns="columns" 
        :data="filteredRequests" 
        :filter-value="searchQuery" 
        :page-size="computedPageSize"
      />
    </div>
  </AppLayout>
</template>
