<script setup lang="ts">
/**
 * Reusable Inventory Logs Audit Trail Card component.
 * Displays stock transaction logs, filters (search, action, time range), and paginated DataTable.
 */
import { ref, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { ArrowUpDown, ChevronDown } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import TableSearch from '@/Components/TableSearch.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import { Checkbox } from '@/Components/ui/checkbox';
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import { getLocalizedAuditAction, renderAuditNote } from '@/lib/auditAction';

export interface InventoryLogItem {
  id: number;
  model_id?: number | null;
  lot_id?: number | null;
  model_param?: string | null;
  lot_param?: string | null;
  barang_number: string;
  barang_name: string;
  lot_number: string;
  action_type: string;
  quantity_change: number;
  actor: string;
  note: string;
  waktu: string;
  created_at_raw: string | null;
  request_id?: number | null;
  request_number?: string | null;
  fulfillment_id?: number | null;
  is_consumable?: boolean;
}

const props = withDefaults(defineProps<{
  logs: InventoryLogItem[];
  title?: string;
  hideBarangColumn?: boolean;
  hideFilters?: boolean;
  showHideStockInFilter?: boolean;
}>(), {
  title: undefined,
  hideBarangColumn: false,
  hideFilters: false,
  showHideStockInFilter: false,
});

const { t } = useI18n();

const STOCK_IN_ACTIONS = new Set(['stock_in', 'stock in', 'stok masuk']);
const isStockInAction = (act?: string | null): boolean => {
  if (!act) return false;
  return STOCK_IN_ACTIONS.has(act.toLowerCase());
};

const effectiveTitle = computed(() => props.title ?? t('admin.inventoryAuditTitle'));

const parseDateTime = (val: string) => {
  if (!val || val === '-') return 0;
  const [datePart, timePart] = val.split(' ');
  const [day, month, year] = datePart.split('-');
  const tStr = timePart || '00:00:00';
  const formattedTime = tStr.split(':').length === 2 ? `${tStr}:00` : tStr;
  return new Date(`${year}-${month}-${day}T${formattedTime}`).getTime();
};

const auditSearch = ref('');
const auditActionFilter = ref('semua');
const auditTimeFilter = ref('semua');
const auditRowsPerPage = ref('50');
const hideStockIn = ref(false);

const isAnyFilterActive = computed(() => {
  return auditSearch.value !== '' || 
    auditActionFilter.value !== 'semua' || 
    auditTimeFilter.value !== 'semua' ||
    hideStockIn.value;
});

const resetFilters = () => {
  auditSearch.value = '';
  auditActionFilter.value = 'semua';
  auditTimeFilter.value = 'semua';
  hideStockIn.value = false;
};

const formatAction = (act: string) => {
  if (!act || act === '-') return '-';
  if (act === 'semua') return t('admin.allActions');
  return getLocalizedAuditAction(act, t);
};

const auditActionFilterLabel = computed(() => {
  return formatAction(auditActionFilter.value);
});

const auditTimeFilterLabel = computed(() => {
  if (auditTimeFilter.value === '7-hari') return t('admin.last7Days');
  if (auditTimeFilter.value === '30-hari') return t('admin.last30Days');
  return t('admin.allTimeRanges');
});

const auditRowsPerPageLabel = computed(() => {
  if (auditRowsPerPage.value === 'Semua baris') return t('admin.allRows');
  return auditRowsPerPage.value;
});

const computedAuditPageSize = computed(() => {
  if (auditRowsPerPage.value === 'Semua baris' || !auditRowsPerPage.value) {
    return 999999;
  }
  return parseInt(auditRowsPerPage.value, 10) || 50;
});

const filteredLogs = computed(() => {
  if (props.hideFilters) {
    if (!hideStockIn.value) {
      return props.logs;
    }
    return props.logs.filter((l) => !isStockInAction(l.action_type));
  }

  let list = props.logs;

  if (hideStockIn.value) {
    list = list.filter((l) => !isStockInAction(l.action_type));
  }

  if (auditSearch.value.trim() !== '') {
    const q = auditSearch.value.toLowerCase();
    list = list.filter(l => 
      (l.barang_number && l.barang_number.toLowerCase().includes(q)) ||
      (l.barang_name && l.barang_name.toLowerCase().includes(q)) ||
      (l.lot_number && l.lot_number.toLowerCase().includes(q)) ||
      (l.actor && l.actor.toLowerCase().includes(q))
    );
  }

  if (auditActionFilter.value !== 'semua') {
    list = list.filter(l => l.action_type === auditActionFilter.value);
  }

  if (auditTimeFilter.value !== 'semua') {
    const now = new Date();
    list = list.filter(l => {
      const logTime = parseDateTime(l.waktu);
      if (logTime === 0) return false;
      const diffTime = Math.abs(now.getTime() - logTime);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if (auditTimeFilter.value === '7-hari') return diffDays <= 7;
      if (auditTimeFilter.value === '30-hari') return diffDays <= 30;
      return true;
    });
  }

  return list;
});

const auditActionOptions = computed(() => {
  const actions = new Set<string>();
  props.logs.forEach(l => {
    if (l.action_type) actions.add(l.action_type);
  });
  return Array.from(actions);
});

const getActionBadgeClass = (action: string) => {
  const act = action.toLowerCase();
  if (act.includes('stock_in') || act.includes('masuk') || act === 'create') {
    return 'bg-emerald-100 text-emerald-800';
  }
  if (act.includes('stock_out') || act.includes('keluar') || act === 'delete') {
    return 'bg-rose-100 text-rose-800';
  }
  if (act === 'update' || act.includes('adjustment')) {
    return 'bg-blue-100 text-blue-800';
  }
  return 'bg-gray-100 text-gray-800';
};

const auditColumns = computed<ColumnDef<InventoryLogItem>[]>(() => {
  const cols: ColumnDef<InventoryLogItem>[] = [];

  if (!props.hideBarangColumn) {
    cols.push({
      accessorKey: 'barang_number',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('admin.stockBarang'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]);
      },
      cell: ({ row }) => {
        const item = row.original;
        const hasNumber = item.barang_number && item.barang_number !== '-';
        const modelTarget = item.model_param || item.model_id || (hasNumber ? item.barang_number.replace(/[^a-zA-Z0-9]/g, '') : null);

        const numberVNode = hasNumber && modelTarget
          ? h('button', {
              type: 'button',
              class: 'font-mono text-sm font-semibold text-primary hover:underline hover:text-primary/80 transition-colors cursor-pointer text-left focus:outline-none whitespace-nowrap',
              onClick: () => router.visit(`/smart/stock-management/${modelTarget}`)
            }, item.barang_number)
          : h('div', { class: 'font-mono text-sm font-medium text-foreground whitespace-nowrap' }, item.barang_number || '-');

        return h('div', { class: 'space-y-0.5' }, [
          numberVNode,
          item.barang_name && item.barang_name !== '-' 
            ? h('div', { class: 'text-xs text-muted-foreground', title: item.barang_name }, item.barang_name) 
            : null
        ]);
      }
    });
  }

  cols.push(
    {
      accessorKey: 'waktu',
      size: 120,
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('admin.auditTime'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]);
      },
      cell: ({ row }) => {
        const val = String(row.getValue('waktu') || '-');
        if (val === '-' || !val.includes(' ')) {
          return h('div', { class: 'text-foreground' }, val);
        }
        const [datePart, timePart] = val.split(' ');
        return h('div', { class: 'text-foreground leading-tight space-y-0.5' }, [
          h('div', { class: 'font-medium' }, datePart),
          h('div', { class: 'text-muted-foreground text-xs' }, timePart)
        ]);
      },
      sortingFn: (rowA, rowB, columnId) => {
        const valA = rowA.getValue(columnId) as string;
        const valB = rowB.getValue(columnId) as string;
        return parseDateTime(valA) - parseDateTime(valB);
      }
    },
    {
      accessorKey: 'lot_number',
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('admin.stockLot'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]);
      },
      cell: ({ row }) => {
        const item = row.original;
        const val = row.getValue('lot_number') as string;
        const hasLot = val && val !== '-';
        const lotTarget = item.lot_param || item.lot_id || (hasLot ? val.replace(/[^a-zA-Z0-9]/g, '') : null);

        if (hasLot && lotTarget) {
          const isConsumable = item.is_consumable ?? false;
          const modelTarget = item.model_param || item.model_id;

          const targetUrl = (isConsumable && modelTarget && item.lot_id)
            ? `/smart/stock-management/${modelTarget}?tab=lots&lot_id=${item.lot_id}`
            : `/smart/stock-management/lots/${lotTarget}`;

          return h('button', {
            type: 'button',
            class: 'font-mono text-sm font-semibold text-primary hover:underline hover:text-primary/80 transition-colors cursor-pointer text-left focus:outline-none whitespace-nowrap',
            onClick: () => router.visit(targetUrl)
          }, val);
        }

        return h('div', { class: 'font-mono text-sm font-medium text-foreground whitespace-nowrap' }, val || '-');
      },
    },
    {
      accessorKey: 'action_type',
      size: 130,
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('admin.auditAction'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]);
      },
      cell: ({ row }) => {
        const act = row.getValue('action_type') as string;
        return h('span', {
          class: ['inline-flex items-center px-2 py-0.5 rounded-sm font-semibold', getActionBadgeClass(act)]
        }, formatAction(act));
      }
    },
    {
      accessorKey: 'quantity_change',
      size: 130,
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('admin.stockQtyChange'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]);
      },
      cell: ({ row }) => {
        const qty = Number(row.getValue('quantity_change') || 0);
        if (qty > 0) {
          return h('span', { class: 'text-sm font-semibold text-chart-3' }, `+${qty}`);
        }
        if (qty < 0) {
          return h('span', { class: 'text-sm font-semibold text-destructive' }, `${qty}`);
        }
        return h('span', { class: 'text-sm text-muted-foreground' }, '-');
      }
    },
    {
      accessorKey: 'actor',
      size: 160,
      header: ({ column }) => {
        return h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('admin.auditActor'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
        ]);
      },
      cell: ({ row }) => h('div', { class: 'text-foreground truncate text-sm' }, row.getValue('actor') || '-'),
    },
    {
      accessorKey: 'catatan',
      header: () => h('div', { class: 'font-semibold text-foreground justify-start' }, t('admin.auditNotes')),
      cell: ({ row }) => renderAuditNote(
        row.getValue('catatan') || row.original.note,
        row.original.request_id,
        row.original.request_number
      ),
    }
  );

  return cols;
});
</script>

<template>
  <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
    <div class="py-3 no-print">
      <!-- Title & Rows Per Page (Header row when filters are hidden) -->
      <div :class="['flex items-center justify-between', hideFilters ? 'pb-1' : '']">
        <h2 class="text-lg font-bold text-foreground">{{ effectiveTitle }}</h2>

        <!-- Rows Per Page & Optional Hide Stock In (Shown in header if hideFilters is true) -->
        <div v-if="hideFilters" class="flex flex-wrap items-center gap-3 sm:gap-4 ml-auto">
          <!-- Hide Stock In Checkbox Pill -->
          <label
            v-if="showHideStockInFilter"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-[14px] border border-border bg-background hover:bg-muted/50 cursor-pointer text-xs sm:text-sm font-medium text-foreground transition-all duration-150 select-none shadow-sm active:scale-95"
            :class="{ 'border-primary/50 bg-primary/5 text-primary': hideStockIn }"
          >
            <Checkbox v-model="hideStockIn" class="rounded-[4px]" />
            <span>{{ t('admin.hideStockIn') }}</span>
          </label>

          <!-- Rows Per Page -->
          <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <span>{{ t('admin.rowsPerPage') }}</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[100px] justify-between rounded-[14px] font-normal', (auditRowsPerPage === 'Semua baris' || !auditRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                  {{ auditRowsPerPageLabel }}
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[100px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                <DropdownMenuItem @select="auditRowsPerPage = 'Semua baris'">{{ t('admin.allRows') }}</DropdownMenuItem>
                <DropdownMenuItem @select="auditRowsPerPage = '10'">10</DropdownMenuItem>
                <DropdownMenuItem @select="auditRowsPerPage = '25'">25</DropdownMenuItem>
                <DropdownMenuItem @select="auditRowsPerPage = '50'">50</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>

      <!-- Filters & Actions (Hidden when hideFilters is true) -->
      <div v-if="!hideFilters" class="mt-4 flex flex-col space-y-4">
        <div class="flex flex-wrap items-end gap-3">
          <!-- Search -->
          <div class="space-y-1.5 flex-1 min-w-[200px] max-w-md">
            <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('admin.filter') }}</label>
            <TableSearch 
              v-model="auditSearch"
              :placeholder="t('admin.inventoryAuditSearchPlaceholder')" 
            />
          </div>

          <!-- Aksi Filter Dropdown -->
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal', (auditActionFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">{{ auditActionFilterLabel }}</span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
              <DropdownMenuItem @select="auditActionFilter = 'semua'">{{ t('admin.allActions') }}</DropdownMenuItem>
              <DropdownMenuItem v-for="act in auditActionOptions" :key="act" @select="auditActionFilter = act">
                {{ formatAction(act) }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Kurun Waktu Filter Dropdown -->
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[220px] justify-between rounded-[14px] font-normal', (auditTimeFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                <span class="truncate">
                  {{ auditTimeFilterLabel }}
                </span>
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[220px] rounded-[14px] z-[110]" align="start" :side-offset="4">
              <DropdownMenuItem @select="auditTimeFilter = 'semua'">{{ t('admin.allTimeRanges') }}</DropdownMenuItem>
              <DropdownMenuItem @select="auditTimeFilter = '7-hari'">{{ t('admin.last7Days') }}</DropdownMenuItem>
              <DropdownMenuItem @select="auditTimeFilter = '30-hari'">{{ t('admin.last30Days') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Reset Filter Button -->
          <ResetFilterButton v-if="isAnyFilterActive" @click="resetFilters" />

          <!-- Rows Per Page -->
          <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
            <span>{{ t('admin.rowsPerPage') }}</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" :class="['w-[100px] justify-between rounded-[14px] font-normal', (auditRowsPerPage === 'Semua baris' || !auditRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                  {{ auditRowsPerPageLabel }}
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-[100px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                <DropdownMenuItem @select="auditRowsPerPage = 'Semua baris'">{{ t('admin.allRows') }}</DropdownMenuItem>
                <DropdownMenuItem @select="auditRowsPerPage = '10'">10</DropdownMenuItem>
                <DropdownMenuItem @select="auditRowsPerPage = '25'">25</DropdownMenuItem>
                <DropdownMenuItem @select="auditRowsPerPage = '50'">50</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>

      <!-- Log table via DataTable -->
      <div class="mt-4 pb-4">
        <DataTable 
          cell-class="py-2.5"
          :columns="auditColumns" 
          :data="filteredLogs" 
          :page-size="computedAuditPageSize"
          :show-selection-count="false"
          :default-sorting="[{ id: 'waktu', desc: true }]"
        />
      </div>
    </div>
  </div>
</template>
