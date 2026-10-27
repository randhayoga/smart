<script setup lang="ts">
/**
 * Admin Pending Activation List Page component tracking newly created assets awaiting IFS Manager approval.
 */
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import NonConsumableListTab from './Tabs/NonConsumableListTab.vue';

interface Props {
  units: any[];
  locations: any[];
  organizers?: { id: number; name: string; }[];
  vendors?: { id: number; name: string; }[];
}

const props = defineProps<Props>();
const { t } = useI18n();
</script>

<template>
  <AppLayout :title="t('fulfillment.pendingActivation')">
    <!-- ── Title Halaman ── -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900 leading-none">{{ t('fulfillment.pendingActivation') }}</h1>
    </div>

    <NonConsumableListTab
      :units="props.units"
      :locations="props.locations"
      :organizers="props.organizers"
      :vendors="props.vendors"
      status-scope="unverified"
      :hide-status-filter="true"
      :hide-condition-filter="true"
      :hide-actions="true"
      :hide-export="false"
      :cardless="true"
      :hide-header="true"
    />
  </AppLayout>
</template>
