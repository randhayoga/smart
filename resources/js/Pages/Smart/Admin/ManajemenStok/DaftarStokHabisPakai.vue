<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DaftarStokHabisPakaiTab from './Tabs/DaftarStokHabisPakaiTab.vue';

interface Props {
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
    initial_quantity: number | null;
    current_quantity: number | null;
    updated_at: string;
    
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
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  projects: { id: number; no_project: string; project_name: string; client_id: string; }[];
}

const props = defineProps<Props>();
</script>

<template>
  <AppLayout title="Daftar Stok (Habis Pakai)">
    <DaftarStokHabisPakaiTab
      :lots="props.lots"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      :projects="props.projects"
    />
  </AppLayout>
</template>
