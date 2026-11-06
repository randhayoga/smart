import { computed, h, type Ref } from 'vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Eye, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatDate, formatLocation, isLocationActive } from '@/lib/utils';

export function useLotTableColumns(options: {
  barang: () => any;
  locale: Ref<string>;
  t: (key: string, params?: any) => string;
  canManage: () => boolean;
  openDetailLOTConsumables: (lot: any) => void;
  openDeleteLotModal: (lots: any | any[]) => void;
  itemsToDelete: Ref<any[]>;
  locations?: () => any[];
}) {
  const { barang, locale, t, canManage, openDetailLOTConsumables, openDeleteLotModal, itemsToDelete, locations } = options;

  const formatRupiah = (val: number | string | null | undefined) => {
    if (val === null || val === undefined) return '-';
    const num = typeof val === 'string' ? parseFloat(val) : val;
    if (isNaN(num)) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
    const formatted = Math.floor(num).toLocaleString(loc);
    return `Rp${formatted}`;
  };

  const columns = computed<ColumnDef<any>[]>(() => {
    const b = barang();
    const cols: ColumnDef<any>[] = [
      {
        id: 'select',
        size: 40,
        meta: { fit: true },
        header: ({ table }) =>
          h('div', { class: 'text-center no-print flex items-center justify-center' }, [
            h('input', {
              type: 'checkbox',
              class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
              checked: table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate'),
              onChange: table.getToggleAllPageRowsSelectedHandler(),
            }),
          ]),
        cell: ({ row }) =>
          h('div', { class: 'text-center no-print flex items-center justify-center' }, [
            h('input', {
              type: 'checkbox',
              class: 'rounded-full border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer',
              checked: row.getIsSelected(),
              onChange: row.getToggleSelectedHandler(),
            }),
          ]),
      },
      {
        accessorKey: 'number',
        meta: { fit: true },
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.lotCode'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'text-muted-foreground font-mono font-medium' }, row.original.number),
      },
      {
        accessorKey: 'assetCount',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.stockCount'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) =>
          h(
            'div',
            { class: 'pl-0 text-muted-foreground' },
            b.is_consumable
              ? `${row.original.current_quantity ?? 0}/${row.original.initial_quantity ?? 0}`
              : row.original.assetCount
          ),
      },
      ...(!b.is_consumable
        ? [
            {
              id: 'entry_type',
              accessorFn: (row: any) => row.entry_type || 'Procurement',
              header: ({ column }: any) =>
                h(
                  Button,
                  {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                    class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
                  },
                  () => [
                    t('inventory.entryType'),
                    h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
                  ]
                ),
              cell: ({ row }: any) => {
                const type = row.original.entry_type;
                const isGrant = type === 'Hibah' || type === 'Grant';
                return h('div', { class: 'pl-0 font-medium' }, isGrant ? t('inventory.grant') : t('inventory.procurement'));
              },
            },
          ]
        : []),
      {
        id: 'po_number',
        accessorFn: (row) => (row.entry_type === 'Hibah' || row.entry_type === 'Grant')
          ? (row.memo_number || row.po_number || '-')
          : (row.po_number || row.memo_number || '-'),
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.number'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => {
          const val = (row.original.entry_type === 'Hibah' || row.original.entry_type === 'Grant')
            ? (row.original.memo_number || row.original.po_number || '-')
            : (row.original.po_number || row.original.memo_number || '-');
          return h('div', { class: 'pl-0 font-medium' }, val);
        },
      },
      {
        accessorKey: 'date_of_receipt',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.registrationDate'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'pl-0 text-muted-foreground' }, formatDate(row.original.date_of_receipt)),
        sortingFn: (rowA, rowB) => {
          const valA = String(rowA.original.date_of_receipt || '');
          const valB = String(rowB.original.date_of_receipt || '');
          return valA.localeCompare(valB);
        },
      },
    ];

    if (!b.is_consumable) {
      // Non-consumables: organizer column is kept, unitPrice and location are removed
      cols.push({
        accessorKey: 'organizer',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.organizer'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'pl-0' }, row.original.organizer || '-'),
      });
    } else {
      // Consumables: location is kept, organizer and unitPrice are removed
      cols.push({
        accessorKey: 'location',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.location'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => {
          const locs = locations ? locations() : [];
          const isActive = isLocationActive(row.original.location_id || row.original.location, locs);
          return h(
            'div',
            {
              class: [
                'pl-0 text-sm',
                !isActive ? 'text-destructive font-medium' : 'text-muted-foreground',
              ],
            },
            formatLocation(row.original.location, row.original.floor, row.original.room)
          );
        },
      });
    }

    cols.push({
      id: 'actions',
      size: 80,
      meta: { fit: true },
      header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('common.actions')),
      cell: ({ row }) => {
        const buttons = [];
        if (b.is_consumable) {
          buttons.push(
            h(
              Button,
              {
                variant: 'table-view',
                size: 'icon-sm',
                title: t('inventory.viewDetails'),
                onClick: () => openDetailLOTConsumables(row.original),
              },
              () => [h(Eye), h('span', { class: 'sr-only' }, t('inventory.viewDetails'))]
            )
          );
        } else {
          buttons.push(
            h(
              Button,
              {
                variant: 'table-view',
                size: 'icon-sm',
                title: t('inventory.viewDetails'),
                onClick: () => {
                  const lotParam = row.original.number
                    ? String(row.original.number).replace(/[^a-zA-Z0-9]/g, '')
                    : row.original.id;
                  router.get(`/smart/stock-management/lots/${lotParam}`);
                },
              },
              () => [h(Eye), h('span', { class: 'sr-only' }, t('inventory.viewDetails'))]
            )
          );
        }
        if (canManage()) {
          buttons.push(
            h(
              Button,
              {
                variant: 'table-destructive',
                size: 'icon-sm',
                title: t('common.delete'),
                onClick: () => openDeleteLotModal(row.original),
              },
              () => [h(Trash2), h('span', { class: 'sr-only' }, t('common.delete'))]
            )
          );
        }
        return h('div', { class: 'flex items-center justify-center gap-2 no-print' }, buttons);
      },
    });

    return cols;
  });

  const deleteFields = computed(() => {
    if (itemsToDelete.value.length === 1) {
      const data = itemsToDelete.value[0];
      const b = barang();

      const isConsumable = b.is_consumable;
      const isComp = b.category_code === 'COMP' || b.category === 'Computer' || b.category === 'COMP';
      const availableStock = isConsumable ? (data.current_quantity ?? 0) : (data.availableAssetCount ?? 0);
      const initialStock = isConsumable ? (data.initial_quantity ?? 0) : (data.assetCount ?? 0);
      const entryNumber = (data.entry_type === 'Hibah' || data.entry_type === 'Grant')
        ? (data.memo_number || data.po_number || '-')
        : (data.po_number || data.memo_number || '-');
      const entryTypeLabel = (data.entry_type === 'Hibah' || data.entry_type === 'Grant')
        ? t('inventory.grant')
        : t('inventory.procurement');

      return [
        { label: t('inventory.lotCode'), value: data.number },
        { label: t('inventory.category'), value: b.category },
        { label: t('inventory.subcategory'), value: b.subcategory },
        { label: t('inventory.brand'), value: b.brand },
        { label: t('inventory.name'), value: b.name },
        ...(!isComp ? [{ label: t('inventory.specification'), value: b.specification || '-' }] : []),
        { label: t('inventory.availableStock'), value: availableStock },
        { label: t('inventory.initialStock'), value: initialStock },
        { label: t('inventory.entryType'), value: entryTypeLabel },
        { label: t('inventory.number'), value: entryNumber },
        { label: t('inventory.registrationDate'), value: formatDate(data.date_of_receipt) },
        {
          label: t('inventory.age'),
          value: data.age !== undefined && data.age !== null ? `${data.age} ${t('inventory.yearUnit')}` : '-',
        },
        ...(isConsumable ? [{ label: t('inventory.location'), value: formatLocation(data.location, data.floor, data.room) }] : []),
        ...(!isConsumable ? [{ label: t('inventory.organizer'), value: data.organizer }] : []),
        {
          label: t('inventory.vendor'),
          value: data.vendor_id && data.vendor !== '-' ? data.vendor : t('inventory.vendorMultiple'),
        },
        ...(isConsumable ? [{ label: t('inventory.burden'), value: data.burden || '-' }] : []),
        { label: t('inventory.lastUpdate'), value: data.updated_at || '-' },
      ];
    }

    return null;
  });

  return {
    columns,
    deleteFields,
    formatLocation,
    formatRupiah,
  };
}
