<script setup lang="ts">
/**
 * Table Page Skeleton Component
 * Mimics the silhouette of Phase 1 table/list pages:
 * - Manajemen Stok
 * - Daftar Aset
 * - Stok Habis Pakai
 * - Pending Non-Aktif
 * - Daftar Karyawan & Pinjaman
 * - Master Data
 * - Permintaan Aktif / Lacak Peminjaman
 * - Arsip
 * - Jejak Audit
 * - Approval Status
 */
import { Skeleton } from '@/Components/ui/skeleton';

interface Props {
  hasBreadcrumbs?: boolean;
  hasTitle?: boolean;
  hasTabs?: boolean;
  tabCount?: number;
  filterCount?: number;
  rowCount?: number;
  hasActionButtons?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  hasBreadcrumbs: true,
  hasTitle: true,
  hasTabs: false,
  tabCount: 2,
  filterCount: 2,
  rowCount: 6,
  hasActionButtons: true,
});
</script>

<template>
  <div class="space-y-4">
    <!-- Breadcrumb Skeleton -->
    <div v-if="hasBreadcrumbs" class="flex items-center gap-2 pb-1">
      <Skeleton class="h-4 w-28 sm:w-36" />
      <span class="text-muted-foreground/40 text-xs">/</span>
      <Skeleton class="h-4 w-20 sm:w-24" />
    </div>

    <!-- Optional Page Heading -->
    <div v-if="hasTitle" class="pb-1">
      <Skeleton class="h-7 sm:h-8 w-48 sm:w-60" />
    </div>

    <!-- Optional Pill Tabs Skeleton (e.g. Master Data 7 tabs, Approval 2 tabs, Active Requests) -->
    <div v-if="hasTabs" class="inline-flex p-1 bg-muted/50 rounded-full gap-1 border border-border/50 overflow-x-auto max-w-full">
      <Skeleton 
        v-for="t in tabCount" 
        :key="t" 
        class="h-8 rounded-full shrink-0" 
        :class="t === 1 ? 'w-28' : 'w-24'" 
      />
    </div>

    <!-- Main Card Container -->
    <div class="p-4 sm:p-6 bg-card rounded-xl border border-border shadow-sm space-y-4">
      <!-- Section Title / Subtitle -->
      <div class="space-y-1">
        <Skeleton class="h-5 sm:h-6 w-44 sm:w-56" />
        <Skeleton class="h-3.5 w-64 sm:w-80 text-muted-foreground" />
      </div>

      <!-- Toolbar Row: Search, Filters, & Action Buttons -->
      <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
        <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[240px]">
          <!-- Search Input -->
          <Skeleton class="h-10 w-full sm:w-64 rounded-xl" />
          <!-- Dynamic Filter Dropdowns -->
          <Skeleton 
            v-for="f in filterCount" 
            :key="f" 
            class="h-10 w-36 sm:w-44 rounded-xl hidden sm:block" 
          />
        </div>

        <!-- Right Side Actions (e.g. Export / Tambah Button) -->
        <div v-if="hasActionButtons" class="flex items-center gap-2">
          <Skeleton class="h-10 w-24 rounded-xl hidden md:block" />
          <Skeleton class="h-10 w-32 rounded-xl" />
        </div>
      </div>

      <!-- Table Frame -->
      <div class="rounded-xl border border-border shadow-sm overflow-hidden bg-card">
        <!-- Table Header Skeleton -->
        <div class="bg-muted/50 px-4 py-3 border-b border-border flex items-center justify-between gap-4">
          <Skeleton class="h-4 w-4 rounded shrink-0" />
          <Skeleton class="h-4 w-20 shrink-0" />
          <Skeleton class="h-4 w-36 flex-1 max-w-xs" />
          <Skeleton class="h-4 w-24 hidden sm:block" />
          <Skeleton class="h-4 w-24 hidden md:block" />
          <Skeleton class="h-4 w-20 shrink-0" />
          <Skeleton class="h-4 w-16 shrink-0" />
        </div>

        <!-- Table Rows Skeleton -->
        <div 
          v-for="r in rowCount" 
          :key="r" 
          class="px-4 py-3.5 border-b border-border last:border-none flex items-center justify-between gap-4"
        >
          <!-- Checkbox -->
          <Skeleton class="h-4 w-4 rounded shrink-0" />
          <!-- Code / Barcode -->
          <Skeleton class="h-4 w-20 shrink-0" />
          <!-- Name / Description -->
          <div class="flex-1 max-w-xs space-y-1">
            <Skeleton class="h-4 w-3/4" />
            <Skeleton class="h-3 w-1/2 opacity-70" />
          </div>
          <!-- Category / Location -->
          <Skeleton class="h-4 w-24 hidden sm:block" />
          <!-- Badge / Status -->
          <Skeleton class="h-6 w-20 rounded-full hidden md:block" />
          <!-- Amount / Date -->
          <Skeleton class="h-4 w-16 shrink-0" />
          <!-- Action Icons -->
          <div class="flex items-center gap-1.5 shrink-0">
            <Skeleton class="h-8 w-8 rounded-lg" />
            <Skeleton class="h-8 w-8 rounded-lg hidden sm:block" />
          </div>
        </div>
      </div>

      <!-- Pagination Footer Skeleton -->
      <div class="flex items-center justify-between pt-2 px-1">
        <Skeleton class="h-4 w-32" />
        <div class="flex items-center gap-2">
          <Skeleton class="h-9 w-20 sm:w-24 rounded-[14px]" />
          <Skeleton class="h-9 w-20 sm:w-24 rounded-[14px]" />
        </div>
      </div>
    </div>
  </div>
</template>
