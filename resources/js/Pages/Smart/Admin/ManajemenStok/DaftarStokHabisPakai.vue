<script setup lang="ts">
/**
 * Admin Consumable Stock Inventory Page component displaying item balances, batch LOTs, and reorder levels.
 */
import { useI18n } from 'vue-i18n';
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
  projects: () => [],
  selectedBarangCode: null,
});

const { t } = useI18n();
</script>

<template>
  <AppLayout :title="t('inventory.consumableStockList')">
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
      :projects="props.projects"
      :selected-barang-code="props.selectedBarangCode"
    />
  </AppLayout>
</template>

