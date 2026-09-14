<script setup lang="ts">
/**
 * Detail Page Skeleton Component
 * Mimics the silhouette of Phase 1 detail and inspect views:
 * - Detail Barang (/smart/inventory/{barang})
 * - Detail LOT Consumables & Non-Consumables (/smart/inventory/lots/{lot})
 * - Detail Lacak Peminjaman (/smart/borrowed/{id})
 * - Detail Arsip (/smart/arsip/{id})
 * - Hasil Pindai Barcode (/smart/scan/{unit})
 */
import { Skeleton } from '@/Components/ui/skeleton';
import { Card, CardHeader, CardContent } from '@/Components/ui/card';
</script>

<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Back Action Skeleton -->
    <div class="flex items-center justify-between gap-4 pb-1">
      <div class="flex items-center gap-2">
        <Skeleton class="h-4 w-28" />
        <span class="text-muted-foreground/40 text-xs">/</span>
        <Skeleton class="h-4 w-32" />
        <span class="text-muted-foreground/40 text-xs">/</span>
        <Skeleton class="h-4 w-24" />
      </div>
      <Skeleton class="h-9 w-24 rounded-xl hidden sm:block" />
    </div>

    <!-- Main Detail Overview Card -->
    <Card class="rounded-xl border border-border shadow-sm overflow-hidden">
      <CardHeader class="p-4 sm:p-6 border-b border-border space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="space-y-1.5 flex-1 min-w-[200px]">
            <Skeleton class="h-7 sm:h-8 w-56 sm:w-72" />
            <Skeleton class="h-4 w-36 sm:w-48 text-muted-foreground" />
          </div>
          <div class="flex items-center gap-2">
            <Skeleton class="h-7 w-24 rounded-full" />
            <Skeleton class="h-9 w-28 rounded-xl" />
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-4 sm:p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left 2 Cols: Attribute Grid -->
          <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-for="i in 6" :key="i" class="p-3.5 rounded-lg border border-border/60 bg-muted/20 space-y-1.5">
              <Skeleton class="h-3.5 w-24" />
              <Skeleton class="h-5 w-36 sm:w-44" />
            </div>
          </div>

          <!-- Right Col: Image / QR Preview Box -->
          <div class="lg:col-span-1 flex flex-col items-center justify-center p-6 rounded-xl border border-dashed border-border bg-muted/10 min-h-[180px] space-y-3">
            <Skeleton class="h-32 w-32 sm:h-36 sm:w-36 rounded-lg" />
            <Skeleton class="h-4 w-28" />
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Sub-table / Related Items Card (e.g. Lots, Units, or Loan Items) -->
    <div class="p-4 sm:p-6 bg-card rounded-xl border border-border shadow-sm space-y-4">
      <div class="flex items-center justify-between">
        <Skeleton class="h-5 sm:h-6 w-36 sm:w-44" />
        <Skeleton class="h-9 w-28 rounded-xl hidden sm:block" />
      </div>

      <!-- Sub-table Frame -->
      <div class="rounded-xl border border-border shadow-sm overflow-hidden bg-card">
        <!-- Table Header Skeleton -->
        <div class="bg-muted/50 px-4 py-3 border-b border-border flex items-center justify-between gap-4">
          <Skeleton class="h-4 w-24" />
          <Skeleton class="h-4 w-36 flex-1 max-w-xs" />
          <Skeleton class="h-4 w-20 hidden sm:block" />
          <Skeleton class="h-4 w-16 shrink-0" />
        </div>

        <!-- Table Rows Skeleton -->
        <div v-for="r in 3" :key="r" class="px-4 py-3.5 border-b border-border last:border-none flex items-center justify-between gap-4">
          <Skeleton class="h-4 w-24 shrink-0" />
          <div class="flex-1 max-w-xs space-y-1">
            <Skeleton class="h-4 w-3/4" />
            <Skeleton class="h-3 w-1/2 opacity-70" />
          </div>
          <Skeleton class="h-6 w-20 rounded-full hidden sm:block" />
          <Skeleton class="h-8 w-8 rounded-lg shrink-0" />
        </div>
      </div>
    </div>
  </div>
</template>
