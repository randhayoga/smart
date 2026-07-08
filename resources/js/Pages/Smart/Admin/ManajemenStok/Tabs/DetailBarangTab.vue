<script setup lang="ts">
import { computed } from 'vue';
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
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();

const totalStok = computed(() => {
  const isConsumable = props.barang.is_consumable;
  return (props.lots || []).reduce((acc, lot) => {
    const qty = isConsumable ? (lot.current_quantity ?? 0) : (lot.assetCount ?? 0);
    return acc + Number(qty);
  }, 0);
});
</script>

<template>
  <div class="space-y-4">
    <!-- Detail Barang Card -->
    <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">Detail Tipe</h2>
      
      <div class="flex flex-col md:flex-row gap-6">
        <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
          <img v-if="props.barang.image_url" :src="'/storage/' + props.barang.image_url" class="w-full h-full object-cover" />
          <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
        </div>

        <div class="flex-grow">
          <p class="font-bold text-foreground"><span class="text-foreground">Kode Tipe:</span> {{ props.barang.code }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.barang.brand }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.barang.name }}</p>
          <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.barang.specification || '-' }}</p>
          <p class="text-foreground">Kategori: {{ props.barang.category }}</p>
          <p class="text-foreground">Subkategori: {{ props.barang.subcategory }}</p>
          <p class="text-foreground">Jumlah LOT: {{ props.lots.length }}</p>
          <p class="text-foreground">Total stok: {{ totalStok }} {{ props.barang.uom }}</p>
          <p class="text-foreground">Satuan: {{ props.barang.uom }}</p>
          <p class="text-foreground">Pembaruan terakhir: {{ props.barang.lastUpdate }}</p>
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
      :floors="props.floors"
      :rooms="props.rooms"
      :projects="props.projects"
    />
  </div>
</template>
