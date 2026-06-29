<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import { watch } from 'vue';
import CartItemCard from '@/Components/CartItemCard.vue';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';

interface Props {
  user?: any;
  cartItems?: CartItem[];
}
const props = withDefaults(defineProps<Props>(), {
  cartItems: () => []
});

// --- Data Types ---
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
  stock: number; // Total available stock
  quantity: number; // Quantity requested by the user
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

// --- Computed: Selected items (for summary & validation) ---
const selectedItems = computed(() => cartItems.value.filter(item => item.selected));

const isAllSelected = computed({
  get() {
    if (cartItems.value.length === 0) return false;
    return cartItems.value.every(item => item.selected);
  },
  set(val: boolean) {
    cartItems.value.forEach(item => {
      item.selected = val;
    });
  }
});

// "Proceed to Confirmation" button is only active if at least 1 item is selected
const canProceed = computed(() => selectedItems.value.length > 0);

// --- Actions: Remove item from cart ---
const removeItem = (id: number) => {
  router.delete(route('smart.asset-cart.destroy', id), {
    preserveScroll: true,
  });
};

// --- Actions: Update quantity ---
const updateQty = (item: CartItem, value: number) => {
  router.put(route('smart.asset-cart.update', item.id), {
    quantity: value
  }, {
    preserveScroll: true,
  });
};

// --- Actions: Proceed to confirmation page ---
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
    <!-- Page Title -->
    <div class="mb-2 flex flex-row items-center justify-between sm:flex-col sm:items-start">
      <div class="min-w-0">
        <h1 class="text-xl font-bold text-gray-900 leading-none">Keranjang Habis Pakai</h1>
        <p class="text-muted-foreground mt-2 hidden sm:block">Pilih barang-barang yang ingin dimasukkan dalam permintaan.</p>
      </div>

      <!-- Pilih Semua Checkbox -->
      <div 
        class="items-center gap-2 bg-card border border-border px-3 py-1.5 sm:py-2 rounded-lg w-fit sm:mt-3 transition-opacity duration-200"
        :class="cartItems.length === 0 ? 'hidden sm:flex opacity-50 cursor-not-allowed select-none' : 'flex'"
      >
        <Checkbox 
          id="select-all"
          :model-value="isAllSelected"
          @update:model-value="(val) => isAllSelected = !!val"
          :disabled="cartItems.length === 0"
          class="cursor-pointer disabled:cursor-not-allowed"
        />
        <label 
          for="select-all" 
          class="text-sm font-medium text-foreground select-none"
          :class="cartItems.length === 0 ? 'cursor-not-allowed text-muted-foreground' : 'cursor-pointer'"
        >
          Pilih Semua
        </label>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row sm:gap-6 mt-3 pb-20 lg:pb-0">
      <!-- ============================================================ -->
      <!-- Left Column: Items List in Cart                              -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0">
        <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-14rem)] lg:h-[calc(100vh-14.5rem)]">
          <div class="p-3 sm:p-6">
            <div class="space-y-3">
              <!-- If cart is empty -->
              <div v-if="filteredItems.length === 0" class="text-center py-10">
                <p class="text-muted-foreground text-sm">Keranjang kosong atau tidak ada barang yang sesuai filter.</p>
              </div>

              <!-- Item Card -->
              <CartItemCard
                v-for="item in filteredItems"
                :key="item.id"
                :item="item"
                v-model:selected="item.selected"
                @remove="removeItem(item.id)"
                @update:quantity="(qty) => updateQty(item, qty)"
              />
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- ============================================================ -->
      <!-- Right Column: Borrow Summary                                 -->
      <!-- ============================================================ -->
      <div class="hidden lg:block lg:w-80 xl:w-96 flex-shrink-0">
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sticky top-24">
          <h2 class="text-lg font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- List of selected items -->
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

          <!-- Proceed to Confirmation Button -->
          <!-- Disabled if no items are selected -->
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

      <!-- Mobile Sticky Bottom Footer -->
      <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-card border-t border-border px-4 py-3 shadow-lg flex items-center justify-between pb-safe">
        <div class="flex flex-col">
          <span class="text-xs text-muted-foreground font-medium">Total:</span>
          <span class="text-sm font-bold text-foreground">
            {{ selectedItems.length }} jenis barang
          </span>
        </div>
        <Button
          variant="primary"
          size="default"
          class="px-6 rounded-xl"
          :disabled="!canProceed"
          @click="handleProceed"
        >
          Konfirmasi
        </Button>
      </div>
    </div>
  </AppLayout>
</template>
