<script setup lang="ts">
/**
 * Status Badge component mapping entity lifecycle statuses and conditions to visual pill styles.
 * Supports reactive bilingual localization (Bahasa Indonesia & English).
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';

const props = defineProps<{
  status?: string | null;
  proposedStatus?: string | null;
  class?: string;
}>();

const { t, te } = useI18n();

const STATUS_KEY_MAP: Record<string, string> = {
  'tersedia': 'status.tersedia',
  'dipinjam': 'status.dipinjam',
  'standby': 'status.standby',
  'tidak aktif': 'status.tidakAktif',
  'pending': 'status.pending',
  'pending:dm': 'status.pendingDm',
  'pending: dm': 'status.pendingDm',
  'bagus': 'status.bagus',
  'rusak': 'status.rusak',
  'qc passed': 'status.qcPassed',
  'lelang/hibah': 'status.lelangHibah',
  'rusak total': 'status.rusakTotal',
  'hilang': 'status.hilang',
  'dihapus': 'status.dihapus',
  'ditolak': 'status.ditolak',
  'rejected': 'status.ditolak',
  'disetujui': 'status.disetujui',
  'approved': 'status.disetujui',
  'sukses': 'status.sukses',
  'success': 'status.sukses',
  'menunggu approval': 'status.menungguApproval',
  'menunggu persetujuan': 'status.menungguApproval',
  'di-approve': 'status.diApprove',
  'diapprove': 'status.diApprove',
  'dikonfirmasi admin': 'status.dikonfirmasiAdmin',
  'dikonfirmasi': 'status.dikonfirmasiAdmin',
  'menunggu serah terima': 'status.menungguSerahTerima',
  'menunggu_serah_terima': 'status.menungguSerahTerima',
  'serah terima': 'status.serahTerima',
  'handover': 'status.serahTerima',
  'selesai': 'status.selesai',
  'dibatalkan': 'status.dibatalkan',
  'cancel': 'status.dibatalkan',
  'parsial': 'status.parsial',
  'partial': 'status.parsial',
};

const badgeClass = computed(() => {
  const s = props.status?.trim();
  if (!s) return 'bg-gray-100 text-gray-800';
  const lower = s.toLowerCase();

  if (s === 'Tersedia' || lower === 'tersedia') return 'bg-emerald-100 text-emerald-800';
  if (s === 'Dipinjam' || lower === 'dipinjam') return 'bg-amber-100 text-amber-800';
  if (s === 'Standby' || lower === 'standby') return 'bg-blue-100 text-blue-800';
  if (s === 'Tidak Aktif' || lower === 'tidak aktif') return 'bg-gray-200 text-gray-800';
  if (s === 'Pending' || lower === 'pending' || lower.startsWith('pending')) return 'bg-purple-100 text-purple-800';
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

const displayStatus = computed(() => {
  if (!props.status) return '';
  const s = props.status.trim().toLowerCase();
  const key = STATUS_KEY_MAP[s];
  if (key && te(key)) {
    return t(key);
  }
  return props.status;
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
    {{ displayStatus }}
  </span>
</template>
