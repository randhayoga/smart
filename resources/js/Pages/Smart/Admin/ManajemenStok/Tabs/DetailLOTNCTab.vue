<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Plus } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import CreateAssetModal from '../Modals/CreateAssetModal.vue';
import DaftarAsetTab from './DaftarAsetTab.vue';

interface Props {
  lot: {
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
    initial_quantity?: number | null;
    current_quantity?: number | null;
    age?: number | null;
    burden?: string;
    project_id?: number | null;
    project_name?: string | null;
    project_no?: string | null;
    updated_at: string;
    
    // Parent barang info
    barang_id: number;
    barang_code: string;
    barang_brand: string;
    barang_nama: string;
    barang_specification: string;
    barang_category: string;
    barang_subcategory: string;
    barang_uom: string;
  };
  units: {
    id: number;
    number: string;
    status: string;
    condition: string;
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    updated_at: string;
  }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  organizers?: { id: number; name: string; }[];
  vendors?: { id: number; name: string; }[];
}

const props = defineProps<Props>();

const isCreateAssetModalOpen = ref(false);

const openCreateAssetModal = () => {
  isCreateAssetModalOpen.value = true;
};

const handleAssetSuccess = () => {
  // Page reload happens via Inertia, nothing else is needed here
};

const formatRupiah = (val: number | string | null | undefined) => {
  if (val === null || val === undefined) return '-';
  const num = typeof val === 'string' ? parseFloat(val) : val;
  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
};

const formatLocation = (loc: string | null, floor: string | null, room: string | null) => {
  let parts: string[] = [];
  if (loc) parts.push(loc);
  if (floor) parts.push(floor);
  if (room) parts.push(room);
  return parts.join(' - ');
};

const formatDateWithDashes = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-';
  if (dateStr.includes('-') && dateStr.indexOf('-') === 4) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr.replace(/\//g, '-');
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isCreateAssetModalOpen.value) {
      isCreateAssetModalOpen.value = false;
    }
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <div class="space-y-4">
    <!-- Detail LOT Card -->
    <div class="px-4 py-3 bg-card rounded-xl border border-border shadow-sm overflow-hidden no-print">
      <h2 class="text-lg font-bold text-foreground mb-4">Detail LOT</h2>
      
      <div class="flex flex-col md:flex-row gap-6">
        <div class="w-48 h-48 rounded-xl bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border">
          <img v-if="props.lot.imageUrl" :src="'/storage/' + props.lot.imageUrl" class="w-full h-full object-cover" />
          <img v-else src="https://placehold.co/400x400?text=Placeholder" class="w-full h-full object-cover opacity-50" />
        </div>

        <div class="flex-grow grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-4">
            <p class="font-bold text-foreground"><span class="text-foreground">Kode Barang:</span> {{ props.lot.barang_code }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Merek:</span> {{ props.lot.barang_brand }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Nama:</span> {{ props.lot.barang_nama }}</p>
            <p class="font-bold text-foreground"><span class="text-foreground">Spesifikasi:</span> {{ props.lot.barang_specification }}</p>
            <p class="text-foreground">Kategori: {{ props.lot.barang_category }}</p>
            <p class="text-foreground">Subkategori: {{ props.lot.barang_subcategory }}</p>
            <p class="text-foreground">Satuan: {{ props.lot.barang_uom }}</p>
          </div>
          <div class="md:col-span-8">
            <p class="font-bold text-foreground"><span class="text-foreground">Kode LOT:</span> {{ props.lot.number }}</p>
            <p class="text-foreground">Jumlah stok tersedia: {{ props.units.filter(u => u.status === 'Tersedia').length }}</p>
            <p class="text-foreground">Jumlah stok diawal: {{ props.units.length }}</p>
            <p class="text-foreground">Lokasi <span class="italic">default</span>: {{ formatLocation(props.lot.location, props.lot.floor, props.lot.room) }}</p>
            <p class="text-foreground">Nomor PO: {{ props.lot.po_number }}</p>
            <p class="text-foreground">Tanggal registrasi: {{ formatDateWithDashes(props.lot.date_of_receipt) }}</p>
            <p class="text-foreground">Umur: {{ props.lot.age !== undefined && props.lot.age !== null ? `${props.lot.age} tahun` : '-' }}</p>
            <p class="text-foreground">Harga satuan <span class="italic">default</span>: {{ formatRupiah(props.lot.unitPrice) }}</p>
            <p class="text-foreground">Pembebanan: {{ props.lot.burden || '-' }}</p>
            <p v-if="props.lot.burden === 'Project'" class="text-foreground">Proyek: {{ props.lot.project_no ? `${props.lot.project_no} - ${props.lot.project_name || '-'}` : '-' }}</p>
            <p class="text-foreground">Organizer: {{ props.lot.organizer }}</p>
            <p class="text-foreground">Vendor: {{ props.lot.vendor }}</p>
            <p class="text-foreground">Pembaruan terakhir: {{ props.lot.updated_at }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Daftar Aset Card using the unified component -->
    <DaftarAsetTab
      :units="props.units"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :hide-barang-columns="true"
      :lot="props.lot"
      :barang="{ category: props.lot.barang_category }"
      filter-variant="simple"
    >
      <template #extra-actions>
        <Button @click="openCreateAssetModal" variant="primary" size="lg">
          <Plus class="w-4 h-4" />
          <span>Aset Baru</span>
        </Button>
      </template>
    </DaftarAsetTab>
  </div>

  <CreateAssetModal
    v-model:open="isCreateAssetModalOpen"
    :lot="props.lot"
    :units="props.units"
    :barang="{ category: props.lot.barang_category }"
    :locations="props.locations"
    :floors="props.floors"
    :rooms="props.rooms"
    @success="handleAssetSuccess"
  />
</template>
