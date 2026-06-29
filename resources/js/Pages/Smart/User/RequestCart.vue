<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-vue-next';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from "@/Components/ui/number-field";

import { watch } from 'vue';

interface Props {
  user?: any;
  cartItems?: CartItem[];
}
const props = withDefaults(defineProps<Props>(), {
  cartItems: () => []
});

// --- Tipe Data ---
interface CartItem {
  id: number;
  barang_id: number | null;
  brand: string;
  name: string;
  spec: string;
  category: string;
  category_name: string;
  subcategory_name: string;
  code: string;
  stock: number; // Total stok tersedia
  quantity: number; // Jumlah yang diminta user
  selected: boolean;
  imageUrl?: string;
}

const cartItems = ref<CartItem[]>(props.cartItems.map(item => ({ ...item, selected: false })));

watch(() => props.cartItems, (newVal) => {
  const selectedMap = new Map(cartItems.value.map(i => [i.id, i.selected]));
  cartItems.value = newVal.map(item => ({
    ...item,
    selected: selectedMap.get(item.id) || false
  }));
}, { deep: true });

const filteredItems = computed(() => {
  return cartItems.value;
});

// --- Computed: Item yang dipilih (untuk ringkasan & validasi) ---
const selectedItems = computed(() => cartItems.value.filter(item => item.selected));

// Tombol "Lanjut ke Konfirmasi" hanya aktif jika ada minimal 1 item yang dipilih
const canProceed = computed(() => selectedItems.value.length > 0);

// --- Aksi: Hapus item dari keranjang ---
const removeItem = (id: number) => {
  router.delete(route('smart.asset-cart.destroy', id), {
    preserveScroll: true,
  });
};

// --- Aksi: Update jumlah ---
const updateQty = (item: CartItem, value: number) => {
  router.put(route('smart.asset-cart.update', item.id), {
    quantity: value
  }, {
    preserveScroll: true,
  });
};

// --- Aksi: Lanjut ke halaman konfirmasi ---
const handleProceed = () => {
  const ids = selectedItems.value.map(i => i.id).join(',');
  router.get(route('smart.asset-cart.confirmation'), { ids });
};

const getItemDisplayName = (item: CartItem) => {
  if (!item.barang_id) {
    return item.subcategory_name;
  }
  const parts = [];
  if (item.brand && item.brand !== '-') {
    parts.push(item.brand);
  }
  if (item.name && item.name !== 'Tidak Spesifik') {
    parts.push(item.name);
  }
  if (item.spec && item.spec !== '-') {
    parts.push(item.spec);
  }
  return parts.join(' ') || 'Barang';
};
</script>

<template>
  <Head title="Keranjang Habis Pakai" />

  <AppLayout title="Keranjang Habis Pakai">
    <!-- Judul Halaman -->
    <div class="mb-2">
      <h1 class="text-xl font-bold text-gray-900 leading-none">Keranjang Habis Pakai</h1>
      <p class="text-muted-foreground mt-2 hidden sm:block">Pilih barang-barang yang ingin dimasukkan dalam permintaan.</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 mt-6">
      <!-- ============================================================ -->
      <!-- Kolom Kiri: Daftar Barang di Keranjang                        -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0">
        <!-- Daftar Item -->
        <ScrollArea class="border border-border rounded-[14px] bg-card h-[380px] lg:h-[calc(100vh-200px)]">
          <div class="p-4 sm:p-6">
            <div class="space-y-3">
              <!-- Jika keranjang kosong -->
              <div v-if="filteredItems.length === 0" class="text-center py-10">
                <p class="text-muted-foreground text-sm">Keranjang kosong atau tidak ada barang yang sesuai filter.</p>
              </div>

              <!-- Item Card -->
              <div
                v-for="item in filteredItems"
                :key="item.id"
                class="bg-card border rounded-[14px] p-3 sm:p-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 transition-colors"
                :class="item.selected ? 'border-primary/50 bg-primary/5' : 'border-border'"
              >
                <!-- Top/Main Content Area for Checkbox, Image & Info -->
                <div class="flex items-center gap-3 sm:gap-4 flex-1 min-w-0">
                  <!-- Checkbox seleksi -->
                  <Checkbox 
                    v-model="item.selected"
                    class="cursor-pointer shrink-0"
                  />

                  <!-- Image -->
                  <div class="w-16 h-16 sm:w-24 sm:h-24 shrink-0 bg-muted rounded-[14px] overflow-hidden flex items-center justify-center border border-border relative">
                    <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
                    <img v-if="item.imageUrl" :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/storage/' + item.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
                    <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
                  </div>

                  <!-- Info -->
                  <div class="flex-1 min-w-0 flex flex-col justify-center">
                    <template v-if="!item.barang_id">
                      <h3 class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">{{ item.subcategory_name }}</h3>
                      <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">{{ item.category_name }}</p>
                      <p class="text-[10px] sm:text-xs text-muted-foreground italic hidden sm:block">*gambar hanya illustrasi</p>
                    </template>
                    <template v-else>
                      <span v-if="item.brand && item.brand !== '-'" class="text-xs sm:text-lg font-bold text-foreground leading-tight truncate">
                        {{ item.brand }}
                      </span>
                      <h3 class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">
                        {{ item.name }}{{ item.spec && item.spec !== '-' ? ' ' + item.spec : '' }}
                      </h3>
                      <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">
                        {{ item.category_name }} ({{ item.subcategory_name }})
                      </p>
                      <p class="text-[10px] sm:text-xs text-muted-foreground mt-0.5 sm:mt-1 truncate">
                        {{ item.code }}
                      </p>
                    </template>
                  </div>
                </div>

                <!-- Bottom/Controls Area for Mobile (re-aligns to side on Desktop) -->
                <div class="flex items-center justify-between sm:justify-end gap-3 border-t sm:border-t-0 border-border pt-3 sm:pt-0">
                  <!-- Tombol Hapus -->
                  <Button
                    variant="ghost"
                    size="icon"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive flex-shrink-0 rounded-full"
                    @click="removeItem(item.id)"
                    title="Hapus dari keranjang"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>

                  <!-- Kontrol Jumlah -->
                  <div class="flex-shrink-0">
                    <NumberField 
                      :model-value="item.quantity" 
                      @update:model-value="(val) => updateQty(item, val)"
                      :min="1" 
                      :max="999999" 
                      locale="id-ID" 
                      class="w-28 sm:w-32"
                    >
                      <NumberFieldContent>
                        <NumberFieldDecrement />
                        <NumberFieldInput class="h-9 text-xs sm:text-sm" />
                        <NumberFieldIncrement />
                      </NumberFieldContent>
                    </NumberField>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- ============================================================ -->
      <!-- Kolom Kanan: Ringkasan Peminjaman                             -->
      <!-- ============================================================ -->
      <div class="w-full lg:w-lg flex-shrink-0">
        <div class="bg-card border border-border rounded-[14px] p-5 sticky top-24">
          <h2 class="text-lg font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- Daftar item yang dipilih -->
          <div class="space-y-3 mb-6">
            <div v-if="selectedItems.length === 0" class="text-sm text-muted-foreground italic">
              Belum ada barang yang dipilih.
            </div>
            <div
              v-for="item in selectedItems"
              :key="item.id"
              class="flex items-center justify-between gap-2"
            >
              <span class="text-base text-foreground font-medium truncate flex-1">
                {{ getItemDisplayName(item) }}
              </span>
              <span class="text-base text-muted-foreground flex-shrink-0 whitespace-nowrap">
                {{ item.quantity }} satuan
              </span>
            </div>
          </div>

          <hr class="border-border mb-5" />

          <!-- Tombol Lanjut ke Konfirmasi -->
          <!-- Disabled jika tidak ada item yang dipilih -->
          <Button
            variant="primary"
            size="lg"
            class="w-full"
            :disabled="!canProceed"
            @click="handleProceed"
          >
            Lanjut ke Konfirmasi
          </Button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
