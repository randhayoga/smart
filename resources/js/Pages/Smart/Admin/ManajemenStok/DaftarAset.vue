<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DaftarAsetTab from './Tabs/DaftarAsetTab.vue';

interface Props {
  units: {
    id: number;
    number: string;
    status: string;
    proposed_status: string | null;
    doc_url: string | null;
    condition: string;
    price: number | string;
    image_url: string;
    vehicle_registration: string | null;
    updated_at: string;
    
    // Location info
    location: string;
    location_id: number;
    floor: string | null;
    floor_id: number | null;
    room: string | null;
    room_id: number | null;

    // Parent lot info
    lot_id: number;
    lot_number: string;
    lot_imageUrl: string | null;
    lot_unitPrice: number | string | null;
    organizer: string;
    organizer_id: number | null;
    vendor: string;
    vendor_id: number | null;
    lot_organizer: string;
    lot_date_of_receipt: string | null;
    lot_vendor: string;
    lot_po_number: string;

    // Parent barang info
    barang_id: number;
    barang_code: string;
    barang_nama: string;
    barang_brand: string;
    barang_specification: string;
    barang_category: string;
    barang_subcategory: string;
    barang_uom: string;
  }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
}

const props = defineProps<Props>();
</script>

<template>
  <AppLayout title="Daftar Aset">
    <DaftarAsetTab
      :units="props.units"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      :organizers="props.organizers"
      :vendors="props.vendors"
    />
  </AppLayout>
</template>
