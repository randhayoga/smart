<script setup lang="ts">
/**
 * Barcode Scanner Skeleton Component
 * Mimics the silhouette of PindaiBarcode.vue:
 * - Breadcrumbs & page title
 * - Camera viewfinder viewport card with scan frame
 * - Manual barcode input form card
 */
import { Skeleton } from '@/Components/ui/skeleton';
import { Card, CardHeader, CardContent } from '@/Components/ui/card';
</script>

<template>
  <div class="space-y-6 max-w-3xl mx-auto">
    <!-- Breadcrumb & Header Skeleton -->
    <div class="space-y-2">
      <div class="flex items-center gap-2">
        <Skeleton class="h-4 w-24" />
        <span class="text-muted-foreground/40 text-xs">/</span>
        <Skeleton class="h-4 w-28" />
      </div>
      <Skeleton class="h-7 sm:h-8 w-48 sm:w-64" />
      <Skeleton class="h-4 w-72 sm:w-96 text-muted-foreground" />
    </div>

    <!-- Camera Viewport Card -->
    <Card class="rounded-xl border border-border shadow-sm overflow-hidden">
      <CardHeader class="p-4 sm:p-5 border-b border-border flex flex-row items-center justify-between">
        <div class="space-y-1">
          <Skeleton class="h-5 w-36" />
          <Skeleton class="h-3.5 w-48" />
        </div>
        <!-- Camera / Torch toggle button skeletons -->
        <div class="flex items-center gap-2">
          <Skeleton class="h-9 w-9 rounded-xl" />
          <Skeleton class="h-9 w-9 rounded-xl" />
        </div>
      </CardHeader>

      <CardContent class="p-6 flex flex-col items-center justify-center">
        <!-- Scanner Viewfinder Box -->
        <div class="w-full max-w-sm aspect-square rounded-2xl border-2 border-dashed border-primary/30 bg-muted/20 relative flex items-center justify-center overflow-hidden">
          <!-- Viewfinder reticle corners -->
          <div class="absolute inset-8 border border-primary/20 rounded-xl flex items-center justify-center">
            <div class="w-16 h-16 rounded-full border-2 border-primary/30 animate-ping opacity-30" />
            <Skeleton class="h-2 w-3/4 rounded-full absolute" />
          </div>
        </div>
        <Skeleton class="h-4 w-52 mt-4" />
      </CardContent>
    </Card>

    <!-- Manual Barcode Input Card -->
    <Card class="rounded-xl border border-border shadow-sm">
      <CardContent class="p-4 sm:p-6 space-y-4">
        <div class="space-y-1">
          <Skeleton class="h-5 w-44" />
          <Skeleton class="h-3.5 w-64" />
        </div>
        <div class="flex flex-col sm:flex-row gap-3 pt-1">
          <Skeleton class="h-10 flex-1 rounded-xl" />
          <Skeleton class="h-10 w-full sm:w-32 rounded-xl" />
        </div>
      </CardContent>
    </Card>
  </div>
</template>
