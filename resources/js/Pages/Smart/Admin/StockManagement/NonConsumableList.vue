<script setup lang="ts">
/**
 * Admin Non-Consumable Inventory List Page component displaying asset units, serial numbers, locations, and condition filters.
 */
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import NonConsumableListTab from './Tabs/NonConsumableListTab.vue';
import type { CompactUnitsPayload } from './utils/hydrateCompactUnits';

interface Props {
  units?: any[];
  units_compact?: CompactUnitsPayload;
  locations: any[];
  organizers: { id: number; name: string }[];
  vendors: { id: number; name: string }[];
  projects?: { id: number; no_project: string; project_name: string; client_id?: string }[];
  users?: { id: number; name: string }[];
}

const props = defineProps<Props>();
const { t } = useI18n();
</script>

<template>
  <AppLayout :title="t('inventory.nonConsumableList')">
    <NonConsumableListTab
      :units="props.units"
      :units-compact="props.units_compact"
      :locations="props.locations"
      :organizers="props.organizers"
      :vendors="props.vendors"
      :projects="props.projects"
      :users="props.users"
      status-scope="standard"
    />
  </AppLayout>
</template>
