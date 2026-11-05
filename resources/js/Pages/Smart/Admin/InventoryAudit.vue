<script setup lang="ts">
/**
 * Dedicated Inventory Logs Audit Trail Page component.
 * Uses reusable InventoryAuditCard inside AppLayout.
 */
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import InventoryAuditCard, { type InventoryLogItem } from '@/Pages/Smart/Admin/StockManagement/Tabs/components/InventoryAuditCard.vue';

const props = defineProps<{
  logs: InventoryLogItem[];
}>();

const { t } = useI18n();
</script>

<template>
  <AppLayout :title="t('admin.inventoryAuditTitle')">
    <div class="space-y-4">
      <InventoryAuditCard :logs="props.logs" />
    </div>
  </AppLayout>
</template>
