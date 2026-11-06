import { computed, h, type ComputedRef } from 'vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowUpDown, Eye } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import StatusBadge from '@/Components/StatusBadge.vue';
import { cn, formatLocation, formatDate, isLocationActive } from '@/lib/utils';
import { getAssetStatusLabel } from '@/lib/assetStatus';
import { formatQcStatus, getQcColorClass } from '@/lib/qualityControl';

export { formatLocation };

/**
 * Calculates software license expiration styling:
 * Red (destructive) if expired (<= 0 days)
 * Yellow (amber) if within renewal reminder days (<= renewal_reminder or 30 days)
 */
export function getSoftwareRowClass(row: any): string {
  const expDateStr = row.license_exp_date || row.le;
  if (!expDateStr) return '';
  const expDate = new Date(expDateStr);
  if (isNaN(expDate.getTime())) return '';
  const now = new Date();
  const expMidnight = new Date(expDate.getFullYear(), expDate.getMonth(), expDate.getDate());
  const nowMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const diffTime = expMidnight.getTime() - nowMidnight.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  const reminder = row.renewal_reminder !== undefined && row.renewal_reminder !== null ? Number(row.renewal_reminder) : 30;

  if (diffDays <= 0) {
    return 'bg-destructive/10 hover:bg-destructive/15 text-destructive font-medium';
  }
  if (diffDays <= reminder) {
    return 'bg-amber-500/10 hover:bg-amber-500/15 text-amber-700 dark:text-amber-400 font-medium';
  }
  return '';
}

const CONDITION_KEY_MAP: Record<string, string> = {
  'bagus': 'inventory.conditionGood',
  'rusak': 'inventory.conditionDamaged',
  'rusak total': 'inventory.conditionTotalDamage',
  'hilang': 'inventory.conditionLost',
};

export const buildLocationTargetSetMap = (allLocs: any[]): Map<number, Set<number>> => {
  const childrenByParent = new Map<number, number[]>();
  for (let i = 0; i < allLocs.length; i++) {
    const loc = allLocs[i];
    const pid = Number(loc.parent_id);
    if (pid) {
      let arr = childrenByParent.get(pid);
      if (!arr) {
        arr = [];
        childrenByParent.set(pid, arr);
      }
      arr.push(Number(loc.id));
    }
  }

  const targetMap = new Map<number, Set<number>>();
  const collectDescendants = (rootId: number): Set<number> => {
    const existing = targetMap.get(rootId);
    if (existing) return existing;
    const set = new Set<number>([rootId]);
    const stack = [...(childrenByParent.get(rootId) || [])];
    while (stack.length > 0) {
      const cid = stack.pop()!;
      if (!set.has(cid)) {
        set.add(cid);
        const grandChildren = childrenByParent.get(cid);
        if (grandChildren) {
          for (let j = 0; j < grandChildren.length; j++) {
            stack.push(grandChildren[j]);
          }
        }
      }
    }
    targetMap.set(rootId, set);
    return set;
  };

  for (let i = 0; i < allLocs.length; i++) {
    collectDescendants(Number(allLocs[i].id));
  }
  return targetMap;
};

export function useNonConsumableColumns(options: {
  props: {
    hideActions?: boolean;
    hideBarangColumns?: boolean;
    hideQc?: boolean;
  };
  isComp: ComputedRef<boolean>;
  isAllSoft: ComputedRef<boolean>;
  isVehicle?: ComputedRef<boolean>;
  locations?: () => any[];
  t: (key: string, params?: any) => string;
  te: (key: string) => boolean;
  locale: { value: string };
  openViewAssetModal: (asset: any) => void;
}) {
  const { props, isComp, isAllSoft, isVehicle, locations, t, te, locale, openViewAssetModal } = options;

  const getStatusLabel = (status: string) => {
    return getAssetStatusLabel(status, t, te);
  };

  const getConditionLabel = (cond: string) => {
    if (!cond || cond === '-') return '';
    const key = CONDITION_KEY_MAP[cond.toLowerCase()];
    return key && te(key) ? t(key) : cond;
  };

  const formatRupiah = (val: number | string | null | undefined) => {
    if (val === null || val === undefined) return '-';
    const num = typeof val === 'string' ? parseFloat(val) : val;
    if (isNaN(num)) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'id-ID';
    const formatted = Math.floor(num).toLocaleString(loc);
    return `Rp${formatted}`;
  };

  const columns = computed<ColumnDef<any>[]>(() => {
    const list: ColumnDef<any>[] = [];

    if (!props.hideActions) {
      list.push({
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
      });
    }

    list.push({
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
            t('inventory.assetCode'),
            h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
          ]
        ),
      cell: ({ row }) =>
        h('div', { class: 'text-muted-foreground font-mono text-sm font-medium' }, row.getValue('number')),
    });

    if (isVehicle?.value) {
      list.push({
        accessorKey: 'vehicle_registration',
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
              t('inventory.nopol'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) =>
          h('div', { class: 'text-foreground font-medium' }, row.original.vehicle_registration || row.original.vr || '-'),
      });
    }

    if (!props.hideBarangColumns) {
      list.push(
        {
          accessorKey: 'barang_nama',
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
            h('div', { class: 'text-foreground font-medium', title: row.getValue('barang_nama') }, row.getValue('barang_nama')),
        },
        {
          id: 'specification',
          accessorFn: (row: any) => row.comp_specification || row.specification || row.barang_specification || '-',
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
            const spec = row.original.comp_specification || row.original.specification || row.original.barang_specification || '-';
            return h(
              'div',
              {
                class: spec && spec !== '-' ? 'text-foreground' : 'text-muted-foreground',
                title: spec,
              },
              spec
            );
          },
        },
        {
          accessorKey: 'barang_brand',
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
          cell: ({ row }) => h('div', { class: 'text-foreground' }, row.getValue('barang_brand')),
        }
      );
    }

    list.push({
      accessorKey: 'status',
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
            t('inventory.status'),
            h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
          ]
        ),
      cell: ({ row }) => {
        const status = (row.getValue('status') as string) || '';
        const approvalStatus = row.original.approval_status;
        const proposedStatus = row.original.proposed_status;
        return h(StatusBadge, {
          status,
          approvalStatus,
          proposedStatus,
          class: 'rounded-sm',
        });
      },
    });

    if (isAllSoft.value) {
      list.push({
        accessorKey: 'license_exp_date',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.expirationDate'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => {
          const val = row.original.license_exp_date || row.original.le;
          return h('div', { class: 'text-muted-foreground' }, val ? formatDate(val) : t('inventory.perpetual'));
        },
      });

      if (props.hideBarangColumns) {
        list.push({
          accessorKey: 'age',
          header: ({ column }) =>
            h(
              Button,
              {
                variant: 'ghost',
                onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
              },
              () => [
                t('inventory.age'),
                h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
              ]
            ),
          cell: ({ row }) => {
            const age = row.original.age ?? (props as any).lot?.age;
            return h('div', { class: 'text-muted-foreground' }, age !== undefined && age !== null ? `${age} ${t('inventory.yearUnit')}` : '-');
          },
        });
      }
    }

    if (!isAllSoft.value) {
      list.push({
        accessorKey: 'condition',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('inventory.condition'),
              h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
            ]
          ),
        cell: ({ row }) => {
          const cond = (row.getValue('condition') as string) || '';
          let textClass = 'text-foreground';
          if (cond === 'Bagus') textClass = 'text-emerald-600 font-semibold';
          else if (cond === 'Rusak' || cond === 'Rusak Total' || cond === 'Hilang') textClass = 'text-rose-600 font-semibold';

          return h('span', { class: textClass }, getConditionLabel(cond));
        },
      });

      if (!props.hideQc && !isVehicle?.value) {
        list.push({
          accessorKey: 'qc_pass',
          header: ({ column }) =>
            h(
              Button,
              {
                variant: 'ghost',
                onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
              },
              () => [
                t('inventory.qualityControl'),
                h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground no-print' }),
              ]
            ),
          cell: ({ row }) => {
            const qcPass = row.original.qc_pass;
            const lastQc = row.original.last_qc ?? row.original.lq;
            if (qcPass === null || qcPass === undefined) {
              return h('span', { class: 'text-muted-foreground' }, '-');
            }
            const textClass = getQcColorClass(qcPass);
            const statusLabel = qcPass ? t('inventory.qcPass') : t('inventory.qcFail');
            const formattedDate = lastQc ? formatDate(lastQc) : null;
            const hasDate = Boolean(formattedDate && formattedDate !== '-');

            if (!hasDate) {
              return h('div', { class: cn('font-semibold', textClass) }, statusLabel);
            }

            return h('div', { class: 'leading-tight space-y-0.5' }, [
              h('div', { class: cn('font-semibold', textClass) }, statusLabel),
              h('div', { class: 'text-xs text-muted-foreground font-normal' }, formattedDate),
            ]);
          },
        });
      }
    }

    if (props.hideBarangColumns && isComp.value) {
      list.push({
        accessorKey: 'comp_specification',
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
          const spec = row.original.comp_specification || row.original.specification || '-';
          return h(
            'div',
            {
              class: 'text-foreground font-medium',
              title: spec,
            },
            spec
          );
        },
      });
    }

    if (!isAllSoft.value && !isVehicle?.value) {
      list.push({
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
          const r = row.original;
          const formattedLoc = formatLocation(r.location, r.floor, r.room);
          const locs = locations ? locations() : [];
          const isActive = isLocationActive(r.location_id || r.location, locs);
          return h(
            'div',
            {
              class: cn(
                'text-sm',
                !isActive ? 'text-destructive font-medium' : 'text-muted-foreground',
                props.hideBarangColumns && isComp.value ? '' : ''
              ),
              title: formattedLoc,
            },
            formattedLoc
          );
        },
      });
    }

    if (!props.hideActions) {
      list.push({
        id: 'actions',
        size: 80,
        meta: { fit: true },
        header: () => h('div', { class: 'text-center font-semibold text-foreground no-print' }, t('common.actions')),
        cell: ({ row }) =>
          h('div', { class: 'flex items-center justify-center gap-2 no-print' }, [
            h(
              Button,
              {
                variant: 'table-view',
                size: 'icon-sm',
                title: t('inventory.viewDetails'),
                onClick: () => openViewAssetModal(row.original),
              },
              () => [h(Eye), h('span', { class: 'sr-only' }, t('inventory.viewDetails'))]
            ),
          ]),
      });
    }

    return list;
  });

  return {
    columns,
    getStatusLabel,
    getConditionLabel,
    formatRupiah,
    formatLocation,
  };
}
