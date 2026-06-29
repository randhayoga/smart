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
  <div
    class="flex flex-col border rounded-[0.875rem] bg-card overflow-hidden h-full transition-all duration-300 shadow-card"
    :class="[
      disabled ? 'opacity-60 border-border' : 'border-border hover:shadow-card-hover hover:-translate-y-0.5'
    ]"
  >
    <!-- Image Placeholder or Actual Image -->
    <div class="aspect-square bg-muted rounded-[0.875rem] overflow-hidden flex items-center justify-center shrink-0 relative w-full">
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
        class="w-full mt-auto bg-gradient-primary shadow-button hover:opacity-90 text-white rounded-[0.75rem] h-[2.375rem] text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed px-2"
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
