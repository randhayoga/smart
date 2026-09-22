<script setup lang="ts">
/**
 * Sudah Approve Status Page (Manager)
 * Displays historical records of manager decisions regarding asset disposal, deactivation, and condition changes.
 */
import { ref, computed, watch, h, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  FileText,
  ArrowUpDown,
  ChevronDown,
  Eye
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
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

import Combobox from '@/Components/Combobox.vue';
import ApprovalStatusModal from './Modals/ApprovalStatusModal.vue';

// --- Data Types & Props ---
interface AuditTrail {
  waktu: string;
  status: string;
  action_type: string;
  aktor: string;
  durasi: string | number;
  catatan: string;
}

interface UnitDetails {
  id: number;
  number: string;
  status: string;
  condition: string;
  price: string;
  image_url: string | null;
  vehicle_registration: string | null;
  location: string;
  floor: string | null;
  room: string | null;
  lot_code: string;
  organizer: string;
  date_of_receipt: string;
  vendor: string;
  po_number: string;
  age?: number | null;
  barang_code: string;
  barang_spec: string;
  barang_unit: string;
  lifecycles: AuditTrail[];
}

interface ApprovalItem {
  id: number;
  unit_id: number;
  asset_code: string;
  category: string;
  subcategory: string;
  brand: string;
  nama: string;
  specification: string;
  proposed_condition?: string;
  previous_condition?: string;
  proposed_status: string;
  previous_status: string;
  status_label: string;
  decision: string;
  note: string | null;
  requested_by: string;
  requested_at: string;
  decided_at: string | null;
  approver_name: string | null;
  memo_url: string | null;
  lost_doc_url: string | null;
  bod_boc_approval_url?: string | null;
  unit_details: UnitDetails;
}

interface Props {
  user: any;
  approvals: ApprovalItem[];
}

const props = defineProps<Props>();

const { t } = useI18n();

// --- Filter & Search State ---
const searchQuery = ref('');
const subcategoryFilter = ref('');
const decisionFilter = ref('all');
const rowsPerPage = ref('50');

// Filter options
const subcategoryOptions = computed(() => {
  const subs = new Set<string>();
  props.approvals.forEach(app => {
    if (app.subcategory) subs.add(app.subcategory);
  });
  return Array.from(subs);
});

// Filtered data
const filteredApprovals = computed(() => {
  let list = [...props.approvals];

  if (subcategoryFilter.value) {
    list = list.filter(app => app.subcategory === subcategoryFilter.value);
  }

  if (decisionFilter.value !== 'all') {
    list = list.filter(app => app.decision === decisionFilter.value);
  }

  // default sort by decided_at desc (fall back to id desc)
  list.sort((a, b) => {
    const timeA = a.decided_at ? new Date(a.decided_at).getTime() : 0;
    const timeB = b.decided_at ? new Date(b.decided_at).getTime() : 0;
    if (timeB !== timeA) return timeB - timeA;
    return b.id - a.id;
  });

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'all') {
    return filteredApprovals.value.length || 50;
  }
  return parseInt(rowsPerPage.value, 10) || 50;
});

// Memo document opener
const openMemoFile = (path?: string | null) => {
  if (!path) {
    toast.error(t('approvals.memoFileNotFound'));
    return;
  }
  window.open('/media/' + path, '_blank');
};

// ─────────────────────────────────────────────
// Popup Detail Asset States
// ─────────────────────────────────────────────
const isDetailPopupOpen = ref(false);
const activeApproval = ref<ApprovalItem | null>(null);

const openDetailPopup = (approval: ApprovalItem) => {
  activeApproval.value = approval;
  isDetailPopupOpen.value = true;
};

const closeDetailPopup = () => {
  isDetailPopupOpen.value = false;
  setTimeout(() => {
    activeApproval.value = null;
  }, 200);
};

const columns = computed<ColumnDef<ApprovalItem>[]>(() => [
  {
    accessorKey: 'asset_code',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.assetCode'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono text-md truncate font-medium' }, row.getValue('asset_code')),
  },
  {
    accessorKey: 'subcategory',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.subcategory'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('subcategory')),
  },
  {
    accessorKey: 'brand',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.brand'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('brand')),
  },
  {
    accessorKey: 'nama',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.name'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate', title: row.getValue('nama') }, row.getValue('nama')),
  },
  {
    accessorKey: 'status_label',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.proposedCondition'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground font-medium' }, row.getValue('status_label')),
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
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-left' }, [
      h('span', { 
        class: 'inline-flex items-center px-2 py-0.5 rounded-md font-semibold ' + 
          (row.getValue('decision') === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')
      }, row.getValue('decision') === 'approved' ? t('approvals.approved') : t('approvals.rejected'))
    ]),
  },
  {
    accessorKey: 'approver_name',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.processedBy'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => {
      const item = row.original;
      return h('div', { class: 'text-left' }, [
        h('div', { class: 'font-semibold text-foreground' }, item.approver_name || '-'),
        h('div', { class: 'text-xs text-muted-foreground font-mono mt-0.5' }, item.decided_at || '-')
      ]);
    }
  },
  {
    id: 'actions',
    size: 100,
    enableGlobalFilter: false,
    header: () => h('div', { class: 'text-right font-semibold text-foreground' }, t('approvals.actions')),
    cell: ({ row }) => {
      const item = row.original;
      const buttons = [
        h(Button, {
          variant: 'table-warning',
          size: 'icon-sm',
          title: t('approvals.openMemo'),
          onClick: () => openMemoFile(item.memo_url)
        }, () => [
          h(FileText),
          h('span', { class: 'sr-only' }, t('approvals.openMemo'))
        ])
      ];
      if (item.proposed_condition === 'Hilang' || item.proposed_status === 'Hilang') {
        buttons.push(
          h(Button, {
            variant: 'table-warning',
            size: 'icon-sm',
            title: t('approvals.openLostDoc'),
            onClick: () => openMemoFile(item.lost_doc_url)
          }, () => [
            h(FileText),
            h('span', { class: 'sr-only' }, t('approvals.openLostDoc'))
          ])
        );
      }
      if (item.bod_boc_approval_url) {
        buttons.push(
          h(Button, {
            variant: 'table-warning',
            size: 'icon-sm',
            title: t('approvals.openBodDoc'),
            onClick: () => openMemoFile(item.bod_boc_approval_url)
          }, () => [
            h(FileText),
            h('span', { class: 'sr-only' }, t('approvals.openBodDoc'))
          ])
        );
      }
      buttons.push(
        h(Button, {
          variant: 'table-view',
          size: 'icon-sm',
          title: t('approvals.assetDetail'),
          onClick: () => openDetailPopup(item)
        }, () => [
          h(Eye),
          h('span', { class: 'sr-only' }, t('approvals.assetDetail'))
        ])
      );
      return h('div', { class: 'flex items-center justify-end gap-2' }, buttons);
    },
    enableSorting: false,
  }
]);

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isDetailPopupOpen.value) {
      closeDetailPopup();
    }
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <Head :title="t('approvals.statusHistoryTitle')" />

  <AppLayout :title="t('approvals.statusHistoryTitle')">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">{{ t('approvals.statusHistoryTitle') }}</h1>
    </div>

    <!-- ── Filter & Search Section ── -->
    <div class="space-y-4 mb-6">
      <!-- Filters Row -->
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1.5 flex-1 min-w-[300px] max-w-sm">
          <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ $t('common.filter') }}</label>
          <TableSearch 
            v-model="searchQuery"
            :placeholder="t('approvals.searchAssetPlaceholder')" 
            bg-class="bg-white"
          />
        </div>

        <!-- Subcategory Combobox (searchable/scrollable) -->
        <Combobox
          v-model="subcategoryFilter"
          :options="subcategoryOptions"
          :search-placeholder="t('approvals.searchSubcategory')"
          :default-label="t('approvals.allSubcategories')"
          width-class="w-[200px] bg-white"
        />

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', decisionFilter === 'all' ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">
                {{ decisionFilter === 'all' ? t('approvals.allDecisions') : (decisionFilter === 'approved' ? t('approvals.approved') : t('approvals.rejected')) }}
              </span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="decisionFilter = 'all'">{{ t('approvals.allDecisions') }}</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'approved'">{{ t('approvals.approved') }}</DropdownMenuItem>
            <DropdownMenuItem @select="decisionFilter = 'rejected'">{{ t('approvals.rejected') }}</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div class="flex items-center gap-3 text-sm text-muted-foreground ml-auto">
          <span>{{ $t('approvals.rowsPerPage') }}</span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal bg-white', rowsPerPage === 'all' ? 'text-muted-foreground' : 'text-foreground']">
                {{ rowsPerPage === 'all' ? $t('approvals.allRows') : rowsPerPage }}
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
        :data="filteredApprovals" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
        :show-selection-count="false"
      />
    </div>
    <!-- Detail Asset Modal -->
    <ApprovalStatusModal
      v-model:open="isDetailPopupOpen"
      :approval="activeApproval"
      mode="decided"
    />
  </AppLayout>
</template>
