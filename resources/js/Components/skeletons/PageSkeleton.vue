<script setup lang="ts">
/**
 * Page Skeleton Dispatcher Component
 * Inspects the current or target pathname and dynamically renders the most visually accurate
 * skeleton archetype (Dashboard, TablePage, DetailPage, or BarcodeScanner).
 */
import { computed } from 'vue';
import DashboardSkeleton from './DashboardSkeleton.vue';
import TablePageSkeleton from './TablePageSkeleton.vue';
import DetailPageSkeleton from './DetailPageSkeleton.vue';
import BarcodeScannerSkeleton from './BarcodeScannerSkeleton.vue';

interface Props {
  path?: string;
}

const props = withDefaults(defineProps<Props>(), {
  path: '',
});

type SkeletonType = 'dashboard' | 'scanner' | 'detail' | 'master-table' | 'approval-table' | 'requests-table' | 'inventory-table' | 'karyawan-table' | 'general-table';

const skeletonType = computed<SkeletonType>(() => {
  const cleanPath = (props.path || '').split('?')[0].replace(/\/$/, '');

  // 1. Dashboard
  if (cleanPath.endsWith('/dashboard') || cleanPath === '/smart' || cleanPath === '') {
    return 'dashboard';
  }

  // 2. Barcode Camera Scanner (exact /smart/scan)
  if (cleanPath === '/smart/scan') {
    return 'scanner';
  }

  // 3. Detail Views (URLs with dynamic IDs or sub-detail paths)
  if (
    cleanPath.startsWith('/smart/scan/') ||
    /\/smart\/inventory\/lots\/[^\/]+$/.test(cleanPath) ||
    /\/smart\/inventory\/\d+$/.test(cleanPath) ||
    /\/smart\/borrowed\/\d+$/.test(cleanPath) ||
    /\/smart\/arsip\/\d+$/.test(cleanPath)
  ) {
    return 'detail';
  }

  // 4. Employee List (Daftar Karyawan)
  if (cleanPath.includes('/karyawan')) {
    return 'karyawan-table';
  }

  // 5. Master Data (7 tabs)
  if (cleanPath === '/smart/master') {
    return 'master-table';
  }

  // 6. Manager Approval Status (2 tabs: Perlu Approve & Sudah Approve)
  if (cleanPath.includes('/approve-status')) {
    return 'approval-table';
  }

  // 7. Active Requests (Lacak Peminjaman tab)
  if (cleanPath.includes('/requests') || cleanPath.includes('/borrowed')) {
    return 'requests-table';
  }

  // 8. Inventory List Views (Multi-combobox filters)
  if (cleanPath.includes('/inventory')) {
    return 'inventory-table';
  }

  // 9. General Table fallback (Arsip, Jejak Audit, etc.)
  return 'general-table';
});
</script>

<template>
  <div>
    <!-- Dashboard Skeleton -->
    <DashboardSkeleton v-if="skeletonType === 'dashboard'" />

    <!-- Barcode Scanner Skeleton -->
    <BarcodeScannerSkeleton v-else-if="skeletonType === 'scanner'" />

    <!-- Detail Page Skeleton -->
    <DetailPageSkeleton v-else-if="skeletonType === 'detail'" />

    <!-- Employee Table Skeleton (matches DaftarKaryawan: no breadcrumbs, 1 combobox filter) -->
    <TablePageSkeleton
      v-else-if="skeletonType === 'karyawan-table'"
      :has-breadcrumbs="false"
      :has-title="false"
      :has-tabs="false"
      :filter-count="1"
    />

    <!-- Master Data Table Skeleton (7 tabs) -->
    <TablePageSkeleton
      v-else-if="skeletonType === 'master-table'"
      :has-tabs="true"
      :tab-count="7"
      :filter-count="1"
    />

    <!-- Approval Status Table Skeleton (2 tabs) -->
    <TablePageSkeleton
      v-else-if="skeletonType === 'approval-table'"
      :has-tabs="true"
      :tab-count="2"
      :filter-count="1"
    />

    <!-- Active Requests Table Skeleton (1 tab) -->
    <TablePageSkeleton
      v-else-if="skeletonType === 'requests-table'"
      :has-tabs="true"
      :tab-count="1"
      :filter-count="2"
    />

    <!-- Inventory Table Skeleton (3 filters: brand, type, rows) -->
    <TablePageSkeleton
      v-else-if="skeletonType === 'inventory-table'"
      :has-tabs="false"
      :filter-count="3"
    />

    <!-- General Table Fallback Skeleton (Karyawan, Arsip, Audit) -->
    <TablePageSkeleton
      v-else
      :has-tabs="false"
      :filter-count="2"
    />
  </div>
</template>
