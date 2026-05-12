<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';

interface Props {
  brand: string;
  category: string;
  subcategory: string;
  quantity: string | number;
  assets: string[];
  quantityLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
  quantityLabel: 'Jumlah dipinjam'
});

const showAssets = ref(true);
</script>

<template>
  <div class="p-4 rounded-xl border border-border bg-muted/5 space-y-3">
    <div class="flex items-start gap-4">
      <div class="w-20 h-20 rounded-lg bg-muted flex items-center justify-center overflow-hidden shrink-0 border border-border">
        <img src="https://placehold.co/100x100?text=Item" class="w-full h-full object-cover opacity-50" />
      </div>
      
      <div class="flex-grow space-y-0.5">
        <h4 class="text-lg font-bold text-foreground">{{ brand }}</h4>
        <p class="text-sm text-muted-foreground">{{ category }} ({{ subcategory }})</p>
        <p class="text-sm font-medium text-foreground">{{ quantityLabel }}: {{ quantity }} satuan</p>
        
        <div class="pt-1">
          <button 
            @click="showAssets = !showAssets"
            class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition-colors"
          >
            <span>{{ showAssets ? 'Sembunyikan Alokasi Aset' : 'Lihat Alokasi Aset' }}</span>
            <ChevronUp v-if="showAssets" class="w-3 h-3" />
            <ChevronDown v-else class="w-3 h-3" />
          </button>

          <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform -translate-y-2 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform -translate-y-2 opacity-0"
          >
            <div v-if="showAssets" class="mt-2 space-y-1 overflow-hidden">
              <p class="text-xs font-bold text-muted-foreground mb-1">Aset:</p>
              <ul class="space-y-1">
                <li v-for="(asset, idx) in assets" :key="idx" class="text-xs text-foreground flex items-center gap-2">
                  <span class="w-1 h-1 rounded-full bg-muted-foreground/40 shrink-0"></span>
                  <span class="font-mono">{{ asset }}</span>
                </li>
              </ul>
            </div>
          </Transition>
        </div>
      </div>
    </div>
    
    <div v-if="$slots.footer" class="flex justify-end pt-2 border-t border-border/50">
      <slot name="footer" />
    </div>
  </div>
</template>
