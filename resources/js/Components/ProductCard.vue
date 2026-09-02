<script setup lang="ts">
/**
 * ProductCard.vue
 *
 * Catalog card component displaying an item category or subcategory in the user
 * browsing view (`Browse.vue`).
 *
 * Key features:
 * - 1:1 square aspect ratio image container with fallback placeholder.
 * - Line-clamped typography for titles and parent categories to maintain uniform grid heights.
 * - Tooltip attributes on labels for accessibility and long-string inspection.
 * - Gradient call-to-action button to trigger addition into the active request/borrow cart.
 * - Graceful disabled state when items are unavailable ("Tidak Tersedia").
 *
 * @emits add-to-cart - Fired when the user clicks the "Tambah" (Add) button.
 */
import { Button } from "@/Components/ui/button";
import { formatImageUrl } from "@/lib/utils";

/**
 * Component Props Interface
 */
interface Props {
  /** Name of the item subcategory (e.g., "Laptop", "Kursi Kerja"). */
  subcategoryName: string;
  /** Name of the parent category (e.g., "Teknologi Informasi", "Furnitur"). */
  categoryName: string;
  /** Optional URL or media path to the subcategory preview image. */
  imageUrl?: string;
  /** When true, disables the card interaction and changes button state to unavailable. */
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false
});

const emit = defineEmits<{
  /** Emitted when the add button is clicked to insert this item into the user's cart. */
  (e: 'add-to-cart'): void;
}>();
</script>

<template>
  <div
    class="flex flex-col border rounded-[0.875rem] bg-card overflow-hidden h-full transition-all duration-300 shadow-card"
    :class="[
      disabled ? 'opacity-60 border-border' : 'border-border hover:shadow-card-hover hover:-translate-y-0.5'
    ]"
  >
    <!-- Product Thumbnail Banner (1:1 Aspect Ratio) -->
    <div class="aspect-square bg-muted overflow-hidden flex items-center justify-center shrink-0 relative w-full">
      <img :src="formatImageUrl(imageUrl)" alt="Product Image" class="w-full h-full object-cover relative z-10" />
    </div>
    
    <!-- Card Body & Action Trigger -->
    <div class="flex flex-col flex-grow p-3.5 sm:p-4">
      <!-- Subcategory & Category Titles -->
      <p class="font-bold text-foreground text-sm sm:text-base leading-snug line-clamp-2" :title="subcategoryName">{{ subcategoryName }}</p>
      <p class="text-xs sm:text-sm text-muted-foreground line-clamp-1 mt-0.5" :title="categoryName">{{ categoryName }}</p>
 
      <!-- Flex Spacer to push action button to bottom -->
      <div class="flex-grow pt-3"></div>
      
      <!-- Add-to-Cart / Availability Button -->
      <Button 
        @click="emit('add-to-cart')"
        :disabled="disabled"
        class="w-full mt-auto bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-[0.75rem] h-9 sm:h-[2.25rem] text-xs sm:text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed px-3"
      >
        <template v-if="disabled">
          Tidak Tersedia
        </template>
        <template v-else>
          <span>Tambah</span>
        </template>
      </Button>
    </div>
  </div>
</template>
