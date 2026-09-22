<script setup lang="ts">
/**
 * Dedicated Inventory Logs Audit Trail Page component for monitoring stock transactions,
 * barang/LOT CRUD events, stock changes, actor attributions, and context notes.
 */
import { ref, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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
import type { ColumnDef } from '@tanstack/vue-table';
import DataTable from '@/Components/DataTable.vue';
import { getLocalizedAuditAction } from '@/lib/auditAction';

const { t } = useI18n();

interface InventoryLogItem {
  id: number;
  barang_number: string;
  barang_name: string;
  lot_number: string;
  action_type: string;
  quantity_change: number;
  actor: string;
  note: string;
  waktu: string;
  created_at_raw: string | null;
}

const props = defineProps<{
  logs: InventoryLogItem[];
}>();

const parseDateTime = (val: string) => {
  if (!val || val === '-') return 0;
  const [datePart, timePart] = val.split(' ');
  const [day, month, year] = datePart.split('-');
  const t = timePart || '00:00:00';
  const formattedTime = t.split(':').length === 2 ? `${t}:00` : t;
  return new Date(`${year}-${month}-${day}T${formattedTime}`).getTime();
};

const auditSearch = ref('');
const auditActionFilter = ref('semua');
const auditTimeFilter = ref('semua');
const auditRowsPerPage = ref('50');

const isAnyFilterActive = computed(() => {
  return auditSearch.value !== '' || 
    auditActionFilter.value !== 'semua' || 
    auditTimeFilter.value !== 'semua';
});

const resetFilters = () => {
  auditSearch.value = '';
  auditActionFilter.value = 'semua';
  auditTimeFilter.value = 'semua';
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
  let list = [...props.logs];

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

const auditColumns = computed<ColumnDef<InventoryLogItem>[]>(() => [
  {
    accessorKey: 'barang_number',
    size: 200,
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
      return h('div', { class: 'space-y-0.5 max-w-[200px]' }, [
        h('div', { class: 'font-mono text-sm font-medium text-foreground truncate' }, item.barang_number),
        item.barang_name && item.barang_name !== '-' 
          ? h('div', { class: 'text-xs text-muted-foreground truncate', title: item.barang_name }, item.barang_name) 
          : null
      ]);
    }
  },
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
    size: 150,
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
    cell: ({ row }) => h('div', { class: 'font-mono text-sm font-medium text-foreground truncate' }, row.getValue('lot_number') || '-'),
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
    cell: ({ row }) => {
      const note = String(row.original.note || row.getValue('catatan') || '');
      if (note.includes(' | ')) {
        const lines = note.split(' | ');
        return h('ul', { class: 'list-disc pl-4 space-y-0.5 text-muted-foreground whitespace-normal text-left min-w-[200px]' }, 
          lines.map(line => h('li', {}, line))
        );
      }
      return h('div', { class: 'text-muted-foreground whitespace-normal text-left min-w-[200px]' }, note);
    },
  }
]);
</script>

<template>
  <AppLayout :title="t('admin.inventoryAuditTitle')">
    <div class="space-y-4">
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">{{ t('admin.inventoryAuditTitle') }}</h2>

          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
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
                    <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (auditRowsPerPage === 'Semua baris' || !auditRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                      {{ auditRowsPerPageLabel }}
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px] z-[110]" align="start" :side-offset="4">
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
    </div>
  </AppLayout>
</template>
