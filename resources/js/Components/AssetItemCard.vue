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
  imageUrl?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  quantityLabel: 'Jumlah dipinjam',
  imageUrl: null
});

const showAssets = ref(true);
</script>

<template>
  <div class="p-4 border border-border/70 hover:border-primary/20 hover:bg-muted/5 transition-all rounded-[14px] space-y-3">
    <div class="flex items-start gap-4">
      <!-- Thumbnail Barang -->
      <div class="w-16 h-16 rounded-[12px] bg-muted border border-border overflow-hidden shrink-0 flex items-center justify-center mt-0.5">
        <img 
          v-if="imageUrl" 
          :src="imageUrl.startsWith('http') || imageUrl.startsWith('/') ? imageUrl : '/storage/' + imageUrl" 
          class="w-full h-full object-cover" 
        />
        <div v-else class="text-sm font-black text-muted-foreground/50 select-none">
          {{ subcategory.substring(0, 3).toUpperCase() }}
        </div>
      </div>
      
      <div class="min-w-0 flex-grow space-y-1">
        <h4 class="text-sm md:text-base font-bold text-foreground truncate">{{ brand }}</h4>
        <p class="text-xs text-muted-foreground">Kategori: {{ category }} ({{ subcategory }})</p>
        <p class="text-xs text-foreground font-semibold">{{ quantityLabel }}: {{ quantity }} satuan</p>
        
        <div v-if="assets && assets.length > 0" class="pt-1.5">
          <button 
            @click="showAssets = !showAssets"
            class="text-xs font-bold text-[#6366F1] hover:text-[#5558EB] flex items-center gap-1 transition-colors focus:outline-none"
          >
            <span>{{ showAssets ? 'Sembunyikan Alokasi Aset' : 'Lihat Alokasi Aset' }}</span>
            <ChevronUp v-if="showAssets" class="w-3.5 h-3.5" />
            <ChevronDown v-else class="w-3.5 h-3.5" />
          </button>

          <!-- Expanded Asset List -->
          <div 
            v-if="showAssets" 
            class="mt-2 pl-3 py-1 space-y-1 animate-in fade-in slide-in-from-top-1 duration-200"
          >
            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Aset:</p>
            <ul class="space-y-1">
              <li v-for="(asset, idx) in assets" :key="idx" class="text-xs text-foreground font-semibold flex items-center gap-1.5">
                <span class="w-1 h-1 rounded-full bg-foreground shrink-0"></span>
                <span>{{ asset }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    
    <div v-if="$slots.footer" class="flex justify-end pt-2 border-t border-border/50">
      <slot name="footer" />
    </div>
  </div>
</template>
