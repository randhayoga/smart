<script setup lang="ts">
/**
 * Perlu Approve Status Page (Manager)
 * Manages pending asset disposal, deactivation, and status-change requests awaiting manager decision.
 * Includes single and bulk approval/rejection, supporting documentation viewer, and audit trail inspection.
 */
import { ref, computed, watch, h, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  FileText,
  ThumbsUp,
  Ban,
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

import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import ApprovalStatusModal from './Modals/ApprovalStatusModal.vue';

// --- Data Types & Helpers ---
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
const categoryFilter = ref('all');
const kondisiFilter = ref('all');
const rowsPerPage = ref('all');

const dataTableRef = ref<any>(null);

// Selection States
const selectedIds = computed(() => {
  if (!dataTableRef.value || !dataTableRef.value.table) return [];
  return dataTableRef.value.table.getFilteredRowModel().rows
    .filter((r: any) => r.getIsSelected())
    .map((r: any) => r.original.id);
});

// Filter options
const categoryOptions = computed(() => {
  const cats = new Set<string>();
  props.approvals.forEach(app => {
    if (app.category) cats.add(app.category);
  });
  return Array.from(cats);
});

const kondisiOptions = computed(() => {
  const stats = new Set<string>();
  props.approvals.forEach(app => {
    if (app.status_label) stats.add(app.status_label);
  });
  return Array.from(stats);
});

// Filtered data
const filteredApprovals = computed(() => {
  let list = [...props.approvals];

  if (categoryFilter.value !== 'all') {
    list = list.filter(app => app.category === categoryFilter.value);
  }

  if (kondisiFilter.value !== 'all') {
    list = list.filter(app => app.status_label === kondisiFilter.value);
  }

  // default sort by id desc
  list.sort((a, b) => b.id - a.id);

  return list;
});

const computedPageSize = computed(() => {
  if (rowsPerPage.value === 'all') {
    return filteredApprovals.value.length || 10;
  }
  return parseInt(rowsPerPage.value, 10);
});

watch([categoryFilter, kondisiFilter], () => {
  if (dataTableRef.value && dataTableRef.value.table) {
    dataTableRef.value.table.resetRowSelection();
  }
});

const columns = computed<ColumnDef<ApprovalItem>[]>(() => [
  {
    id: 'select',
    size: 40,
    header: ({ table }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
        onChange: table.getToggleAllPageRowsSelectedHandler(),
        'aria-label': 'Select all',
      })
    ]),
    cell: ({ row }) => h('div', { class: 'text-center no-print flex items-center justify-center' }, [
      h('input', {
        type: 'checkbox',
        class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
        checked: row.getIsSelected(),
        onChange: row.getToggleSelectedHandler(),
        'aria-label': 'Select row',
      })
    ]),
    enableSorting: false,
    enableHiding: false,
  },
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
    accessorKey: 'category',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.category'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('category')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('subcategory')),
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
    cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('brand')),
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
    accessorKey: 'specification',
    enableGlobalFilter: false,
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('approvals.specification'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
      ])
    },
    cell: ({ row }) => h('div', { class: 'text-foreground truncate', title: row.getValue('specification') }, row.getValue('specification')),
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
    cell: ({ row }) => h('div', { class: 'text-destructive font-semibold truncate' }, row.getValue('status_label')),
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

// ─────────────────────────────────────────────
// Confirmation Modal States
// ─────────────────────────────────────────────
const isConfirmModalOpen = ref(false);
const confirmActionType = ref<'approved' | 'rejected'>('approved');
const confirmNote = ref('');
const isBulkAction = ref(false);
const processing = ref(false);

const openConfirmModal = (type: 'approved' | 'rejected', bulk: boolean) => {
  confirmActionType.value = type;
  isBulkAction.value = bulk;
  confirmNote.value = '';
  isConfirmModalOpen.value = true;
};

const closeConfirmModal = () => {
  isConfirmModalOpen.value = false;
};

const confirmAssets = computed(() => {
  if (isBulkAction.value) {
    return props.approvals.filter(app => selectedIds.value.includes(app.id));
  } else if (activeApproval.value) {
    return [activeApproval.value];
  }
  return [];
});

const handleConfirmSubmit = () => {
  const idsToProcess = isBulkAction.value 
    ? selectedIds.value 
    : (activeApproval.value ? [activeApproval.value.id] : []);

  if (idsToProcess.length === 0) {
    toast.error(t('approvals.noAssetSelected'));
    return;
  }

  router.post(route('smart.approve-status.bulk-store'), {
    ids: idsToProcess,
    decision: confirmActionType.value,
    note: confirmNote.value,
  }, {
    onStart: () => { processing.value = true; },
    onFinish: () => { processing.value = false; },
    onSuccess: () => {
      closeConfirmModal();
      closeDetailPopup();
      if (dataTableRef.value && dataTableRef.value.table) {
        dataTableRef.value.table.resetRowSelection();
      }
      toast.success(confirmActionType.value === 'approved' 
        ? t('approvals.statusApprovedToast') 
        : t('approvals.statusRejectedToast')
      );
    },
    onError: (errs) => {
      toast.error(Object.values(errs).join(', '));
    }
  });
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isDetailPopupOpen.value) {
      closeDetailPopup();
    } else if (isConfirmModalOpen.value) {
      closeConfirmModal();
    }
  }
};

const page = usePage();

const checkSearchParam = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search');
  if (searchParam) {
    searchQuery.value = searchParam;
  }
};

onMounted(() => {
  checkSearchParam();
  document.addEventListener('keydown', closeOnEscape);
});

watch(() => page.url, (newUrl) => {
  if (newUrl) {
    const url = new URL(newUrl, window.location.origin);
    const searchParam = url.searchParams.get('search');
    if (searchParam) {
      searchQuery.value = searchParam;
    }
  }
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <Head :title="t('approvals.statusPendingTaskbarTitle')" />

  <AppLayout :title="t('approvals.statusPendingTaskbarTitle')">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">{{ t('approvals.statusPendingTitle') }}</h1>
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

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', categoryFilter === 'all' ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ categoryFilter === 'all' ? t('approvals.allCategories') : categoryFilter }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="categoryFilter = 'all'">{{ t('approvals.allCategories') }}</DropdownMenuItem>
            <DropdownMenuItem v-for="cat in categoryOptions" :key="cat" @select="categoryFilter = cat">
              {{ cat }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal bg-white', kondisiFilter === 'all' ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ kondisiFilter === 'all' ? t('approvals.allConditions') : kondisiFilter }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-[200px] rounded-[14px]" align="start" :side-offset="4">
            <DropdownMenuItem @select="kondisiFilter = 'all'">{{ t('approvals.allConditions') }}</DropdownMenuItem>
            <DropdownMenuItem v-for="st in kondisiOptions" :key="st" @select="kondisiFilter = st">
              {{ st }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <!-- ── Bulk Actions ── -->
      <div class="space-y-2 flex-1 min-w-0 pt-2">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ $t('approvals.selectedActions') }}</label>
        <div class="flex flex-wrap items-center gap-2">
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openConfirmModal('approved', true)"
            variant="success"
          >
            <ThumbsUp class="w-4 h-4" />
            <span class="hidden sm:inline">{{ $t('approvals.approveSelected') }}</span>
          </Button>
          <Button 
            :disabled="selectedIds.length < 1"
            @click="openConfirmModal('rejected', true)"
            variant="destructive"
          >
            <Ban class="w-4 h-4" />
            <span class="hidden sm:inline">{{ $t('approvals.rejectSelected') }}</span>
          </Button>
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
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Table Display ── -->
    <div class="pb-4">
      <DataTable 
        ref="dataTableRef"
        :columns="columns" 
        :data="filteredApprovals" 
        :filter-value="searchQuery"
        :page-size="computedPageSize"
      />
    </div>

    <!-- Detail Asset Modal -->
    <ApprovalStatusModal
      v-model:open="isDetailPopupOpen"
      :approval="activeApproval"
      mode="pending"
      @approve="openConfirmModal('approved', false)"
      @reject="openConfirmModal('rejected', false)"
    />

    <!-- ============================================================
         Confirmation Modal (Approve / Reject Action Dialog)
         ============================================================ -->
    <DeleteConfirmationModal
      :is-open="isConfirmModalOpen"
      :item-count="confirmAssets.length"
      :item-name="t('approvals.assetStatusChange')"
      :item-data="confirmAssets"
      :action-type="confirmActionType"
      :processing="processing"
      @close="closeConfirmModal"
      @confirm="handleConfirmSubmit"
    >
      <!-- Catatan text area (passed to slot) -->
      <div class="space-y-1.5 text-left w-full max-w-[90%] mx-auto">
        <label class="text-base font-semibold text-foreground block">{{ $t('approvals.notesLabel') }}</label>
        <textarea
          v-model="confirmNote"
          :placeholder="t('approvals.notesPlaceholder')"
          rows="3"
          class="w-full text-base border border-input rounded-[14px] bg-background text-foreground p-3 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary shadow-sm"
        ></textarea>
      </div>
    </DeleteConfirmationModal>

  </AppLayout>
</template>
