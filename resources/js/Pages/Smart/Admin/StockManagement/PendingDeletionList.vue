<script setup lang="ts">
/**
 * Admin Deletion Pending List Page component tracking assets awaiting approval (BoD/BoC and DM stages).
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Tabs from '@/Components/Tabs.vue';
import NonConsumableListTab from './Tabs/NonConsumableListTab.vue';
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
  { id: 'Pending:DM', label: 'Pending:DM' },
  { id: 'Pending:BoD/BoC', label: 'Pending:BoD/BoC' }
]);

const getInitialTab = (): string => {
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam === 'Pending:BoD/BoC') {
      return 'Pending:BoD/BoC';
    }
  }
  return 'Pending:DM';
};

const activeTab = ref(getInitialTab());

watch(activeTab, (val) => {
  if (typeof window !== 'undefined') {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', val);
    window.history.replaceState({}, '', url.toString());
  }
});

const currentStatusScope = computed(() => {
  return activeTab.value === 'Pending:BoD/BoC' ? 'pending:bod/boc' : 'pending:manager';
});

const handleCustomPrint = (items: any[]) => {
  printFormulirPersetujuanPenghapusan(items);
};
</script>

<template>
  <AppLayout :title="t('fulfillment.pendingDeletion')">
    <div class="space-y-1">
      <!-- Tabs header matching Master Data -->
      <Tabs v-model="activeTab" :tabs="computedTabs" />

      <!-- Content Tab (Table view matching Daftar Tidak Habis Pakai) -->
      <NonConsumableListTab
        :key="activeTab"
        :title="t('fulfillment.pendingDeletion')"
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
