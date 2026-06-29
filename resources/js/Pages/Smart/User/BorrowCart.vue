<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import CartItemCard from '@/Components/CartItemCard.vue';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';

interface Props {
  user?: any;
  cartItems?: CartItem[];
  defaultStartDate?: string;
  defaultStartTime?: string;
  defaultEndDate?: string;
  defaultEndTime?: string;
}

const props = withDefaults(defineProps<Props>(), {
  cartItems: () => [],
  defaultStartDate: '',
  defaultStartTime: '',
  defaultEndDate: '',
  defaultEndTime: '',
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
  stock: number;       // Total available stock
  quantity: number;    // Requested quantity
  selected: boolean;
  isPreorder?: boolean; // Pre-order allowed but current stock is 0
  imageUrl?: string;
}

// --- Helper: Get client local date & time ---
const getClientDefaultDate = () => {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
};

const getClientDefaultTime = () => {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2, '0');
  const min = String(now.getMinutes()).padStart(2, '0');
  return `${hh}:${min}`;
};

const handlePickerClick = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target && typeof target.showPicker === 'function') {
    target.showPicker();
  }
};

// --- Borrow Date State ---
// MUST be filled first - all other actions are disabled until the start date is filled
const startDate = ref(getClientDefaultDate());
const startTime = ref(getClientDefaultTime());
const endDate   = ref('');
const endTime   = ref('');

const cartItems = ref<CartItem[]>(props.cartItems.map(item => ({ ...item, selected: false })));

watch(() => props.cartItems, (newVal) => {
  const selectedMap = new Map(cartItems.value.map(i => [i.id, i.selected]));
  cartItems.value = newVal.map(item => ({
    ...item,
    selected: selectedMap.get(item.id) || false
  }));
}, { deep: true });

const isDateSelected = computed(() => startDate.value && startDate.value.trim() !== '');

const isAllSelected = computed({
  get() {
    if (cartItems.value.length === 0) return false;
    return cartItems.value.every(item => item.selected);
  },
  set(val: boolean) {
    if (!isDateSelected.value) return;
    cartItems.value.forEach(item => {
      item.selected = val;
    });
  }
});

// If start date changes → reset all selections (since availability can change)
watch(startDate, () => {
  cartItems.value.forEach(item => {
    item.selected = false;
    item.quantity = 1;
  });
});
watch(startTime, () => {
  cartItems.value.forEach(item => {
    item.selected = false;
    item.quantity = 1;
  });
});

watch(endDate, (newVal) => {
  if (!newVal || newVal.trim() === '') {
    endTime.value = '';
  }
});

const filteredItems = computed(() => {
  return cartItems.value;
});

// --- Computed: Selected items for summary ---
const selectedItems = computed(() => cartItems.value.filter(item => item.selected));

// "Proceed to Confirmation" button is active if start date is filled AND at least 1 item is selected
const canProceed = computed(() => isDateSelected.value && selectedItems.value.length > 0);

// --- Actions: Remove item from cart ---
const removeItem = (id: number) => {
  router.delete(route('smart.borrow-cart.destroy', id), {
    preserveScroll: true,
  });
};

// --- Actions: Update quantity ---
const updateQty = (item: CartItem, value: number) => {
  router.put(route('smart.borrow-cart.update', item.id), {
    quantity: value
  }, {
    preserveScroll: true,
  });
};

// --- Actions: Proceed to confirmation ---
const handleProceed = () => {
  const ids = selectedItems.value.map(i => i.id).join(',');
  router.get(route('smart.borrow-cart.confirmation'), {
    ids,
    start_date: startDate.value,
    start_time: startTime.value,
    end_date: endDate.value,
    end_time: endTime.value,
  });
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
  <AppLayout title="Keranjang Peminjaman">
    <!-- Page Title -->
    <div class="mb-2 flex flex-row items-center justify-between sm:flex-col sm:items-start">
      <div class="min-w-0">
        <h1 class="text-xl font-bold text-gray-900 leading-none">Keranjang Peminjaman</h1>
        <p class="text-muted-foreground mt-2 hidden sm:block">Pilih tanggal peminjaman lalu pilih barang-barang yang ingin dimasukkan dalam peminjaman.</p>
      </div>

      <!-- Pilih Semua Checkbox -->
      <div 
        class="items-center gap-2 bg-card border border-border px-3 py-1.5 sm:py-2 rounded-lg w-fit sm:mt-3 transition-opacity duration-200"
        :class="[
          cartItems.length === 0 
            ? 'hidden sm:flex opacity-50 cursor-not-allowed select-none' 
            : 'flex',
          (!isDateSelected && cartItems.length > 0)
            ? 'opacity-50 cursor-not-allowed' 
            : ''
        ]"
      >
        <Checkbox 
          id="select-all"
          :model-value="isAllSelected"
          @update:model-value="(val) => isAllSelected = !!val"
          :disabled="!isDateSelected || cartItems.length === 0"
          class="cursor-pointer disabled:cursor-not-allowed"
        />
        <label 
          for="select-all" 
          class="text-sm font-medium text-foreground select-none"
          :class="(!isDateSelected || cartItems.length === 0) ? 'cursor-not-allowed text-muted-foreground' : 'cursor-pointer'"
        >
          Pilih Semua
        </label>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row sm:gap-6 mt-3 pb-20 lg:pb-0">
      <!-- ============================================================ -->
      <!-- Left Column: Date Form + Items List                          -->
      <!-- ============================================================ -->
      <div class="flex-1 min-w-0 space-y-4">

        <!-- === Borrow Date Block (Must be selected first) === -->
        <div class="bg-card border border-border rounded-[0.875rem] p-5">
          <h2 class="text-base text-foreground mb-1">Tanggal Peminjaman</h2>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start">
            <!-- Start Date -->
            <div class="space-y-1 order-1 sm:order-1">
              <label class="text-sm font-medium text-foreground">
                <span class="sm:hidden">Mulai</span>
                <span class="hidden sm:inline">Tanggal mulai</span><span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startDate"
                type="date"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- Start Time -->
            <div class="space-y-1 order-3 sm:order-2">
              <label class="text-sm font-medium text-foreground hidden sm:block">
                Waktu mulai<span class="text-destructive">*</span>
              </label>
              <Input
                v-model="startTime"
                type="time"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                :disabled="!startDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- End Date -->
            <div class="space-y-1 order-2 sm:order-3">
              <label class="text-sm font-medium text-muted-foreground">
                <span class="sm:hidden">Selesai</span>
                <span class="hidden sm:inline">Tanggal selesai</span>
              </label>
              <Input
                v-model="endDate"
                type="date"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                :disabled="!startDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>

            <!-- End Time -->
            <div class="space-y-1 order-4 sm:order-4">
              <label class="text-sm font-medium text-muted-foreground hidden sm:block">Waktu selesai</label>
              <Input
                v-model="endTime"
                type="time"
                class="h-10 rounded-[0.875rem] text-sm w-full cursor-pointer"
                :disabled="!endDate"
                @click="handlePickerClick"
                @keydown.prevent
              />
            </div>
          </div>

          <!-- Helper message -->
          <div class="text-xs sm:text-sm italic hidden sm:flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mt-3">
            <p class="text-destructive font-medium">*harus diisi</p>
            <p class="text-muted-foreground">tanggal &amp; waktu selesai dapat dikosongkan</p>
          </div>
        </div>

        <ScrollArea class="border border-border rounded-[0.875rem] bg-card h-[calc(100vh-26.23rem)] sm:h-[calc(100vh-27rem)]">
          <div class="p-3 sm:p-6">
            <div class="space-y-3">
              <!-- Message if empty -->
              <div v-if="filteredItems.length === 0" class="text-center py-10">
                <p class="text-muted-foreground text-sm">Keranjang kosong atau tidak ada barang yang sesuai filter.</p>
              </div>

              <!-- Item Card -->
              <CartItemCard
                v-for="item in filteredItems"
                :key="item.id"
                :item="item"
                v-model:selected="item.selected"
                :disabled="!isDateSelected"
                @remove="removeItem(item.id)"
                @update:quantity="(qty) => updateQty(item, qty)"
              />
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- ============================================================ -->
      <!-- Right Column: Borrow Summary (sticky)                        -->
      <!-- ============================================================ -->
      <div class="hidden lg:block lg:w-80 xl:w-96 flex-shrink-0">
        <div class="bg-card border border-border rounded-[0.875rem] p-5 sticky top-24">
          <h2 class="text-lg font-bold text-foreground mb-4">Ringkasan Peminjaman</h2>

          <!-- List of selected items -->
          <div class="space-y-3 mb-6">
            <div v-if="selectedItems.length === 0" class="text-sm text-muted-foreground italic">
              {{ !isDateSelected ? 'Pilih tanggal peminjaman terlebih dahulu.' : 'Belum ada barang yang dipilih.' }}
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
          <!-- Disabled if: start date is not filled OR no items are selected -->
          <Button
            variant="primary"
            size="lg"
            class="w-full"
            :disabled="!canProceed"
            @click="handleProceed"
          >
            Lanjut ke Konfirmasi
          </Button>

          <!-- Contextual hint -->
          <p v-if="!isDateSelected" class="text-xs text-muted-foreground text-center mt-3">
            Pilih tanggal mulai terlebih dahulu.
          </p>
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
