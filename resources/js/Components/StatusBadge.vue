<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  status?: string | null;
  proposedStatus?: string | null;
  class?: string;
}>();

const badgeClass = computed(() => {
  const s = props.status;
  if (s === 'Tersedia') return 'bg-emerald-100 text-emerald-800';
  if (s === 'Dipinjam') return 'bg-amber-100 text-amber-800';
  if (s === 'Perbaikan') return 'bg-blue-100 text-blue-800';
  if (s === 'Rusak Total') return 'bg-red-100 text-red-800';
  if (s === 'Hilang') return 'bg-rose-100 text-rose-800';
  if (s === 'Tidak Aktif') return 'bg-gray-200 text-gray-800';
  if (s === 'Pending') return 'bg-purple-100 text-purple-800';
  if (s === 'Dihapus') return 'bg-neutral-950 text-neutral-50';
  return 'bg-gray-100 text-gray-800';
});

const displayText = computed(() => {
  if (props.status === 'Pending' && props.proposedStatus) {
    return `Pending: ${props.proposedStatus}`;
  }
  return props.status || '';
});
</script>

<template>
  <span
    :class="cn(
      'inline-flex items-center px-2 py-0.5 rounded-md font-semibold',
      badgeClass,
      props.class
    )"
  >
    {{ displayText }}
  </span>
</template>
