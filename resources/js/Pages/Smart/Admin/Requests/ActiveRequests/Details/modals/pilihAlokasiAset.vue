<script setup lang="ts">
/**
 * Modal Pilih Alokasi (Aset & Habis Pakai)
 * Handles all 4 fulfillment item allocation variants:
 * 1. Specific Non-Consumable (Aset Spesifik): Pick discrete units, showing Kode LOT.
 * 2. Non-Specific Non-Consumable (Aset Non-Spesifik): Pick discrete units, showing Varian (brand + name + spec) + Varian combobox filter.
 * 3. Specific Consumable (Habis Pakai Spesifik): Allocate stock per LOT via numeric input, showing Kode LOT, Lokasi, Stok Tersedia, Stok Dialokasikan.
 * 4. Non-Specific Consumable (Habis Pakai Non-Spesifik): Allocate stock per LOT via numeric input, showing Kode LOT, Varian, Lokasi, Stok Tersedia, Stok Dialokasikan + Varian combobox filter.
 */
import { ref, computed, watch, onMounted, onUnmounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModalLock } from '@/composables/useModalLock';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import TableSearch from '@/Components/TableSearch.vue';
import Combobox from '@/Components/Combobox.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';
import { REQUEST_STATUS_PILL_BASE } from '@/lib/requestStatus';
import type { ColumnDef } from '@tanstack/vue-table';
import { X, ArrowUpDown, Loader2, Sparkles, RotateCcw, ChevronDown } from 'lucide-vue-next';

export interface AvailableUnit {
  id: number;
  asset_code: string;
  lot_code: string;
  brand?: string;
  name?: string;
  spec?: string;
  variant?: string;
  status: string;
  condition: string;
  storage_location: string;
  is_currently_assigned: boolean;
  is_locked: boolean;
  is_staged_elsewhere?: boolean;
}

export interface AvailableLot {
  id: number;
  lot_code: string;
  brand: string;
  name: string;
  spec: string;
  variant: string;
  storage_location: string;
  current_quantity: number;
  allocated_quantity: number;
  uom: string;
  date_of_receipt: string;
}

export interface AllocationSlot {
  slot_number: number;
  unit_id: number | null;
  asset_number: string | null;
  color: 'purple' | 'green' | 'red';
}

export interface FulfillmentItem {
  id: number;
  barang_id?: number | null;
  subcategory_id?: number | null;
  category: string;
  subcategory: string;
  brand?: string | null;
  name?: string | null;
  spec?: string | null;
  quantity: number;
  quantity_requested: number;
  is_consumable: boolean;
  imageUrl?: string | null;
  uom: string;
  status?: string;
  allocation_slots?: AllocationSlot[];
  available_units?: AvailableUnit[];
  available_lots?: AvailableLot[];
  [key: string]: any;
}

interface Props {
  open: boolean;
  item: FulfillmentItem | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

// --- Mode Detection ---
const isConsumable = computed(() => Boolean(props.item?.is_consumable));
const isSpecific = computed(() => Boolean(props.item?.barang_id));

// --- State ---
const tempSelectedUnitIds = ref<number[]>([]);
const tempAllocatedLots = ref<Record<number, number>>({});
const unitSearchQuery = ref('');
const selectedVariantFilter = ref<string | number | null>('');
const isSavingAllocation = ref(false);
const rowsPerPage = ref('10');

const rowsPerPageLabel = computed(() => {
  if (rowsPerPage.value === 'Semua baris') return t('fulfillment.allRows');
  return rowsPerPage.value;
});

const hasActiveFilters = computed(() => {
  return !!(
    unitSearchQuery.value ||
    (selectedVariantFilter.value !== '' && selectedVariantFilter.value !== null)
  );
});

const clearFilters = () => {
  unitSearchQuery.value = '';
  selectedVariantFilter.value = '';
};

const pageSizeNumber = computed(() => {
  if (rowsPerPage.value === 'Semua baris' || !rowsPerPage.value) {
    return 999999;
  }
  return parseInt(rowsPerPage.value, 10) || 10;
});

// Modal Title based on consumable vs non-consumable
const modalTitle = computed(() => {
  return isConsumable.value ? t('fulfillment.selectStockTitle') : t('fulfillment.selectAssetTitle');
});

// Reset and initialize state upon opening modal
watch(() => props.open, (isOpen) => {
  if (isOpen && props.item) {
    unitSearchQuery.value = '';
    selectedVariantFilter.value = '';
    rowsPerPage.value = '10';

    if (isConsumable.value) {
      tempSelectedUnitIds.value = [];
      const lotMap: Record<number, number> = {};
      (props.item.available_lots || []).forEach(l => {
        lotMap[l.id] = l.allocated_quantity || 0;
      });
      tempAllocatedLots.value = lotMap;
    } else {
      tempAllocatedLots.value = {};
      const currentAssigned = (props.item.allocation_slots || [])
        .filter(slot => slot.unit_id !== null)
        .map(slot => slot.unit_id as number);
      tempSelectedUnitIds.value = [...currentAssigned];
    }
  } else {
    tempSelectedUnitIds.value = [];
    tempAllocatedLots.value = {};
    selectedVariantFilter.value = '';
  }
});

const closeModal = () => {
  emit('update:open', false);
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) {
    closeModal();
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

// Product title for info header
const displayBrand = computed(() => {
  if (!props.item?.brand || props.item.brand.trim() === '' || props.item.brand === '-') {
    return '';
  }
  return props.item.brand;
});

const displayItemTitle = computed(() => {
  const itemName = props.item?.name && props.item.name !== 'Tidak Spesifik' 
    ? props.item.name 
    : props.item?.subcategory || '';
  if (displayBrand.value) {
    return `${displayBrand.value} ${itemName}`;
  }
  return itemName;
});

// Variant Combobox Options (Unique variants from available records)
const variantOptions = computed(() => {
  const rawList = isConsumable.value
    ? (props.item?.available_lots || [])
    : (props.item?.available_units || []);

  const variants = Array.from(
    new Set(rawList.map((i: any) => i.variant).filter(Boolean))
  ) as string[];

  return variants.map(v => ({ id: v, name: v }));
});

// Search placeholder based on mode
const searchPlaceholder = computed(() => {
  if (isConsumable.value) {
    return isSpecific.value ? 'Cari kode LOT, lokasi...' : 'Cari kode LOT, varian, lokasi...';
  }
  return isSpecific.value ? 'Cari kode aset, LOT, lokasi...' : 'Cari kode aset, varian, lokasi...';
});

// Filtered Units (For Non-Consumable Variants 1 & 2)
const filteredAvailableUnits = computed(() => {
  const item = props.item;
  if (!item || !item.available_units) return [];

  let list = item.available_units;

  // Variant Combobox filter for non-specific variant
  if (!isSpecific.value && selectedVariantFilter.value) {
    list = list.filter(u => u.variant === selectedVariantFilter.value);
  }

  const q = unitSearchQuery.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter(u => {
    const matchCommon = 
      (u.asset_code && u.asset_code.toLowerCase().includes(q)) ||
      (u.status && u.status.toLowerCase().includes(q)) ||
      (u.condition && u.condition.toLowerCase().includes(q)) ||
      (u.storage_location && u.storage_location.toLowerCase().includes(q));

    if (isSpecific.value) {
      return matchCommon || (u.lot_code && u.lot_code.toLowerCase().includes(q));
    }
    return matchCommon || (u.variant && u.variant.toLowerCase().includes(q));
  });
});

// Filtered Lots (For Consumable Variants 3 & 4)
const filteredAvailableLots = computed(() => {
  const item = props.item;
  if (!item || !item.available_lots) return [];

  let list = item.available_lots;

  // Variant Combobox filter for non-specific variant
  if (!isSpecific.value && selectedVariantFilter.value) {
    list = list.filter(l => l.variant === selectedVariantFilter.value);
  }

  const q = unitSearchQuery.value.trim().toLowerCase();
  if (!q) return list;

  return list.filter(l => {
    const matchCommon = 
      (l.lot_code && l.lot_code.toLowerCase().includes(q)) ||
      (l.storage_location && l.storage_location.toLowerCase().includes(q));

    if (!isSpecific.value) {
      return matchCommon || (l.variant && l.variant.toLowerCase().includes(q));
    }
    return matchCommon;
  });
});

// Active data for DataTable
const currentTableData = computed(() => {
  return isConsumable.value ? filteredAvailableLots.value : filteredAvailableUnits.value;
});

// Non-consumable: Available free units
const availableFreeUnits = computed(() => {
  const item = props.item;
  if (!item || !item.available_units) return [];
  return item.available_units.filter(u => !u.is_locked && !tempSelectedUnitIds.value.includes(u.id));
});

// Consumable: Total allocated quantity
const totalConsumableAllocated = computed(() => {
  return Object.values(tempAllocatedLots.value).reduce((sum, val) => sum + (val || 0), 0);
});

// Auto Allocate Eligibility
const canAutoAllocate = computed(() => {
  const max = props.item?.quantity_requested || 0;
  if (isConsumable.value) {
    const hasStock = (props.item?.available_lots || []).some(l => (l.current_quantity || 0) > 0);
    return hasStock && totalConsumableAllocated.value < max;
  }
  return availableFreeUnits.value.length > 0 && tempSelectedUnitIds.value.length < max;
});

// Clear Selection Eligibility
const canClearSelection = computed(() => {
  if (isConsumable.value) {
    return totalConsumableAllocated.value > 0;
  }
  const lockedIds = (props.item?.available_units || [])
    .filter(u => u.is_locked)
    .map(u => u.id);
  const unlockedSelected = tempSelectedUnitIds.value.filter(id => !lockedIds.includes(id));
  return unlockedSelected.length > 0;
});

// Auto Allocation Handler (FIFO: Oldest available records first)
const handleAutoAllocate = () => {
  const item = props.item;
  if (!item) return;

  const max = item.quantity_requested || 0;

  if (isConsumable.value) {
    if (!item.available_lots) return;
    let remaining = max;
    const newAlloc: Record<number, number> = {};

    for (const lot of item.available_lots) {
      if (remaining <= 0) break;
      const available = lot.current_quantity || 0;
      if (available <= 0) continue;
      const take = Math.min(remaining, available);
      newAlloc[lot.id] = take;
      remaining -= take;
    }

    tempAllocatedLots.value = newAlloc;
    const total = Object.values(newAlloc).reduce((a, b) => a + b, 0);
    toast.success(t('fulfillment.autoAllocateSuccessStock', { count: total, uom: item.uom || t('fulfillment.stockUnit') }));
  } else {
    if (!item.available_units) return;
    const remaining = max - tempSelectedUnitIds.value.length;
    if (remaining <= 0) return;

    const candidateUnits = availableFreeUnits.value.slice(0, remaining);
    candidateUnits.forEach(u => {
      tempSelectedUnitIds.value.push(u.id);
    });

    toast.success(t('fulfillment.autoAllocateSuccessAsset', { count: candidateUnits.length }));
  }
};

// Clear Selection Handler
const handleClearSelection = () => {
  if (isConsumable.value) {
    tempAllocatedLots.value = {};
    toast.info(t('fulfillment.clearStockInfo'));
  } else {
    const lockedIds = (props.item?.available_units || [])
      .filter(u => u.is_locked)
      .map(u => u.id);

    tempSelectedUnitIds.value = tempSelectedUnitIds.value.filter(id => lockedIds.includes(id));
    toast.info(t('fulfillment.clearAssetInfo'));
  }
};

// Unit selection helpers
const isUnitSelected = (unitId: number): boolean => {
  return tempSelectedUnitIds.value.includes(unitId);
};

const toggleUnitSelection = (unit: AvailableUnit) => {
  if (unit.is_locked) return;

  const idx = tempSelectedUnitIds.value.indexOf(unit.id);
  if (idx !== -1) {
    tempSelectedUnitIds.value.splice(idx, 1);
  } else {
    tempSelectedUnitIds.value.push(unit.id);
  }
};

// Consumable lot quantity change with strict numeric constraints
const handleLotQuantityChange = (lotId: number, rawVal: string, maxAvailable: number) => {
  if (rawVal === '') {
    tempAllocatedLots.value[lotId] = 0;
    return;
  }
  let num = parseInt(rawVal, 10);
  if (isNaN(num) || num < 0) {
    num = 0;
  } else if (num > maxAvailable) {
    num = maxAvailable;
    toast.warning(t('fulfillment.lotMaxLimitWarning', { max: maxAvailable }));
  }
  tempAllocatedLots.value[lotId] = num;
};

// Over Limit Validation
const isOverLimit = computed(() => {
  const max = props.item?.quantity_requested || 0;
  if (isConsumable.value) {
    return totalConsumableAllocated.value > max;
  }
  return tempSelectedUnitIds.value.length > max;
});

// Dynamic Columns Construction for all 4 variants
const columns = computed<ColumnDef<any>[]>(() => {
  if (isConsumable.value) {
    // Consumable Variants (3 & 4)
    const cols: ColumnDef<any>[] = [
      {
        id: 'select',
        size: 50,
        header: () => h('div', { class: 'text-center flex items-center justify-center font-semibold text-foreground text-sm' }, t('fulfillment.select')),
        cell: ({ row }) => {
          const lot = row.original as AvailableLot;
          const isAllocated = (tempAllocatedLots.value[lot.id] || 0) > 0;
          return h('div', { class: 'text-center flex items-center justify-center' }, [
            isAllocated
              ? h('div', {
                  class: 'w-4 h-4 rounded bg-primary text-primary-foreground flex items-center justify-center shadow-xs select-none pointer-events-none'
                }, [
                  h('svg', {
                    xmlns: 'http://www.w3.org/2000/svg',
                    viewBox: '0 0 24 24',
                    fill: 'none',
                    stroke: 'currentColor',
                    'stroke-width': '3',
                    'stroke-linecap': 'round',
                    'stroke-linejoin': 'round',
                    class: 'w-3 h-3 text-white'
                  }, [
                    h('polyline', { points: '20 6 9 17 4 12' })
                  ])
                ])
              : h('div', {
                  class: 'w-4 h-4 rounded border border-input bg-background select-none pointer-events-none'
                })
          ]);
        }
      },
      {
        accessorKey: 'lot_code',
        header: ({ column }) => h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('fulfillment.lotCode'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
        ]),
        cell: ({ row }) => h('div', { 
          class: 'font-mono text-muted-foreground text-sm select-none' 
        }, row.getValue('lot_code'))
      }
    ];

    // Non-Specific Consumable (Variant 4) adds Varian column
    if (!isSpecific.value) {
      cols.push({
        accessorKey: 'variant',
        header: ({ column }) => h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('fulfillment.variant'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
        ]),
        cell: ({ row }) => h('div', { 
          class: 'font-medium text-foreground text-sm select-none min-w-[240px] max-w-[340px] truncate',
          title: row.getValue('variant')
        }, row.getValue('variant') || '-')
      });
    }

    cols.push(
      {
        accessorKey: 'storage_location',
        header: ({ column }) => h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('fulfillment.storageLocation'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
        ]),
        cell: ({ row }) => h('div', { 
          class: 'text-muted-foreground text-sm select-none' 
        }, row.getValue('storage_location'))
      },
      {
        accessorKey: 'current_quantity',
        header: ({ column }) => h(Button, {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
        }, () => [
          t('fulfillment.availableStock'),
          h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
        ]),
        cell: ({ row }) => {
          const lot = row.original as AvailableLot;
          return h('div', { class: 'font-medium text-foreground text-sm select-none whitespace-nowrap' }, 
            `${lot.current_quantity} ${lot.uom || props.item?.uom || t('fulfillment.stockUnit')}`
          );
        }
      },
      {
        id: 'stock_allocated',
        header: () => h('div', { class: 'font-semibold text-foreground text-sm whitespace-nowrap' }, t('fulfillment.allocatedStock')),
        cell: ({ row }) => {
          const lot = row.original as AvailableLot;
          const currentVal = tempAllocatedLots.value[lot.id] || 0;
          const maxAvailable = lot.current_quantity || 0;

          return h('div', {
            class: 'flex items-center gap-2',
            onClick: (e: MouseEvent) => e.stopPropagation()
          }, [
            h('input', {
              type: 'number',
              min: 0,
              max: maxAvailable,
              value: currentVal === 0 ? '' : currentVal,
              placeholder: '0',
              onInput: (e: Event) => {
                const target = e.target as HTMLInputElement;
                handleLotQuantityChange(lot.id, target.value, maxAvailable);
              },
              class: 'w-24 h-8 px-2 py-1 text-center font-mono text-sm border rounded-[10px] focus:ring-1 focus:ring-primary focus:border-primary border-input bg-background text-foreground'
            }),
            h('span', { class: 'text-xs text-muted-foreground whitespace-nowrap' }, lot.uom || props.item?.uom || t('fulfillment.stockUnit'))
          ]);
        }
      }
    );

    return cols;
  }

  // Non-Consumable Asset Variants (1 & 2)
  const cols: ColumnDef<any>[] = [
    {
      id: 'select',
      size: 50,
      header: () => h('div', { class: 'text-center flex items-center justify-center font-semibold text-foreground text-sm' }, t('fulfillment.select')),
      cell: ({ row }) => {
        const unit = row.original as AvailableUnit;
        return h('div', { 
          class: 'text-center flex items-center justify-center', 
          onClick: (e: MouseEvent) => e.stopPropagation() 
        }, [
          h('input', {
            type: 'checkbox',
            checked: isUnitSelected(unit.id),
            disabled: unit.is_locked,
            onChange: () => toggleUnitSelection(unit),
            class: 'rounded border-input text-primary focus:ring-primary/20 w-4 h-4 cursor-pointer disabled:opacity-50'
          })
        ]);
      }
    },
    {
      accessorKey: 'asset_code',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.assetCode'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
      ]),
      cell: ({ row }) => h('div', { 
        class: 'font-mono text-muted-foreground font-medium text-sm select-none',
        onClick: () => toggleUnitSelection(row.original)
      }, row.getValue('asset_code'))
    }
  ];

  if (isSpecific.value) {
    // Specific Asset (Variant 1): Kode LOT column
    cols.push({
      accessorKey: 'lot_code',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.lotCode'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
      ]),
      cell: ({ row }) => h('div', { 
        class: 'font-mono text-muted-foreground text-sm select-none',
        onClick: () => toggleUnitSelection(row.original)
      }, row.getValue('lot_code'))
    });
  } else {
    // Non-Specific Asset (Variant 2): Varian column (widest)
    cols.push({
      accessorKey: 'variant',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.variant'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
      ]),
      cell: ({ row }) => h('div', { 
        class: 'font-medium text-foreground text-sm select-none min-w-[240px] max-w-[340px] truncate',
        title: row.getValue('variant'),
        onClick: () => toggleUnitSelection(row.original)
      }, row.getValue('variant') || '-')
    });
  }

  cols.push(
    {
      accessorKey: 'status',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.status'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
      ]),
      cell: ({ row }) => {
        const u = row.original as AvailableUnit;
        return h('div', {
          class: 'select-none flex items-center gap-1.5',
          onClick: () => toggleUnitSelection(row.original)
        }, [
          h(StatusBadge, {
            status: row.getValue('status') as string,
            class: 'rounded-sm'
          }),
          u.is_staged_elsewhere ? h('span', {
            class: `${REQUEST_STATUS_PILL_BASE} bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 whitespace-nowrap`
          }, t('fulfillment.stagedElsewhere')) : null
        ]);
      }
    },
    {
      accessorKey: 'condition',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.condition'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
      ]),
      cell: ({ row }) => {
        const cond = row.getValue('condition') as string || '';
        let textClass = 'text-foreground text-sm';
        if (cond === 'Bagus' || cond === 'QC Passed') textClass = 'text-emerald-600 font-semibold text-sm';
        else if (cond === 'Lelang/Hibah') textClass = 'text-purple-600 font-semibold text-sm';
        else if (cond === 'Rusak' || cond === 'Rusak Total' || cond === 'Hilang') textClass = 'text-rose-600 font-semibold text-sm';
        return h('div', {
          class: 'select-none',
          onClick: () => toggleUnitSelection(row.original)
        }, [
          h('span', { class: textClass }, cond)
        ]);
      }
    },
    {
      accessorKey: 'storage_location',
      header: ({ column }) => h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
      }, () => [
        t('fulfillment.storageLocation'),
        h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
      ]),
      cell: ({ row }) => h('div', { 
        class: 'text-muted-foreground text-sm select-none',
        onClick: () => toggleUnitSelection(row.original)
      }, row.getValue('storage_location'))
    }
  );

  return cols;
});

// Save Allocation Handler
const saveAllocation = () => {
  const item = props.item;
  if (!item || isOverLimit.value) return;

  isSavingAllocation.value = true;

  if (isConsumable.value) {
    const lotAllocations = Object.entries(tempAllocatedLots.value)
      .map(([lotId, qty]) => ({
        lot_id: Number(lotId),
        quantity: Number(qty),
      }))
      .filter(a => a.quantity > 0);

    router.post(route('smart.fulfillment.items.assign-lots', item.id), {
      lot_allocations: lotAllocations
    }, {
      preserveScroll: true,
      onSuccess: () => {
        isSavingAllocation.value = false;
        closeModal();
        emit('success');
        toast.success(t('fulfillment.allocationSavedStock'));
      },
      onError: (errs) => {
        isSavingAllocation.value = false;
        toast.error(Object.values(errs).join(', '));
      }
    });
  } else {
    router.post(route('smart.fulfillment.items.assign', item.id), {
      unit_ids: tempSelectedUnitIds.value
    }, {
      preserveScroll: true,
      onSuccess: () => {
        isSavingAllocation.value = false;
        closeModal();
        emit('success');
        toast.success(t('fulfillment.allocationSavedAsset'));
      },
      onError: (errs) => {
        isSavingAllocation.value = false;
        toast.error(Object.values(errs).join(', '));
      }
    });
  }
};
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open" 
            class="bg-card w-full md:max-w-[95%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                {{ modalTitle }}
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] space-y-4 overscroll-contain">
              <!-- Top Toolbar: Search + Combobox (Non-Specific) + Action Buttons (Left) & Rows Per Page (Right) -->
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <!-- Left Action Group: Search, Varian Combobox, Auto-Allocate, Clear -->
                <div class="flex items-center gap-3 flex-wrap flex-grow">
                  <div class="w-full sm:w-72">
                    <TableSearch 
                      v-model="unitSearchQuery" 
                      :placeholder="searchPlaceholder" 
                      bg-class="bg-background"
                    />
                  </div>

                  <!-- Varian Combobox Filter (Only for Non-Specific Variants) -->
                  <div v-if="!isSpecific && variantOptions.length > 0" class="w-full sm:w-[320px] lg:w-[380px]">
                    <Combobox
                      v-model="selectedVariantFilter"
                      :options="variantOptions"
                      :placeholder="t('fulfillment.allVariants')"
                      :default-label="t('fulfillment.allVariants')"
                      :search-placeholder="t('fulfillment.searchVariant')"
                      width-class="w-full sm:w-[320px] lg:w-[380px]"
                    />
                  </div>

                  <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                  >
                    <ResetFilterButton 
                      v-if="hasActiveFilters"
                      @click="clearFilters"
                    />
                  </Transition>

                  <!-- Alokasi Otomatis (Green button) -->
                  <Button
                    type="button"
                    variant="success"
                    size="default"
                    :disabled="!canAutoAllocate"
                    @click="handleAutoAllocate"
                    class="h-9 px-4 inline-flex items-center gap-1.5 font-semibold text-white shadow-sm rounded-[14px]"
                  >
                    <Sparkles class="w-4 h-4" />
                    <span>{{ t('fulfillment.autoAllocate') }}</span>
                  </Button>

                  <!-- Hapus Pilihan -->
                  <Button
                    type="button"
                    variant="destructive"
                    size="default"
                    :disabled="!canClearSelection"
                    @click="handleClearSelection"
                    class="h-9 px-4 inline-flex items-center gap-1.5 rounded-[14px]"
                  >
                    <RotateCcw class="w-4 h-4" />
                    <span>{{ t('fulfillment.clearSelection') }}</span>
                  </Button>
                </div>

                <!-- Right: Baris per Halaman (Dropdown matching DaftarAsetTab) -->
                <div class="flex items-center gap-3 text-sm text-muted-foreground shrink-0">
                  <span class="whitespace-nowrap">{{ t('fulfillment.rowsPerPage') }}</span>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" :class="['w-[140px] justify-between rounded-[14px] font-normal', (rowsPerPage === 'Semua baris' || !rowsPerPage) ? 'text-muted-foreground' : 'text-foreground']">
                        {{ rowsPerPageLabel }}
                        <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="z-[110] w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]" align="start" :side-offset="4">
                      <DropdownMenuItem @select="rowsPerPage = 'Semua baris'">{{ t('fulfillment.allRows') }}</DropdownMenuItem>
                      <DropdownMenuItem @select="rowsPerPage = '10'">10</DropdownMenuItem>
                      <DropdownMenuItem @select="rowsPerPage = '25'">25</DropdownMenuItem>
                      <DropdownMenuItem @select="rowsPerPage = '50'">50</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </div>

              <!-- DataTable with DaftarAsetTab styling and dynamic pagination size -->
              <div class="pb-1">
                <DataTable
                  :columns="columns"
                  :data="currentTableData"
                  :page-size="pageSizeNumber"
                  :show-selection-count="false"
                  :row-class="(row: any) => {
                    if (isConsumable) {
                      return (tempAllocatedLots[row.id] || 0) > 0 ? 'bg-primary/5' : '';
                    }
                    return isUnitSelected(row.id) ? 'bg-primary/5 cursor-pointer' : 'cursor-pointer';
                  }"
                  :default-sorting="[isConsumable ? { id: 'lot_code', desc: false } : { id: 'asset_code', desc: false }]"
                  table-container-class="max-h-[350px]"
                />
              </div>

              <!-- Bottom Info / Counter Section -->
              <div class="flex flex-col justify-center gap-1 pt-1">
                <p class="text-xs text-muted-foreground">
                  <template v-if="isConsumable">
                    {{ t('fulfillment.allocateMaxStock', { title: displayItemTitle, max: item?.quantity_requested, uom: item?.uom || t('fulfillment.stockUnit') }) }}
                  </template>
                  <template v-else>
                    {{ t('fulfillment.selectMaxUnits', { title: displayItemTitle, max: item?.quantity_requested }) }}
                  </template>
                </p>
                <div class="text-xs font-semibold">
                  <template v-if="isConsumable">
                    {{ t('fulfillment.totalAllocatedStock') }} 
                    <span :class="[
                      totalConsumableAllocated === (item?.quantity_requested || 0) ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'
                    ]">
                      {{ totalConsumableAllocated }} / {{ item?.quantity_requested }} {{ item?.uom || t('fulfillment.stockUnit') }}
                    </span>
                    <span v-if="isOverLimit" class="text-rose-600 font-normal ml-2">
                      {{ t('fulfillment.overStockLimitWarning') }}
                    </span>
                  </template>
                  <template v-else>
                    {{ t('fulfillment.totalSelectedUnits') }} 
                    <span :class="[
                      tempSelectedUnitIds.length === (item?.quantity_requested || 0) ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'
                    ]">
                      {{ tempSelectedUnitIds.length }} / {{ item?.quantity_requested }} {{ t('fulfillment.unitWord') }}
                    </span>
                    <span v-if="isOverLimit" class="text-rose-600 font-normal ml-2">
                      {{ t('fulfillment.overUnitLimitWarning') }}
                    </span>
                  </template>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10">
              <Button 
                variant="white" 
                size="lg" 
                @click="closeModal"
              >
                {{ t('fulfillment.batal') }}
              </Button>
              <Button 
                variant="primary" 
                size="lg" 
                :disabled="isSavingAllocation || isOverLimit"
                @click="saveAllocation" 
                class="relative"
              >
                <Loader2 v-if="isSavingAllocation" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                <span :class="{ 'opacity-0': isSavingAllocation }">
                  {{ t('fulfillment.saveAllocation') }}
                </span>
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
