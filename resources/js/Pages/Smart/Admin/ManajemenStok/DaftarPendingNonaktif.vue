<script setup lang="ts">
/**
 * Admin Deactivation Pending List Page component tracking assets awaiting approval (BoD/BoC and DM stages).
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';
import Tabs from '@/Components/Tabs.vue';
import DaftarAsetTab from './Tabs/DaftarAsetTab.vue';
import { printFormulirPersetujuanPenghapusan } from '@/utils/printFormulirPersetujuanPenghapusan';

interface Props {
  units: any[];
  locations: any[];
  organizers?: { id: number; name: string; }[];
  vendors?: { id: number; name: string; }[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const computedTabs = computed(() => [
  { id: 'Pending:BoD/BoC', label: 'Pending:BoD/BoC' },
  { id: 'Pending:DM', label: 'Pending:DM' }
]);
const activeTab = ref('Pending:BoD/BoC');

const currentStatusScope = computed(() => {
  return activeTab.value === 'Pending:BoD/BoC' ? 'pending:bod/boc' : 'pending:manager';
});

const handleCustomPrint = (items: any[]) => {
  printFormulirPersetujuanPenghapusan(items);
};
</script>

<template>
  <AppLayout :title="t('fulfillment.pendingDeactivation')">
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory/pending-nonaktif">{{ t('fulfillment.pendingDeactivation') }}</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-1">
      <!-- Tabs header matching Master Data -->
      <Tabs v-model="activeTab" :tabs="computedTabs" />

      <!-- Content Tab (Table view matching Daftar Aset) -->
      <DaftarAsetTab
        :key="activeTab"
        :units="props.units"
        :locations="props.locations"
        :organizers="props.organizers"
        :vendors="props.vendors"
        :status-scope="currentStatusScope"
        :hide-status-filter="true"
        :hide-export="activeTab === 'Pending:DM'"
        :custom-print-handler="handleCustomPrint"
      />
    </div>
  </AppLayout>
</template>
