<script setup lang="ts">
/**
 * Admin Asset Inventory List Page component displaying asset units, serial numbers, locations, and condition filters.
 */
import { useI18n } from 'vue-i18n';
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
  locations: any[];
  organizers: { id: number; name: string; }[];
  vendors: { id: number; name: string; }[];
  users?: { id: number; name: string; }[];
}

const props = defineProps<Props>();
const { t } = useI18n();
</script>

<template>
  <AppLayout :title="t('inventory.assetList')">
    <DaftarAsetTab
      :units="props.units"
      :locations="props.locations"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :users="props.users"
    />
  </AppLayout>
</template>
