<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  status?: string | null;
  proposedStatus?: string | null;
  class?: string;
}>();

const badgeClass = computed(() => {
  const s = props.status?.trim();
  if (!s) return 'bg-gray-100 text-gray-800';
  const lower = s.toLowerCase();

  if (s === 'Tersedia' || lower === 'tersedia') return 'bg-emerald-100 text-emerald-800';
  if (s === 'Dipinjam' || lower === 'dipinjam') return 'bg-amber-100 text-amber-800';
  if (s === 'Standby' || lower === 'standby') return 'bg-blue-100 text-blue-800';
  if (s === 'Tidak Aktif' || lower === 'tidak aktif') return 'bg-gray-200 text-gray-800';
  if (s === 'Pending' || lower === 'pending') return 'bg-purple-100 text-purple-800';
  if (s === 'Bagus' || lower === 'bagus') return 'bg-emerald-100 text-emerald-800';
  if (s === 'Rusak' || lower === 'rusak') return 'bg-rose-100 text-rose-800';
  if (s === 'QC Passed' || lower === 'qc passed') return 'bg-sky-100 text-sky-800';
  if (s === 'Lelang/Hibah' || lower === 'lelang/hibah') return 'bg-indigo-100 text-indigo-800';
  if (s === 'Rusak Total' || lower === 'rusak total') return 'bg-red-100 text-red-800';
  if (s === 'Hilang' || lower === 'hilang') return 'bg-rose-100 text-rose-800';
  if (lower === 'dihapus' || lower === 'ditolak' || lower === 'rejected' || lower === 'cancel' || lower === 'dibatalkan') return 'bg-rose-100 text-rose-800';
  if (lower === 'disetujui' || lower === 'approved' || lower === 'sukses' || lower === 'success') return 'bg-emerald-100 text-emerald-800';
  return 'bg-gray-100 text-gray-800';
});

const displayText = computed(() => {
  if (props.status === 'Pending' && props.proposedStatus) {
    return `${props.status}: ${props.proposedStatus}`;
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
