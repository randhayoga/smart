<script setup lang="ts">
/**
 * AssetPhotoGrid component
 * High-performance image grid renderer for catalog items, lots, and units.
 * Supports:
 * - 0 images: placeholder with opacity
 * - 1 image: full-width single image
 * - 2-3 images: 2 side-by-side images
 * - 4+ images: 2x2 grid of top 4 distinct images
 */
import { computed } from 'vue';

interface Props {
  photos?: (string | null | undefined)[];
  fallbackUrl?: string;
  sizeClass?: string;
  roundedClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
  photos: () => [],
  fallbackUrl: '/media/inventory/placeholder.jpg',
  sizeClass: 'w-48 h-48',
  roundedClass: 'rounded-xl',
});

const validPhotos = computed(() => {
  return (props.photos || []).filter((p): p is string => Boolean(p && String(p).trim() !== ''));
});

const toMediaUrl = (path: string) => {
  if (!path) return props.fallbackUrl;
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path;
  }
  return `/media/${path}`;
};
</script>

<template>
  <div 
    :class="[
      sizeClass, 
      roundedClass, 
      'bg-muted shrink-0 flex items-center justify-center overflow-hidden border border-border select-none'
    ]"
  >
    <!-- Case 0: No Photos Available -->
    <img 
      v-if="validPhotos.length === 0" 
      :src="fallbackUrl" 
      class="w-full h-full object-cover opacity-50" 
      loading="lazy" 
      alt="Placeholder"
    />

    <!-- Case 1: Exactly 1 Photo -->
    <img 
      v-else-if="validPhotos.length === 1" 
      :src="toMediaUrl(validPhotos[0])" 
      class="w-full h-full object-cover" 
      loading="lazy" 
      alt="Asset Photo"
    />

    <!-- Case 2: 2 or 3 Photos -> 2 Side-by-Side -->
    <div 
      v-else-if="validPhotos.length < 4" 
      class="grid grid-cols-2 h-full w-full gap-0.5"
    >
      <img 
        :src="toMediaUrl(validPhotos[0])" 
        class="w-full h-full object-cover" 
        loading="lazy" 
        alt="Asset Photo 1"
      />
      <img 
        :src="toMediaUrl(validPhotos[1])" 
        class="w-full h-full object-cover" 
        loading="lazy" 
        alt="Asset Photo 2"
      />
    </div>

    <!-- Case 3: 4+ Photos -> 2x2 Grid -->
    <div 
      v-else 
      class="grid grid-cols-2 grid-rows-2 h-full w-full gap-0.5"
    >
      <img 
        v-for="(photo, idx) in validPhotos.slice(0, 4)" 
        :key="idx" 
        :src="toMediaUrl(photo)" 
        class="w-full h-full object-cover" 
        loading="lazy" 
        :alt="`Asset Photo ${idx + 1}`"
      />
    </div>
  </div>
</template>
