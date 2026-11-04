import { computed, h, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ArrowUpDown, Eye, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

export const parseIndonesianDateTime = (str: string): number => {
  if (!str || str === '-') return 0;
  const parts = str.trim().split(/\s+/);
  const dateParts = parts[0].split('/').map(Number);
  if (dateParts.length !== 3) return 0;
  const [d, m, y] = dateParts;
  let hour = 0;
  let minute = 0;
  if (parts[1]) {
    const timeParts = parts[1].split(':').map(Number);
    hour = timeParts[0] || 0;
    minute = timeParts[1] || 0;
  }
  return new Date(y, m - 1, d, hour, minute).getTime();
};

export function useModelCatalogTable(options: {
  mode: 'hierarchical' | 'consumable';
  lots: () => any[];
  dataTableRef: Ref<any>;
  t: (key: string, params?: any) => string;
  canManage: () => boolean;
  onViewDetail: (item: any) => void;
  onDeleteRequest: (item: any) => void;
  selectedBarang?: Ref<any | null>;
  activeBarang?: ComputedRef<any | null>;
}) {
  const { mode, lots, dataTableRef, t, canManage, onViewDetail, onDeleteRequest, selectedBarang, activeBarang } = options;

  // Precomputed O(1) initial stock sum by barang_id for consumables
  const initialStockByBarangId = computed<Map<string, number>>(() => {
    const map = new Map<string, number>();
    const allLots = lots() || [];
    for (let i = 0; i < allLots.length; i++) {
      const l = allLots[i];
      const key = String(l.barang_id);
      map.set(key, (map.get(key) || 0) + Number(l.initial_quantity ?? 0));
    }
    return map;
  });

  const columns = computed<ColumnDef<any>[]>(() => {
    const isConsumableMode = mode === 'consumable';
    return [
      {
        id: 'select',
        size: 40,
        header: ({ table }) =>
          h('div', { class: 'text-center no-print flex items-center justify-center' }, [
            h('input', {
              type: 'checkbox',
              class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
              checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
              onChange: table.getToggleAllPageRowsSelectedHandler(),
              'aria-label': 'Select all',
            }),
          ]),
        cell: ({ row }) =>
          h('div', { class: 'text-center no-print flex items-center justify-center' }, [
            h('input', {
              type: 'checkbox',
              class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
              checked: row.getIsSelected(),
              onChange: row.getToggleSelectedHandler(),
              'aria-label': 'Select row',
            }),
          ]),
        enableSorting: false,
        enableHiding: false,
      },
      {
        accessorKey: 'name',
        size: 225,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.name'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) =>
          h(
            'div',
            {
              class: isConsumableMode ? 'text-foreground truncate font-medium' : 'text-foreground truncate',
              title: row.getValue('name'),
            },
            row.getValue('name')
          ),
      },
      {
        accessorKey: 'specification',
        size: 225,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.specification'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => {
          const spec = row.getValue('specification') as string | null | undefined;
          return h(
            'div',
            {
              class: `${spec ? 'text-foreground' : 'text-muted-foreground'} truncate`,
              title: spec || '-',
            },
            spec || '-'
          );
        },
      },
      {
        accessorKey: 'brand',
        size: 225,
        enableGlobalFilter: isConsumableMode ? undefined : false,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.brand'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('brand')),
        ...(isConsumableMode
          ? {}
          : {
              filterFn: (row: any, id: string, value: any) => {
                if (!value) return true;
                return row.getValue(id) === value;
              },
            }),
      },
      {
        accessorKey: 'category',
        enableGlobalFilter: isConsumableMode ? undefined : false,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.category'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('category')),
        ...(isConsumableMode
          ? {}
          : {
              filterFn: (row: any, id: string, value: any) => {
                if (!value) return true;
                return row.getValue(id) === value;
              },
            }),
      },
      {
        accessorKey: 'subcategory',
        enableGlobalFilter: isConsumableMode ? undefined : false,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.subcategory'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'text-foreground truncate' }, row.getValue('subcategory')),
        ...(isConsumableMode
          ? {}
          : {
              filterFn: (row: any, id: string, value: any) => {
                if (!value) return true;
                return row.getValue(id) === value;
              },
            }),
      },
      {
        accessorKey: 'amount',
        size: isConsumableMode ? 110 : 95,
        enableGlobalFilter: isConsumableMode ? undefined : false,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t(isConsumableMode ? 'inventory.totalStock' : 'inventory.total'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => {
          if (!isConsumableMode) {
            return h('div', { class: 'font-medium text-foreground' }, row.getValue('amount'));
          }
          const item = row.original;
          const remainingStock = Number(item.amount ?? 0);
          const totalStock =
            initialStockByBarangId.value.get(String(item.id)) ?? Number(item.initial_amount ?? 0);
          const threshold =
            item.min_stock_threshold !== null && item.min_stock_threshold !== undefined
              ? Number(item.min_stock_threshold)
              : null;

          let textColorClass = 'text-foreground';
          if (threshold !== null && remainingStock <= threshold) {
            textColorClass = 'text-rose-600 font-semibold';
          }
          return h('div', { class: `font-medium ${textColorClass}` }, `${remainingStock}/${totalStock}`);
        },
      },
      {
        accessorKey: 'lastUpdate',
        size: 181,
        enableGlobalFilter: isConsumableMode ? undefined : false,
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.lastUpdate'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('lastUpdate')),
        sortingFn: (rowA, rowB, columnId) =>
          parseIndonesianDateTime(rowA.getValue(columnId)) - parseIndonesianDateTime(rowB.getValue(columnId)),
      },
      {
        id: 'actions',
        size: 84,
        enableGlobalFilter: isConsumableMode ? undefined : false,
        header: () =>
          h(
            'div',
            { class: isConsumableMode ? 'text-center font-semibold text-foreground no-print' : 'no-print' },
            t('common.actions')
          ),
        cell: ({ row }) => {
          const buttons = [
            h(
              Button,
              {
                variant: 'table-view',
                size: 'icon-sm',
                title: t('inventory.viewDetails'),
                onClick: () => onViewDetail(row.original),
              },
              () => [h(Eye), h('span', { class: 'sr-only' }, t('inventory.viewDetails'))]
            ),
          ];

          if (!isConsumableMode || canManage()) {
            buttons.push(
              h(
                Button,
                {
                  variant: 'table-destructive',
                  size: 'icon-sm',
                  title: t(isConsumableMode ? 'common.delete' : 'inventory.delete'),
                  onClick: () => onDeleteRequest(row.original),
                },
                () => [h(Trash2), h('span', { class: 'sr-only' }, t(isConsumableMode ? 'common.delete' : 'inventory.delete'))]
              )
            );
          }

          return h('div', { class: 'flex items-center justify-end gap-2 no-print' }, buttons);
        },
      },
    ];
  });

  // Shared modal & flash state
  const isCreateModalOpen = ref(false);
  const openCreateModal = () => {
    isCreateModalOpen.value = true;
  };

  const isBulkEditModalOpen = ref(false);
  const selectedItemsForEdit = ref<any[]>([]);

  const openBulkEditModal = () => {
    if (!dataTableRef.value) return;
    const selectedRows = dataTableRef.value.table
      .getFilteredRowModel()
      .rows.filter((r: any) => r.getIsSelected())
      .map((r: any) => r.original);

    if (selectedRows.length === 0) return;
    selectedItemsForEdit.value = selectedRows;
    isBulkEditModalOpen.value = true;
  };

  const openEditModal = () => {
    if (!activeBarang?.value) return;
    selectedItemsForEdit.value = [activeBarang.value];
    isBulkEditModalOpen.value = true;
  };

  const handleEditSuccess = () => {
    selectedItemsForEdit.value = [];
    if (dataTableRef.value) {
      dataTableRef.value.table.resetRowSelection();
    }
  };

  const isDeleteModalOpen = ref(false);
  const itemsToDelete = ref<any[]>([]);
  const processing = ref(false);

  const openDeleteModal = (items?: any | any[]) => {
    if (!items || items instanceof Event) {
      items = activeBarang?.value ? [activeBarang.value] : [];
    }
    itemsToDelete.value = Array.isArray(items) ? items : [items];
    isDeleteModalOpen.value = true;
  };

  const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    itemsToDelete.value = [];
  };

  const handleConfirmDelete = (redirectIfSelectedDeletedUrl?: string) => {
    if (processing.value || itemsToDelete.value.length === 0) return;
    processing.value = true;

    const ids = itemsToDelete.value.map((item) => item.id);
    const isSelectedDeleted = selectedBarang?.value && ids.includes(selectedBarang.value.id);

    const onSuccess = () => {
      if (dataTableRef.value) {
        dataTableRef.value.table.resetRowSelection();
      }
      closeDeleteModal();
      if (isSelectedDeleted && redirectIfSelectedDeletedUrl) {
        router.get(redirectIfSelectedDeletedUrl);
      }
    };

    if (ids.length === 1) {
      router.delete(`/smart/stock-management/models/${ids[0]}`, {
        onStart: () => {
          processing.value = true;
        },
        onFinish: () => {
          processing.value = false;
        },
        onSuccess,
      });
    } else {
      router.delete('/smart/stock-management/models/bulk', {
        data: { ids },
        onStart: () => {
          processing.value = true;
        },
        onFinish: () => {
          processing.value = false;
        },
        onSuccess,
      });
    }
  };

  const page = usePage();
  const isErrorModalOpen = ref(false);
  const errorModalMessage = ref('');

  const closeErrorModal = () => {
    isErrorModalOpen.value = false;
    if ((page.props as any).flash) {
      (page.props as any).flash.error = null;
    }
  };

  const flashSuccess = computed(() => (page.props as any).flash?.success);
  const flashNewStockCreated = computed(() => (page.props as any).flash?.new_stock_created);
  const flashError = computed(() => (page.props as any).flash?.error);

  watch(
    flashSuccess,
    (newVal) => {
      if (newVal && (page.props as any).flash?.success) {
        const newStock = flashNewStockCreated.value;
        if (newStock && newStock.target_url) {
          toast.success(
            h('span', [
              t('inventory.newStockSuccessPrefix'),
              h(
                'a',
                {
                  href: newStock.target_url,
                  class: 'underline font-semibold text-primary hover:text-primary/80 transition-colors cursor-pointer',
                  onClick: (e: MouseEvent) => {
                    e.preventDefault();
                    router.visit(newStock.target_url);
                  },
                },
                t('inventory.seeHere')
              ),
            ])
          );
        } else {
          toast.success(newVal);
        }

        if ((page.props as any).flash) {
          (page.props as any).flash.success = null;
          (page.props as any).flash.new_stock_created = null;
        }
      }
    },
    { immediate: true }
  );

  watch(
    flashError,
    (newVal) => {
      if (newVal) {
        errorModalMessage.value = newVal;
        isErrorModalOpen.value = true;
      }
    },
    { immediate: true }
  );

  const closeOnEscape = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      if (isCreateModalOpen.value) {
        isCreateModalOpen.value = false;
      } else if (isBulkEditModalOpen.value) {
        isBulkEditModalOpen.value = false;
      } else if (isDeleteModalOpen.value) {
        closeDeleteModal();
      } else if (isErrorModalOpen.value) {
        closeErrorModal();
      }
    }
  };

  return {
    columns,
    isCreateModalOpen,
    openCreateModal,
    isBulkEditModalOpen,
    selectedItemsForEdit,
    openBulkEditModal,
    openEditModal,
    handleEditSuccess,
    isDeleteModalOpen,
    itemsToDelete,
    processing,
    openDeleteModal,
    closeDeleteModal,
    handleConfirmDelete,
    isErrorModalOpen,
    errorModalMessage,
    closeErrorModal,
    closeOnEscape,
  };
}
