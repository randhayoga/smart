<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DaftarStokHabisPakaiTab from './Tabs/DaftarStokHabisPakaiTab.vue';

interface Props {
  barangs?: any[];
  categories?: any[];
  subcategories?: any[];
  brands?: any[];
  uoms?: any[];
  lots?: any[];
  organizers?: any[];
  vendors?: any[];
  locations?: any[];
  floors?: any[];
  rooms?: any[];
  projects?: any[];
  selectedBarangCode?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  barangs: () => [],
  categories: () => [],
  subcategories: () => [],
  brands: () => [],
  uoms: () => [],
  lots: () => [],
  organizers: () => [],
  vendors: () => [],
  locations: () => [],
  floors: () => [],
  rooms: () => [],
  projects: () => [],
  selectedBarangCode: null,
});
</script>

<template>
  <AppLayout title="Daftar Stok (Habis Pakai)">
    <DaftarStokHabisPakaiTab
      :barangs="props.barangs"
      :categories="props.categories"
      :subcategories="props.subcategories"
      :brands="props.brands"
      :uoms="props.uoms"
      :lots="props.lots"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :locations="props.locations"
      :floors="props.floors"
      :rooms="props.rooms"
      :projects="props.projects"
      :selected-barang-code="props.selectedBarangCode"
    />
  </AppLayout>
</template>

