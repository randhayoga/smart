<script setup lang="ts">
/**
 * Modal Pilih Alokasi Aset
 * Allows admin to search, inspect, and select physical asset units for an item
 * using DataTable based on FIFO or manual selection up to quantity_requested.
 */
import { ref, computed, watch, h } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/Components/ui/button';
import TableSearch from '@/Components/TableSearch.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { X, ArrowUpDown, Loader2 } from 'lucide-vue-next';

export interface AvailableUnit {
  id: number;
  asset_code: string;
  lot_code: string;
  status: string;
  condition: string;
  storage_location: string;
  is_currently_assigned: boolean;
  is_locked: boolean;
}

export interface AllocationSlot {
  slot_number: number;
  unit_id: number | null;
  asset_number: string | null;
  color: 'purple' | 'green' | 'red';
}

export interface FulfillmentItem {
  id: number;
  barang_id?: number;
  subcategory_id?: number;
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

const tempSelectedUnitIds = ref<number[]>([]);
const unitSearchQuery = ref('');
const isSavingAllocation = ref(false);

watch(() => props.open, (isOpen) => {
  if (isOpen && props.item) {
    unitSearchQuery.value = '';
    const currentAssigned = (props.item.allocation_slots || [])
      .filter(slot => slot.unit_id !== null)
      .map(slot => slot.unit_id as number);
    tempSelectedUnitIds.value = [...currentAssigned];
  } else {
    tempSelectedUnitIds.value = [];
  }
});

const closeModal = () => {
  emit('update:open', false);
};

const displayBrand = computed(() => {
  if (!props.item?.brand || props.item.brand.trim() === '' || props.item.brand === '-') {
    return '';
  }
  return props.item.brand;
});

const filteredAvailableUnits = computed(() => {
  const item = props.item;
  if (!item || !item.available_units) return [];

  const q = unitSearchQuery.value.trim().toLowerCase();
  if (!q) return item.available_units;

  return item.available_units.filter(u => 
    (u.asset_code && u.asset_code.toLowerCase().includes(q)) ||
    (u.lot_code && u.lot_code.toLowerCase().includes(q)) ||
    (u.status && u.status.toLowerCase().includes(q)) ||
    (u.condition && u.condition.toLowerCase().includes(q)) ||
    (u.storage_location && u.storage_location.toLowerCase().includes(q))
  );
});

const isUnitSelected = (unitId: number): boolean => {
  return tempSelectedUnitIds.value.includes(unitId);
};

const toggleUnitSelection = (unit: AvailableUnit) => {
  if (unit.is_locked) return;

  const idx = tempSelectedUnitIds.value.indexOf(unit.id);
  if (idx !== -1) {
    tempSelectedUnitIds.value.splice(idx, 1);
  } else {
    const max = props.item?.quantity_requested || 1;
    if (tempSelectedUnitIds.value.length >= max) {
      toast.warning(`Maksimal ${max} unit aset untuk barang ini.`);
      return;
    }
    tempSelectedUnitIds.value.push(unit.id);
  }
};

// DataTable Columns with identical styling and sorting as DaftarAsetTab
const columns = computed<ColumnDef<AvailableUnit>[]>(() => [
  {
    id: 'select',
    size: 50,
    header: () => h('div', { class: 'text-center flex items-center justify-center font-semibold text-foreground text-sm' }, 'Pilih'),
    cell: ({ row }) => {
      const unit = row.original;
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
      'Kode Aset',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { 
      class: 'font-mono text-muted-foreground font-medium text-sm select-none',
      onClick: () => toggleUnitSelection(row.original)
    }, row.getValue('asset_code'))
  },
  {
    accessorKey: 'lot_code',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kode LOT',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { 
      class: 'font-mono text-muted-foreground text-sm select-none',
      onClick: () => toggleUnitSelection(row.original)
    }, row.getValue('lot_code'))
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Status',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', {
      class: 'select-none',
      onClick: () => toggleUnitSelection(row.original)
    }, [
      h(StatusBadge, {
        status: row.getValue('status') as string,
        class: 'rounded-sm'
      })
    ])
  },
  {
    accessorKey: 'condition',
    header: ({ column }) => h(Button, {
      variant: 'ghost',
      onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      class: 'p-0 hover:bg-transparent font-semibold text-foreground justify-start'
    }, () => [
      'Kondisi',
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
      'Lokasi Penyimpanan',
      h(ArrowUpDown, { class: 'ml-2 h-3.5 w-3.5 text-muted-foreground' }),
    ]),
    cell: ({ row }) => h('div', { 
      class: 'text-muted-foreground text-sm select-none',
      onClick: () => toggleUnitSelection(row.original)
    }, row.getValue('storage_location'))
  }
]);

const saveAllocation = () => {
  const item = props.item;
  if (!item) return;

  isSavingAllocation.value = true;
  router.post(route('smart.fulfillment.items.assign', item.id), {
    unit_ids: tempSelectedUnitIds.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSavingAllocation.value = false;
      closeModal();
      emit('success');
      toast.success('Alokasi unit aset berhasil diperbarui.');
    },
    onError: (errs) => {
      isSavingAllocation.value = false;
      toast.error(Object.values(errs).join(', '));
    }
  });
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
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
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
            class="bg-card w-full md:max-w-[80%] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                Pilih Alokasi Aset
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] space-y-4">
              <!-- Selection Counter, Item Info & Search Filter -->
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="w-full sm:w-72">
                  <TableSearch 
                    v-model="unitSearchQuery" 
                    placeholder="Cari kode aset, LOT, lokasi..." 
                    bg-class="bg-background"
                  />
                </div>

                <div class="flex flex-col justify-center sm:items-end gap-0.5 sm:text-right">
                  <p class="text-xs text-muted-foreground">
                    <template v-if="displayBrand">{{ displayBrand }} </template>{{ item?.name && item.name !== 'Tidak Spesifik' ? item.name : item?.subcategory }} - Pilih maksimal {{ item?.quantity_requested }} unit aset.
                  </p>
                  <div class="text-xs font-semibold">
                    Terpilih: 
                    <span :class="tempSelectedUnitIds.length === (item?.quantity_requested || 0) ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold'">
                      {{ tempSelectedUnitIds.length }} / {{ item?.quantity_requested }} unit
                    </span>
                  </div>
                </div>
              </div>

              <!-- DataTable with DaftarAsetTab styling -->
              <div class="pb-1">
                <DataTable
                  :columns="columns"
                  :data="filteredAvailableUnits"
                  :page-size="10"
                  :show-selection-count="false"
                  :row-class="(row: any) => isUnitSelected(row.id) ? 'bg-primary/5 cursor-pointer' : 'cursor-pointer'"
                  :default-sorting="[{ id: 'asset_code', desc: false }]"
                />
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10">
              <Button 
                variant="white" 
                size="lg" 
                @click="closeModal"
              >
                Batal
              </Button>
              <Button 
                variant="primary" 
                size="lg" 
                :disabled="isSavingAllocation"
                @click="saveAllocation" 
                class="relative"
              >
                <Loader2 v-if="isSavingAllocation" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                <span :class="{ 'opacity-0': isSavingAllocation }">
                  Simpan Alokasi
                </span>
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
