<script setup lang="ts">
/**
 * Status Badge component mapping entity lifecycle statuses and conditions to visual pill styles.
 * Supports multi-status comma-separated strings and reactive bilingual localization (Bahasa Indonesia & English).
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';
import {
  parseAssetStatuses,
  getAssetStatusBadgeClass,
  getAssetStatusLabel,
} from '@/lib/assetStatus';

const props = defineProps<{
  status?: string | null;
  approvalStatus?: string | null;
  proposedStatus?: string | null;
  class?: string;
}>();

const { t, te } = useI18n();

const statusTokens = computed(() => {
  const tokens = parseAssetStatuses(props.status, props.approvalStatus);
  return tokens.length > 0 ? tokens : [''];
});

const badgeItems = computed(() =>
  statusTokens.value.map((token) => ({
    raw: token,
    label: getAssetStatusLabel(token, t, te),
    badgeClass: getAssetStatusBadgeClass(token),
  }))
);
</script>

<template>
  <span
    v-if="badgeItems.length <= 1"
    :class="cn(
      'inline-flex items-center px-2 py-0.5 rounded-md font-semibold',
      badgeItems[0]?.badgeClass,
      props.class
    )"
  >
    {{ badgeItems[0]?.label }}
  </span>
  <span v-else class="inline-flex flex-wrap items-center gap-1.5">
    <span
      v-for="(item, idx) in badgeItems"
      :key="`${item.raw}-${idx}`"
      :class="cn(
        'inline-flex items-center px-2 py-0.5 rounded-md font-semibold',
        item.badgeClass,
        props.class
      )"
    >
      {{ item.label }}
    </span>
  </span>
</template>
