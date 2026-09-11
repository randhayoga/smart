<script setup lang="ts">
/**
 * Jejak Audit Tab component rendering filtered asset and request lifecycle history logs.
 */
import { ref, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowUpDown, ChevronDown } from 'lucide-vue-next';
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
import StatusBadge from '@/Components/StatusBadge.vue';

interface AuditTrail {
  waktu: string;
  status: string;
  action_type: string;
  aktor: string;
  durasi: string | number;
  catatan: string;
}

const props = defineProps<{
  lifecycles: AuditTrail[];
}>();

const { t } = useI18n();

const parseDateTime = (val: string) => {
  if (!val || val === '-') return 0;
  const [datePart, timePart] = val.split(' ');
  const [day, month, year] = datePart.split('-');
  const t = timePart || '00:00:00';
  const formattedTime = t.split(':').length === 2 ? `${t}:00` : t;
  return new Date(`${year}-${month}-${day}T${formattedTime}`).getTime();
};

const formatDurasi = (val: string | number) => {
  if (val === null || val === undefined || val === '-' || val === '') return '-';

  let totalDays = 0;

  if (typeof val === 'number') {
    totalDays = Math.floor(val);
  } else if (typeof val === 'string') {
    const trimmed = val.trim();
    if (trimmed.endsWith('jam') || trimmed.endsWith('hours') || trimmed.endsWith('hour')) {
      const hours = parseFloat(trimmed.replace(/(jam|hours|hour)/, '').trim());
      if (isNaN(hours)) return val;
      totalDays = Math.floor(hours / 24);
    } else if (trimmed.includes('hari') || trimmed.includes('bulan') || trimmed.includes('tahun') || trimmed.includes('day') || trimmed.includes('month') || trimmed.includes('year')) {
      return trimmed;
    } else {
      const parsed = parseFloat(trimmed);
      if (isNaN(parsed)) return val;
      totalDays = Math.floor(parsed);
    }
  }

  if (totalDays < 30) {
    return `${totalDays} ${t('approvals.days')}`;
  }

  const years = Math.floor(totalDays / 365);
  const remDaysAfterYears = totalDays % 365;
  const months = Math.floor(remDaysAfterYears / 30);
  const days = remDaysAfterYears % 30;

  const parts: string[] = [];
  if (years > 0) parts.push(`${years} ${t('approvals.years')}`);
  if (months > 0) parts.push(`${months} ${t('approvals.months')}`);
  if (days > 0 || parts.length === 0) parts.push(`${days} ${t('approvals.days')}`);

  return parts.join(' ');
};

const auditSearch = ref('');
const auditStatusFilter = ref('all');
const auditActionFilter = ref('all');
const auditTimeFilter = ref('all');
const auditRowsPerPage = ref('all');

const computedAuditPageSize = computed(() => {
  if (auditRowsPerPage.value === 'all') {
    return filteredLifecycles.value.length || 10;
  }
  return parseInt(auditRowsPerPage.value, 10);
});

const filteredLifecycles = computed(() => {
  let logs = [...props.lifecycles];

  if (auditSearch.value.trim() !== '') {
    const q = auditSearch.value.toLowerCase();
    logs = logs.filter(l => 
      l.aktor && l.aktor.toLowerCase().includes(q)
    );
  }

  if (auditStatusFilter.value !== 'all') {
    logs = logs.filter(l => l.status === auditStatusFilter.value);
  }

  if (auditActionFilter.value !== 'all') {
    logs = logs.filter(l => l.action_type === auditActionFilter.value);
  }

  if (auditTimeFilter.value !== 'all') {
    const now = new Date();
    logs = logs.filter(l => {
      const logTime = parseDateTime(l.waktu);
      if (logTime === 0) return false;
      const diffTime = Math.abs(now.getTime() - logTime);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if (auditTimeFilter.value === '7-days') return diffDays <= 7;
      if (auditTimeFilter.value === '30-days') return diffDays <= 30;
      return true;
    });
  }

  return logs;
});

const auditStatusOptions = computed(() => {
  const stats = new Set<string>();
  props.lifecycles.forEach(l => {
    if (l.status) stats.add(l.status);
  });
  return Array.from(stats);
});

const auditActionOptions = computed(() => {
  const actions = new Set<string>();
  props.lifecycles.forEach(l => {
    if (l.action_type) actions.add(l.action_type);
  });
  return Array.from(actions);
});

const auditColumns = computed<ColumnDef<AuditTrail>[]>(() => [
  {
    accessorKey: 'waktu',
    size: 160,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.auditTime'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('waktu')),
    sortingFn: (rowA, rowB, columnId) => {
      const valA = rowA.getValue(columnId) as string;
      const valB = rowB.getValue(columnId) as string;
      return parseDateTime(valA) - parseDateTime(valB);
    }
  },
  {
    accessorKey: 'status',
    size: 144,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.auditStatus'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const status = row.getValue('status') as string || '';
      return h(StatusBadge, {
        status,
        class: 'rounded-sm'
      });
    }
  },
  {
    accessorKey: 'action_type',
    size: 144,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.auditAction'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('action_type') || '-'),
  },
  {
    accessorKey: 'aktor',
    size: 160,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.auditActor'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('aktor')),
  },
  {
    accessorKey: 'durasi',
    size: 112,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-center w-full'
      }, () => [
        t('approvals.auditDuration'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-center text-foreground truncate' }, formatDurasi(row.getValue('durasi'))),
  },
  {
    accessorKey: 'catatan',
    header: () => h('div', { class: 'font-semibold text-foreground justify-start' }, t('approvals.auditNotes')),
    cell: ({ row }) => {
      const note = String(row.getValue('catatan') || '');
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
  <div class="space-y-4">
    <!-- Internal Search & Local Filters -->
    <div class="flex flex-wrap items-end gap-4">
      <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ $t('common.filter') }}</label>
        <TableSearch 
          v-model="auditSearch"
          :placeholder="t('approvals.auditSearchPlaceholder')" 
          bg-class="bg-white"
        />
      </div>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal bg-white', auditStatusFilter === 'all' ? 'text-muted-foreground' : 'text-foreground']">
            <span class="truncate">{{ auditStatusFilter === 'all' ? t('approvals.allStatus') : auditStatusFilter }}</span>
            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
          <DropdownMenuItem @select="auditStatusFilter = 'all'">{{ t('approvals.allStatus') }}</DropdownMenuItem>
          <DropdownMenuItem v-for="st in auditStatusOptions" :key="st" @select="auditStatusFilter = st">
            {{ st }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal bg-white', auditActionFilter === 'all' ? 'text-muted-foreground' : 'text-foreground']">
            <span class="truncate">{{ auditActionFilter === 'all' ? t('approvals.allAction') : auditActionFilter }}</span>
            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
          <DropdownMenuItem @select="auditActionFilter = 'all'">{{ t('approvals.allAction') }}</DropdownMenuItem>
          <DropdownMenuItem v-for="act in auditActionOptions" :key="act" @select="auditActionFilter = act">
            {{ act }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[240px] justify-between rounded-[14px] font-normal bg-white', auditTimeFilter === 'all' ? 'text-muted-foreground' : 'text-foreground']">
            <span class="truncate">
              {{ 
                auditTimeFilter === 'all' ? t('approvals.allTime') : 
                auditTimeFilter === '7-days' ? t('approvals.last7Days') : 
                t('approvals.last30Days') 
              }}
            </span>
            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
          <DropdownMenuItem @select="auditTimeFilter = 'all'">{{ t('approvals.allTime') }}</DropdownMenuItem>
          <DropdownMenuItem @select="auditTimeFilter = '7-days'">{{ t('approvals.last7Days') }}</DropdownMenuItem>
          <DropdownMenuItem @select="auditTimeFilter = '30-days'">{{ t('approvals.last30Days') }}</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
        <span>{{ $t('approvals.rowsPerPage') }}</span>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', auditRowsPerPage === 'all' ? 'text-muted-foreground' : 'text-foreground']">
              {{ auditRowsPerPage === 'all' ? $t('approvals.allRows') : auditRowsPerPage }}
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[140px] rounded-[14px] z-[110]" align="start" :side-offset="4">
            <DropdownMenuItem @select="auditRowsPerPage = 'all'">{{ $t('approvals.allRows') }}</DropdownMenuItem>
            <DropdownMenuItem @select="auditRowsPerPage = '10'">10</DropdownMenuItem>
            <DropdownMenuItem @select="auditRowsPerPage = '25'">25</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>

    <!-- Log table via DataTable -->
    <div class="pb-4">
      <DataTable 
        class="mb-6"
        cell-class="py-2.5"
        :columns="auditColumns" 
        :data="filteredLifecycles" 
        :filter-value="auditSearch"
        :page-size="computedAuditPageSize"
        :show-selection-count="false"
        :default-sorting="[{ id: 'waktu', desc: true }]"
      />
    </div>
  </div>
</template>
