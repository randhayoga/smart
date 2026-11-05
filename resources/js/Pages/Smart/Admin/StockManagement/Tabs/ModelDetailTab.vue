<script setup lang="ts">
/**
 * Detail Barang Tab component displaying item specification overview, aggregate stock metrics, and embedded lot table.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetPhotoGrid from '@/Components/AssetPhotoGrid.vue';
import LotListTab from './LotListTab.vue';

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
    category_code?: string;
    brand_id: number;
    uom_id: number;
    is_consumable: boolean;
    min_stock_threshold?: number | null;
  };
  lots: {
    id: number;
    number: string;
    po_number?: string | null;
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
  units?: any[];
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
const isCompCategory = computed(() => {
  return props.barang.category_code === 'COMP' ||
    props.barang.category === 'Computer' ||
    props.barang.category === 'COMP';
});

const distinctPhotos = computed(() => {
  if (props.barang?.image_url) return [props.barang.image_url];
  const photos: string[] = [];
  const seen = new Set<string>();
  for (const u of props.units || []) {
    if (u.image_url && !seen.has(u.image_url)) {
      seen.add(u.image_url);
      photos.push(u.image_url);
      if (photos.length === 4) break;
    }
  }
  return photos;
});
</script>

<template>
  <div class="space-y-4">
    <!-- Detail Barang Card -->
    <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">{{ t('inventory.typeDetail') }}</h2>
      
      <div class="flex flex-col md:flex-row gap-6">
        <AssetPhotoGrid :photos="distinctPhotos" />

        <div class="flex-grow flex flex-col gap-1.5 text-foreground">
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.brand') }}:</span> {{ props.barang.brand }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.name') }}:</span> {{ props.barang.name }}</p>
          <p v-if="!isCompCategory" class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.specification') }}:</span> {{ props.barang.specification || '-' }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">{{ t('inventory.category') }}:</span> {{ props.barang.category }}</p>
          <p class="text-foreground">{{ t('inventory.subcategory') }}: {{ props.barang.subcategory }}</p>
          <p class="text-foreground">{{ t('inventory.lotCount') }}: {{ props.lots.length }}</p>
          <p :class="stockColorClass">{{ t('inventory.totalStock') }}: {{ totalStok }} {{ props.barang.uom }}</p>
          <p v-if="props.barang.min_stock_threshold !== null && props.barang.min_stock_threshold !== undefined" class="text-foreground">{{ t('inventory.minStockThreshold') }}: {{ props.barang.min_stock_threshold }} {{ props.barang.uom }}</p>
          <p class="text-foreground">{{ t('inventory.uom') }}: {{ props.barang.uom }}</p>
          <p class="text-foreground">{{ t('inventory.lastUpdate') }}: {{ props.barang.lastUpdate }}</p>
        </div>
      </div>
    </div>

    <!-- Lot List Tab -->
    <LotListTab
      :barang="props.barang"
      :lots="props.lots"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :projects="props.projects"
    />
  </div>
</template>
