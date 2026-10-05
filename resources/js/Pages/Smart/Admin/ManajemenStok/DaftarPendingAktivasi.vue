<script setup lang="ts">
/**
 * Admin Pending Activation List Page component tracking newly created assets awaiting IFS Manager approval.
 */
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbItem } from '@/Components/ui/breadcrumb';
import DaftarAsetTab from './Tabs/DaftarAsetTab.vue';

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
    <Breadcrumb>
      <BreadcrumbList class="pb-3">
        <BreadcrumbItem>
          <BreadcrumbLink href="/smart/inventory/pending-aktivasi">{{ t('fulfillment.pendingActivation') }}</BreadcrumbLink>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div class="space-y-1">
      <DaftarAsetTab
        :units="props.units"
        :locations="props.locations"
        :organizers="props.organizers"
        :vendors="props.vendors"
        status-scope="unverified"
        :hide-status-filter="true"
        :hide-condition-filter="true"
        :hide-actions="true"
        :hide-export="false"
      />
    </div>
  </AppLayout>
</template>
