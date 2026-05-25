<script setup lang="ts">
import { Button } from "@/Components/ui/button";

interface Props {
  brand: string;
  spec: string;
  category: string;
  stock: number;
  imageUrl?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'add-to-cart'): void;
}>();
</script>

<template>
  <div class="flex flex-col border border-border rounded-[14px] bg-card overflow-hidden h-full shadow-sm">
    <!-- Image Placeholder or Actual Image -->
    <div class="aspect-[4/3] bg-muted rounded-[14px] overflow-hidden flex items-center justify-center shrink-0 relative w-full">
      <img v-if="imageUrl" :src="imageUrl" alt="Product Image" class="w-full h-full object-cover relative z-10" />
      <img v-else src="https://placehold.co/400x300?text=Barang" class="w-full h-full object-cover opacity-50" />
    </div>
    
    <!-- Content -->
    <div class="flex flex-col flex-grow p-4 space-y-0.5">
      <h4 class="text-sm font-bold text-foreground">{{ brand }}</h4>
      <p class="text-sm font-bold text-foreground">{{ spec }}</p>
      <p class="text-xs text-muted-foreground">{{ category }}</p>

      
      <!-- Spacer to push button to bottom -->
      <div class="flex-grow pt-3"></div>
      
      <Button 
        @click="emit('add-to-cart')"
        class="w-full mt-auto bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-full h-[38px] text-xs font-semibold"
      >
        Tambah ke Keranjang
      </Button>
    </div>
  </div>
</template>
