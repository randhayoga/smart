<script setup lang="ts">
import { ref, computed, h } from 'vue';
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

const parseDateTime = (val: string) => {
  if (!val || val === '-') return 0;
  const [datePart, timePart] = val.split(' ');
  const [day, month, year] = datePart.split('-');
  const t = timePart || '00:00:00';
  const formattedTime = t.split(':').length === 2 ? `${t}:00` : t;
  return new Date(`${year}-${month}-${day}T${formattedTime}`).getTime();
};

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

const auditSearch = ref('');
const auditStatusFilter = ref('semua');
const auditActionFilter = ref('semua');
const auditTimeFilter = ref('semua');
const auditRowsPerPage = ref('Semua baris');

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
      l.aktor.toLowerCase().includes(q) || 
      (l.catatan && l.catatan.toLowerCase().includes(q)) || 
      l.status.toLowerCase().includes(q)
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
    size: 120,
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
    cell: ({ row }) => h('div', { class: 'text-foreground font-semibold truncate' }, row.getValue('status')),
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
        'Durasi (hari)',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-center text-foreground truncate' }, row.getValue('durasi')),
  },
  {
    accessorKey: 'catatan',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Catatan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
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
  <div class="space-y-4">
    <!-- Internal Search & Local Filters -->
    <div class="flex flex-wrap items-end gap-4">
      <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
        <TableSearch 
          v-model="auditSearch"
          placeholder="Cari Jejak Audit..." 
          bg-class="bg-white"
        />
      </div>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal bg-white', (auditStatusFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
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

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[180px] justify-between rounded-[14px] font-normal bg-white', (auditActionFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
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

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[240px] justify-between rounded-[14px] font-normal bg-white', (auditTimeFilter === 'semua') ? 'text-muted-foreground' : 'text-foreground']">
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
        <DropdownMenuContent class="w-[180px] rounded-[14px] z-[110]" align="start" :side-offset="4">
          <DropdownMenuItem @select="auditTimeFilter = 'semua'">Semua Kurun Waktu</DropdownMenuItem>
          <DropdownMenuItem @select="auditTimeFilter = '7-hari'">7 hari terakhir</DropdownMenuItem>
          <DropdownMenuItem @select="auditTimeFilter = '30-hari'">30 hari terakhir</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
        <span>Baris per halaman</span>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (auditRowsPerPage === 'Semua baris' || !auditRowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
              {{ auditRowsPerPage }}
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[140px] rounded-[14px] z-[110]" align="start" :side-offset="4">
            <DropdownMenuItem @select="auditRowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
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
