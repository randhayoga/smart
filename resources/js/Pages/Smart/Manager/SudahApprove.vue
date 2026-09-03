<script setup lang="ts">
/**
 * Sudah Diproses page component showing archived requisition and borrow requests that have been processed by the manager.
 */
import { ref, computed, watch, h } from 'vue';
import { Head } from '@inertiajs/vue3';
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

interface RequestHistory {
  id: number;
  number: string;
  type: 'permintaan' | 'peminjaman';
  requester: string;
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  status: string;
  raw_status: string;
  created_at: string;
  approval_by: string | null;
  approval_at: string | null;
}

interface Props {
  user: any;
  requests: RequestHistory[];
}

const props = defineProps<Props>();

const requests = ref<RequestHistory[]>([...props.requests]);

watch(() => props.requests, (newVal) => {
  requests.value = [...newVal];
}, { deep: true });

// ─────────────────────────────────────────────
// States & Filters
// ─────────────────────────────────────────────
const searchQuery = ref('');
const typeFilter = ref('Semua tipe');
const decisionFilter = ref('Semua keputusan');
const rowsPerPage = ref('Semua baris');

const getStatusBadgeClass = (rawStatus: string) => {
  switch (rawStatus) {
    case 'approve':
      return 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20';
    case 'confirm':
    case 'handover':
      return 'bg-blue-500/10 text-blue-600 border border-blue-500/20';
    case 'borrow':
    case 'return':
      return 'bg-indigo-500/10 text-indigo-600 border border-indigo-500/20';
    case 'success':
      return 'bg-green-500/10 text-green-600 border border-green-500/20';
    case 'reject':
      return 'bg-destructive/10 text-destructive border border-destructive/20';
    case 'cancel':
      return 'bg-muted text-muted-foreground border border-border';
    case 'partial':
      return 'bg-amber-500/10 text-amber-600 border border-amber-500/20';
    default:
      return 'bg-muted text-foreground border border-border';
  }
};

// Filtered data
const filteredRequests = computed(() => {
  let list = [...requests.value];

  if (typeFilter.value !== 'Semua tipe') {
    const type = typeFilter.value === 'Peminjaman' ? 'peminjaman' : 'permintaan';
    list = list.filter(req => req.type === type);
  }

  if (decisionFilter.value !== 'Semua keputusan') {
    list = list.filter(req => {
      if (decisionFilter.value === 'Disetujui') {
        return req.raw_status !== 'reject';
      } else if (decisionFilter.value === 'Ditolak') {
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
  if (rowsPerPage.value === 'Semua baris') {
    return filteredRequests.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

const columns: ColumnDef<RequestHistory>[] = [
  {
    accessorKey: 'number',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Nomor',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-sm truncate font-medium' }, row.getValue('number')),
  },
  {
    accessorKey: 'type',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Tipe',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => h('div', { class: 'text-foreground capitalize' }, row.getValue('type')),
  },
  {
    accessorKey: 'requester',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Pemohon',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => h('div', { class: 'text-foreground font-semibold' }, row.getValue('requester')),
  },
  {
    accessorKey: 'pemanfaatan',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Pemanfaatan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      const isCorporate = item.pemanfaatan === 'corporate';
      return h('div', { class: 'text-foreground' }, [
        h('span', { class: 'font-semibold' }, isCorporate ? 'Corporate ' : 'Project '),
        h('span', { class: 'text-muted-foreground font-normal' }, isCorporate ? `(${item.pemanfaatanDetail})` : item.pemanfaatanDetail)
      ]);
    }
  },
  {
    accessorKey: 'decision',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Keputusan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      const isApproved = item.raw_status !== 'reject';
      return h('div', { class: 'text-left' }, [
        h('span', { 
          class: 'inline-flex items-center px-2 py-0.5 rounded-md font-semibold ' + 
            (isApproved ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')
        }, isApproved ? 'Disetujui' : 'Ditolak')
      ]);
    }
  },
  {
    accessorKey: 'approval_at',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Tanggal Diputuskan',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'text-left' }, [
        h('div', { class: 'font-medium text-foreground' }, item.approval_at || '-'),
        item.approval_by ? h('div', { class: 'text-[11px] text-muted-foreground mt-0.5' }, `Oleh: ${item.approval_by}`) : null
      ]);
    }
  },
  {
    accessorKey: 'status',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        'Status Terakhir',
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ]);
    },
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'text-left' }, [
        h('span', { 
          class: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' + getStatusBadgeClass(item.raw_status) 
        }, item.status)
      ]);
    }
  }
];
</script>

<template>
  <Head title="Approval" />

  <AppLayout title="Approval">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Approval: Sudah Diproses</h1>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <!-- Filters Row -->
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">Filter</label>
          <TableSearch 
            v-model="searchQuery" 
            placeholder="Cari Permintaan..." 
            bg-class="bg-white"
          />
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!typeFilter || typeFilter === 'Semua tipe') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ typeFilter || 'Semua tipe' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="typeFilter = 'Semua tipe'">Semua tipe</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'Peminjaman'">Peminjaman</DropdownMenuItem>
            <DropdownMenuItem @select="typeFilter = 'Permintaan'">Permintaan</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', (!decisionFilter || decisionFilter === 'Semua keputusan') ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ decisionFilter || 'Semua keputusan' }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="decisionFilter = 'Semua keputusan'">Semua keputusan</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'Disetujui'">Disetujui</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'Ditolak'">Ditolak</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>Baris per halaman</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPage }}
                <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[140px] rounded-[14px]" align="start" :side-offset="4">
              <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">Semua baris</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
              <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
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
