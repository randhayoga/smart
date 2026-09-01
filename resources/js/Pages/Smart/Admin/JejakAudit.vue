<script setup lang="ts">
/**
 * Admin Audit Trail Page component for monitoring asset lifecycle transitions, actor logs, and duration analytics.
 */
import { ref, computed, h } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowUpDown, ChevronDown } from 'lucide-vue-next';
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
import StatusBadge from '@/Components/StatusBadge.vue';

interface AuditTrail {
  id: number;
  kode_aset: string;
  nama_aset: string;
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
    if (trimmed.endsWith('jam')) {
      const hours = parseFloat(trimmed.replace('jam', '').trim());
      if (isNaN(hours)) return val;
      totalDays = Math.floor(hours / 24);
    } else if (trimmed.includes('hari') || trimmed.includes('bulan') || trimmed.includes('tahun')) {
      return trimmed;
    } else {
      const parsed = parseFloat(trimmed);
      if (isNaN(parsed)) return val;
      totalDays = Math.floor(parsed);
    }
  }

  if (totalDays < 30) {
    return `${totalDays} hari`;
  }

  const years = Math.floor(totalDays / 365);
  const remDaysAfterYears = totalDays % 365;
  const months = Math.floor(remDaysAfterYears / 30);
  const days = remDaysAfterYears % 30;

  const parts: string[] = [];
  if (years > 0) parts.push(`${years} tahun`);
  if (months > 0) parts.push(`${months} bulan`);
  if (days > 0 || parts.length === 0) parts.push(`${days} hari`);

  return parts.join(' ');
};

const auditSearch = ref('');
const auditStatusFilter = ref('semua');
const auditActionFilter = ref('semua');
const auditTimeFilter = ref('semua');
const auditRowsPerPage = ref('Semua baris');

const isAnyFilterActive = computed(() => {
  return auditSearch.value !== '' || 
    auditStatusFilter.value !== 'semua' || 
    auditActionFilter.value !== 'semua' || 
    auditTimeFilter.value !== 'semua';
});

const resetFilters = () => {
  auditSearch.value = '';
  auditStatusFilter.value = 'semua';
  auditActionFilter.value = 'semua';
  auditTimeFilter.value = 'semua';
};

const computedAuditPageSize = computed(() => {
  if (auditRowsPerPage.value === 'Semua baris') {
    return filteredLifecycles.value.length || 10;
  }
  return parseInt(auditRowsPerPage.value, 10);
});

const filteredLifecycles = computed(() => {
  let logs = [...props.lifecycles];

  if (auditSearch.value.trim() !== '') {
    const q = auditSearch.value.toLowerCase();
    logs = logs.filter(l => 
      (l.kode_aset && l.kode_aset.toLowerCase().includes(q)) ||
      (l.nama_aset && l.nama_aset.toLowerCase().includes(q)) ||
      (l.aktor && l.aktor.toLowerCase().includes(q)) || 
      (l.catatan && l.catatan.toLowerCase().includes(q)) || 
      (l.status && l.status.toLowerCase().includes(q)) ||
      (l.action_type && l.action_type.toLowerCase().includes(q))
    );
  }

  if (auditStatusFilter.value !== 'semua') {
    logs = logs.filter(l => l.status === auditStatusFilter.value);
  }

  if (auditActionFilter.value !== 'semua') {
    logs = logs.filter(l => l.action_type === auditActionFilter.value);
  }

  if (auditTimeFilter.value !== 'semua') {
    const now = new Date();
    logs = logs.filter(l => {
      const logTime = parseDateTime(l.waktu);
      if (logTime === 0) return false;
      const diffTime = Math.abs(now.getTime() - logTime);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if (auditTimeFilter.value === '7-hari') return diffDays <= 7;
      if (auditTimeFilter.value === '30-hari') return diffDays <= 30;
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

const auditColumns: ColumnDef<AuditTrail>[] = [
  {
    id: 'select',
    size: 40,
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
    accessorKey: 'kode_aset',
    size: 200,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Kode Aset',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'space-y-0.5 max-w-[200px]' }, [
        h('div', { class: 'font-mono text-sm font-medium text-foreground truncate' }, item.kode_aset),
        item.nama_aset && item.nama_aset !== '-' ? h('div', { class: 'text-xs text-muted-foreground truncate', title: item.nama_aset }, item.nama_aset) : null
      ]);
    }
  },
  {
    accessorKey: 'waktu',
    size: 160,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Waktu',
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
        'Status',
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
        'Aksi',
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
        'Aktor',
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
        'Durasi',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-center text-foreground truncate' }, formatDurasi(row.getValue('durasi'))),
  },
  {
    accessorKey: 'catatan',
    header: () => h('div', { class: 'font-semibold text-foreground justify-start' }, 'Catatan'),
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
];
</script>

<template>
  <AppLayout title="Jejak Audit">
    <div class="space-y-4">
      <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="py-3 no-print">
          <h2 class="text-lg font-bold text-foreground">Jejak Audit</h2>

          <!-- Filters & Actions -->
          <div class="mt-4 flex flex-col space-y-4">
            <div class="flex flex-wrap items-end gap-3">
              <!-- Search -->
              <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
                <TableSearch 
                  v-model="auditSearch"
                  placeholder="Cari Kode Aset, Nama..." 
                />
              </div>

              <!-- Status Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal', (auditStatusFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ auditStatusFilter === 'semua' ? 'Semua Status' : auditStatusFilter }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="auditStatusFilter = 'semua'">Semua Status</DropdownMenuItem>
                  <DropdownMenuItem v-for="st in auditStatusOptions" :key="st" @select="auditStatusFilter = st">
                    {{ st }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Aksi Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal', (auditActionFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">{{ auditActionFilter === 'semua' ? 'Semua Aksi' : auditActionFilter }}</span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="auditActionFilter = 'semua'">Semua Aksi</DropdownMenuItem>
                  <DropdownMenuItem v-for="act in auditActionOptions" :key="act" @select="auditActionFilter = act">
                    {{ act }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Kurun Waktu Filter Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" :class="['w-[220px] justify-between rounded-[14px] font-normal', (auditTimeFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
                    <span class="truncate">
                      {{ 
                        auditTimeFilter === 'semua' ? 'Semua kurun waktu' : 
                        auditTimeFilter === '7-hari' ? '7 hari terakhir' : 
                        '30 hari terakhir' 
                      }}
                    </span>
                    <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[220px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                  <DropdownMenuItem @select="auditTimeFilter = 'semua'">Semua Kurun Waktu</DropdownMenuItem>
                  <DropdownMenuItem @select="auditTimeFilter = '7-hari'">7 hari terakhir</DropdownMenuItem>
                  <DropdownMenuItem @select="auditTimeFilter = '30-hari'">30 hari terakhir</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <!-- Reset Filter Button -->
              <ResetFilterButton v-if="isAnyFilterActive" @click="resetFilters" />

              <!-- Rows Per Page -->
              <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
                <span>Baris per halaman</span>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (auditRowsPerPage === 'Semua baris' || !auditRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                      {{ auditRowsPerPage }}
                      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-[140px] rounded-[14px] z-[110]" align="start" :side-offset="4">
                    <DropdownMenuItem @select="auditRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
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
              :data="filteredLifecycles" 
              :filter-value="auditSearch"
              :page-size="computedAuditPageSize"
              :show-selection-count="true"
              :default-sorting="[{ id: 'waktu', desc: true }]"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
