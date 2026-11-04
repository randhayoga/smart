import { computed, h, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { router } from '@inertiajs/vue3';
import { ArrowUpDown, ChevronDown, ChevronRight, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import Switch from '@/Components/ui/switch/Switch.vue';
import type { LocationItem, MasterDataTabKey, VendorItem } from '../Modals/MasterDataModal.vue';

export interface LocationRow extends LocationItem {
  depth: number;
  hasChildren: boolean;
  isCollapsed: boolean;
  childrenList: LocationRow[];
}

export function useMasterDataColumns(options: {
  activeTab: Ref<MasterDataTabKey>;
  currentTabSingular: ComputedRef<string>;
  searchQuery: Ref<string>;
  locations: () => LocationItem[];
  t: (key: string, params?: any) => string;
  openEditModal: (item: any) => void;
  openDeleteModal: (item: any) => void;
}) {
  const { activeTab, currentTabSingular, searchQuery, locations, t, openEditModal, openDeleteModal } = options;

  const collapsedLocationIds = ref<Set<number>>(new Set());

  function toggleLocationExpand(id: number, e?: Event) {
    if (e) e.stopPropagation();
    const next = new Set(collapsedLocationIds.value);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    collapsedLocationIds.value = next;
  }

  watch(searchQuery, (newVal, oldVal) => {
    if (newVal !== oldVal) {
      collapsedLocationIds.value = new Set();
    }
  });

  const locationRows = computed<LocationRow[]>(() => {
    const childrenMap = new Map<number | 'root', LocationItem[]>();
    (locations() || []).forEach((loc) => {
      const pid = loc.parent_id ?? 'root';
      if (!childrenMap.has(pid)) {
        childrenMap.set(pid, []);
      }
      childrenMap.get(pid)!.push(loc);
    });

    const roots = childrenMap.get('root') || [];

    function buildTree(items: LocationItem[], depth: number): LocationRow[] {
      return items.map((item) => {
        const childItems = childrenMap.get(item.id) || [];
        const childrenNodes = buildTree(childItems, depth + 1);
        return {
          ...item,
          depth,
          hasChildren: childrenNodes.length > 0,
          isCollapsed: collapsedLocationIds.value.has(item.id),
          childrenList: childrenNodes,
        };
      });
    }

    return buildTree(roots, 0);
  });

  const flattenedLocationDisplay = computed<LocationRow[]>(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const matchedIds = new Set<number>();

    if (q) {
      function addAllDescendants(node: LocationRow) {
        matchedIds.add(node.id);
        node.childrenList.forEach((c) => addAllDescendants(c));
      }

      function checkMatch(node: LocationRow): boolean {
        const dept = node.related_department;
        const deptMatch = dept
          ? Boolean(dept.org_name?.toLowerCase().includes(q)) ||
            Boolean(dept.org_code && dept.org_code.toLowerCase().includes(q))
          : false;
        const selfMatch =
          node.name.toLowerCase().includes(q) ||
          (node.full_name ?? '').toLowerCase().includes(q) ||
          deptMatch;
        let childMatch = false;
        node.childrenList.forEach((c) => {
          if (checkMatch(c)) childMatch = true;
        });

        if (selfMatch) {
          addAllDescendants(node);
          return true;
        }

        if (childMatch) {
          matchedIds.add(node.id);
          return true;
        }

        return false;
      }
      locationRows.value.forEach((r) => checkMatch(r));
    }

    const result: LocationRow[] = [];
    function traverse(nodes: LocationRow[]) {
      nodes.forEach((node) => {
        if (q && !matchedIds.has(node.id)) return;
        const isCollapsed = collapsedLocationIds.value.has(node.id);
        result.push({
          ...node,
          isCollapsed,
        });
        if (node.hasChildren && !isCollapsed) {
          traverse(node.childrenList);
        }
      });
    }

    traverse(locationRows.value);
    return result;
  });

  function toggleLocationActive(item: LocationItem) {
    router.patch(
      route('smart.master.locations.toggle-active', item.id),
      {},
      {
        preserveScroll: true,
      }
    );
  }

  const columns = computed<ColumnDef<any>[]>(() => {
    const cols: ColumnDef<any>[] = [];

    if (!['uoms', 'brands', 'organizers', 'locations', 'vendors'].includes(activeTab.value)) {
      cols.push({
        accessorKey: 'code',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'px-2 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('masterData.columns.codeWithTab', { tab: currentTabSingular.value }),
              h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground font-mono text-sm truncate' }, row.getValue('code')),
      });
    }

    if (activeTab.value === 'subcategories') {
      cols.push({
        accessorKey: 'is_consumable',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('masterData.columns.classification'),
              h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
            ]
          ),
        cell: ({ row }) =>
          h('div', { class: 'pl-2' }, [
            h(
              'span',
              {
                class: row.original.is_consumable
                  ? 'inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-800'
                  : 'inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-purple-100 text-purple-800',
              },
              row.original.is_consumable
                ? t('masterData.classification.consumable')
                : t('masterData.classification.asset')
            ),
          ]),
      });
    }

    cols.push({
      accessorKey: 'name',
      header: ({ column }) =>
        h(
          Button,
          {
            variant: 'ghost',
            onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
            class: 'pl-2 hover:bg-transparent font-semibold text-foreground justify-start',
          },
          () => [
            t('masterData.columns.nameWithTab', { tab: currentTabSingular.value }),
            h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
          ]
        ),
      cell: ({ row }) => {
        if (activeTab.value === 'locations') {
          const item = row.original as LocationRow;
          const depth = item.depth || 0;
          const childrenElements: any[] = [];

          if (item.hasChildren) {
            childrenElements.push(
              h(
                'button',
                {
                  type: 'button',
                  class:
                    'p-1 -ml-1 mr-1 text-muted-foreground hover:text-foreground rounded transition-colors inline-flex items-center justify-center cursor-pointer',
                  onClick: (e: Event) => toggleLocationExpand(item.id, e),
                },
                [h(item.isCollapsed ? ChevronRight : ChevronDown, { class: 'h-4 w-4' })]
              )
            );
          } else {
            childrenElements.push(h('span', { class: 'inline-block w-6' }));
          }

          childrenElements.push(h('span', { class: 'font-medium text-foreground' }, item.name));

          if (!item.is_active) {
            childrenElements.push(
              h(
                'span',
                {
                  class:
                    'ml-2.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                },
                t('masterData.status.inactive')
              )
            );
          }

          return h(
            'div',
            {
              class: 'flex items-center text-foreground truncate',
              style: { paddingLeft: `${depth * 24 + 8}px` },
            },
            childrenElements
          );
        }

        return h('div', { class: 'pl-2 text-foreground truncate' }, row.getValue('name'));
      },
    });

    if (activeTab.value === 'vendors') {
      cols.push({
        accessorKey: 'phone_number',
        header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.phone')),
        cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('phone_number') || '-'),
      });
      cols.push({
        accessorKey: 'email',
        header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.email')),
        cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate' }, row.getValue('email') || '-'),
      });
      cols.push({
        id: 'contact_person',
        header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.contactPerson')),
        cell: ({ row }) => {
          const vendor = row.original as VendorItem;
          const cps = vendor.contact_persons || [];
          if (!cps.length) {
            return h('div', { class: 'pl-2 text-muted-foreground truncate' }, '-');
          }
          const first = cps[0].contact_person || cps[0].cp_phone || cps[0].cp_email || '-';
          if (cps.length === 1) {
            return h('div', { class: 'pl-2 text-muted-foreground truncate' }, first);
          }
          return h('div', { class: 'pl-2 text-muted-foreground truncate flex items-center gap-1.5' }, [
            h('span', { class: 'truncate' }, first),
            h(
              'span',
              {
                class:
                  'inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground shrink-0',
              },
              `+${cps.length - 1}`
            ),
          ]);
        },
      });
    }

    if (['subcategories', 'brands'].includes(activeTab.value)) {
      cols.push({
        accessorKey: 'description',
        header: () =>
          h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight max-w-[200px]' }, t('masterData.columns.description')),
        cell: ({ row }) => h('div', { class: 'pl-2 text-muted-foreground truncate max-w-[200px]' }, row.getValue('description') || '-'),
      });
    }

    if (activeTab.value === 'subcategories') {
      cols.push({
        accessorKey: 'parent',
        header: ({ column }) =>
          h(
            Button,
            {
              variant: 'ghost',
              onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
              class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start',
            },
            () => [
              t('masterData.columns.parentCategory'),
              h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
            ]
          ),
        cell: ({ row }) => h('div', { class: 'text-muted-foreground truncate' }, row.getValue('parent')),
        filterFn: (row, _id, value) => {
          if (!value) return true;
          return row.original.parentCode === value;
        },
      });
    }

    if (activeTab.value === 'locations') {
      cols.push({
        accessorKey: 'related_departement',
        header: () => h('div', { class: 'pl-2 py-1 font-semibold text-foreground leading-tight' }, t('masterData.columns.relatedDepartment')),
        cell: ({ row }) => {
          const item = row.original as LocationRow;
          const dept = item.related_department;
          if (!dept) {
            return h('div', { class: 'pl-2 text-muted-foreground' }, '-');
          }
          const deptLabel = dept.org_code ? `${dept.org_code} - ${dept.org_name}` : dept.org_name;
          return h('div', { class: 'pl-2 text-foreground font-medium truncate', title: deptLabel }, deptLabel);
        },
      });

      cols.push({
        accessorKey: 'is_active',
        size: 90,
        header: () => h('div', { class: 'text-center font-semibold text-foreground leading-tight' }, t('masterData.columns.active')),
        cell: ({ row }) => {
          const item = row.original as LocationRow;
          return h('div', { class: 'flex items-center justify-center' }, [
            h(Switch, {
              modelValue: Boolean(item.is_active),
              class:
                'data-[state=checked]:!bg-emerald-600 data-[state=unchecked]:!bg-slate-300 dark:data-[state=unchecked]:!bg-slate-700',
              title: item.is_active ? t('masterData.actions.deactivate') : t('masterData.actions.activate'),
              'onUpdate:modelValue': () => toggleLocationActive(item),
            }),
          ]);
        },
      });
    }

    cols.push({
      id: 'actions',
      size: 84,
      header: () => h('div', { class: 'text-right' }, t('masterData.columns.actions')),
      cell: ({ row }) => {
        const item = row.original;
        const actionButtons: any[] = [];

        actionButtons.push(
          h(
            Button,
            {
              variant: 'table-edit',
              size: 'icon-sm',
              title: t('common.edit'),
              onClick: () => openEditModal(item),
            },
            () => [h(Pencil), h('span', { class: 'sr-only' }, t('common.edit'))]
          )
        );

        const isDeleteDisabled = activeTab.value === 'locations' && Boolean(item.hasChildren);
        actionButtons.push(
          h(
            Button,
            {
              variant: 'table-destructive',
              size: 'icon-sm',
              title: isDeleteDisabled ? t('masterData.actions.cannotDeleteWithChildren') : t('common.delete'),
              disabled: isDeleteDisabled,
              class: isDeleteDisabled ? 'opacity-40 cursor-not-allowed' : '',
              onClick: () => {
                if (!isDeleteDisabled) {
                  openDeleteModal(item);
                }
              },
            },
            () => [h(Trash2), h('span', { class: 'sr-only' }, t('common.delete'))]
          )
        );

        return h('div', { class: 'flex items-center justify-end gap-2' }, actionButtons);
      },
    });

    return cols;
  });

  return {
    columns,
    flattenedLocationDisplay,
  };
}
