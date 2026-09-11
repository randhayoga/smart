<script setup lang="ts">
/**
 * Detail Barang Tab component displaying item specification overview, aggregate stock metrics, and embedded lot table.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DaftarLOTTab from './DaftarLOTTab.vue';

interface Props {
  barang: {
    id: number;
    code: string;
    category: string;
    subcategory: string;
    brand: string;
    name: string;
    specification: string;
    lastUpdate: string;
    amount: number;
    image_url: string | null;
    uom: string;
    subcategory_id: number;
    category_id: number;
    brand_id: number;
    uom_id: number;
    is_consumable: boolean;
    min_stock_threshold?: number | null;
  };
  lots: {
    id: number;
    number: string;
    po_number: string;
    date_of_receipt: string;
    organizer: string;
    organizer_id: number;
    vendor: string;
    vendor_id: number;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    unitPrice: number | string;
    imageUrl: string;
    assetCount: number;
    availableAssetCount: number;
    initial_quantity?: number | null;
    current_quantity?: number | null;
    updated_at: string;
  }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: any[];
  floors?: any[];
  rooms?: any[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();

const { t } = useI18n();

const totalStok = computed(() => {
  const isConsumable = props.barang.is_consumable;
  return (props.lots || []).reduce((acc, lot) => {
    const qty = isConsumable ? (lot.current_quantity ?? 0) : (lot.assetCount ?? 0);
    return acc + Number(qty);
  }, 0);
});

const stockColorClass = computed(() => {
  const threshold = props.barang.min_stock_threshold !== null && props.barang.min_stock_threshold !== undefined
    ? Number(props.barang.min_stock_threshold)
    : null;

  if (totalStok.value === 0) {
    return 'text-red-600 font-semibold';
  }
  if (threshold !== null && totalStok.value <= threshold) {
    return 'text-amber-600 font-semibold';
  }
  return 'text-foreground';
});
</script>

<template>
  <div class="space-y-4">
    <!-- Detail Barang Card -->
    <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">{{ t('inventory.typeDetail') }}</h2>
      
      <div class="flex flex-col md:flex-row gap-6">
        <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
          <img v-if="props.barang.image_url" :src="'/media/' + props.barang.image_url" class="w-full h-full object-cover" />
          <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
        </div>

        <div class="flex-grow">
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.typeCode') }}:</span> {{ props.barang.code }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.brand') }}:</span> {{ props.barang.brand }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.name') }}:</span> {{ props.barang.name }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.specification') }}:</span> {{ props.barang.specification || '-' }}</p>
          <p class="text-foreground">{{ t('inventory.category') }}: {{ props.barang.category }}</p>
          <p class="text-foreground">{{ t('inventory.subcategory') }}: {{ props.barang.subcategory }}</p>
          <p class="text-foreground">{{ t('inventory.lotCount') }}: {{ props.lots.length }}</p>
          <p :class="stockColorClass">{{ t('inventory.totalStock') }}: {{ totalStok }} {{ props.barang.uom }}</p>
          <p v-if="props.barang.min_stock_threshold !== null && props.barang.min_stock_threshold !== undefined" class="text-foreground">{{ t('inventory.minStockThreshold') }}: {{ props.barang.min_stock_threshold }} {{ props.barang.uom }}</p>
          <p class="text-foreground">{{ t('inventory.uom') }}: {{ props.barang.uom }}</p>
          <p class="text-foreground">{{ t('inventory.lastUpdate') }}: {{ props.barang.lastUpdate }}</p>
        </div>
      </div>
    </div>

    <!-- Daftar LOT Tab -->
    <DaftarLOTTab
      :barang="props.barang"
      :lots="props.lots"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :projects="props.projects"
    />
  </div>
</template>
