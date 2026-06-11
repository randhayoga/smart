<script setup lang="ts">
import { ref, computed } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';

interface Props {
  brand: string;
  category: string;
  subcategory: string;
  quantity: string | number;
  assets: string[];
  quantityLabel?: string;
  imageUrl?: string | null;
  placements?: Record<string, string>;
  stock?: number | null;
  status?: string;
  isAdmin?: boolean;
  isConsumable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  quantityLabel: 'Jumlah dipinjam',
  imageUrl: null,
  placements: () => ({}),
  stock: null,
  status: undefined,
  isAdmin: false,
  isConsumable: false,
});

const showAssets = ref(true);

const activeAssets = computed(() => {
  return props.assets ? props.assets.filter(asset => asset && String(asset).trim() !== '') : [];
});
</script>

<template>
  <div 
    class="p-4 border transition-all rounded-[14px] space-y-3"
    :class="[
      (stock !== null && stock < Number(quantity) && status !== 'fulfilled')
        ? 'bg-zinc-100/50 dark:bg-zinc-900/50 border-red-500/25 opacity-75 shadow-sm'
        : 'bg-card border-border/70 hover:border-primary/20 hover:bg-muted/5'
    ]"
  >
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
        <div class="flex items-center gap-2 flex-wrap mb-0.5">
          <h4 class="text-sm md:text-base font-bold text-foreground truncate">{{ brand }}</h4>
           <span 
            v-if="status === 'fulfilled'" 
            class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-0.5 text-[10px] font-bold text-green-600 ring-1 ring-inset ring-green-500/20"
          >
            {{ isConsumable ? 'Sudah Disediakan' : 'Sudah Dialokasikan' }}
          </span>
          <span 
            v-else-if="status === 'pending' && !isAdmin && (stock !== null && stock < Number(quantity))" 
            class="inline-flex items-center rounded-full bg-red-500/10 px-2 py-0.5 text-[10px] font-bold text-red-600 ring-1 ring-inset ring-red-500/20"
          >
            Stok Habis
          </span>
        </div>
        <p class="text-xs text-muted-foreground">Kategori: {{ category }} ({{ subcategory }})</p>
        <div v-if="stock !== null && isAdmin" class="text-xs font-semibold text-foreground">
          Stok tersedia: 
          <span :class="stock >= Number(quantity) ? 'text-green-600' : 'text-red-500'">
            {{ stock }} satuan
          </span>
        </div>
        <p class="text-xs text-foreground font-semibold">{{ quantityLabel }}: {{ quantity }} satuan</p>
        
        <div v-if="activeAssets.length > 0" class="pt-1.5">
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
              <li v-for="(asset, idx) in activeAssets" :key="idx" class="text-xs text-foreground font-semibold flex items-center gap-1.5">
                <span class="w-1 h-1 rounded-full bg-foreground shrink-0"></span>
                <span>{{ asset }} <span v-if="placements && placements[asset]" class="text-muted-foreground font-normal">({{ placements[asset] }})</span></span>
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
