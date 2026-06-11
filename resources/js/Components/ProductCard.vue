<script setup lang="ts">
import { Button } from "@/Components/ui/button";

interface Props {
  subcategoryName: string;
  categoryName: string;
  imageUrl?: string;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false
});

const emit = defineEmits<{
  (e: 'add-to-cart'): void;
}>();
</script>

<template>
  <div class="flex flex-col border border-border rounded-[14px] bg-card overflow-hidden h-full shadow-sm">
    <!-- Image Placeholder or Actual Image -->
    <div class="aspect-square bg-muted rounded-[14px] overflow-hidden flex items-center justify-center shrink-0 relative w-full">
      <img v-if="imageUrl" :src="imageUrl" alt="Product Image" class="w-full h-full object-cover relative z-10" />
      <img v-else src="https://placehold.co/400x400?text=Barang" class="w-full h-full object-cover opacity-50" />
    </div>
    
    <!-- Content -->
    <div class="flex flex-col flex-grow p-4">
      <p class="font-bold text-foreground">{{ subcategoryName }}</p>
      <p class="text-sm text-muted-foreground">{{ categoryName }}</p>

      <!-- Spacer to push button to bottom -->
      <div class="flex-grow pt-3"></div>
      
      <Button 
        @click="emit('add-to-cart')"
        :disabled="disabled"
        class="w-full mt-auto bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-[12px] h-[38px] text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {{ disabled ? 'Tidak Tersedia' : 'Tambah ke Keranjang' }}
      </Button>
    </div>
  </div>
</template>
