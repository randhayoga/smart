<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';
import Tabs from '@/Components/Tabs.vue';
import DaftarAsetTab from './Tabs/DaftarAsetTab.vue';
import { printFormulirPersetujuanPenghapusan } from '@/utils/printFormulirPersetujuanPenghapusan';

interface Props {
  units: any[];
  locations: { id: number; name: string; }[];
  floors: { id: number; name: string; location_id: number; }[];
  rooms: { id: number; name: string; floor_id: number; }[];
  organizers?: { id: number; name: string; }[];
  vendors?: { id: number; name: string; }[];
}

const props = defineProps<Props>();

const tabs = ['Pending:BoD/BoC', 'Pending:DM'];
const activeTab = ref('Pending:BoD/BoC');

const filteredUnits = computed(() => {
  return (props.units || []).filter(unit => {
    if (activeTab.value === 'Pending:BoD/BoC') {
      return unit.status === 'Pending:BoD/BoC' || unit.status === 'Pending';
    }
    if (activeTab.value === 'Pending:DM') {
      return unit.status === 'Pending:DM';
    }
    return unit.status === activeTab.value;
  });
});

const handleCustomPrint = (items: any[]) => {
  printFormulirPersetujuanPenghapusan(items);
};
</script>

<template>
  <AppLayout title="Daftar Pending Nonaktif">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory/pending-nonaktif">Daftar Pending Nonaktif</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-1">
      <!-- Tabs header matching Master Data -->
      <Tabs v-model="activeTab" :tabs="tabs" />

      <!-- Content Tab (Table view matching Daftar Aset) -->
      <DaftarAsetTab
        :key="activeTab"
        :units="filteredUnits"
        :locations="props.locations"
        :floors="props.floors"
        :rooms="props.rooms"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :hide-status-filter="true"
        :custom-print-handler="handleCustomPrint"
      />
    </div>
  </AppLayout>
</template>
