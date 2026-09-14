<script setup lang="ts">
/**
 * Dashboard Skeleton Component
 * Mimics the silhouette of the Admin/IFS Manager Dashboard:
 * - Header greeting
 * - Mobile scan shortcut banner
 * - Tab pills
 * - 3 Metric summary cards (Total Consumables, CFS Assets, ICT Assets)
 * - 3 Donut/Pie charts grid
 */
import { Skeleton } from '@/Components/ui/skeleton';
import { Card, CardHeader, CardContent } from '@/Components/ui/card';
</script>

<template>
  <div class="space-y-4">
    <!-- Header Greeting Skeleton -->
    <div class="pb-1">
      <Skeleton class="h-8 w-64 sm:w-72" />
    </div>

    <!-- Mobile Barcode Quick Shortcut Skeleton (matches Dashboard.vue lg:hidden) -->
    <div class="lg:hidden w-full">
      <Skeleton class="h-16 w-full rounded-xl" />
    </div>

    <!-- Tab Pills Skeleton (matches Klasik / WIP tabs) -->
    <div class="inline-flex p-1 bg-muted/50 rounded-full gap-1 border border-border/50">
      <Skeleton class="h-8 w-24 rounded-full" />
      <Skeleton class="h-8 w-24 rounded-full" />
    </div>

    <!-- Main Dashboard Container -->
    <div class="p-6 bg-card rounded-xl border border-border shadow-sm overflow-hidden space-y-6">
      <!-- 3 Metric Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        <Card v-for="i in 3" :key="i" class="h-full min-w-0">
          <CardContent class="p-3.5 sm:p-4 md:p-5 flex items-center justify-between h-full gap-3 sm:gap-4">
            <div class="min-w-0 flex-1 space-y-2">
              <Skeleton class="h-4 w-28 sm:w-36" />
              <Skeleton class="h-7 sm:h-8 w-20 sm:w-24" />
            </div>
            <Skeleton class="h-12 w-12 sm:h-14 sm:w-14 rounded-lg shrink-0" />
          </CardContent>
        </Card>
      </div>

      <!-- 3 Donut / Pie Charts Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card v-for="i in 3" :key="i" class="flex flex-col justify-between">
          <CardHeader class="pb-2 space-y-2">
            <Skeleton class="h-5 w-36 sm:w-44" />
            <Skeleton class="h-3.5 w-52 sm:w-60" />
          </CardHeader>
          <CardContent class="p-5 flex-1 flex items-center justify-center min-h-[220px]">
            <div class="h-40 w-40 sm:h-44 sm:w-44 rounded-full border-[18px] border-primary/10 animate-pulse" />
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
